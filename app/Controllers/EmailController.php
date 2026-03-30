<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Core\Database;
use App\Models\EmailAccount;
use App\Models\EmailTemplate;
use App\Models\EmailCampaign;
use App\Models\EmailLog;
use App\Models\Lead;
use App\Services\EmailSenderService;
use App\Services\SmartContextService;
use App\Services\TokenService;
use App\Services\AI\AIProviderFactory;

class EmailController
{
    private EmailSenderService $sender;
    private SmartContextService $smartContext;
    private TokenService $tokens;

    public function __construct()
    {
        $this->sender = new EmailSenderService();
        $this->smartContext = new SmartContextService();
        $this->tokens = new TokenService();
    }

    // ─── Main Page ─────────────────────────────────────────────

    /**
     * GET /emails — Dashboard principal do módulo de email.
     */
    public function index(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('id');

        $accounts   = EmailAccount::allByUser($tenantId, $userId);
        $templates  = EmailTemplate::allByTenant($tenantId);
        $campaigns  = EmailCampaign::allByTenant($tenantId);
        $stats      = EmailLog::getStats($tenantId);
        $recentLogs = EmailLog::allByTenant($tenantId, 20);

        View::render('emails/index', [
            'active'    => 'emails',
            'accounts'  => $accounts,
            'templates' => $templates,
            'campaigns' => $campaigns,
            'stats'     => $stats,
            'recentLogs' => $recentLogs,
            'categories' => EmailTemplate::CATEGORIES,
        ]);
    }

    // ─── Accounts ──────────────────────────────────────────────

    /**
     * POST /emails/account — Connect email account.
     */
    public function createAccount(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('id');

        $data = $_POST;
        if (empty($data['email_address']) || empty($data['smtp_host'])) {
            View::json(['error' => 'E-mail e servidor SMTP são obrigatórios.'], 400);
            return;
        }

        // Test connection first
        $test = $this->sender->testConnection($data);
        if (!$test['ok']) {
            View::json(['error' => 'Falha na conexão: ' . ($test['error'] ?? 'Erro desconhecido')], 400);
            return;
        }

        $id = EmailAccount::create($tenantId, $userId, $data);
        EmailAccount::update($id, $tenantId, ['is_verified' => 1]);

        View::json(['ok' => true, 'id' => $id, 'message' => 'Conta conectada e verificada.']);
    }

    /**
     * POST /emails/account/:id/update — Update account settings.
     */
    public function updateAccount(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        EmailAccount::update($id, $tenantId, $_POST);
        View::json(['ok' => true]);
    }

    /**
     * POST /emails/account/:id/delete — Remove account.
     */
    public function deleteAccount(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        EmailAccount::delete($id, $tenantId);
        View::json(['ok' => true]);
    }

    /**
     * POST /emails/account/test — Test SMTP connection.
     */
    public function testAccount(): void
    {
        Session::requireAuth();
        $result = $this->sender->testConnection($_POST);
        View::json($result);
    }

    // ─── Templates ─────────────────────────────────────────────

    /**
     * GET /emails/templates — Templates page.
     */
    public function templates(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $templates = EmailTemplate::allByTenant($tenantId);

        View::render('emails/templates', [
            'active' => 'emails',
            'templates' => $templates,
            'categories' => EmailTemplate::CATEGORIES,
        ]);
    }

    /**
     * POST /emails/template — Create template.
     */
    public function createTemplate(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('id');

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $id = EmailTemplate::create($tenantId, $userId, $input);

        View::json(['ok' => true, 'id' => $id]);
    }

    /**
     * POST /emails/template/:id/update — Update template.
     */
    public function updateTemplate(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        EmailTemplate::update($id, $tenantId, $input);

        View::json(['ok' => true]);
    }

    /**
     * POST /emails/template/:id/delete — Delete template.
     */
    public function deleteTemplate(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        EmailTemplate::delete($id, $tenantId);
        View::json(['ok' => true]);
    }

    // ─── Campaigns ─────────────────────────────────────────────

    /**
     * GET /emails/campaigns — Campaigns listing.
     */
    public function campaigns(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $campaigns = EmailCampaign::allByTenant($tenantId);
        $accounts  = EmailAccount::allByTenant($tenantId);

        View::render('emails/campaigns', [
            'active'    => 'emails',
            'campaigns' => $campaigns,
            'accounts'  => $accounts,
        ]);
    }

    /**
     * POST /emails/campaign — Create campaign.
     */
    public function createCampaign(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('id');

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $id = EmailCampaign::create($tenantId, $userId, $input);

        View::json(['ok' => true, 'id' => $id]);
    }

    /**
     * GET /emails/campaign/:id — Campaign detail/editor.
     */
    public function campaignDetail(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $campaign = EmailCampaign::find($id, $tenantId);
        if (!$campaign) {
            Session::flash('error', 'Campanha não encontrada.');
            View::redirect('/emails');
            return;
        }

        $steps     = EmailCampaign::getSteps($id, $tenantId);
        $templates = EmailTemplate::allByTenant($tenantId);
        $accounts  = EmailAccount::allByTenant($tenantId);
        $logs      = EmailLog::allByCampaign($id, $tenantId);
        $stats     = EmailLog::getStats($tenantId, $id);

        // Load leads for the campaign
        $leadIds = json_decode($campaign['lead_ids'] ?? '[]', true);
        $leads = [];
        if (!empty($leadIds)) {
            $placeholders = implode(',', array_fill(0, count($leadIds), '?'));
            $leads = Database::select(
                "SELECT id, name, email, segment, pipeline_status FROM leads WHERE id IN ({$placeholders}) AND tenant_id = ?",
                array_merge($leadIds, [$tenantId])
            );
        }

        View::render('emails/campaign_detail', [
            'active'    => 'emails',
            'campaign'  => $campaign,
            'steps'     => $steps,
            'templates' => $templates,
            'accounts'  => $accounts,
            'logs'      => $logs,
            'stats'     => $stats,
            'leads'     => $leads,
        ]);
    }

    /**
     * POST /emails/campaign/:id/update — Update campaign.
     */
    public function updateCampaign(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        EmailCampaign::update($id, $tenantId, $input);

        View::json(['ok' => true]);
    }

    /**
     * POST /emails/campaign/:id/delete — Delete campaign.
     */
    public function deleteCampaign(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        EmailCampaign::delete($id, $tenantId);
        View::json(['ok' => true]);
    }

    // ─── Campaign Steps ────────────────────────────────────────

    /**
     * POST /emails/campaign/:id/step — Add step.
     */
    public function createStep(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $stepId = EmailCampaign::createStep($tenantId, $id, $input);

        View::json(['ok' => true, 'id' => $stepId]);
    }

    /**
     * POST /emails/step/:id/update — Update step.
     */
    public function updateStep(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        EmailCampaign::updateStep($id, $tenantId, $input);

        View::json(['ok' => true]);
    }

    /**
     * POST /emails/step/:id/delete — Delete step.
     */
    public function deleteStep(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        EmailCampaign::deleteStep($id, $tenantId);
        View::json(['ok' => true]);
    }

    // ─── Sending ───────────────────────────────────────────────

    /**
     * POST /emails/send — Send a single email.
     */
    public function sendEmail(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('id');

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $accountId = $input['account_id'] ?? '';

        if (empty($accountId)) {
            $account = EmailAccount::findActive($tenantId, $userId);
            if (!$account) {
                View::json(['error' => 'Nenhuma conta de e-mail ativa. Conecte uma conta primeiro.'], 400);
                return;
            }
            $accountId = $account['id'];
        }

        // Replace variables if lead_id provided
        if (!empty($input['lead_id'])) {
            $lead = Lead::findByTenant($input['lead_id'], $tenantId);
            if ($lead) {
                $input['subject'] = EmailSenderService::replaceVariables($input['subject'] ?? '', $lead);
                $input['body'] = EmailSenderService::replaceVariables($input['body'] ?? '', $lead);
                if (empty($input['to_email'])) {
                    $input['to_email'] = $lead['email'] ?? '';
                }
                if (empty($input['to_name'])) {
                    $input['to_name'] = $lead['name'] ?? '';
                }
            }
        }

        if (empty($input['to_email'])) {
            View::json(['error' => 'E-mail do destinatário é obrigatório.'], 400);
            return;
        }

        $result = $this->sender->send($accountId, $tenantId, $userId, $input);

        // Record activity on lead if applicable
        if ($result['ok'] && !empty($input['lead_id'])) {
            $this->recordLeadActivity($tenantId, $userId, $input['lead_id'], $input['subject'] ?? '');
        }

        View::json($result);
    }

    /**
     * POST /emails/campaign/:id/execute — Execute campaign (send to all leads).
     */
    public function executeCampaign(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('id');

        $campaign = EmailCampaign::find($id, $tenantId);
        if (!$campaign) {
            View::json(['error' => 'Campanha não encontrada.'], 404);
            return;
        }

        if (!$campaign['account_id']) {
            View::json(['error' => 'Selecione uma conta de e-mail para a campanha.'], 400);
            return;
        }

        $steps = EmailCampaign::getSteps($id, $tenantId);
        if (empty($steps)) {
            View::json(['error' => 'A campanha precisa de pelo menos uma etapa.'], 400);
            return;
        }

        $leadIds = json_decode($campaign['lead_ids'] ?? '[]', true);
        if (empty($leadIds)) {
            View::json(['error' => 'Nenhum lead selecionado para a campanha.'], 400);
            return;
        }

        // Get first step for initial send
        $firstStep = $steps[0];
        $sentCount = 0;
        $errors = [];

        // Update campaign status
        EmailCampaign::update($id, $tenantId, [
            'status' => 'active',
            'started_at' => date('Y-m-d H:i:s'),
            'total_leads' => count($leadIds),
        ]);

        foreach ($leadIds as $leadId) {
            $lead = Lead::findByTenant($leadId, $tenantId);
            if (!$lead || empty($lead['email'])) continue;

            $subject = EmailSenderService::replaceVariables($firstStep['subject'], $lead);
            $body = EmailSenderService::replaceVariables($firstStep['body'], $lead);

            $result = $this->sender->send($campaign['account_id'], $tenantId, $userId, [
                'to_email'    => $lead['email'],
                'to_name'     => $lead['name'] ?? '',
                'subject'     => $subject,
                'body'        => $body,
                'lead_id'     => $leadId,
                'campaign_id' => $id,
                'step_id'     => $firstStep['id'],
            ]);

            if ($result['ok']) {
                $sentCount++;
                $this->recordLeadActivity($tenantId, $userId, $leadId, $subject);
            } else {
                $errors[] = ['lead' => $lead['name'], 'error' => $result['error'] ?? 'Unknown'];
                // If rate limited, stop sending
                if (str_contains($result['error'] ?? '', 'Limite') || str_contains($result['error'] ?? '', 'Aguarde')) {
                    break;
                }
            }
        }

        // Update campaign stats
        EmailCampaign::update($id, $tenantId, [
            'sent_count' => $sentCount,
            'status' => $sentCount >= count($leadIds) ? 'completed' : 'active',
        ]);

        View::json([
            'ok' => true,
            'sent' => $sentCount,
            'total' => count($leadIds),
            'errors' => $errors,
        ]);
    }

    // ─── AI Email Generation ───────────────────────────────────

    /**
     * POST /emails/ai/generate — Generate email with AI.
     */
    public function aiGenerate(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        $userId = Session::get('id');

        $input = json_decode(file_get_contents('php://input'), true);
        $leadId = $input['lead_id'] ?? null;
        $purpose = $input['purpose'] ?? 'prospecção';
        $tone = $input['tone'] ?? 'profissional';
        $context = $input['context'] ?? '';

        // Load company profile
        $profile = $this->smartContext->loadCompanyProfile($tenantId);

        // Load lead data if available
        $leadContext = '';
        if ($leadId) {
            $lead = Lead::findByTenant($leadId, $tenantId);
            if ($lead) {
                $leadContext = "Lead: {$lead['name']}, Segmento: {$lead['segment']}, Email: {$lead['email']}, Status: {$lead['pipeline_status']}";
                if (!empty($lead['analysis'])) {
                    $analysis = json_decode($lead['analysis'], true);
                    if ($analysis) {
                        $leadContext .= "\nAnálise: " . json_encode($analysis, JSON_UNESCAPED_UNICODE);
                    }
                }
            }
        }

        $systemPrompt = <<<PROMPT
Você é um especialista em copywriting de vendas B2B da empresa "{$profile['name']}".

CONTEXTO DA EMPRESA:
- Oferta: {$profile['offer_title']}
- Serviços: {$this->arrayToStr($profile['offer_services'])}
- Diferenciais: {$this->arrayToStr($profile['differentials'])}
- ICP: {$this->arrayToStr($profile['icp'])}
- Proposta de Valor: {$profile['unique_proposal']}

REGRAS:
1. Escreva e-mails concisos, claros e personalizados
2. Não use linguagem genérica ou excessivamente formal
3. Foque na dor/necessidade do lead
4. Inclua CTA claro no final
5. Mantenha o tom {$tone}
6. O e-mail deve ter no máximo 150 palavras
7. Retorne um JSON com: {"subject": "...", "body": "..."}
8. O body deve ser HTML simples (parágrafos com <p>, negrito com <strong>)
PROMPT;

        $userPrompt = "Gere um e-mail de {$purpose}.\n";
        if ($leadContext) $userPrompt .= "Dados do lead: {$leadContext}\n";
        if ($context) $userPrompt .= "Contexto adicional: {$context}\n";

        try {
            $provider = AIProviderFactory::make('email_generate', $tenantId);
            $meta = $provider->generateJsonWithMeta($systemPrompt, $userPrompt);
            $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];

            $this->tokens->consume(
                'email_generate',
                $tenantId,
                $userId,
                $provider->getProviderName(),
                $provider->getModel(),
                $usage['input'],
                $usage['output']
            );

            $parsed = $meta['parsed'] ?? [];

            View::json([
                'ok' => true,
                'subject' => $parsed['subject'] ?? '',
                'body' => $parsed['body'] ?? '',
            ]);
        } catch (\Throwable $e) {
            View::json(['error' => $e->getMessage()], 500);
        }
    }

    // ─── Tracking ──────────────────────────────────────────────

    /**
     * GET /email/track/open/:token — Open tracking pixel.
     */
    public function trackOpen(string $token): void
    {
        EmailLog::markOpened(trim($token));

        // Update campaign stats
        $log = EmailLog::findByTrackingToken(trim($token));
        if ($log && $log['campaign_id']) {
            Database::execute(
                "UPDATE email_campaigns SET opened_count = (SELECT COUNT(*) FROM email_log WHERE campaign_id = ? AND opened_at IS NOT NULL) WHERE id = ?",
                [$log['campaign_id'], $log['campaign_id']]
            );
        }

        // Return 1x1 transparent GIF
        header('Content-Type: image/gif');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    }

    // ─── Lead Email History ────────────────────────────────────

    /**
     * GET /emails/lead/:id — Get email history for a lead (JSON).
     */
    public function leadEmails(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $emails = EmailLog::allByLead($id, $tenantId);
        View::json(['ok' => true, 'emails' => $emails]);
    }

    // ─── Helpers ───────────────────────────────────────────────

    private function recordLeadActivity(string $tenantId, string $userId, string $leadId, string $subject): void
    {
        $actId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        Database::execute(
            "INSERT INTO lead_activities (id, tenant_id, lead_id, user_id, type, title, created_at) VALUES (?, ?, ?, ?, 'email', ?, datetime('now'))",
            [$actId, $tenantId, $leadId, $userId, 'Email enviado: ' . $subject]
        );
    }

    private function arrayToStr(array $arr): string
    {
        return implode(', ', array_filter($arr));
    }
}
