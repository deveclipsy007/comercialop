<?php

namespace App\Controllers;

use App\Core\Session;
use App\Services\DeepIntelligence\DeepIntelligenceManager;
use App\Services\AI\AIProviderFactory;
use App\Services\TokenService;
use App\Helpers\AIResponseParser;
use App\Models\Lead;
use App\Models\TokenQuota;
use Exception;

class DeepIntelligenceController
{
    private DeepIntelligenceManager $manager;
    private TokenService $tokens;

    public function __construct()
    {
        $this->manager = new DeepIntelligenceManager();
        $this->tokens = new TokenService();
    }

    /**
     * Endpoint API para executar uma inteligência sob demanda via AJAX.
     * POST /intelligence/run
     * Params: lead_id, type
     */
    public function runIntelligence(): void
    {
        Session::requireAuth();

        // Limpar qualquer output anterior (PHP warnings/deprecation notices)
        if (ob_get_level()) ob_end_clean();
        ob_start();

        header('Content-Type: application/json; charset=utf-8');

        $request = json_decode(file_get_contents('php://input'), true);
        $leadId = trim($request['lead_id'] ?? '');
        $type = trim($request['type'] ?? '');

        if (empty($leadId) || empty($type)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
            return;
        }

        $tenantId = Session::get('tenant_id');
        $userId = Session::get('id');

        try {
            $result = $this->manager->runIntelligence($leadId, $tenantId, $type, $userId);

            // Fetch balance pós-consumo
            $newBalance = TokenQuota::getBalance($tenantId);
            $result['tokenBalance'] = $newBalance;

            ob_end_clean();
            echo json_encode($result);

        } catch (\Throwable $e) {
            ob_end_clean();
            error_log('[DeepIntelligence] Erro: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Endpoint AJAX para salvar ou descobrir perfis sociais do lead.
     * POST /intelligence/social-profiles
     * Params: lead_id, action(save|discover), instagram, linkedin
     */
    public function socialProfiles(): void
    {
        Session::requireAuth();

        if (ob_get_level()) ob_end_clean();
        ob_start();

        header('Content-Type: application/json; charset=utf-8');

        $request = json_decode(file_get_contents('php://input'), true) ?? [];
        $leadId = trim((string) ($request['lead_id'] ?? ''));
        $action = trim((string) ($request['action'] ?? 'save'));

        if ($leadId === '') {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Lead inválido']);
            return;
        }

        $tenantId = Session::get('tenant_id');
        $lead = Lead::findByTenant($leadId, $tenantId);
        if (!$lead) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Lead não encontrado']);
            return;
        }

        try {
            if ($action === 'discover') {
                $discovery = $this->discoverSocialProfiles($lead, $tenantId);
                $profiles = $this->persistSocialProfiles($lead, $tenantId, $discovery['profiles'], false);

                $balance = TokenQuota::getBalance($tenantId);

                ob_end_clean();
                echo json_encode([
                    'success' => true,
                    'profiles' => $profiles,
                    'notes' => $discovery['notes'],
                    'tokenBalance' => $balance,
                ]);
                return;
            }

            $profiles = $this->persistSocialProfiles($lead, $tenantId, [
                'instagram' => $request['instagram'] ?? null,
                'linkedin' => $request['linkedin'] ?? null,
            ], true);

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'profiles' => $profiles,
            ]);
        } catch (\Throwable $e) {
            ob_end_clean();
            error_log('[DeepIntelligence][socialProfiles] Erro: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function discoverSocialProfiles(array $lead, string $tenantId): array
    {
        $provider = AIProviderFactory::make('lead_social_discovery', $tenantId);
        $leadName = trim((string) ($lead['name'] ?? 'Lead'));
        $website = trim((string) ($lead['website'] ?? ''));
        $segment = trim((string) ($lead['segment'] ?? ''));
        $address = trim((string) ($lead['address'] ?? ''));
        $googleMaps = trim((string) ($lead['google_maps_url'] ?? ''));

        $systemPrompt = <<<PROMPT
Você é um pesquisador de perfis sociais públicos.

TAREFA:
- Descobrir perfis OFICIAIS de Instagram e LinkedIn de uma empresa/lead.

REGRAS ABSOLUTAS:
- Use somente pesquisa pública.
- Não invente URLs, handles ou nomes de perfis.
- Se não achar com segurança, retorne vazio.
- Prefira perfis oficiais ligados ao site da empresa, ao nome comercial e à localização.
- Responda APENAS com JSON válido:
{
  "instagram": {"value": "url ou handle confirmado", "note": "como foi encontrado ou por que está confiável"},
  "linkedin": {"value": "url confirmado", "note": "como foi encontrado ou por que está confiável"}
}
PROMPT;

        $userPrompt = <<<PROMPT
Encontre os perfis sociais públicos OFICIAIS deste lead:

Nome: {$leadName}
Segmento: {$segment}
Website: {$website}
Endereço: {$address}
Google Maps: {$googleMaps}

Procure prioritariamente Instagram e LinkedIn.
PROMPT;

        $meta = $provider->generateJsonWithMeta($systemPrompt, $userPrompt, [
            'temperature' => 0.1,
            'max_tokens' => 1200,
            'google_search' => true,
        ]);
        $parsed = $meta['parsed'] ?? [];
        $usage = $meta['usage'] ?? ['input' => 0, 'output' => 0];

        $this->tokens->consume(
            'lead_social_discovery',
            $tenantId,
            Session::get('id'),
            $provider->getProviderName(),
            $provider->getModel(),
            $usage['input'],
            $usage['output']
        );

        if (AIResponseParser::hasError($parsed)) {
            throw new Exception('Erro na busca de perfis sociais: ' . ($parsed['error'] ?? 'Desconhecido'));
        }

        $profiles = [
            'instagram' => $this->normalizeSocialInput('instagram', $parsed['instagram']['value'] ?? null),
            'linkedin' => $this->normalizeSocialInput('linkedin', $parsed['linkedin']['value'] ?? null),
        ];

        $notes = [
            'instagram' => trim((string) ($parsed['instagram']['note'] ?? '')),
            'linkedin' => trim((string) ($parsed['linkedin']['note'] ?? '')),
        ];

        return [
            'profiles' => array_filter($profiles, static fn($value) => $value !== ''),
            'notes' => array_filter($notes, static fn($value) => $value !== ''),
        ];
    }

    private function persistSocialProfiles(array $lead, string $tenantId, array $incoming, bool $allowClear): array
    {
        $current = is_array($lead['social_presence'] ?? null) ? $lead['social_presence'] : [];

        foreach (['instagram', 'linkedin'] as $platform) {
            if (!array_key_exists($platform, $incoming)) {
                continue;
            }

            $normalized = $this->normalizeSocialInput($platform, $incoming[$platform]);
            if ($normalized === '') {
                if ($allowClear) {
                    unset($current[$platform]);
                }
                continue;
            }

            $current[$platform] = $normalized;
        }

        Lead::update((string) $lead['id'], $tenantId, ['social_presence' => $current]);

        return [
            'instagram' => trim((string) ($current['instagram'] ?? '')),
            'linkedin' => trim((string) ($current['linkedin'] ?? '')),
        ];
    }

    private function normalizeSocialInput(string $platform, mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\?.*$/', '', $value) ?? $value;
        $value = preg_replace('/#.*$/', '', $value) ?? $value;

        if ($platform === 'instagram') {
            if (preg_match('#^https?://#i', $value)) {
                return rtrim($value, '/');
            }

            $handle = ltrim($value, '@');
            return $handle !== '' ? 'https://www.instagram.com/' . trim($handle, '/') : '';
        }

        if ($platform === 'linkedin') {
            if (preg_match('#^https?://#i', $value)) {
                return rtrim($value, '/');
            }

            $candidate = trim($value, '/');
            if (str_starts_with($candidate, 'company/') || str_starts_with($candidate, 'in/')) {
                return 'https://www.linkedin.com/' . $candidate;
            }
        }

        return $value;
    }
}
