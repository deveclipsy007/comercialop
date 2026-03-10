<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Models\Lead;
use App\Models\TokenQuota;
use App\Services\LeadAnalysisService;

class LeadController
{
    private LeadAnalysisService $analysisService;

    public function __construct()
    {
        $this->analysisService = new LeadAnalysisService();
    }

    // ── Vault (Kanban / List) ────────────────────────────────
    public function vault(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $view     = $_GET['view'] ?? 'kanban'; // kanban | list
        $segment  = $_GET['segment'] ?? '';
        $search   = $_GET['q'] ?? '';
        $minScore = $_GET['min_score'] ?? 0;

        $leads = Lead::allByTenant($tenantId, [
            'segment'   => $segment ?: null,
            'search'    => $search ?: null,
            'min_score' => (int) $minScore,
            'order'     => 'priority_score DESC',
        ]);

        // Group by pipeline status for Kanban
        $columns = [];
        foreach (Lead::STAGES as $stage => $label) {
            $columns[$stage] = [
                'label' => $label,
                'leads' => [],
            ];
        }
        foreach ($leads as $lead) {
            $stage = $lead['pipeline_status'] ?? 'new';
            if (isset($columns[$stage])) {
                $columns[$stage]['leads'][] = $lead;
            }
        }

        $tokenBalance = TokenQuota::getBalance($tenantId);

        View::render('vault/index', [
            'active'       => 'vault',
            'leads'        => $leads,
            'columns'      => $columns,
            'view'         => $view,
            'filters'      => compact('segment', 'search', 'minScore'),
            'tokenBalance' => $tokenBalance,
            'stages'       => Lead::STAGES,
        ]);
    }

    // ── Lead Detail ──────────────────────────────────────────
    public function show(int|string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $lead = Lead::findByTenant($id, $tenantId);
        if (!$lead) {
            View::render('errors/404', ['active' => '']);
            return;
        }

        $tokenBalance = TokenQuota::getBalance($tenantId);

        View::render('vault/show', [
            'active'       => 'vault',
            'lead'         => $lead,
            'tokenBalance' => $tokenBalance,
        ]);
    }

    // ── Create Lead ──────────────────────────────────────────
    public function store(): void
    {
        Session::requireAuth();

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            $this->jsonError('Token inválido', 403);
        }

        $tenantId = Session::get('tenant_id');

        $data = [
            'name'     => trim($_POST['name'] ?? ''),
            'segment'  => trim($_POST['segment'] ?? ''),
            'website'  => trim($_POST['website'] ?? ''),
            'phone'    => trim($_POST['phone'] ?? ''),
            'email'    => trim($_POST['email'] ?? ''),
            'address'  => trim($_POST['address'] ?? ''),
        ];

        if (empty($data['name']) || empty($data['segment'])) {
            Session::flash('error', 'Nome e segmento são obrigatórios.');
            View::redirect('/vault');
        }

        $id = Lead::create($tenantId, $data);

        Session::flash('success', 'Lead adicionado ao Vault!');
        View::redirect("/vault/{$id}");
    }

    // ── Update Lead ──────────────────────────────────────────
    public function update(int|string $id): void
    {
        Session::requireAuth();

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            $this->jsonError('Token inválido', 403);
        }

        $tenantId = Session::get('tenant_id');
        $lead = Lead::findByTenant($id, $tenantId);
        if (!$lead) { http_response_code(404); return; }

        $allowed = ['name', 'segment', 'website', 'phone', 'email', 'address',
                    'manual_score_override', 'next_followup_at', 'assigned_to'];
        $data = [];
        foreach ($allowed as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = trim($_POST[$field]);
            }
        }

        // Human context (temperature, timing, objection)
        if (isset($_POST['temperature']) || isset($_POST['timingStatus']) || isset($_POST['objectionCategory'])) {
            $ctx = $lead['human_context'] ?? [];
            if (isset($_POST['temperature']))      $ctx['temperature']      = $_POST['temperature'];
            if (isset($_POST['timingStatus']))     $ctx['timingStatus']     = $_POST['timingStatus'];
            if (isset($_POST['objectionCategory'])) $ctx['objectionCategory'] = $_POST['objectionCategory'];
            if (isset($_POST['notes']))            $ctx['notes']            = trim($_POST['notes']);
            $data['human_context'] = $ctx;
        }

        Lead::update($id, $tenantId, $data);
        Session::flash('success', 'Lead atualizado.');
        View::redirect("/vault/{$id}");
    }

    // ── Update Stage (AJAX) ──────────────────────────────────
    public function updateStage(int|string $id): void
    {
        Session::requireAuth();
        header('Content-Type: application/json');

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $token = $body['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (!Session::validateCsrf($token)) {
            echo json_encode(['success' => false, 'error' => 'Token inválido']);
            return;
        }

        $tenantId = Session::get('tenant_id');
        $stage    = $body['stage'] ?? '';

        if (!array_key_exists($stage, Lead::STAGES)) {
            echo json_encode(['success' => false, 'error' => 'Stage inválido']);
            return;
        }

        $ok = Lead::updateStage($id, $tenantId, $stage);
        echo json_encode(['success' => $ok]);
    }

    // ── AI: Qualify Lead ────────────────────────────────────
    public function analyze(int|string $id): void
    {
        Session::requireAuth();
        header('Content-Type: application/json');

        $tenantId = Session::get('tenant_id');
        $lead     = Lead::findByTenant($id, $tenantId);
        if (!$lead) { echo json_encode(['error' => 'Lead não encontrado']); return; }

        $result = $this->analysisService->analyzeLeadWithAI($lead, $tenantId);
        echo json_encode($result);
    }

    // ── AI: Deep Analysis ───────────────────────────────────
    public function deepAnalysis(int|string $id): void
    {
        Session::requireAuth();
        header('Content-Type: application/json');

        $tenantId = Session::get('tenant_id');
        $lead     = Lead::findByTenant($id, $tenantId);
        if (!$lead) { echo json_encode(['error' => 'Lead não encontrado']); return; }

        $spin     = $this->analysisService->generateSpin($lead, $tenantId);
        $scripts  = $this->analysisService->generateScriptVariations($lead, $tenantId);

        echo json_encode(['spin' => $spin, 'scripts' => $scripts]);
    }

    // ── AI: Deep Insights (Custom Cards) ────────────────────
    public function deepAnalysisInsights(int|string $id): void
    {
        Session::requireAuth();
        header('Content-Type: application/json');

        $tenantId = Session::get('tenant_id');
        $lead     = Lead::findByTenant($id, $tenantId);
        if (!$lead) { echo json_encode(['error' => 'Lead não encontrado']); return; }

        $result = $this->analysisService->runDeepInsights($lead, $tenantId);
        echo json_encode($result);
    }

    // ── AI: Operon 4D ────────────────────────────────────────
    public function operon4D(int|string $id): void
    {
        Session::requireAuth();
        header('Content-Type: application/json');

        $tenantId = Session::get('tenant_id');
        $lead     = Lead::findByTenant($id, $tenantId);
        if (!$lead) { echo json_encode(['error' => 'Lead não encontrado']); return; }

        $result = $this->analysisService->runOperon4D($lead, $tenantId);
        echo json_encode($result);
    }

    // ── Delete Lead ──────────────────────────────────────────
    public function destroy(int|string $id): void
    {
        Session::requireAuth();

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Token inválido.');
            View::redirect('/vault');
        }

        $tenantId = Session::get('tenant_id');
        Lead::delete($id, $tenantId);

        Session::flash('success', 'Lead removido do Vault.');
        View::redirect('/vault');
    }

    // ── CSV Import (Genesis) ─────────────────────────────────
    public function import(): void
    {
        Session::requireAuth();

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Token inválido.');
            View::redirect('/genesis');
        }

        $tenantId = Session::get('tenant_id');
        $file     = $_FILES['csv'] ?? null;

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Arquivo CSV inválido.');
            View::redirect('/genesis');
        }

        $handle  = fopen($file['tmp_name'], 'r');
        $headers = fgetcsv($handle);
        $imported = 0;
        $errors   = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) continue;
            $data = array_combine(array_slice($headers, 0, count($row)), $row);
            $name    = trim($data['name'] ?? $data['Nome'] ?? $data['nome'] ?? '');
            $segment = trim($data['segment'] ?? $data['Segmento'] ?? $data['segmento'] ?? '');

            if (empty($name) || empty($segment)) { $errors++; continue; }

            Lead::create($tenantId, [
                'name'    => $name,
                'segment' => $segment,
                'website' => trim($data['website'] ?? $data['Website'] ?? ''),
                'phone'   => trim($data['phone'] ?? $data['Telefone'] ?? ''),
                'email'   => trim($data['email'] ?? $data['Email'] ?? ''),
                'address' => trim($data['address'] ?? $data['Endereço'] ?? ''),
            ]);
            $imported++;
        }
        fclose($handle);

        Session::flash('success', "{$imported} leads importados com sucesso!" . ($errors ? " ({$errors} linhas ignoradas)" : ''));
        View::redirect('/vault');
    }

    // ── Update Context (AJAX) ────────────────────────────────
    public function updateContext(int|string $id): void
    {
        Session::requireAuth();
        header('Content-Type: application/json');

        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $token = $body['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (!Session::validateCsrf($token)) {
            echo json_encode(['success' => false, 'error' => 'Token inválido']);
            return;
        }

        $tenantId = Session::get('tenant_id');
        $lead     = Lead::findByTenant($id, $tenantId);
        if (!$lead) { echo json_encode(['success' => false]); return; }

        $field = $body['field'] ?? '';
        $value = $body['value'] ?? '';

        $allowed = ['temperature', 'timingStatus', 'objectionCategory', 'notes'];
        if (!in_array($field, $allowed, true)) {
            echo json_encode(['success' => false, 'error' => 'Campo inválido']);
            return;
        }

        $ctx          = $lead['human_context'] ?? [];
        $ctx[$field]  = $value;

        $ok = Lead::update($id, $tenantId, ['human_context' => $ctx]);
        echo json_encode(['success' => $ok]);
    }

    // ── Update Tags (AJAX) ───────────────────────────────────
    public function updateTags(int|string $id): void
    {
        Session::requireAuth();
        header('Content-Type: application/json');

        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $token = $body['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (!Session::validateCsrf($token)) {
            echo json_encode(['success' => false, 'error' => 'Token inválido']);
            return;
        }

        $tenantId = Session::get('tenant_id');
        $lead     = Lead::findByTenant($id, $tenantId);
        if (!$lead) { echo json_encode(['success' => false]); return; }

        $action = $body['action'] ?? 'add'; // add | remove
        $tag    = trim($body['tag'] ?? '');

        $tags = $lead['tags'] ?? [];
        if ($action === 'add' && $tag && !in_array($tag, $tags, true)) {
            $tags[] = $tag;
        } elseif ($action === 'remove') {
            $tags = array_values(array_filter($tags, fn($t) => $t !== $tag));
        }

        Lead::update($id, $tenantId, ['tags' => $tags]);
        echo json_encode(['success' => true, 'tags' => $tags]);
    }

    // ── Helper ───────────────────────────────────────────────
    private function jsonError(string $message, int $code = 400): never
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit;
    }
}
