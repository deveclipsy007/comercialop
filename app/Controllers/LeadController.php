<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Models\Lead;
use App\Models\TokenQuota;
use App\Models\WhatsAppLeadLink;
use App\Models\WhatsAppConversationAnalysis;
use App\Services\LeadAnalysisService;
use App\Services\DeepIntelligence\DeepIntelligenceManager;

class LeadController
{
    private LeadAnalysisService $analysisService;
    private DeepIntelligenceManager $deepManager;

    public function __construct()
    {
        $this->analysisService = new LeadAnalysisService();
        $this->deepManager = new DeepIntelligenceManager();
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

        // Buscar Timeline/Activities do Lead (ordenado do mais novo para o mais antigo)
        $activities = \App\Core\Database::select(
            "SELECT a.*, u.name as user_name 
             FROM lead_activities a 
             LEFT JOIN users u ON a.user_id = u.id 
             WHERE a.tenant_id = ? AND a.lead_id = ? 
             ORDER BY a.created_at DESC", 
            [$tenantId, $id]
        );

        // Buscar Inteligências Profundas
        $availableIntelligences = $this->deepManager->getAvailableIntelligences();
        $intelligenceHistory = $this->deepManager->getLeadIntelligences((int)$id, $tenantId);

        // Buscar conversas WhatsApp vinculadas a este lead
        $waConversations = [];
        try {
            $waLinks = WhatsAppLeadLink::findAllByLead($tenantId, (string)$id);
            foreach ($waLinks as $link) {
                $link['summary']  = WhatsAppConversationAnalysis::latestByType($link['conversation_id'], 'summary');
                $link['score']    = WhatsAppConversationAnalysis::latestByType($link['conversation_id'], 'interest_score');
                $waConversations[] = $link;
            }
        } catch (\Throwable $e) {
            // WhatsApp tables may not exist yet — ignore silently
        }

        View::render('vault/show', [
            'active'       => 'vault',
            'lead'         => $lead,
            'tokenBalance' => $tokenBalance,
            'activities'   => $activities,
            'availableIntelligences' => $availableIntelligences,
            'intelligenceHistory' => $intelligenceHistory,
            'waConversations' => $waConversations,
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

    /**
     * Normaliza um header de coluna para o nome canônico do campo.
     * Suporta variações em PT/EN, acentos, case, espaços, underscores.
     */
    private function normalizeColumnHeader(string $raw): ?string
    {
        $s = mb_strtolower(trim($raw));
        $s = str_replace(['_', '-', '.'], ' ', $s);
        // Remove acentos
        $s = preg_replace('/[àáâãä]/u', 'a', $s);
        $s = preg_replace('/[èéêë]/u', 'e', $s);
        $s = preg_replace('/[ìíîï]/u', 'i', $s);
        $s = preg_replace('/[òóôõö]/u', 'o', $s);
        $s = preg_replace('/[ùúûü]/u', 'u', $s);
        $s = preg_replace('/[ç]/u', 'c', $s);
        $s = preg_replace('/\s+/', ' ', $s);

        $map = [
            'name' => 'name', 'nome' => 'name', 'empresa' => 'name', 'razao social' => 'name',
            'razao' => 'name', 'company' => 'name', 'company name' => 'name', 'nome da empresa' => 'name',
            'nome empresa' => 'name', 'fantasia' => 'name', 'nome fantasia' => 'name',

            'segment' => 'segment', 'segmento' => 'segment', 'nicho' => 'segment', 'setor' => 'segment',
            'ramo' => 'segment', 'area' => 'segment', 'industry' => 'segment', 'categoria' => 'segment',
            'tipo' => 'segment', 'atividade' => 'segment',

            'website' => 'website', 'site' => 'website', 'url' => 'website', 'pagina' => 'website',
            'web' => 'website', 'link' => 'website',

            'phone' => 'phone', 'telefone' => 'phone', 'tel' => 'phone', 'celular' => 'phone',
            'fone' => 'phone', 'whatsapp' => 'phone', 'contato' => 'phone', 'numero' => 'phone',

            'email' => 'email', 'e mail' => 'email', 'correio' => 'email', 'mail' => 'email',

            'address' => 'address', 'endereco' => 'address', 'logradouro' => 'address',
            'rua' => 'address', 'cidade' => 'address', 'localizacao' => 'address',
        ];

        return $map[$s] ?? null;
    }

    /**
     * Detecta e converte encoding para UTF-8 se necessário.
     */
    private function ensureUtf8(string $filePath): void
    {
        $contents = file_get_contents($filePath);
        if ($contents === false) return;

        // Remove BOM UTF-8
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);

        // Detecta encoding
        $encoding = mb_detect_encoding($contents, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $contents = mb_convert_encoding($contents, 'UTF-8', $encoding);
        }

        file_put_contents($filePath, $contents);
    }

    public function import(): void
    {
        Session::requireAuth();

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Token CSRF inválido. Recarregue a página.');
            View::redirect('/genesis');
            return;
        }

        $tenantId = Session::get('tenant_id');
        $file     = $_FILES['csv'] ?? null;

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'Arquivo excede o tamanho máximo permitido pelo servidor.',
                UPLOAD_ERR_FORM_SIZE  => 'Arquivo excede o tamanho máximo do formulário.',
                UPLOAD_ERR_PARTIAL    => 'Upload foi interrompido. Tente novamente.',
                UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo foi enviado.',
                UPLOAD_ERR_NO_TMP_DIR => 'Erro interno do servidor (diretório temporário).',
            ];
            $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            Session::flash('error', $uploadErrors[$errorCode] ?? 'Arquivo CSV inválido.');
            View::redirect('/genesis');
            return;
        }

        // Validar tamanho (5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            Session::flash('error', 'Arquivo excede o limite de 5MB.');
            View::redirect('/genesis');
            return;
        }

        // Validar extensão
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt', 'tsv'])) {
            Session::flash('error', 'Formato não suportado. Use arquivos .csv');
            View::redirect('/genesis');
            return;
        }

        // Converter encoding para UTF-8
        $this->ensureUtf8($file['tmp_name']);

        // Detectar delimitador (vírgula, ponto-e-vírgula, tab)
        $firstLine = fgets(fopen($file['tmp_name'], 'r'));
        $delimiters = [';' => 0, ',' => 0, "\t" => 0];
        foreach ($delimiters as $d => &$count) {
            $count = substr_count($firstLine, $d);
        }
        arsort($delimiters);
        $delimiter = array_key_first($delimiters);
        if ($delimiters[$delimiter] === 0) $delimiter = ',';

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            Session::flash('error', 'Erro ao abrir o arquivo CSV.');
            View::redirect('/genesis');
            return;
        }

        $rawHeaders = fgetcsv($handle, 0, $delimiter);
        if (!$rawHeaders || count($rawHeaders) === 0) {
            fclose($handle);
            Session::flash('error', 'CSV vazio ou sem cabeçalho válido.');
            View::redirect('/genesis');
            return;
        }

        // Normalizar headers automaticamente
        $columnMap = []; // index => canonical field name
        $unmapped = [];
        foreach ($rawHeaders as $i => $rawHeader) {
            $canonical = $this->normalizeColumnHeader($rawHeader);
            if ($canonical !== null) {
                $columnMap[$i] = $canonical;
            } else {
                $unmapped[] = $rawHeader;
            }
        }

        // Verificar campos obrigatórios
        $mappedFields = array_values($columnMap);
        if (!in_array('name', $mappedFields)) {
            fclose($handle);
            $hint = !empty($unmapped) ? ' Colunas não reconhecidas: ' . implode(', ', array_slice($unmapped, 0, 5)) : '';
            Session::flash('error', 'Coluna obrigatória "name" (ou "Nome", "Empresa") não encontrada.' . $hint);
            View::redirect('/genesis');
            return;
        }

        $imported = 0;
        $errors   = 0;
        $skipped  = [];
        $maxRows  = 5000;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false && $imported + $errors < $maxRows) {
            if (count($row) < 2) { $errors++; continue; }

            // Montar dados usando mapeamento normalizado
            $data = [];
            foreach ($columnMap as $i => $field) {
                if (isset($row[$i])) {
                    $data[$field] = trim($row[$i]);
                }
            }

            $name    = $data['name'] ?? '';
            $segment = $data['segment'] ?? '';

            if (empty($name)) {
                $errors++;
                if (count($skipped) < 5) $skipped[] = "Linha " . ($imported + $errors + 1) . ": nome vazio";
                continue;
            }

            // Se não tem segment, usar "Não classificado" como fallback
            if (empty($segment)) {
                $segment = 'Não classificado';
            }

            Lead::create($tenantId, [
                'name'    => $name,
                'segment' => $segment,
                'website' => $data['website'] ?? '',
                'phone'   => $data['phone'] ?? '',
                'email'   => $data['email'] ?? '',
                'address' => $data['address'] ?? '',
            ]);
            $imported++;
        }
        fclose($handle);

        if ($imported === 0 && $errors > 0) {
            $detail = !empty($skipped) ? ' Detalhes: ' . implode('; ', $skipped) : '';
            Session::flash('error', "Nenhum lead importado. {$errors} linhas com problemas.{$detail}");
            View::redirect('/genesis');
            return;
        }

        $msg = "{$imported} leads importados com sucesso!";
        if ($errors > 0) $msg .= " ({$errors} linhas ignoradas)";
        if (!empty($unmapped)) $msg .= " | Colunas ignoradas: " . implode(', ', array_slice($unmapped, 0, 3));

        Session::flash('success', $msg);
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

    // ── Timeline: Add Note ───────────────────────────────────
    public function addNote(int|string $id): void
    {
        Session::requireAuth();

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Token inválido.');
            View::redirect("/vault/{$id}");
        }

        $tenantId = Session::get('tenant_id');
        $userId   = Session::get('user_id');
        
        $lead = Lead::findByTenant($id, $tenantId);
        if (!$lead) {
            http_response_code(404);
            return;
        }

        $content = trim($_POST['note_content'] ?? '');
        if (empty($content)) {
            Session::flash('error', 'A nota não pode ficar vazia.');
            View::redirect("/vault/{$id}");
        }

        $activityId = uniqid('act_');
        \App\Core\Database::execute(
            "INSERT INTO lead_activities (id, tenant_id, lead_id, user_id, type, title, content) 
             VALUES (?, ?, ?, ?, 'note', 'Nota Adicionada', ?)",
            [$activityId, $tenantId, $id, $userId, $content]
        );

        Session::flash('success', 'Nota adicionada com sucesso.');
        View::redirect("/vault/{$id}");
    }

    // ── Timeline: Upload Attachment ──────────────────────────
    public function uploadAttachment(int|string $id): void
    {
        Session::requireAuth();

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Token inválido.');
            View::redirect("/vault/{$id}");
        }

        $tenantId = Session::get('tenant_id');
        $userId   = Session::get('user_id');
        
        $lead = Lead::findByTenant($id, $tenantId);
        if (!$lead) {
            http_response_code(404);
            return;
        }

        $file = $_FILES['attachment'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Nenhum arquivo válido foi enviado.');
            View::redirect("/vault/{$id}");
        }

        // Validações básicas (tamanho, ext)
        $maxSize = 5 * 1024 * 1024; // 5 MB
        if ($file['size'] > $maxSize) {
            Session::flash('error', 'O arquivo excede o limite de 5MB.');
            View::redirect("/vault/{$id}");
        }

        $allowedExts = ['jpg', 'jpeg', 'png', 'pdf', 'csv', 'docx', 'xlsx'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts)) {
            Session::flash('error', 'Tipo de arquivo não permitido.');
            View::redirect("/vault/{$id}");
        }

        // Criar diretório se não existir
        $uploadDir = __DIR__ . '/../../public/uploads/leads/' . $tenantId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = uniqid('file_') . '.' . $ext;
        $destPath = $uploadDir . '/' . $filename;
        $publicUrl = '/uploads/leads/' . $tenantId . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            // Salvar na timeline como attachment, url dentro do metadata
            $activityId = uniqid('act_');
            $metadata = json_encode(['url' => $publicUrl, 'filename' => $file['name'], 'ext' => $ext]);

            \App\Core\Database::execute(
                "INSERT INTO lead_activities (id, tenant_id, lead_id, user_id, type, title, content, metadata) 
                 VALUES (?, ?, ?, ?, 'attachment', 'Anexo Adicionado', ?, ?)",
                [$activityId, $tenantId, $id, $userId, 'Arquivo anexado ao perfil.', $metadata]
            );

            Session::flash('success', 'Arquivo anexado com sucesso.');
        } else {
            Session::flash('error', 'Erro ao salvar o arquivo no servidor.');
        }

        View::redirect("/vault/{$id}");
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
