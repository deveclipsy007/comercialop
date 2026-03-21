<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Helpers;
use App\Models\ApiToken;
use App\Models\Lead;
use App\Services\Extension\ExtensionAuthService;
use App\Services\Extension\LeadNormalizationService;

class ExtensionApiController
{
    private ?array $auth = null;

    public function __construct()
    {
        header('Content-Type: application/json; charset=utf-8');

        // CORS para extensões Chrome
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (str_starts_with($origin, 'chrome-extension://') || $origin === '') {
            header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            header('Access-Control-Max-Age: 86400');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $this->ensureTablesExist();
    }

    // ─── POST /api/ext/auth ─────────────────────────────────────
    public function auth(): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Rate limiting
        if (!ExtensionAuthService::checkRateLimit($ip, 'ext_auth')) {
            http_response_code(429);
            echo json_encode(['error' => true, 'message' => 'Muitas tentativas. Aguarde 15 minutos.']);
            return;
        }

        ExtensionAuthService::logAttempt($ip, 'ext_auth');

        $body    = $this->readJsonBody();
        $email   = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(['error' => true, 'message' => 'Email e senha são obrigatórios.']);
            return;
        }

        $result = ExtensionAuthService::authenticate($email, $password);

        if (!$result) {
            http_response_code(401);
            echo json_encode(['error' => true, 'message' => 'Credenciais inválidas.']);
            return;
        }

        echo json_encode([
            'success'      => true,
            'token'        => $result['token'],
            'expires_at'   => $result['expires_at'],
            'user'         => $result['user'],
            'tenant_id'    => $result['tenant_id'],
            'platform_url' => env('APP_URL', ''),
        ]);
    }

    // ─── POST /api/ext/logout ───────────────────────────────────
    public function logout(): void
    {
        if (!$this->requireToken()) return;

        ExtensionAuthService::logout($this->auth['token_id']);

        echo json_encode(['success' => true, 'message' => 'Token revogado.']);
    }

    // ─── GET /api/ext/me ────────────────────────────────────────
    public function me(): void
    {
        if (!$this->requireToken()) return;

        // Busca nome do tenant
        $tenant = Database::selectFirst(
            "SELECT name FROM tenants WHERE id = ?",
            [$this->auth['tenant_id']]
        );

        echo json_encode([
            'success'     => true,
            'user_id'     => $this->auth['user_id'],
            'user_name'   => $this->auth['user_name'],
            'email'       => $this->auth['email'],
            'role'        => $this->auth['role'],
            'tenant_id'   => $this->auth['tenant_id'],
            'tenant_name' => $tenant['name'] ?? '',
        ]);
    }

    // ─── GET /api/ext/segments ──────────────────────────────────
    public function segments(): void
    {
        if (!$this->requireToken()) return;

        $segments = Database::select(
            "SELECT DISTINCT segment FROM leads
             WHERE tenant_id = ? AND TRIM(COALESCE(segment, '')) != ''
             ORDER BY segment",
            [$this->auth['tenant_id']]
        );

        echo json_encode([
            'success'  => true,
            'segments' => array_column($segments, 'segment'),
        ]);
    }

    // ─── POST /api/ext/check ────────────────────────────────────
    public function check(): void
    {
        if (!$this->requireToken()) return;

        $body = $this->readJsonBody();
        $normalized = LeadNormalizationService::normalize($body);
        $existing = LeadNormalizationService::checkDuplicate($this->auth['tenant_id'], $normalized);

        echo json_encode([
            'success'  => true,
            'exists'   => $existing !== null,
            'lead'     => $existing,
        ]);
    }

    // ─── POST /api/ext/capture ──────────────────────────────────
    public function capture(): void
    {
        if (!$this->requireToken()) return;

        $body = $this->readJsonBody();

        // Validação mínima
        if (empty(trim($body['name'] ?? ''))) {
            http_response_code(422);
            echo json_encode(['error' => true, 'message' => 'Nome é obrigatório.']);
            return;
        }

        // Normalização server-side
        $data = LeadNormalizationService::normalize($body);

        // Verificação de duplicidade (a menos que forçado)
        if (empty($body['force_create'])) {
            $existing = LeadNormalizationService::checkDuplicate($this->auth['tenant_id'], $data);
            if ($existing) {
                echo json_encode([
                    'success'       => false,
                    'duplicate'     => true,
                    'existing_lead' => $existing,
                    'message'       => 'Lead já existe no Vault.',
                ]);
                return;
            }
        }

        // Montar dados para Lead::create()
        $socialPresence = [];
        if (!empty($data['linkedin_url'])) $socialPresence['linkedin'] = $data['linkedin_url'];
        if (!empty($data['instagram_url'])) $socialPresence['instagram'] = $data['instagram_url'];

        $enrichmentData = [
            'source'          => $data['source'] ?? 'chrome_extension',
            'source_url'      => $data['source_url'] ?? null,
            'extractor_type'  => $data['extractor_type'] ?? 'generic',
            'cargo'           => $data['cargo'] ?? null,
            'bio'             => $data['bio'] ?? null,
            'captured_by'     => $this->auth['user_id'],
            'captured_at'     => date('c'),
        ];

        $leadData = [
            'name'            => $data['name'],
            'segment'         => $data['segment'] ?? 'Geral',
            'website'         => $data['website'] ?? null,
            'phone'           => $data['phone'] ?? null,
            'email'           => $data['email'] ?? null,
            'address'         => $data['address'] ?? null,
            'category'        => $data['category'] ?? null,
            'google_maps_url' => $data['google_maps_url'] ?? null,
            'rating'          => $data['rating'] ?? null,
            'review_count'    => $data['review_count'] ?? null,
            'opening_hours'   => $data['opening_hours'] ?? null,
            'closing_hours'   => $data['closing_hours'] ?? null,
            'social_presence' => !empty($socialPresence) ? $socialPresence : null,
            'enrichment_data' => $enrichmentData,
        ];

        $leadId = Lead::create($this->auth['tenant_id'], $leadData);

        // Registrar na timeline
        Database::execute(
            "INSERT INTO lead_activities (id, tenant_id, lead_id, user_id, type, title, content, metadata)
             VALUES (?, ?, ?, ?, 'capture_extension', 'Lead capturado via Operon Capture', ?, ?)",
            [
                Helpers::uuid(),
                $this->auth['tenant_id'],
                $leadId,
                $this->auth['user_id'],
                'Capturado de: ' . ($data['source'] ?? 'web') . ' — ' . ($data['source_url'] ?? ''),
                json_encode([
                    'source'         => $data['source'] ?? 'web',
                    'source_url'     => $data['source_url'] ?? null,
                    'extractor_type' => $data['extractor_type'] ?? 'generic',
                ]),
            ]
        );

        echo json_encode([
            'success' => true,
            'lead_id' => $leadId,
            'url'     => '/vault/' . $leadId,
            'message' => 'Lead capturado com sucesso!',
        ]);
    }

    // ─── POST /api/ext/check-bulk ─────────────────────────────────
    public function checkBulk(): void
    {
        if (!$this->requireToken()) return;

        $body = $this->readJsonBody();
        $leads = $body['leads'] ?? [];

        if (!is_array($leads) || count($leads) === 0) {
            echo json_encode(['success' => true, 'results' => []]);
            return;
        }

        $results = [];
        foreach ($leads as $i => $lead) {
            $normalized = LeadNormalizationService::normalize($lead);
            $existing = LeadNormalizationService::checkDuplicate($this->auth['tenant_id'], $normalized);
            $results[] = [
                'index'    => $i,
                'name'     => $normalized['name'] ?? '',
                'exists'   => $existing !== null,
                'existing' => $existing,
            ];
        }

        echo json_encode(['success' => true, 'results' => $results]);
    }

    // ─── POST /api/ext/capture-bulk ────────────────────────────
    public function captureBulk(): void
    {
        if (!$this->requireToken()) return;

        $body = $this->readJsonBody();
        $leads = $body['leads'] ?? [];

        if (!is_array($leads) || count($leads) === 0) {
            http_response_code(422);
            echo json_encode(['error' => true, 'message' => 'Nenhum lead enviado.']);
            return;
        }

        // Limite de segurança: máximo 50 leads por batch
        if (count($leads) > 50) {
            http_response_code(422);
            echo json_encode(['error' => true, 'message' => 'Máximo de 50 leads por envio.']);
            return;
        }

        $created = [];
        $duplicates = [];
        $errors = [];

        foreach ($leads as $i => $leadData) {
            try {
                $name = trim($leadData['name'] ?? '');
                if (empty($name)) {
                    $errors[] = ['index' => $i, 'message' => 'Nome vazio'];
                    continue;
                }

                $data = LeadNormalizationService::normalize($leadData);

                // Verificação de duplicidade
                $existing = LeadNormalizationService::checkDuplicate($this->auth['tenant_id'], $data);
                if ($existing) {
                    $duplicates[] = [
                        'index'         => $i,
                        'name'          => $data['name'],
                        'existing_lead' => $existing,
                    ];
                    continue;
                }

                // Montar dados para Lead::create()
                $socialPresence = [];
                if (!empty($data['linkedin_url'])) $socialPresence['linkedin'] = $data['linkedin_url'];
                if (!empty($data['instagram_url'])) $socialPresence['instagram'] = $data['instagram_url'];

                $enrichmentData = [
                    'source'          => $data['source'] ?? 'chrome_extension',
                    'source_url'      => $data['source_url'] ?? null,
                    'extractor_type'  => $data['extractor_type'] ?? 'google-maps',
                    'captured_by'     => $this->auth['user_id'],
                    'captured_at'     => date('c'),
                    'bulk_capture'    => true,
                ];

                $createData = [
                    'name'            => $data['name'],
                    'segment'         => $data['segment'] ?? 'Geral',
                    'website'         => $data['website'] ?? null,
                    'phone'           => $data['phone'] ?? null,
                    'email'           => $data['email'] ?? null,
                    'address'         => $data['address'] ?? null,
                    'category'        => $data['category'] ?? null,
                    'google_maps_url' => $data['google_maps_url'] ?? null,
                    'rating'          => $data['rating'] ?? null,
                    'review_count'    => $data['review_count'] ?? null,
                    'opening_hours'   => $data['opening_hours'] ?? null,
                    'social_presence' => !empty($socialPresence) ? $socialPresence : null,
                    'enrichment_data' => $enrichmentData,
                ];

                $leadId = Lead::create($this->auth['tenant_id'], $createData);

                // Registrar na timeline
                Database::execute(
                    "INSERT INTO lead_activities (id, tenant_id, lead_id, user_id, type, title, content, metadata)
                     VALUES (?, ?, ?, ?, 'capture_extension', 'Lead capturado via Operon Capture (bulk)', ?, ?)",
                    [
                        Helpers::uuid(),
                        $this->auth['tenant_id'],
                        $leadId,
                        $this->auth['user_id'],
                        'Captura em massa de: ' . ($data['source'] ?? 'google_maps'),
                        json_encode([
                            'source'         => $data['source'] ?? 'google_maps',
                            'source_url'     => $data['source_url'] ?? null,
                            'extractor_type' => $data['extractor_type'] ?? 'google-maps',
                            'bulk'           => true,
                        ]),
                    ]
                );

                $created[] = [
                    'index'   => $i,
                    'lead_id' => $leadId,
                    'name'    => $data['name'],
                    'url'     => '/vault/' . $leadId,
                ];
            } catch (\Exception $e) {
                $errors[] = ['index' => $i, 'name' => $leadData['name'] ?? '', 'message' => $e->getMessage()];
            }
        }

        echo json_encode([
            'success'    => true,
            'created'    => $created,
            'duplicates' => $duplicates,
            'errors'     => $errors,
            'summary'    => [
                'total'      => count($leads),
                'created'    => count($created),
                'duplicates' => count($duplicates),
                'errors'     => count($errors),
            ],
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────

    /**
     * Extrai e valida o Bearer token. Retorna false e envia 401 se inválido.
     */
    private function requireToken(): bool
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!preg_match('/^Bearer\s+(.+)$/', $header, $matches)) {
            http_response_code(401);
            echo json_encode(['error' => true, 'message' => 'Token de autenticação ausente.']);
            return false;
        }

        $auth = ExtensionAuthService::resolveFromBearerToken($matches[1]);

        if (!$auth) {
            http_response_code(401);
            echo json_encode(['error' => true, 'message' => 'Token inválido ou expirado.']);
            return false;
        }

        $this->auth = $auth;
        return true;
    }

    private function readJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || $raw === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function ensureTablesExist(): void
    {
        try {
            Database::selectFirst("SELECT 1 FROM api_tokens LIMIT 1");
        } catch (\Exception $e) {
            $pdo = Database::connection();
            $migration = ROOT_PATH . '/database/migrations/015_api_tokens.sql';
            if (file_exists($migration)) {
                $sql = file_get_contents($migration);
                // Remove comentários SQL
                $sql = preg_replace('/--.*$/m', '', $sql);
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($statements as $stmt) {
                    if (!empty($stmt)) {
                        $pdo->exec($stmt);
                    }
                }
            }
        }
    }
}
