<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Lead
{
    // Pipeline stages (lowercase, matches schema)
    public const STAGES = [
        'new'         => 'Prospecção',
        'contacted'   => 'Contactado',
        'qualified'   => 'Qualificado',
        'proposal'    => 'Proposta',
        'closed_won'  => 'Ganho',
        'closed_lost' => 'Perdido',
    ];

    public const STAGE_NEW       = 'new';
    public const STAGE_CONTACTED = 'contacted';
    public const STAGE_QUALIFIED = 'qualified';
    public const STAGE_PROPOSAL  = 'proposal';
    public const STAGE_WON       = 'closed_won';
    public const STAGE_LOST      = 'closed_lost';

    // ─── Queries ───────────────────────────────────────────────────

    public static function allByTenant(string $tenantId, array $options = []): array
    {
        $stage    = $options['stage'] ?? null;
        $search   = $options['search'] ?? null;
        $minScore = $options['min_score'] ?? null;
        $segment  = $options['segment'] ?? null;
        $limit    = (int) ($options['limit'] ?? 100);
        $offset   = (int) ($options['offset'] ?? 0);
        $order    = preg_replace('/[^a-z_\s]/', '', $options['order'] ?? 'created_at DESC');

        $sql    = 'SELECT * FROM leads WHERE tenant_id = ?';
        $params = [$tenantId];

        if ($stage) {
            $sql     .= ' AND pipeline_status = ?';
            $params[] = $stage;
        }

        if ($search) {
            $sql     .= ' AND (name LIKE ? OR segment LIKE ? OR phone LIKE ?)';
            $like     = "%{$search}%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($segment) {
            $sql     .= ' AND segment LIKE ?';
            $params[] = "%{$segment}%";
        }

        if ($minScore !== null) {
            $sql     .= ' AND priority_score >= ?';
            $params[] = (int) $minScore;
        }

        $sql .= " ORDER BY {$order} LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return array_map([self::class, 'decode'], Database::select($sql, $params));
    }

    public static function findByTenant(string $id, string $tenantId): ?array
    {
        $lead = Database::selectFirst(
            'SELECT * FROM leads WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );
        return $lead ? self::decode($lead) : null;
    }

    public static function create(string $tenantId, array $data): string
    {
        $id = self::generateId();

        Database::execute(
            'INSERT INTO leads (id, tenant_id, name, segment, website, phone, email, address,
                                pipeline_status, priority_score, fit_score,
                                analysis, human_context, social_presence, tags,
                                cnpj_data, pagespeed_data,
                                google_maps_url, rating, review_count, reviews,
                                opening_hours, closing_hours, category, enrichment_data,
                                created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime(\'now\'), datetime(\'now\'))',
            [
                $id,
                $tenantId,
                $data['name'],
                $data['segment'] ?? '',
                $data['website'] ?? null,
                $data['phone']   ?? null,
                $data['email']   ?? null,
                $data['address'] ?? null,
                $data['pipeline_status'] ?? self::STAGE_NEW,
                (int) ($data['priority_score'] ?? 0),
                (int) ($data['fit_score'] ?? 0),
                isset($data['analysis'])       ? json_encode($data['analysis'])       : null,
                isset($data['human_context'])  ? json_encode($data['human_context'])  : null,
                isset($data['social_presence'])? json_encode($data['social_presence']): null,
                json_encode($data['tags'] ?? []),
                isset($data['cnpj_data'])      ? json_encode($data['cnpj_data'])      : null,
                isset($data['pagespeed_data']) ? json_encode($data['pagespeed_data']) : null,
                $data['google_maps_url'] ?? null,
                isset($data['rating']) ? (float)$data['rating'] : null,
                isset($data['review_count']) ? (int)$data['review_count'] : null,
                isset($data['reviews'])        ? json_encode($data['reviews'])        : null,
                $data['opening_hours'] ?? null,
                $data['closing_hours'] ?? null,
                $data['category'] ?? null,
                isset($data['enrichment_data'])? json_encode($data['enrichment_data']): null,
            ]
        );
        return $id;
    }

    public static function update(string $id, string $tenantId, array $data): bool
    {
        $jsonFields = ['analysis', 'human_context', 'social_presence', 'tags', 'cnpj_data', 'pagespeed_data', 'reviews', 'enrichment_data'];
        $allowed    = ['name', 'segment', 'website', 'phone', 'email', 'address', 'assigned_to',
                       'pipeline_status', 'priority_score', 'fit_score', 'manual_score_override',
                       'next_followup_at', 'analysis', 'human_context', 'social_presence',
                       'tags', 'cnpj_data', 'pagespeed_data',
                       'google_maps_url', 'rating', 'review_count', 'reviews',
                       'opening_hours', 'closing_hours', 'category', 'enrichment_data'];

        $sets   = [];
        $params = [];

        foreach ($data as $key => $value) {
            if (!in_array($key, $allowed, true)) continue;
            $sets[]   = "{$key} = ?";
            $params[] = in_array($key, $jsonFields) ? json_encode($value) : $value;
        }

        if (empty($sets)) return false;

        $sets[]   = "updated_at = datetime('now')";
        $params[] = $id;
        $params[] = $tenantId;

        return Database::execute(
            'UPDATE leads SET ' . implode(', ', $sets) . ' WHERE id = ? AND tenant_id = ?',
            $params
        ) > 0;
    }

    public static function updateStage(string $id, string $tenantId, string $stage): bool
    {
        if (!array_key_exists($stage, self::STAGES)) return false;
        return self::update($id, $tenantId, ['pipeline_status' => $stage]);
    }

    public static function saveAnalysis(string $id, string $tenantId, array $analysis): bool
    {
        $score = max(1, (int) ($analysis['priorityScore'] ?? 25));
        $fit   = max(1, (int) ($analysis['fitScore'] ?? $score));

        $updateData = [
            'analysis'       => $analysis,
            'priority_score' => $score,
            'fit_score'      => $fit,
        ];

        // Enriquecer lead com dados extraídos pela IA (apenas se o lead não tiver esses dados ainda)
        $currentLead = self::findByTenant($id, $tenantId);

        if (!empty($analysis['extractedContact'])) {
            $contact = $analysis['extractedContact'];
            if (!empty($contact['phone']) && empty($currentLead['phone'])) {
                $updateData['phone'] = $contact['phone'];
            }
            if (!empty($contact['address']) && empty($currentLead['address'])) {
                $updateData['address'] = $contact['address'];
            }
            if (!empty($contact['website']) && empty($currentLead['website'])) {
                $updateData['website'] = $contact['website'];
            }
        }

        if (!empty($analysis['socialPresence'])) {
            $social = $analysis['socialPresence'];
            $existingSocial = $currentLead['social_presence'] ?? [];
            $merged = false;

            foreach (['instagram', 'facebook', 'linkedin'] as $platform) {
                if (!empty($social[$platform]) && empty($existingSocial[$platform])) {
                    $existingSocial[$platform] = $social[$platform];
                    $merged = true;
                }
            }

            if ($merged) {
                $updateData['social_presence'] = $existingSocial;
            }
        }

        return self::update($id, $tenantId, $updateData);
    }

    public static function delete(string $id, string $tenantId): bool
    {
        return Database::execute(
            'DELETE FROM leads WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        ) > 0;
    }

    public static function pipelineStats(string $tenantId): array
    {
        $rows = Database::select(
            'SELECT pipeline_status, COUNT(*) as cnt
             FROM leads WHERE tenant_id = ?
             GROUP BY pipeline_status',
            [$tenantId]
        );

        $stats = ['total' => 0, 'avg_score' => 0];
        foreach ($rows as $row) {
            $stats[$row['pipeline_status']] = (int) $row['cnt'];
            $stats['total']               += (int) $row['cnt'];
        }

        $avg = Database::selectFirst(
            'SELECT AVG(priority_score) as avg FROM leads WHERE tenant_id = ?',
            [$tenantId]
        );
        $stats['avg_score'] = round((float) ($avg['avg'] ?? 0), 1);

        return $stats;
    }

    public static function count(string $tenantId): int
    {
        $row = Database::selectFirst(
            'SELECT COUNT(*) as c FROM leads WHERE tenant_id = ?',
            [$tenantId]
        );
        return (int) ($row['c'] ?? 0);
    }

    public static function searchByPhone(string $suffix, string $tenantId, int $limit = 5): array
    {
        return array_map([self::class, 'decode'], Database::select(
            'SELECT * FROM leads WHERE tenant_id = ? AND phone LIKE ? LIMIT ?',
            [$tenantId, '%' . $suffix, $limit]
        ));
    }

    // ─── Helpers ───────────────────────────────────────────────────

    private static function decode(array $lead): array
    {
        $jsonFields = ['analysis', 'human_context', 'social_presence', 'tags', 'cnpj_data', 'pagespeed_data', 'reviews', 'enrichment_data'];
        foreach ($jsonFields as $field) {
            if (isset($lead[$field]) && is_string($lead[$field])) {
                $lead[$field] = json_decode($lead[$field], true) ?? [];
            }
        }
        // Ensure numeric fields
        $lead['priority_score'] = (int) ($lead['priority_score'] ?? 0);
        if (isset($lead['rating']))       $lead['rating']       = (float) $lead['rating'];
        if (isset($lead['review_count'])) $lead['review_count'] = (int) $lead['review_count'];
        return $lead;
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
