<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Core\Database;
use App\Models\QualificationForm;
use App\Models\FormQuestion;
use App\Models\FormResponse;
use App\Models\Lead;
use App\Services\SmartContextService;
use App\Services\TokenService;
use App\Services\AI\AIProviderFactory;
use App\Models\AnalysisTrace;

class FormController
{
    private SmartContextService $smartContext;
    private TokenService $tokens;

    public function __construct()
    {
        $this->smartContext = new SmartContextService();
        $this->tokens = new TokenService();
    }

    // ─── Listing ─────────────────────────────────────────

    public function index(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $forms = QualificationForm::allByTenant($tenantId);
        foreach ($forms as &$f) {
            $f['response_count'] = QualificationForm::responseCount($f['id'], $tenantId);
            $f['question_count'] = count(FormQuestion::allByForm($f['id'], $tenantId));
        }
        unset($f);

        View::render('forms/index', [
            'active' => 'forms',
            'forms' => $forms,
        ]);
    }

    // ─── Builder ─────────────────────────────────────────

    public function builder(string $id = ''): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('id');

        if ($id) {
            $form = QualificationForm::find($id, $tenantId);
            if (!$form) {
                Session::flash('error', 'Formulário não encontrado.');
                View::redirect('/forms');
                return;
            }
            $questions = FormQuestion::allByForm($id, $tenantId);
        } else {
            // Create new
            $id = QualificationForm::create($tenantId, $userId, [
                'title' => 'Novo Formulário de Qualificação',
            ]);
            View::redirect('/forms/' . $id . '/builder');
            return;
        }

        View::render('forms/builder', [
            'active' => 'forms',
            'form' => $form,
            'questions' => $questions,
            'questionTypes' => FormQuestion::TYPES,
        ]);
    }

    // ─── CRUD Form ───────────────────────────────────────

    public function updateForm(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        QualificationForm::update($id, $tenantId, [
            'title' => $_POST['title'] ?? null,
            'description' => $_POST['description'] ?? null,
            'status' => $_POST['status'] ?? null,
            'settings' => $_POST['settings'] ?? null,
        ]);

        if (!empty($_POST['ajax'])) {
            View::json(['ok' => true]);
            return;
        }
        Session::flash('success', 'Formulário atualizado.');
        View::redirect('/forms/' . $id . '/builder');
    }

    public function deleteForm(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        QualificationForm::delete($id, $tenantId);
        Session::flash('success', 'Formulário excluído.');
        View::redirect('/forms');
    }

    public function duplicateForm(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('id');

        $newId = QualificationForm::duplicate($id, $tenantId, $userId);
        if ($newId) {
            Session::flash('success', 'Formulário duplicado.');
            View::redirect('/forms/' . $newId . '/builder');
        } else {
            Session::flash('error', 'Erro ao duplicar.');
            View::redirect('/forms');
        }
    }

    // ─── CRUD Questions ──────────────────────────────────

    public function saveQuestions(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $form = QualificationForm::find($id, $tenantId);
        if (!$form) { View::json(['error' => 'Form not found'], 404); return; }

        $input = json_decode(file_get_contents('php://input'), true);
        $questions = $input['questions'] ?? [];

        // Delete existing then recreate (simpler for full-save)
        FormQuestion::deleteAllByForm($id, $tenantId);

        foreach ($questions as $i => $q) {
            FormQuestion::create($tenantId, $id, [
                'section_title' => $q['section_title'] ?? '',
                'label' => $q['label'] ?? '',
                'type' => $q['type'] ?? 'short_text',
                'options' => $q['options'] ?? [],
                'placeholder' => $q['placeholder'] ?? '',
                'help_text' => $q['help_text'] ?? '',
                'is_required' => $q['is_required'] ?? 0,
                'sort_order' => $i,
                'metadata' => $q['metadata'] ?? '{}',
            ]);
        }

        View::json(['ok' => true, 'count' => count($questions)]);
    }

    // ─── Publish / Toggle ────────────────────────────────

    public function toggleStatus(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $form = QualificationForm::find($id, $tenantId);
        if (!$form) { View::json(['error' => 'Not found'], 404); return; }

        $newStatus = $form['status'] === 'published' ? 'draft' : 'published';
        QualificationForm::update($id, $tenantId, ['status' => $newStatus]);

        View::json(['ok' => true, 'status' => $newStatus, 'slug' => $form['public_slug']]);
    }

    // ─── Public Form (no auth) ───────────────────────────

    public function publicForm(string $slug): void
    {
        $form = QualificationForm::findBySlug($slug);
        if (!$form) {
            http_response_code(404);
            echo '<!DOCTYPE html><html><head><title>Não encontrado</title></head><body style="background:#000;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif"><h1>Formulário não encontrado</h1></body></html>';
            return;
        }

        $questions = FormQuestion::allByForm($form['id'], $form['tenant_id']);

        View::render('forms/public', [
            'form' => $form,
            'questions' => $questions,
        ], 'layout.minimal');
    }

    public function publicSubmit(string $slug): void
    {
        $form = QualificationForm::findBySlug($slug);
        if (!$form) { View::json(['error' => 'Not found'], 404); return; }

        $answers = $_POST['answers'] ?? [];
        $name = trim($_POST['respondent_name'] ?? '');
        $email = trim($_POST['respondent_email'] ?? '');

        $responseId = FormResponse::create($form['tenant_id'], [
            'form_id' => $form['id'],
            'lead_id' => null,
            'respondent_name' => $name,
            'respondent_email' => $email,
            'answers' => $answers,
            'source' => 'public',
        ]);

        View::json(['ok' => true, 'response_id' => $responseId]);
    }

    // ─── Internal Fill (vendedor na call) ────────────────

    public function internalFill(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $form = QualificationForm::find($id, $tenantId);
        if (!$form) {
            Session::flash('error', 'Formulário não encontrado.');
            View::redirect('/forms');
            return;
        }

        $questions = FormQuestion::allByForm($id, $tenantId);
        $leadId = $_GET['lead_id'] ?? '';
        $lead = $leadId ? Lead::findByTenant($leadId, $tenantId) : null;

        // All leads for select
        $leads = Lead::allByTenant($tenantId, ['limit' => 500]);

        View::render('forms/fill', [
            'active' => 'forms',
            'form' => $form,
            'questions' => $questions,
            'lead' => $lead,
            'leads' => $leads,
        ]);
    }

    public function internalSubmit(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('id');

        $form = QualificationForm::find($id, $tenantId);
        if (!$form) { View::json(['error' => 'Not found'], 404); return; }

        $input = json_decode(file_get_contents('php://input'), true);
        $answers = $input['answers'] ?? [];
        $leadId = $input['lead_id'] ?? null;

        $responseId = FormResponse::create($tenantId, [
            'form_id' => $id,
            'lead_id' => $leadId ?: null,
            'filled_by' => $userId,
            'answers' => $answers,
            'source' => 'internal',
        ]);

        View::json(['ok' => true, 'response_id' => $responseId]);
    }

    // ─── Responses ───────────────────────────────────────

    public function responses(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $form = QualificationForm::find($id, $tenantId);
        if (!$form) {
            Session::flash('error', 'Formulário não encontrado.');
            View::redirect('/forms');
            return;
        }

        $questions = FormQuestion::allByForm($id, $tenantId);
        $responses = FormResponse::allByForm($id, $tenantId);

        foreach ($responses as &$r) {
            $r['answers'] = json_decode($r['answers'] ?? '{}', true);
        }
        unset($r);

        View::json([
            'form' => $form,
            'questions' => $questions,
            'responses' => $responses,
        ]);
    }

    // ─── AI: Generate Questions ──────────────────────────

    public function aiGenerateQuestions(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $form = QualificationForm::find($id, $tenantId);
        if (!$form) { View::json(['error' => 'Not found'], 404); return; }

        $input = json_decode(file_get_contents('php://input'), true);
        $context = $input['context'] ?? '';
        $style = $input['style'] ?? 'consultivo';

        $profile = $this->smartContext->loadCompanyProfile($tenantId);

        $systemPrompt = <<<PROMPT
Você é um especialista em vendas consultivas e qualificação de leads B2B.
Sua tarefa é gerar perguntas estratégicas para um formulário de qualificação comercial.

CONTEXTO DA EMPRESA:
- Nome: {$profile['name']}
- Oferta: {$profile['offer_title']}
- Serviços: {$this->arrayToStr($profile['offer_services'] ?? [])}
- Diferenciais: {$this->arrayToStr($profile['differentials'] ?? [])}
- ICP: {$this->arrayToStr($profile['icp'] ?? [])}

REGRAS:
1. Gere perguntas que ajudem a qualificar leads de forma inteligente
2. Use linguagem profissional mas acessível
3. Cada pergunta deve ter propósito claro de qualificação
4. Inclua variedade de tipos de campo
5. Agrupe em seções lógicas
6. Estilo: {$style}

Retorne um JSON com a estrutura:
{
  "questions": [
    {
      "section_title": "Nome da Seção",
      "label": "Texto da pergunta",
      "type": "short_text|long_text|single_choice|multiple_choice|number|select|rating|checkbox",
      "options": ["opção1","opção2"] (apenas para choice/select),
      "placeholder": "texto placeholder",
      "help_text": "texto de ajuda opcional",
      "is_required": 1
    }
  ]
}
PROMPT;

        $userPrompt = "Gere perguntas de qualificação para o formulário: \"{$form['title']}\"";
        if ($context) {
            $userPrompt .= "\n\nContexto adicional do usuário: {$context}";
        }

        try {
            $provider = AIProviderFactory::make('form_questions', $tenantId);
            $t0 = microtime(true);
            $meta = $provider->generateJsonWithMeta($systemPrompt, $userPrompt);
            $latMs = (int)((microtime(true) - $t0) * 1000);

            $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];
            $this->tokens->consume(
                'form_questions', $tenantId, Session::get('id'),
                $provider->getProviderName(), $provider->getModel(),
                $usage['input'], $usage['output']
            );

            $parsed = $meta['parsed'] ?? [];
            View::json(['ok' => true, 'questions' => $parsed['questions'] ?? []]);
        } catch (\Throwable $e) {
            View::json(['error' => 'Erro ao gerar perguntas: ' . $e->getMessage()], 500);
        }
    }

    // ─── AI: Chat Refinement ─────────────────────────────

    public function aiRefine(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $form = QualificationForm::find($id, $tenantId);
        if (!$form) { View::json(['error' => 'Not found'], 404); return; }

        $input = json_decode(file_get_contents('php://input'), true);
        $message = $input['message'] ?? '';
        $currentQuestions = $input['current_questions'] ?? [];

        $profile = $this->smartContext->loadCompanyProfile($tenantId);

        $systemPrompt = <<<PROMPT
Você é um consultor de vendas especialista em qualificação de leads B2B.
O usuário está construindo um formulário de qualificação e quer refinar as perguntas.

CONTEXTO DA EMPRESA:
- Nome: {$profile['name']}
- Oferta: {$profile['offer_title']}
- Serviços: {$this->arrayToStr($profile['offer_services'] ?? [])}

FORMULÁRIO ATUAL: "{$form['title']}"
PERGUNTAS ATUAIS:
PROMPT;

        foreach ($currentQuestions as $i => $q) {
            $n = $i + 1;
            $systemPrompt .= "\n{$n}. [{$q['type']}] {$q['label']}";
        }

        $systemPrompt .= <<<PROMPT


REGRAS:
1. Analise o pedido do usuário e ajuste as perguntas conforme solicitado
2. Mantenha o mesmo formato JSON de saída
3. Pode adicionar, remover, reordenar ou reescrever perguntas
4. Sempre retorne o array completo atualizado

Retorne APENAS um JSON:
{
  "questions": [
    {
      "section_title": "Seção",
      "label": "Pergunta",
      "type": "short_text|long_text|single_choice|multiple_choice|number|select|rating|checkbox",
      "options": [],
      "placeholder": "",
      "help_text": "",
      "is_required": 1
    }
  ],
  "explanation": "Breve explicação do que foi alterado"
}
PROMPT;

        try {
            $provider = AIProviderFactory::make('form_questions', $tenantId);
            $t0 = microtime(true);
            $meta = $provider->generateJsonWithMeta($systemPrompt, $message);
            $latMs = (int)((microtime(true) - $t0) * 1000);

            $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];
            $this->tokens->consume(
                'form_refine', $tenantId, Session::get('id'),
                $provider->getProviderName(), $provider->getModel(),
                $usage['input'], $usage['output']
            );

            $parsed = $meta['parsed'] ?? [];
            View::json([
                'ok' => true,
                'questions' => $parsed['questions'] ?? [],
                'explanation' => $parsed['explanation'] ?? '',
            ]);
        } catch (\Throwable $e) {
            View::json(['error' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

    // ─── Lead Form Responses ─────────────────────────────

    public function leadResponses(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $responses = FormResponse::allByLead($id, $tenantId);
        foreach ($responses as &$r) {
            $r['answers'] = json_decode($r['answers'] ?? '{}', true);
        }
        unset($r);

        View::json(['ok' => true, 'responses' => $responses]);
    }

    // ─── Helpers ─────────────────────────────────────────

    private function arrayToStr(array $items): string
    {
        return implode(', ', array_filter($items));
    }
}
