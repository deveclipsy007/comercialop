<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Helpers;

class HunterResultAnalysis
{
    public static function createOrUpdate(string $tenantId, string $resultId, array $data): string
    {
        $existing = Database::selectFirst('SELECT id FROM hunter_result_analysis WHERE hunter_result_id = ? AND tenant_id = ?', [$resultId, $tenantId]);
        
        if ($existing) {
            Database::execute(
                'UPDATE hunter_result_analysis SET 
                    executive_summary = ?, pain_points = ?, opportunities = ?, recommended_approach = ?, 
                    icp_match_score = ?, priority_score = ?, priority_level = ?, metadata = ?, updated_at = datetime("now")
                 WHERE id = ? AND tenant_id = ?',
                [
                    $data['executive_summary'] ?? null,
                    is_array($data['pain_points'] ?? null) ? json_encode($data['pain_points']) : null,
                    is_array($data['opportunities'] ?? null) ? json_encode($data['opportunities']) : null,
                    $data['recommended_approach'] ?? null,
                    $data['icp_match_score'] ?? 0,
                    $data['priority_score'] ?? 0,
                    $data['priority_level'] ?? 'cold',
                    is_array($data['metadata'] ?? null) ? json_encode($data['metadata']) : null,
                    $existing['id'],
                    $tenantId
                ]
            );
            return $existing['id'];
        } else {
            $id = Helpers::uuid();
            Database::execute(
                'INSERT INTO hunter_result_analysis (
                    id, tenant_id, hunter_result_id, executive_summary, pain_points, opportunities, 
                    recommended_approach, icp_match_score, priority_score, priority_level, metadata
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $id,
                    $tenantId,
                    $resultId,
                    $data['executive_summary'] ?? null,
                    is_array($data['pain_points'] ?? null) ? json_encode($data['pain_points']) : null,
                    is_array($data['opportunities'] ?? null) ? json_encode($data['opportunities']) : null,
                    $data['recommended_approach'] ?? null,
                    $data['icp_match_score'] ?? 0,
                    $data['priority_score'] ?? 0,
                    $data['priority_level'] ?? 'cold',
                    is_array($data['metadata'] ?? null) ? json_encode($data['metadata']) : null,
                ]
            );
            return $id;
        }
    }

    public static function findByResultId(string $resultId, string $tenantId): ?array
    {
        $row = Database::selectFirst('SELECT * FROM hunter_result_analysis WHERE hunter_result_id = ? AND tenant_id = ?', [$resultId, $tenantId]);
        if ($row) {
            $row['pain_points'] = json_decode($row['pain_points'] ?? '[]', true);
            $row['opportunities'] = json_decode($row['opportunities'] ?? '[]', true);
            $row['metadata'] = json_decode($row['metadata'] ?? '{}', true) ?: [];
        }
        return $row;
    }
}
