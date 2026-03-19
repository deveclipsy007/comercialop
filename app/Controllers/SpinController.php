<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Models\Lead;
use App\Models\ApproachPlaybook;
use App\Models\ApproachScript;
use App\Services\LeadAnalysisService;

class SpinController
{
    public function index(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        $leads = Lead::allByTenant($tenantId, ['limit' => 50, 'order' => 'priority_score DESC']);
        $playbooks = ApproachPlaybook::allByTenant($tenantId);

        View::render('spin/index', [
            'active'    => 'spin',
            'leads'     => $leads,
            'playbooks' => $playbooks,
        ]);
    }

    public function generate(): void
    {
        Session::requireAuth();
        header('Content-Type: application/json');

        $tenantId = Session::get('tenant_id');
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!Session::validateCsrf($body['_csrf'] ?? '')) {
            echo json_encode(['error' => 'Token inválido']); return;
        }

        $leadId = $body['lead_id'] ?? '';
        $lead   = Lead::findByTenant($leadId, $tenantId);

        if (!$lead) {
            echo json_encode(['error' => 'Lead não encontrado']); return;
        }

        $tone         = $body['tone'] ?? 'consultivo';
        $channel      = $body['channel'] ?? null; // null = all channels
        $instructions = $body['instructions'] ?? '';

        $service = new LeadAnalysisService();
        $spin    = $service->generateSpin($lead, $tenantId);
        $scripts = $service->generateAdvancedScripts($lead, $tenantId, $tone, $channel, $instructions);

        // Persist scripts
        foreach ($scripts as $ch => $text) {
            if (empty($text)) continue;
            ApproachScript::insert($tenantId, [
                'lead_id' => $leadId,
                'channel' => $ch,
                'tone'    => $tone,
                'script'  => $text,
                'context' => [
                    'lead_name' => $lead['name'],
                    'lead_segment' => $lead['segment'],
                    'instructions' => $instructions,
                ],
            ]);
        }

        echo json_encode([
            'spin' => $spin,
            'scripts' => $scripts,
            'lead' => ['name' => $lead['name'], 'segment' => $lead['segment']],
        ]);
    }

    /**
     * Refine an existing script via chat instruction.
     */
    public function refineScript(): void
    {
        Session::requireAuth();
        header('Content-Type: application/json');

        $tenantId = Session::get('tenant_id');
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!Session::validateCsrf($body['_csrf'] ?? '')) {
            echo json_encode(['error' => 'Token inválido']); return;
        }

        $leadId      = $body['lead_id'] ?? '';
        $channel     = $body['channel'] ?? 'whatsapp';
        $currentScript = $body['current_script'] ?? '';
        $instruction = $body['instruction'] ?? '';
        $tone        = $body['tone'] ?? 'consultivo';

        if (empty($currentScript) || empty($instruction)) {
            echo json_encode(['error' => 'Script e instrução são obrigatórios']); return;
        }

        $lead = Lead::findByTenant($leadId, $tenantId);
        if (!$lead) {
            echo json_encode(['error' => 'Lead não encontrado']); return;
        }

        $service = new LeadAnalysisService();
        $refined = $service->refineScript($lead, $tenantId, $currentScript, $instruction, $tone, $channel);

        // Persist refined version
        $parentScript = ApproachScript::latestForLead($leadId, $tenantId, $channel);
        ApproachScript::insert($tenantId, [
            'lead_id'   => $leadId,
            'channel'   => $channel,
            'tone'      => $tone,
            'script'    => $refined,
            'context'   => [
                'instruction' => $instruction,
                'lead_name'   => $lead['name'],
            ],
            'version'   => ($parentScript['version'] ?? 0) + 1,
            'parent_id' => $parentScript['id'] ?? null,
        ]);

        echo json_encode(['script' => $refined]);
    }

    /**
     * Upload a playbook document for approach style reference.
     */
    public function uploadPlaybook(): void
    {
        Session::requireAuth();
        header('Content-Type: application/json');

        $tenantId = Session::get('tenant_id');

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            echo json_encode(['error' => 'Token inválido']); return;
        }

        if (empty($_FILES['playbook']) || $_FILES['playbook']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['error' => 'Nenhum arquivo enviado']); return;
        }

        $file = $_FILES['playbook'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['txt', 'md', 'pdf', 'docx'];

        if (!in_array($ext, $allowed)) {
            echo json_encode(['error' => 'Formato não suportado. Use: ' . implode(', ', $allowed)]); return;
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            echo json_encode(['error' => 'Arquivo muito grande. Máximo 10MB.']); return;
        }

        $title = trim($_POST['title'] ?? '') ?: pathinfo($file['name'], PATHINFO_FILENAME);
        $description = trim($_POST['description'] ?? '');

        // Extract text
        $text = $this->extractTextFromFile($file['tmp_name'], $ext);
        if (empty(trim($text))) {
            echo json_encode(['error' => 'Não foi possível extrair texto do documento.']); return;
        }

        // Chunk the text (~500 words per chunk)
        $chunks = $this->chunkText($text, 500);

        $id = ApproachPlaybook::insert($tenantId, [
            'title'       => $title,
            'description' => $description,
            'file_name'   => $file['name'],
            'file_type'   => $ext,
            'content'     => $text,
            'chunks'      => $chunks,
            'status'      => 'ready',
        ]);

        echo json_encode([
            'success'  => true,
            'playbook' => [
                'id'        => $id,
                'title'     => $title,
                'file_name' => $file['name'],
                'chunks'    => count($chunks),
                'status'    => 'ready',
            ],
        ]);
    }

    /**
     * Delete a playbook.
     */
    public function deletePlaybook(): void
    {
        Session::requireAuth();
        header('Content-Type: application/json');

        $tenantId = Session::get('tenant_id');
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!Session::validateCsrf($body['_csrf'] ?? '')) {
            echo json_encode(['error' => 'Token inválido']); return;
        }

        $id = $body['id'] ?? '';
        ApproachPlaybook::delete($id, $tenantId);

        echo json_encode(['success' => true]);
    }

    /**
     * Toggle playbook active/inactive.
     */
    public function togglePlaybook(): void
    {
        Session::requireAuth();
        header('Content-Type: application/json');

        $tenantId = Session::get('tenant_id');
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!Session::validateCsrf($body['_csrf'] ?? '')) {
            echo json_encode(['error' => 'Token inválido']); return;
        }

        $id     = $body['id'] ?? '';
        $active = (bool)($body['active'] ?? true);
        ApproachPlaybook::toggleActive($id, $tenantId, $active);

        echo json_encode(['success' => true]);
    }

    // ── File extraction helpers (reused from KnowledgeController) ──

    private function extractTextFromFile(string $path, string $ext): string
    {
        return match ($ext) {
            'txt', 'md', 'csv' => file_get_contents($path) ?: '',
            'pdf'  => $this->extractTextFromPdf($path),
            'docx' => $this->extractTextFromDocx($path),
            default => '',
        };
    }

    private function extractTextFromPdf(string $path): string
    {
        $content = file_get_contents($path);
        if (!$content) return '';

        $text = '';
        if (preg_match_all('/stream\s*\n(.*?)\nendstream/s', $content, $matches)) {
            foreach ($matches[1] as $stream) {
                $decoded = @gzuncompress($stream);
                if ($decoded === false) $decoded = @gzinflate($stream);
                if ($decoded === false) $decoded = $stream;

                if (preg_match_all('/\(([^)]+)\)/', $decoded, $textMatches)) {
                    $text .= implode(' ', $textMatches[1]) . "\n";
                }
                if (preg_match_all('/\[([^\]]+)\]\s*TJ/i', $decoded, $tjMatches)) {
                    foreach ($tjMatches[1] as $tj) {
                        if (preg_match_all('/\(([^)]+)\)/', $tj, $subMatches)) {
                            $text .= implode('', $subMatches[1]) . ' ';
                        }
                    }
                }
            }
        }

        return trim($text) ?: mb_convert_encoding(preg_replace('/[^\x20-\x7E\xA0-\xFF\n\r]/', '', $content), 'UTF-8', 'auto');
    }

    private function extractTextFromDocx(string $path): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) return '';

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!$xml) return '';

        return strip_tags(str_replace(['<w:p ', '<w:p>', '</w:p>'], ["\n<w:p ", "\n<w:p>", "</w:p>\n"], $xml));
    }

    /**
     * Split text into chunks of ~wordLimit words with overlap.
     */
    private function chunkText(string $text, int $wordLimit = 500, int $overlap = 50): array
    {
        $words  = preg_split('/\s+/', $text);
        $total  = count($words);
        $chunks = [];
        $i = 0;

        while ($i < $total) {
            $end = min($i + $wordLimit, $total);
            $chunks[] = implode(' ', array_slice($words, $i, $end - $i));
            $i = $end - $overlap;
            if ($i >= $total) break;
        }

        return $chunks;
    }
}
