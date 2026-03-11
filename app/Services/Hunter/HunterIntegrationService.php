<?php

declare(strict_types=1);

namespace App\Services\Hunter;

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
            'segment'         => $result['segment'] ?? 'Sem Segmento',
            'website'         => $result['website'] ?? '',
            'phone'           => $result['phone'] ?? '',
            'email'           => $result['email'] ?? '',
            'address'         => $result['address'] . ($result['city'] ? ' - ' . $result['city'] : ''),
            'pipeline_status' => 'new',
            'priority_score'  => $analysis ? $analysis['priority_score'] : 0,
            'fit_score'       => $analysis ? $analysis['icp_match_score'] : 0,
            'social_presence' => ['instagram' => $result['instagram'] ?? ''],
            'tags'            => $tags,
            'assigned_to'     => $assignedToUserId
        ];

        // Se há AI analysis, salvá-la também no campo `analysis` e gerar um evento na timeline
        if ($analysis) {
            $leadData['analysis'] = [
                'type'                 => 'hunter_discovery',
                'executive_summary'    => $analysis['executive_summary'],
                'pain_points'          => $analysis['pain_points'],
                'opportunities'        => $analysis['opportunities'],
                'recommended_approach' => $analysis['recommended_approach'],
                'priority_level'       => $analysis['priority_level']
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
                'content' => 'Este lead foi encontrado e importado pela IA do Hunter Protocol.'
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
