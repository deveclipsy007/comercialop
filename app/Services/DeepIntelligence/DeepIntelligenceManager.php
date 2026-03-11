<?php

namespace App\Services\DeepIntelligence;

use App\Models\DeepIntelligenceRun;
use App\Models\Lead;
use App\Services\TokenService;
use Exception;

class DeepIntelligenceManager
{
    private array $strategies = [];
    private TokenService $tokens;

    public function __construct()
    {
        $this->tokens = new TokenService();
        $this->registerStrategies();
    }

    private function registerStrategies(): void
    {
        // Registrar as estratégias (Módulos de análise)
        $this->strategies = [
            'value_proposition' => new Strategies\ValuePropositionStrategy(),
            'target_audience'   => new Strategies\TargetAudienceStrategy(),
            'competitors'       => new Strategies\CompetitorsStrategy(),
        ];
    }

    /**
     * Retorna as estratégias disponíveis para renderizar a UI.
     */
    public function getAvailableIntelligences(): array
    {
        return array_map(function (IntelligenceStrategyInterface $strategy) {
            return [
                'key'         => $strategy->getKey(),
                'name'        => $strategy->getName(),
                'description' => $strategy->getDescription(),
                'icon'        => $strategy->getIcon(),
                'color'       => $strategy->getColor(),
                'tokens'      => $strategy->getEstimatedTokens(),
            ];
        }, $this->strategies);
    }

    /**
     * Retorna o status de todas as inteligências para um lead específico.
     */
    public function getLeadIntelligences(int $leadId, string $tenantId): array
    {
        $runs = DeepIntelligenceRun::findByLead($leadId, $tenantId);
        
        $history = [];
        // Mapear por key a run mais recente
        foreach ($runs as $run) {
            $key = $run['intelligence_type'];
            if (!isset($history[$key])) {
                // Decode output directly for display
                if($run['result_data']) $run['result_data_decoded'] = json_decode($run['result_data'], true);
                $history[$key] = $run;
            }
        }

        return $history;
    }

    /**
     * Executa uma inteligência específica, gravando status e consumindo tokens.
     */
    public function runIntelligence(int $leadId, string $tenantId, string $type, int $userId): array
    {
        if (!isset($this->strategies[$type])) {
            throw new Exception("Inteligência não encontrada: {$type}");
        }

        $strategy = $this->strategies[$type];
        
        // 1. Criar Registo (Run)
        $runId = DeepIntelligenceRun::create($tenantId, $leadId, $type, $userId);
        DeepIntelligenceRun::updateStatus($runId, DeepIntelligenceRun::STATUS_PROCESSING);

        try {
            // 2. Cobrar Tokens (Verificando saldo implícito se o TokenService fizer isso)
            $this->tokens->consume($type, $tenantId, $strategy->getEstimatedTokens());

            // 3. Buscar Dados do Lead (Para contexto visual + base)
            $lead = Lead::findByTenant($leadId, $tenantId);
            if (!$lead) {
                throw new Exception("Lead não encontrado");
            }

            // 4. Rodar Análise Individual via Strategy
            $resultData = $strategy->execute($lead, $tenantId);

            // 5. Atualizar Registro como Completo
            DeepIntelligenceRun::updateStatus($runId, DeepIntelligenceRun::STATUS_COMPLETED, [
                'result_data' => json_encode($resultData),
                'token_usage' => $strategy->getEstimatedTokens(), // Na prática seria o tokenCount retornado
            ]);

            return [
                'success' => true,
                'run_id'  => $runId,
                'result'  => $resultData,
            ];

        } catch (Exception $e) {
            // Marca como falho em caso de erro
            DeepIntelligenceRun::updateStatus($runId, DeepIntelligenceRun::STATUS_FAILED, [
                'error_message' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
}
