<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Perfil de conhecimento estratégico da empresa por tenant.
 *
 * Convive com agency_settings (não substitui no banco).
 * SmartContextService usa RAG quando indexed, cai para agency_settings se não.
 *
 * Todos os campos JSON são auto-decodificados em decode().
 */
class CompanyProfile
{
    // Status de indexação do pipeline RAG
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_INDEXED    = 'indexed';
    public const STATUS_ERROR      = 'error';

    // Campos que precisam de json_encode/decode
    private const JSON_FIELDS = [
        'services', 'differentials', 'icp_segment', 'icp_pain_points',
        'cases', 'testimonials', 'objection_responses', 'competitors',
    ];

    // ─── Queries ───────────────────────────────────────────────────

    public static function findByTenant(string $tenantId): ?array
    {
        $row = Database::selectFirst(
            'SELECT * FROM company_profiles WHERE tenant_id = ?',
            [$tenantId]
        );
        return $row ? self::decode($row) : null;
    }

    /**
     * Cria ou atualiza o perfil do tenant.
     * Incrementa profile_version e reseta indexing_status para 'pending'
     * a cada atualização (forçando re-indexação).
     *
     * Retorna o id do perfil (criado ou existente).
     */
    public static function upsert(string $tenantId, array $data): string
    {
        $existing = Database::selectFirst(
            'SELECT id, profile_version FROM company_profiles WHERE tenant_id = ?',
            [$tenantId]
        );

        $version = $existing ? ((int) $existing['profile_version'] + 1) : 1;
        $id      = $existing['id'] ?? self::generateId();

        // Codifica campos JSON
        $encoded = self::encodeJsonFields($data);

        if ($existing) {
            Database::execute(
                "UPDATE company_profiles SET
                    agency_name = ?, agency_city = ?, agency_state = ?,
                    agency_niche = ?, founding_year = ?, team_size = ?,
                    website_url = ?, offer_summary = ?, offer_price_range = ?,
                    services = ?, guarantees = ?, delivery_timeline = ?,
                    differentials = ?, unique_value_prop = ?, awards_recognition = ?,
                    icp_profile = ?, icp_segment = ?, icp_company_size = ?,
                    icp_ticket_range = ?, icp_pain_points = ?,
                    cases = ?, testimonials = ?, portfolio_url = ?,
                    objection_responses = ?, competitors = ?,
                    pricing_justification = ?, custom_context = ?,
                    profile_version = ?, indexing_status = 'pending',
                    indexing_error = NULL,
                    updated_at = datetime('now')
                 WHERE id = ?",
                [
                    $encoded['agency_name'] ?? null,
                    $encoded['agency_city'] ?? null,
                    $encoded['agency_state'] ?? null,
                    $encoded['agency_niche'] ?? null,
                    $encoded['founding_year'] ?? null,
                    $encoded['team_size'] ?? null,
                    $encoded['website_url'] ?? null,
                    $encoded['offer_summary'] ?? null,
                    $encoded['offer_price_range'] ?? null,
                    $encoded['services'] ?? null,
                    $encoded['guarantees'] ?? null,
                    $encoded['delivery_timeline'] ?? null,
                    $encoded['differentials'] ?? null,
                    $encoded['unique_value_prop'] ?? null,
                    $encoded['awards_recognition'] ?? null,
                    $encoded['icp_profile'] ?? null,
                    $encoded['icp_segment'] ?? null,
                    $encoded['icp_company_size'] ?? null,
                    $encoded['icp_ticket_range'] ?? null,
                    $encoded['icp_pain_points'] ?? null,
                    $encoded['cases'] ?? null,
                    $encoded['testimonials'] ?? null,
                    $encoded['portfolio_url'] ?? null,
                    $encoded['objection_responses'] ?? null,
                    $encoded['competitors'] ?? null,
                    $encoded['pricing_justification'] ?? null,
                    $encoded['custom_context'] ?? null,
                    $version,
                    $id,
                ]
            );
        } else {
            Database::execute(
                "INSERT INTO company_profiles (
                    id, tenant_id, agency_name, agency_city, agency_state,
                    agency_niche, founding_year, team_size, website_url,
                    offer_summary, offer_price_range, services, guarantees,
                    delivery_timeline, differentials, unique_value_prop,
                    awards_recognition, icp_profile, icp_segment, icp_company_size,
                    icp_ticket_range, icp_pain_points, cases, testimonials,
                    portfolio_url, objection_responses, competitors,
                    pricing_justification, custom_context,
                    profile_version, indexing_status, created_at, updated_at
                ) VALUES (
                    ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,
                    ?, 'pending', datetime('now'), datetime('now')
                )",
                [
                    $id, $tenantId,
                    $encoded['agency_name'] ?? null,
                    $encoded['agency_city'] ?? null,
                    $encoded['agency_state'] ?? null,
                    $encoded['agency_niche'] ?? null,
                    $encoded['founding_year'] ?? null,
                    $encoded['team_size'] ?? null,
                    $encoded['website_url'] ?? null,
                    $encoded['offer_summary'] ?? null,
                    $encoded['offer_price_range'] ?? null,
                    $encoded['services'] ?? null,
                    $encoded['guarantees'] ?? null,
                    $encoded['delivery_timeline'] ?? null,
                    $encoded['differentials'] ?? null,
                    $encoded['unique_value_prop'] ?? null,
                    $encoded['awards_recognition'] ?? null,
                    $encoded['icp_profile'] ?? null,
                    $encoded['icp_segment'] ?? null,
                    $encoded['icp_company_size'] ?? null,
                    $encoded['icp_ticket_range'] ?? null,
                    $encoded['icp_pain_points'] ?? null,
                    $encoded['cases'] ?? null,
                    $encoded['testimonials'] ?? null,
                    $encoded['portfolio_url'] ?? null,
                    $encoded['objection_responses'] ?? null,
                    $encoded['competitors'] ?? null,
                    $encoded['pricing_justification'] ?? null,
                    $encoded['custom_context'] ?? null,
                    $version,
                ]
            );
        }

        return $id;
    }

    /**
     * Atualiza o status de indexação e metadados associados.
     */
    public static function setStatus(
        string $tenantId,
        string $status,
        ?string $error = null,
        int $chunksCount = 0
    ): void {
        $lastIndexedAt = ($status === self::STATUS_INDEXED) ? "datetime('now')" : 'last_indexed_at';

        Database::execute(
            "UPDATE company_profiles
             SET indexing_status = ?,
                 indexing_error  = ?,
                 chunks_count    = ?,
                 last_indexed_at = CASE WHEN ? = 'indexed' THEN datetime('now') ELSE last_indexed_at END,
                 updated_at      = datetime('now')
             WHERE tenant_id = ?",
            [$status, $error, $chunksCount, $status, $tenantId]
        );
    }

    /**
     * Converte uma linha de agency_settings para o shape de company_profiles.
     * Usado em migrate_rag.php para migrar dados existentes.
     */
    public static function fromAgencySettings(array $row): array
    {
        $differentials = is_string($row['differentials'] ?? null)
            ? json_decode($row['differentials'], true)
            : ($row['differentials'] ?? []);

        $services = is_string($row['services'] ?? null)
            ? json_decode($row['services'], true)
            : ($row['services'] ?? []);

        // services em agency_settings: [{name, price}] → normaliza para [{name, price_range}]
        $normalizedServices = array_map(function (mixed $s) {
            if (!is_array($s)) return ['name' => (string) $s, 'description' => '', 'price_range' => ''];
            return [
                'name'        => $s['name'] ?? '',
                'description' => $s['description'] ?? '',
                'price_range' => $s['price'] ?? $s['price_range'] ?? '',
            ];
        }, $services);

        $cases = is_string($row['cases'] ?? null)
            ? json_decode($row['cases'], true)
            : ($row['cases'] ?? []);

        return [
            'agency_name'       => $row['agency_name'] ?? null,
            'agency_city'       => $row['agency_city'] ?? null,
            'agency_niche'      => $row['agency_niche'] ?? null,
            'offer_summary'     => $row['offer_summary'] ?? null,
            'services'          => $normalizedServices,
            'differentials'     => is_array($differentials) ? $differentials : [],
            'icp_profile'       => $row['icp_profile'] ?? null,
            'cases'             => is_array($cases) ? $cases : [],
            'custom_context'    => $row['custom_context'] ?? null,
        ];
    }

    // ─── Helpers ───────────────────────────────────────────────────

    private static function decode(array $row): array
    {
        foreach (self::JSON_FIELDS as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $row[$field] = json_decode($row[$field], true) ?? [];
            }
        }
        $row['profile_version'] = (int) ($row['profile_version'] ?? 1);
        $row['chunks_count']    = (int) ($row['chunks_count'] ?? 0);
        return $row;
    }

    private static function encodeJsonFields(array $data): array
    {
        foreach (self::JSON_FIELDS as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field], JSON_UNESCAPED_UNICODE);
            }
        }
        return $data;
    }

    private static function generateId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
