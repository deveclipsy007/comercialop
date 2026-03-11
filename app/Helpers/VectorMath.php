<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Operações matemáticas sobre vetores de embeddings.
 *
 * Sem dependências externas — PHP puro.
 * Performance: 80 chunks × 768 dims → ~60k operações de float ≈ < 1ms.
 */
class VectorMath
{
    /**
     * Similaridade de cosseno entre dois vetores de float.
     *
     * Retorna valor em [-1.0, 1.0]:
     *   1.0  → vetores idênticos
     *   0.0  → vetores ortogonais (sem relação semântica)
     *  -1.0  → vetores opostos
     *
     * Retorna 0.0 se qualquer vetor tiver magnitude zero ou array vazio.
     * Não lança exceção em produção; registra warning no error_log em caso de
     * divergência de dimensões (retorna 0.0 sem quebrar o fluxo).
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        if (empty($a) || empty($b)) {
            return 0.0;
        }

        if (count($a) !== count($b)) {
            error_log(sprintf(
                '[VectorMath] Dimensões divergentes: %d vs %d — retornando 0.0',
                count($a),
                count($b)
            ));
            return 0.0;
        }

        $dot  = self::dotProduct($a, $b);
        $magA = self::magnitude($a);
        $magB = self::magnitude($b);

        if ($magA === 0.0 || $magB === 0.0) {
            return 0.0;
        }

        // Clamp para [-1, 1] para evitar erros de ponto flutuante fora do range
        return max(-1.0, min(1.0, $dot / ($magA * $magB)));
    }

    /**
     * Produto escalar (dot product) de dois vetores de mesma dimensão.
     *
     * @throws \InvalidArgumentException se os arrays tiverem comprimentos diferentes.
     */
    public static function dotProduct(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            throw new \InvalidArgumentException(sprintf(
                'dotProduct requer vetores de mesma dimensão: %d vs %d',
                count($a),
                count($b)
            ));
        }

        $sum = 0.0;
        foreach ($a as $i => $val) {
            $sum += $val * $b[$i];
        }
        return $sum;
    }

    /**
     * Magnitude L2 (norma Euclidiana) de um vetor.
     * Retorna 0.0 para array vazio.
     */
    public static function magnitude(array $v): float
    {
        if (empty($v)) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($v as $val) {
            $sum += $val * $val;
        }
        return sqrt($sum);
    }
}
