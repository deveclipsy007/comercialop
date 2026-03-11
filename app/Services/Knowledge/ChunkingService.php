<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

/**
 * Transforma o perfil da empresa em documentos e chunks prontos para embedding.
 *
 * Estratégia de chunking:
 *   - Cada seção semântica do perfil vira um documento (doc_type distinto)
 *   - Cada documento é quebrado em chunks de ~300 palavras
 *   - Overlap de 50 palavras entre chunks consecutivos para preservar contexto
 *   - Chunks com menos de 30 palavras são descartados (tail chunks insignificantes)
 *
 * Não depende de banco de dados nem de IA — pure PHP, testável de forma isolada.
 */
class ChunkingService
{
    private const CHUNK_WORDS    = 300;
    private const OVERLAP_WORDS  = 50;
    private const MIN_CHUNK_WORDS = 30;

    /**
     * Pipeline completo: perfil da empresa → lista de chunks com metadados.
     *
     * @param  array $profile Linha decodificada de company_profiles
     * @return array Cada item: [doc_type, title, doc_content, chunk_index, content, word_count]
     */
    public function profileToChunks(array $profile): array
    {
        $documents = $this->buildDocuments($profile);
        $allChunks = [];

        foreach ($documents as $doc) {
            $chunks = $this->chunkDocument($doc['content']);
            foreach ($chunks as $chunk) {
                $allChunks[] = array_merge($doc, $chunk);
            }
        }

        return $allChunks;
    }

    /**
     * Cria a lista de documentos semânticos a partir do perfil.
     * Cada documento corresponde a uma seção estratégica distinta.
     *
     * Seções omitidas se o conteúdo gerado for menor que MIN_CHUNK_WORDS palavras.
     *
     * @param  array $profile Linha decodificada de company_profiles
     * @return array Cada item: [doc_type, title, content]
     */
    public function buildDocuments(array $profile): array
    {
        $builders = [
            'identity'     => [$this, 'buildIdentityDoc'],
            'services'     => [$this, 'buildServicesDoc'],
            'differentials'=> [$this, 'buildDifferentialsDoc'],
            'icp'          => [$this, 'buildIcpDoc'],
            'cases'        => [$this, 'buildCasesDoc'],
            'objections'   => [$this, 'buildObjectionsDoc'],
            'competitors'  => [$this, 'buildCompetitorsDoc'],
            'custom'       => [$this, 'buildCustomDoc'],
        ];

        $titles = [
            'identity'     => 'Identidade e Posicionamento',
            'services'     => 'Serviços e Oferta',
            'differentials'=> 'Diferenciais Competitivos',
            'icp'          => 'Perfil de Cliente Ideal (ICP)',
            'cases'        => 'Cases e Prova Social',
            'objections'   => 'Objeções e Argumentos Comerciais',
            'competitors'  => 'Concorrentes e Estratégias',
            'custom'       => 'Contexto Adicional',
        ];

        $documents = [];

        foreach ($builders as $docType => $builder) {
            $content = trim(call_user_func($builder, $profile));
            $words   = str_word_count($content, 0, 'abcdefghijklmnopqrstuvwxyzáàâãéèêíìóòôõúùûçABCDEFGHIJKLMNOPQRSTUVWXYZÁÀÂÃÉÈÊÍÌÓÒÔÕÚÙÛÇ0123456789');

            if ($words < self::MIN_CHUNK_WORDS) {
                continue; // seção vazia ou sem informação útil
            }

            $documents[] = [
                'doc_type' => $docType,
                'title'    => $titles[$docType],
                'content'  => $content,
            ];
        }

        return $documents;
    }

    /**
     * Quebra um texto em chunks com overlap.
     *
     * @return array Cada item: [chunk_index, content, word_count]
     */
    public function chunkDocument(string $text): array
    {
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        if (empty($words)) {
            return [];
        }

        return $this->wordsToChunks($words);
    }

    // ─── Construtores de documento por seção ──────────────────────

    private function buildIdentityDoc(array $p): string
    {
        $parts = [];

        if (!empty($p['agency_name'])) {
            $parts[] = "A empresa se chama {$p['agency_name']}.";
        }
        if (!empty($p['agency_city']) || !empty($p['agency_state'])) {
            $loc = trim(($p['agency_city'] ?? '') . ', ' . ($p['agency_state'] ?? ''), ', ');
            $parts[] = "Localização: {$loc}.";
        }
        if (!empty($p['agency_niche'])) {
            $parts[] = "Nicho de atuação: {$p['agency_niche']}.";
        }
        if (!empty($p['founding_year'])) {
            $parts[] = "Fundada em {$p['founding_year']}.";
        }
        if (!empty($p['team_size'])) {
            $parts[] = "Tamanho da equipe: {$p['team_size']} pessoas.";
        }
        if (!empty($p['website_url'])) {
            $parts[] = "Website: {$p['website_url']}.";
        }
        if (!empty($p['unique_value_prop'])) {
            $parts[] = "Proposta de valor única: {$p['unique_value_prop']}";
        }
        if (!empty($p['awards_recognition'])) {
            $parts[] = "Prêmios e reconhecimentos: {$p['awards_recognition']}";
        }

        return implode("\n", $parts);
    }

    private function buildServicesDoc(array $p): string
    {
        $parts = [];

        if (!empty($p['offer_summary'])) {
            $parts[] = "Resumo da oferta: {$p['offer_summary']}";
        }
        if (!empty($p['offer_price_range'])) {
            $parts[] = "Faixa de preço: {$p['offer_price_range']}.";
        }
        if (!empty($p['delivery_timeline'])) {
            $parts[] = "Prazo de entrega: {$p['delivery_timeline']}.";
        }
        if (!empty($p['guarantees'])) {
            $parts[] = "Garantia oferecida: {$p['guarantees']}";
        }

        $services = is_array($p['services'] ?? null) ? $p['services'] : [];
        foreach ($services as $s) {
            if (empty($s['name'])) continue;
            $line = "Serviço: {$s['name']}.";
            if (!empty($s['description'])) $line .= " Descrição: {$s['description']}.";
            if (!empty($s['price_range'])) $line .= " Preço: {$s['price_range']}.";
            $parts[] = $line;
        }

        return implode("\n", $parts);
    }

    private function buildDifferentialsDoc(array $p): string
    {
        $parts = [];

        $diffs = is_array($p['differentials'] ?? null) ? $p['differentials'] : [];
        if (!empty($diffs)) {
            $parts[] = 'Diferenciais competitivos da empresa:';
            foreach ($diffs as $d) {
                if (!empty(trim((string) $d))) {
                    $parts[] = '- ' . trim((string) $d);
                }
            }
        }

        if (!empty($p['pricing_justification'])) {
            $parts[] = "Justificativa de preço: {$p['pricing_justification']}";
        }

        return implode("\n", $parts);
    }

    private function buildIcpDoc(array $p): string
    {
        $parts = [];

        if (!empty($p['icp_profile'])) {
            $parts[] = "Perfil do cliente ideal: {$p['icp_profile']}";
        }

        $segments = is_array($p['icp_segment'] ?? null) ? $p['icp_segment'] : [];
        if (!empty($segments)) {
            $parts[] = 'Segmentos-alvo: ' . implode(', ', $segments) . '.';
        }

        if (!empty($p['icp_company_size'])) {
            $parts[] = "Porte de empresa ideal: {$p['icp_company_size']}.";
        }
        if (!empty($p['icp_ticket_range'])) {
            $parts[] = "Ticket médio esperado: {$p['icp_ticket_range']}.";
        }

        $pains = is_array($p['icp_pain_points'] ?? null) ? $p['icp_pain_points'] : [];
        if (!empty($pains)) {
            $parts[] = 'Principais dores que a empresa resolve:';
            foreach ($pains as $pain) {
                if (!empty(trim((string) $pain))) {
                    $parts[] = '- ' . trim((string) $pain);
                }
            }
        }

        return implode("\n", $parts);
    }

    private function buildCasesDoc(array $p): string
    {
        $parts = [];

        $cases = is_array($p['cases'] ?? null) ? $p['cases'] : [];
        if (!empty($cases)) {
            $parts[] = 'Cases de sucesso:';
            foreach ($cases as $c) {
                if (empty($c['client'] ?? $c['result'] ?? null)) continue;
                $line = '';
                if (!empty($c['client']))    $line .= "Cliente: {$c['client']}. ";
                if (!empty($c['result']))    $line .= "Resultado: {$c['result']}. ";
                if (!empty($c['niche']))     $line .= "Nicho: {$c['niche']}. ";
                if (!empty($c['timeframe'])) $line .= "Prazo: {$c['timeframe']}.";
                $parts[] = trim($line);
            }
        }

        $testimonials = is_array($p['testimonials'] ?? null) ? $p['testimonials'] : [];
        if (!empty($testimonials)) {
            $parts[] = 'Depoimentos de clientes:';
            foreach ($testimonials as $t) {
                if (empty($t['text'] ?? null)) continue;
                $author = !empty($t['author']) ? " — {$t['author']}" : '';
                $role   = !empty($t['role']) ? " ({$t['role']})" : '';
                $parts[] = "\"{$t['text']}\"{$author}{$role}.";
            }
        }

        if (!empty($p['portfolio_url'])) {
            $parts[] = "Portfólio disponível em: {$p['portfolio_url']}.";
        }

        return implode("\n", $parts);
    }

    private function buildObjectionsDoc(array $p): string
    {
        $parts = [];

        $objections = is_array($p['objection_responses'] ?? null) ? $p['objection_responses'] : [];
        if (!empty($objections)) {
            $parts[] = 'Como responder às objeções mais comuns:';
            foreach ($objections as $o) {
                if (empty($o['objection'] ?? null)) continue;
                $parts[] = "Objeção: {$o['objection']}";
                if (!empty($o['response'])) {
                    $parts[] = "Resposta: {$o['response']}";
                }
                $parts[] = '';
            }
        }

        return implode("\n", $parts);
    }

    private function buildCompetitorsDoc(array $p): string
    {
        $parts = [];

        $competitors = is_array($p['competitors'] ?? null) ? $p['competitors'] : [];
        if (!empty($competitors)) {
            $parts[] = 'Análise de concorrentes e como vencê-los:';
            foreach ($competitors as $c) {
                if (empty($c['name'] ?? null)) continue;
                $line = "Concorrente: {$c['name']}.";
                if (!empty($c['weakness']))   $line .= " Fraqueza: {$c['weakness']}.";
                if (!empty($c['how_to_win'])) $line .= " Como vencer: {$c['how_to_win']}.";
                $parts[] = $line;
            }
        }

        return implode("\n", $parts);
    }

    private function buildCustomDoc(array $p): string
    {
        return trim((string) ($p['custom_context'] ?? ''));
    }

    // ─── Chunking ──────────────────────────────────────────────────

    /**
     * Sliding window sobre palavras:
     *   - Avança CHUNK_WORDS - OVERLAP_WORDS a cada iteração
     *   - Chunks menores que MIN_CHUNK_WORDS são descartados (tail residual)
     *
     * @param  string[] $words
     * @return array Cada item: [chunk_index, content, word_count]
     */
    private function wordsToChunks(array $words): array
    {
        $chunks   = [];
        $total    = count($words);
        $step     = self::CHUNK_WORDS - self::OVERLAP_WORDS;
        $start    = 0;
        $index    = 0;

        while ($start < $total) {
            $slice     = array_slice($words, $start, self::CHUNK_WORDS);
            $wordCount = count($slice);

            if ($wordCount < self::MIN_CHUNK_WORDS) {
                break; // tail muito pequeno, descartado
            }

            $chunks[] = [
                'chunk_index' => $index,
                'content'     => implode(' ', $slice),
                'word_count'  => $wordCount,
            ];

            $start += $step;
            $index++;

            // Documento cabe em um único chunk → não continuar
            if ($wordCount < self::CHUNK_WORDS) {
                break;
            }
        }

        return $chunks;
    }
}
