<?php

namespace App\Services\DeepIntelligence;

interface IntelligenceStrategyInterface
{
    /**
     * Identificador unico para a inteligência (ex: 'value_proposition').
     */
    public function getKey(): string;

    /**
     * Nome Legível (ex: 'O que vende').
     */
    public function getName(): string;

    /**
     * Breve explicação do que esta Inteligência descobre.
     */
    public function getDescription(): string;

    /**
     * Ícone Material Symbols.
     */
    public function getIcon(): string;

    /**
     * Cor CSS do item para a interface (opcional).
     */
    public function getColor(): string;

    /**
     * Estimativa de consumo de tokens.
     */
    public function getEstimatedTokens(): int;

    /**
     * Método principal responsável por executar a análise e devolver os dados.
     * Retorna um array com o resumo/texto que será gravado no banco de dados.
     */
    public function execute(array $lead, string $tenantId): array;
}
