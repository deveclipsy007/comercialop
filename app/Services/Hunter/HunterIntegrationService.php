<?php

declare(strict_types=1);

namespace App\Services\Hunter;

use App\Core\Database;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\HunterResult;
use App\Models\HunterResultAnalysis;

class HunterIntegrationService
{
    /**
     * Import a Hunter Result to the Main Leads Table.
     * 
     * @return string The ID of the newly created Lead.
     */
    public static function importToCrm(string $hunterResultId, string $tenantId, string $assignedToUserId): ?string
    {
        $result = HunterResult::findById($hunterResultId, $tenantId);
        if (!$result) return null;

        if ($result['is_imported'] && !empty($result['imported_lead_id'])) {
            return $result['imported_lead_id'];
        }

        $existingLead = Database::selectFirst(
            'SELECT id FROM leads
             WHERE tenant_id = ?
               AND (
                    (google_maps_url IS NOT NULL AND google_maps_url <> "" AND google_maps_url = ?)
                    OR
                    (phone IS NOT NULL AND phone <> "" AND phone = ?)
               )
             LIMIT 1',
            [
                $tenantId,
                $result['google_maps_url'] ?? '',
                $result['phone'] ?? '',
            ]
        );

        if ($existingLead && !empty($existingLead['id'])) {
            HunterResult::markImported($hunterResultId, $tenantId, $existingLead['id']);
            return $existingLead['id'];
        }

        $analysis = HunterResultAnalysis::findByResultId($hunterResultId, $tenantId);

        // Tags automáticas baseadas no Analysis
        $tags = ['Origem: Hunter Protocol'];
        if ($analysis && $analysis['priority_level'] === 'hot') {
            $tags[] = 'Hot Prospect';
        }
        if (!empty($result['segment'])) {
            $tags[] = $result['segment'];
        }

        // Verifica duplicidade básica (Nome ou Telefone)
        // Isso poderia ser mais sofisticado
        
        $leadData = [
            'name'            => $result['name'],
            'segment'         => $result['category'] ?? $result['segment'] ?? 'Sem Segmento',
            'website'         => $result['website'] ?? '',
            'phone'           => $result['phone'] ?? '',
            'email'           => $result['email'] ?? '',
            'address'         => trim((string) ($result['address'] ?? '') . (!empty($result['city']) ? ' - ' . $result['city'] : '') . (!empty($result['state']) ? '/' . $result['state'] : '')),
            'pipeline_status' => 'new',
            'priority_score'  => $analysis ? $analysis['priority_score'] : 0,
            'fit_score'       => $analysis ? $analysis['icp_match_score'] : 0,
            'social_presence' => array_filter([
                'instagram' => $result['instagram'] ?? '',
                'facebook' => $result['website_scan']['facebook'] ?? '',
                'linkedin' => $result['website_scan']['linkedin'] ?? '',
            ]),
            'tags'            => $tags,
            'assigned_to'     => $assignedToUserId,
            'google_maps_url' => $result['google_maps_url'] ?? null,
            'rating'          => $result['google_rating'] ?? null,
            'review_count'    => $result['google_reviews'] ?? null,
            'opening_hours'   => $result['opening_hours_text'] ?? null,
            'category'        => $result['category'] ?? null,
            'latitude'        => $result['latitude'] ?? null,
            'longitude'       => $result['longitude'] ?? null,
            'enrichment_data' => [
                'hunter' => [
                    'place_id' => $result['place_id'] ?? null,
                    'status_label' => $result['status_label'] ?? null,
                    'verification' => $result['verification'] ?? [],
                    'field_statuses' => $result['field_statuses'] ?? [],
                    'digital_presence' => $result['digital_presence'] ?? [],
                    'import_notes' => $result['import_notes'] ?? [],
                ],
            ],
        ];

        // Se há AI analysis, salvá-la também no campo `analysis` e gerar um evento na timeline
        if ($analysis) {
            $leadData['analysis'] = [
                'type'                 => 'hunter_discovery',
                'executive_summary'    => $analysis['executive_summary'],
                'pain_points'          => $analysis['pain_points'],
                'opportunities'        => $analysis['opportunities'],
                'recommended_approach' => $analysis['recommended_approach'],
                'priority_level'       => $analysis['priority_level'],
                'metadata'             => $analysis['metadata'] ?? [],
            ];
        }

        $leadId = Lead::create($tenantId, $leadData);

        if ($leadId) {
            HunterResult::markImported($hunterResultId, $tenantId, $leadId);

            // Adicionar evento na timeline
            LeadActivity::create($tenantId, [
                'lead_id' => $leadId,
                'user_id' => $assignedToUserId,
                'type'    => 'stage_change',
                'title'   => 'Importado do Hunter Protocol',
                'content' => 'Este lead foi encontrado e importado com dados verificados do Hunter Protocol.'
            ]);
            
            if ($analysis) {
                 LeadActivity::create($tenantId, [
                    'lead_id' => $leadId,
                    'user_id' => $assignedToUserId,
                    'type'    => 'ai_analysis',
                    'title'   => 'Análise Hunter Discovery',
                    'content' => $analysis['executive_summary'],
                    'metadata'=> ['pain_points' => $analysis['pain_points']]
                ]);
            }
        }

        return $leadId;
    }
}
