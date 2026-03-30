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
        $stage    = $_GET['stage'] ?? '';
        $temperature = strtoupper(trim((string) ($_GET['temperature'] ?? '')));
        $analysisStatus = $_GET['analysis_status'] ?? '';
        $hasWebsite = $_GET['has_website'] ?? '';
        $hasPhone = $_GET['has_phone'] ?? '';
        $sort = $_GET['sort'] ?? 'priority_desc';

        $sortMap = [
            'priority_desc' => 'priority_score DESC',
            'recent_desc'   => 'created_at DESC',
            'updated_desc'  => 'updated_at DESC',
            'name_asc'      => 'name ASC',
        ];

        $leads = Lead::allByTenant($tenantId, [
            'stage'     => $stage ?: null,
            'segment'   => $segment ?: null,
            'search'    => $search ?: null,
            'temperature' => in_array($temperature, ['HOT', 'WARM', 'COLD'], true) ? $temperature : null,
            'analysis_status' => in_array($analysisStatus, ['analyzed', 'not_analyzed'], true) ? $analysisStatus : null,
            'has_website' => $hasWebsite === 'yes' ? true : ($hasWebsite === 'no' ? false : null),
            'has_phone' => $hasPhone === 'yes' ? true : ($hasPhone === 'no' ? false : null),
            'min_score' => (int) $minScore,
            'order'     => $sortMap[$sort] ?? $sortMap['priority_desc'],
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
            'filters'      => [
                'segment' => $segment,
                'search' => $search,
                'minScore' => (int) $minScore,
                'stage' => $stage,
                'temperature' => in_array($temperature, ['HOT', 'WARM', 'COLD'], true) ? $temperature : '',
                'analysisStatus' => in_array($analysisStatus, ['analyzed', 'not_analyzed'], true) ? $analysisStatus : '',
                'hasWebsite' => in_array($hasWebsite, ['yes', 'no'], true) ? $hasWebsite : '',
                'hasPhone' => in_array($hasPhone, ['yes', 'no'], true) ? $hasPhone : '',
                'sort' => array_key_exists($sort, $sortMap) ? $sort : 'priority_desc',
            ],
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
        $intelligenceHistory = $this->deepManager->getLeadIntelligences((string)$id, $tenantId);

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

    /**
     * Detecta delimitador do CSV pela primeira linha.
     */
    private function detectDelimiter(string $filePath): string
    {
        $h = fopen($filePath, 'r');
        $firstLine = fgets($h);
        fclose($h);

        $delimiters = [';' => 0, ',' => 0, "\t" => 0];
        foreach ($delimiters as $d => &$count) {
            $count = substr_count($firstLine, $d);
        }
        arsort($delimiters);
        $delimiter = array_key_first($delimiters);
        return $delimiters[$delimiter] === 0 ? ',' : $delimiter;
    }

    /**
     * Valida e prepara o arquivo CSV. Retorna path do arquivo salvo ou null em caso de erro.
     */
    private function validateAndPrepareUpload(): ?string
    {
        $file = $_FILES['csv'] ?? null;

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
            return null;
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            Session::flash('error', 'Arquivo excede o limite de 5MB.');
            return null;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt', 'tsv'])) {
            Session::flash('error', 'Formato não suportado. Use arquivos .csv, .txt ou .tsv');
            return null;
        }

        // Salvar em diretório temporário persistente para o fluxo de 2 etapas
        $uploadDir = ROOT_PATH . '/storage/uploads';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $savedName = bin2hex(random_bytes(8)) . '.' . $ext;
        $savedPath = $uploadDir . '/' . $savedName;

        if (!move_uploaded_file($file['tmp_name'], $savedPath)) {
            Session::flash('error', 'Erro ao salvar o arquivo.');
            return null;
        }

        $this->ensureUtf8($savedPath);

        return $savedPath;
    }

    /**
     * Step 1: Analisa o CSV e retorna o mapeamento detectado (AJAX).
     */
    public function analyzeCSV(): void
    {
        // Capturar qualquer output/erro PHP para não corromper o JSON
        ob_start();

        try {
            Session::requireAuth();
            header('Content-Type: application/json');

            $csrfToken = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!Session::validateCsrf($csrfToken)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Token CSRF inválido.']);
                return;
            }

            $savedPath = $this->validateAndPrepareUpload();
            if (!$savedPath) {
                $error = Session::getFlash('error') ?: 'Erro no upload.';
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => $error]);
                return;
            }

            $delimiter = $this->detectDelimiter($savedPath);

            $detector = new \App\Services\CsvColumnDetector();
            $analysis = $detector->analyze($savedPath, $delimiter);

            if (empty($analysis['mapping'])) {
                @unlink($savedPath);
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Não foi possível detectar colunas no arquivo.']);
                return;
            }

            // Guardar path e análise na sessão para o step 2
            $fileToken = bin2hex(random_bytes(16));
            Session::set('genesis_pending_' . $fileToken, [
                'path' => $savedPath,
                'delimiter' => $delimiter,
                'mapping' => $analysis['mapping'],
                'stats' => $analysis['stats'],
            ]);

            // Usar o registry centralizado do CsvColumnDetector
            $fieldLabels  = \App\Services\CsvColumnDetector::getFieldLabels();
            $fieldIcons   = \App\Services\CsvColumnDetector::getFieldIcons();
            $fieldsByGroup = \App\Services\CsvColumnDetector::getFieldsByGroup();

            ob_end_clean();
            echo json_encode([
                'success'          => true,
                'file_token'       => $fileToken,
                'mapping'          => $analysis['mapping'],
                'confidence'       => $analysis['confidence'],
                'headers'          => $analysis['headers'],
                'sample_rows'      => $analysis['sample_rows'],
                'preview'          => $analysis['preview'],
                'stats'            => $analysis['stats'],
                'field_labels'     => $fieldLabels,
                'field_icons'      => $fieldIcons,
                'available_fields' => array_keys($fieldLabels),
                'fields_by_group'  => $fieldsByGroup,
                'field_groups'     => \App\Services\CsvColumnDetector::FIELD_GROUPS,
            ]);
        } catch (\Throwable $e) {
            $phpOutput = ob_get_clean();
            error_log('[Genesis Analyze] Error: ' . $e->getMessage() . ' | PHP output: ' . substr($phpOutput, 0, 500));
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * Step 2: Importa os leads usando o mapeamento (pode ser ajustado pelo usuário).
     */
    public function import(): void
    {
        Session::requireAuth();

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Token CSRF inválido. Recarregue a página.');
            View::redirect('/genesis');
            return;
        }

        $tenantId = Session::get('tenant_id');
        $fileToken = $_POST['file_token'] ?? '';

        // Recuperar dados da sessão (fluxo de 2 etapas)
        $pending = Session::get('genesis_pending_' . $fileToken);

        if (!$pending || !file_exists($pending['path'])) {
            Session::flash('error', 'Sessão de importação expirada. Faça upload do arquivo novamente.');
            View::redirect('/genesis');
            return;
        }

        $filePath = $pending['path'];
        $delimiter = $pending['delimiter'];

        // Pegar mapeamento: usa o do usuário se enviado, senão o detectado
        $mapping = [];
        if (!empty($_POST['mapping'])) {
            $userMapping = json_decode($_POST['mapping'], true);
            if (is_array($userMapping)) {
                $mapping = $userMapping;
            }
        }
        if (empty($mapping)) {
            $mapping = $pending['mapping'];
        }

        // Converter chaves para int
        $mappingInt = [];
        foreach ($mapping as $k => $v) {
            if ($v !== '' && $v !== '_skip') {
                $mappingInt[(int)$k] = $v;
            }
        }

        // Verificar que 'name' está mapeado
        if (!in_array('name', $mappingInt)) {
            Session::flash('error', 'É necessário mapear pelo menos a coluna "Nome / Empresa" para importar.');
            View::redirect('/genesis');
            return;
        }

        // Ler todas as linhas do CSV
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            Session::flash('error', 'Erro ao abrir o arquivo.');
            View::redirect('/genesis');
            return;
        }

        // Pular cabeçalho (ou a primeira linha se for dados, o detector tratou isso)
        $headersArData = $pending['stats']['headers_are_data'] ?? false;
        $firstRow = fgetcsv($handle, 0, $delimiter, '"', '');

        $rows = [];
        if ($headersArData && $firstRow) {
            $rows[] = $firstRow; // Primeira linha é dado, não cabeçalho
        }

        $maxRows = 5000;
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false && count($rows) < $maxRows) {
            if (count($row) >= 2) {
                $rows[] = $row;
            }
        }
        fclose($handle);

        // Aplicar mapeamento via CsvColumnDetector
        $result = \App\Services\CsvColumnDetector::applyMapping($rows, $mappingInt);
        $leads = $result['leads'];
        $errors = $result['errors'];
        $skipped = $result['skipped'];

        // Importar leads
        $imported = 0;
        foreach ($leads as $leadData) {
            // Merge social_presence se houver dados sociais
            $socialPresence = $leadData['social_presence'] ?? null;

            $createData = [
                'name'            => $leadData['name'],
                'segment'         => $leadData['segment'],
                'website'         => $leadData['website'],
                'phone'           => $leadData['phone'],
                'email'           => $leadData['email'],
                'address'         => $leadData['address'],
                'google_maps_url' => $leadData['google_maps_url'] ?? null,
                'rating'          => $leadData['rating'] ?? null,
                'review_count'    => $leadData['review_count'] ?? null,
                'reviews'         => $leadData['reviews'] ?? null,
                'opening_hours'   => $leadData['opening_hours'] ?? null,
                'closing_hours'   => $leadData['closing_hours'] ?? null,
                'category'        => $leadData['category'] ?? null,
            ];

            if ($socialPresence) {
                $createData['social_presence'] = $socialPresence;
            }

            Lead::create($tenantId, $createData);
            $imported++;
        }

        // Limpar arquivo e sessão
        @unlink($filePath);
        Session::forget('genesis_pending_' . $fileToken);

        if ($imported === 0 && $errors > 0) {
            $detail = !empty($skipped) ? ' Detalhes: ' . implode('; ', $skipped) : '';
            Session::flash('error', "Nenhum lead importado. {$errors} linhas com problemas.{$detail}");
            View::redirect('/genesis');
            return;
        }

        $msg = "{$imported} leads importados com sucesso!";
        if ($errors > 0) $msg .= " ({$errors} linhas ignoradas)";

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
