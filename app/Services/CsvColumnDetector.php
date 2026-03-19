<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Motor heurístico de detecção de colunas para CSVs desorganizados.
 *
 * Analisa o conteúdo real de cada coluna (não apenas o cabeçalho)
 * para inferir qual campo de lead cada coluna representa.
 *
 * Campos detectados: name, email, phone, website, segment, address,
 *                     city, state, position, notes
 */
class CsvColumnDetector
{
    // Estados brasileiros (sigla => nome)
    private const ESTADOS_BR = [
        'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
        'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
        'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
        'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
        'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
        'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
        'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins',
    ];

    // Palavras-chave de cabeçalho → campo (complemento da análise de conteúdo)
    private const HEADER_HINTS = [
        'name' => ['name', 'nome', 'empresa', 'razao social', 'razao', 'company', 'company name', 'nome da empresa', 'nome empresa', 'fantasia', 'nome fantasia', 'razão social', 'nome completo', 'full name', 'responsavel', 'responsável'],
        'email' => ['email', 'e mail', 'e-mail', 'correio', 'mail', 'electronic mail'],
        'phone' => ['phone', 'telefone', 'tel', 'celular', 'fone', 'whatsapp', 'contato', 'numero', 'mobile', 'cell', 'tel fixo', 'tel celular', 'whats'],
        'website' => ['website', 'site', 'url', 'pagina', 'web', 'link', 'homepage', 'página'],
        'segment' => ['segment', 'segmento', 'nicho', 'setor', 'ramo', 'area', 'industry', 'categoria', 'tipo', 'atividade', 'área'],
        'address' => ['address', 'endereco', 'endereço', 'logradouro', 'rua', 'localizacao', 'localização', 'bairro'],
        'city' => ['cidade', 'city', 'municipio', 'município', 'localidade'],
        'state' => ['estado', 'state', 'uf', 'provincia', 'província', 'region', 'regiao', 'região'],
        'position' => ['cargo', 'position', 'funcao', 'função', 'titulo', 'título', 'job', 'job title', 'role', 'ocupacao', 'ocupação', 'profissao', 'profissão'],
        'notes' => ['observacao', 'observação', 'obs', 'notes', 'nota', 'notas', 'comentario', 'comentário', 'descricao', 'descrição', 'description', 'info', 'informacao', 'informação'],
    ];

    // Sufixos empresariais comuns no Brasil
    private const COMPANY_SUFFIXES = ['ltda', 'me', 'epp', 'eireli', 's/a', 's.a', 'sa', 'inc', 'llc', 'ltd', 'corp', 'co', 'group', 'grupo', 'cia', 'holding'];

    // Cargos/posições comuns
    private const POSITION_KEYWORDS = ['diretor', 'gerente', 'analista', 'coordenador', 'supervisor', 'assistente', 'estagiário', 'consultor', 'vendedor', 'representante', 'sócio', 'proprietário', 'dono', 'ceo', 'cfo', 'cto', 'coo', 'vp', 'presidente', 'manager', 'developer', 'engineer', 'designer', 'specialist', 'head', 'lead', 'founder', 'owner', 'partner'];

    private array $rows = [];
    private array $headers = [];
    private int $totalRows = 0;

    /**
     * Analisa um CSV e retorna o mapeamento detectado.
     *
     * @param string $filePath Caminho do arquivo CSV (já em UTF-8)
     * @param string $delimiter Delimitador detectado
     * @param int $maxSampleRows Máximo de linhas para amostragem
     * @return array{mapping: array, confidence: array, preview: array, headers: array, sample_rows: array, stats: array}
     */
    public function analyze(string $filePath, string $delimiter = ',', int $maxSampleRows = 100): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['mapping' => [], 'confidence' => [], 'preview' => [], 'headers' => [], 'sample_rows' => [], 'stats' => []];
        }

        // Ler cabeçalho
        $this->headers = fgetcsv($handle, 0, $delimiter, '"', '') ?: [];
        $colCount = count($this->headers);

        if ($colCount === 0) {
            fclose($handle);
            return ['mapping' => [], 'confidence' => [], 'preview' => [], 'headers' => [], 'sample_rows' => [], 'stats' => []];
        }

        // Ler linhas de amostra
        $this->rows = [];
        $rowNum = 0;
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false && $rowNum < $maxSampleRows) {
            if (count($row) >= 2) {
                $this->rows[] = $row;
                $rowNum++;
            }
        }

        // Contar total de linhas restantes
        $this->totalRows = $rowNum;
        while (fgetcsv($handle, 0, $delimiter, '"', '') !== false) {
            $this->totalRows++;
        }
        fclose($handle);

        if (empty($this->rows)) {
            return ['mapping' => [], 'confidence' => [], 'preview' => [], 'headers' => $this->headers, 'sample_rows' => [], 'stats' => ['total_rows' => 0]];
        }

        // Detectar se a primeira "linha" (headers) parece ser dados e não cabeçalho
        $headersLookLikeData = $this->headersLookLikeData();

        // Se os headers parecem ser dados, inserir como primeira linha e gerar headers genéricos
        if ($headersLookLikeData) {
            array_unshift($this->rows, $this->headers);
            $this->totalRows++;
            $this->headers = array_map(fn($i) => "Coluna " . ($i + 1), range(0, $colCount - 1));
        }

        // Extrair valores por coluna
        $columns = $this->extractColumns($colCount);

        // Calcular scores para cada coluna × cada tipo de campo
        $scores = [];
        for ($i = 0; $i < $colCount; $i++) {
            $values = $columns[$i] ?? [];
            $header = $this->headers[$i] ?? '';
            $scores[$i] = $this->scoreColumn($values, $header);
        }

        // Resolver mapeamento (atribuição ótima evitando conflitos)
        $mapping = $this->resolveMapping($scores);

        // Calcular confiança
        $confidence = [];
        foreach ($mapping as $colIdx => $field) {
            $confidence[$colIdx] = $scores[$colIdx][$field] ?? 0;
        }

        // Preview das primeiras linhas com o mapeamento aplicado
        $previewRows = array_slice($this->rows, 0, 5);
        $preview = [];
        foreach ($previewRows as $row) {
            $mapped = [];
            foreach ($mapping as $colIdx => $field) {
                $mapped[$field] = trim($row[$colIdx] ?? '');
            }
            $preview[] = $mapped;
        }

        // Sample rows para a UI
        $sampleRows = array_slice($this->rows, 0, 5);

        return [
            'mapping' => $mapping,          // colIdx => fieldName
            'confidence' => $confidence,    // colIdx => 0-100
            'preview' => $preview,          // array de leads mapeados
            'headers' => $this->headers,    // cabeçalhos originais
            'sample_rows' => $sampleRows,   // primeiras linhas brutas
            'stats' => [
                'total_rows' => $this->totalRows,
                'total_columns' => $colCount,
                'mapped_columns' => count($mapping),
                'headers_are_data' => $headersLookLikeData,
            ],
        ];
    }

    /**
     * Verifica se os "cabeçalhos" parecem ser dados em vez de nomes de colunas.
     */
    private function headersLookLikeData(): bool
    {
        $dataSignals = 0;
        $totalHeaders = count($this->headers);

        foreach ($this->headers as $h) {
            $h = trim($h);
            // Se parece email, telefone, URL, ou é muito longo, provavelmente é dado
            if (filter_var($h, FILTER_VALIDATE_EMAIL)) { $dataSignals += 2; continue; }
            if (preg_match('/^[\+\(]?\d[\d\s\-\(\)\.]{7,}$/', $h)) { $dataSignals += 2; continue; }
            if (preg_match('/^https?:\/\//i', $h) || preg_match('/\.(com|net|org|br|io)/i', $h)) { $dataSignals += 2; continue; }
            if (mb_strlen($h) > 50) { $dataSignals++; continue; }
            // Se é puramente numérico
            if (preg_match('/^\d+$/', $h) && strlen($h) > 4) { $dataSignals++; continue; }
        }

        // Se mais de 40% dos headers parecem dados
        return $totalHeaders > 0 && ($dataSignals / $totalHeaders) > 0.4;
    }

    /**
     * Extrai valores por coluna, filtrando vazios.
     */
    private function extractColumns(int $colCount): array
    {
        $columns = array_fill(0, $colCount, []);
        foreach ($this->rows as $row) {
            for ($i = 0; $i < $colCount; $i++) {
                $val = trim($row[$i] ?? '');
                if ($val !== '') {
                    $columns[$i][] = $val;
                }
            }
        }
        return $columns;
    }

    /**
     * Calcula pontuação de cada tipo de campo para uma coluna.
     * Retorna array[fieldType => score 0-100]
     */
    private function scoreColumn(array $values, string $header): array
    {
        $scores = [
            'email' => 0, 'phone' => 0, 'website' => 0,
            'name' => 0, 'segment' => 0, 'address' => 0,
            'city' => 0, 'state' => 0, 'position' => 0, 'notes' => 0,
        ];

        if (empty($values)) return $scores;

        $total = count($values);

        // ═══ 1. SCORES BASEADOS NO CONTEÚDO ═══

        // EMAIL: presença de @ e formato de email
        $emailCount = 0;
        foreach ($values as $v) {
            if (filter_var($v, FILTER_VALIDATE_EMAIL) || preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $v)) {
                $emailCount++;
            }
        }
        $scores['email'] = ($emailCount / $total) * 90;

        // PHONE: padrões numéricos com formatação de telefone
        $phoneCount = 0;
        foreach ($values as $v) {
            $digits = preg_replace('/\D/', '', $v);
            $digitLen = strlen($digits);
            if ($digitLen >= 8 && $digitLen <= 15) {
                // Tem formato de telefone (DDD, parênteses, hífens, +)
                if (preg_match('/[\+\(\)\-\s]/', $v) && preg_match('/\d{2,}/', $v)) {
                    $phoneCount++;
                }
                // Ou é sequência numérica pura com comprimento de telefone
                elseif ($digitLen >= 10 && $digitLen <= 13 && preg_match('/^\+?\d[\d\s\-\(\)\.]*$/', $v)) {
                    $phoneCount++;
                }
                // Telefone brasileiro: (XX) XXXXX-XXXX ou similar
                elseif (preg_match('/^\(?\d{2}\)?\s?\d{4,5}[\-\s]?\d{4}$/', $v)) {
                    $phoneCount++;
                }
            }
        }
        $scores['phone'] = ($phoneCount / $total) * 90;

        // WEBSITE: URLs e domínios
        $urlCount = 0;
        foreach ($values as $v) {
            $v = strtolower($v);
            if (preg_match('/^https?:\/\//i', $v)) { $urlCount++; continue; }
            if (preg_match('/^www\./i', $v)) { $urlCount++; continue; }
            if (preg_match('/^[a-z0-9][\w\-]*\.(com|net|org|br|io|co|app|dev|me|tech|store|shop|digital|online|site|blog|info|biz)/i', $v)) { $urlCount++; continue; }
        }
        $scores['website'] = ($urlCount / $total) * 90;

        // STATE: siglas de estados brasileiros
        $stateCount = 0;
        $stateNames = array_map(fn($n) => mb_strtolower($n), array_values(self::ESTADOS_BR));
        $stateSiglas = array_map('strtolower', array_keys(self::ESTADOS_BR));
        foreach ($values as $v) {
            $vLower = mb_strtolower(trim($v));
            if (in_array($vLower, $stateSiglas, true) || in_array($vLower, $stateNames, true)) {
                $stateCount++;
            }
        }
        $scores['state'] = ($stateCount / $total) * 90;
        // Bônus: se a maioria tem exatamente 2 caracteres (siglas de UF)
        $twoCharCount = count(array_filter($values, fn($v) => mb_strlen(trim($v)) === 2));
        if ($twoCharCount / $total > 0.7 && $stateCount / $total > 0.5) {
            $scores['state'] += 10;
        }

        // CITY: heurística — textos curtos (3-30 chars), sem @, sem números dominantes,
        // e não são siglas de 2 letras (estado), nem emails, nem telefones
        $cityLikeCount = 0;
        foreach ($values as $v) {
            $len = mb_strlen($v);
            if ($len >= 3 && $len <= 40 && !str_contains($v, '@') && !preg_match('/^[\d\+\(\)\-\s\.]+$/', $v)) {
                // Texto com letras, pode ter espaços e acentos, sem números dominantes
                $letterRatio = mb_strlen(preg_replace('/[^a-zA-ZÀ-ÿ\s]/u', '', $v)) / max($len, 1);
                if ($letterRatio > 0.8 && $len > 2) {
                    $cityLikeCount++;
                }
            }
        }
        // City score é baixo naturalmente — depende muito de contexto
        $scores['city'] = ($cityLikeCount / $total) * 40;

        // POSITION (cargo): palavras-chave de cargos
        $positionCount = 0;
        foreach ($values as $v) {
            $vLower = mb_strtolower($v);
            foreach (self::POSITION_KEYWORDS as $kw) {
                if (str_contains($vLower, $kw)) {
                    $positionCount++;
                    break;
                }
            }
        }
        $scores['position'] = ($positionCount / $total) * 85;

        // NAME: textos com 2-60 chars, capitalizados, sem @, sem muitos dígitos
        $nameCount = 0;
        $avgLen = 0;
        foreach ($values as $v) {
            $avgLen += mb_strlen($v);
            $len = mb_strlen($v);
            if ($len >= 2 && $len <= 80) {
                $digitRatio = strlen(preg_replace('/\D/', '', $v)) / max($len, 1);
                $hasAt = str_contains($v, '@');
                $isUrl = preg_match('/^(https?:\/\/|www\.)/i', $v);
                if (!$hasAt && !$isUrl && $digitRatio < 0.3) {
                    $nameCount++;
                }
            }
        }
        $avgLen = $total > 0 ? $avgLen / $total : 0;
        $scores['name'] = ($nameCount / $total) * 35; // Base baixa — name é fallback

        // NAME bônus: nomes com sufixos empresariais (LTDA, ME, etc.)
        $companySuffixCount = 0;
        foreach ($values as $v) {
            $vLower = mb_strtolower($v);
            foreach (self::COMPANY_SUFFIXES as $sfx) {
                if (str_contains($vLower, ' ' . $sfx) || str_ends_with($vLower, $sfx)) {
                    $companySuffixCount++;
                    break;
                }
            }
        }
        if ($companySuffixCount > 0) {
            $scores['name'] += ($companySuffixCount / $total) * 40;
        }

        // NAME bônus: palavras com inicial maiúscula (nomes próprios/empresas)
        $capitalizedCount = 0;
        foreach ($values as $v) {
            if (preg_match('/^[A-ZÀ-Ü]/', $v) && mb_strlen($v) >= 3) {
                $capitalizedCount++;
            }
        }
        if ($capitalizedCount / $total > 0.5) {
            $scores['name'] += 10;
        }

        // SEGMENT: textos curtos, repetitivos (mesmos valores aparecem várias vezes)
        $uniqueValues = array_unique(array_map('mb_strtolower', $values));
        $uniqueRatio = count($uniqueValues) / max($total, 1);
        // Segmento tende a ter poucos valores únicos (alta repetição)
        if ($uniqueRatio < 0.3 && count($uniqueValues) >= 2) {
            $scores['segment'] += 30;
        } elseif ($uniqueRatio < 0.5 && count($uniqueValues) >= 2) {
            $scores['segment'] += 15;
        }
        // Segmento: textos curtos sem @ e sem formato especial
        $segmentLikeCount = 0;
        foreach ($values as $v) {
            $len = mb_strlen($v);
            if ($len >= 3 && $len <= 50 && !str_contains($v, '@') && !preg_match('/^[\d\+\(\)\-\s\.]+$/', $v)) {
                $segmentLikeCount++;
            }
        }
        $scores['segment'] += ($segmentLikeCount / $total) * 15;

        // ADDRESS: textos longos com números, palavras como "rua", "av", "n°"
        $addressCount = 0;
        $addressKeywords = ['rua', 'av ', 'av.', 'avenida', 'alameda', 'travessa', 'praça', 'praca', 'rodovia', 'estrada', 'br-', 'km ', 'nº', 'n°', 'numero', 'bloco', 'sala', 'andar', 'cep', 'bairro', 'lote', 'quadra', 'conj'];
        foreach ($values as $v) {
            $vLower = mb_strtolower($v);
            foreach ($addressKeywords as $kw) {
                if (str_contains($vLower, $kw)) {
                    $addressCount++;
                    break;
                }
            }
        }
        $scores['address'] = ($addressCount / $total) * 85;
        // Endereço: textos mais longos que média
        if ($avgLen > 25) $scores['address'] += 10;

        // NOTES: textos longos, diversificados (alta unicidade)
        if ($avgLen > 40 && $uniqueRatio > 0.8) {
            $scores['notes'] = 30;
        }

        // ═══ 2. BÔNUS POR CABEÇALHO ═══
        $normalizedHeader = $this->normalizeHeader($header);
        foreach (self::HEADER_HINTS as $field => $aliases) {
            if (in_array($normalizedHeader, $aliases, true)) {
                $scores[$field] += 35; // Bônus significativo mas não decisivo
                break;
            }
        }

        // Clamp tudo entre 0-100
        foreach ($scores as $field => $score) {
            $scores[$field] = min(100, max(0, (int)round($score)));
        }

        return $scores;
    }

    /**
     * Normaliza um header removendo acentos, espaços, etc.
     */
    private function normalizeHeader(string $raw): string
    {
        $s = mb_strtolower(trim($raw));
        $s = str_replace(['_', '-', '.'], ' ', $s);
        $s = preg_replace('/[àáâãä]/u', 'a', $s);
        $s = preg_replace('/[èéêë]/u', 'e', $s);
        $s = preg_replace('/[ìíîï]/u', 'i', $s);
        $s = preg_replace('/[òóôõö]/u', 'o', $s);
        $s = preg_replace('/[ùúûü]/u', 'u', $s);
        $s = preg_replace('/[ç]/u', 'c', $s);
        return preg_replace('/\s+/', ' ', $s);
    }

    /**
     * Resolve o mapeamento ótimo: cada campo só pode ser atribuído a uma coluna.
     * Usa algoritmo guloso: atribui primeiro os campos com maior score.
     */
    private function resolveMapping(array $scores): array
    {
        // Construir lista de (colIdx, fieldType, score) ordenada por score desc
        $candidates = [];
        foreach ($scores as $colIdx => $fieldScores) {
            foreach ($fieldScores as $field => $score) {
                if ($score >= 15) { // Threshold mínimo
                    $candidates[] = ['col' => $colIdx, 'field' => $field, 'score' => $score];
                }
            }
        }

        usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

        $mapping = [];         // colIdx => fieldName
        $usedFields = [];      // fieldName => true
        $usedCols = [];        // colIdx => true

        foreach ($candidates as $c) {
            $col = $c['col'];
            $field = $c['field'];

            // Pular se coluna já atribuída ou campo já usado
            if (isset($usedCols[$col]) || isset($usedFields[$field])) continue;

            $mapping[$col] = $field;
            $usedCols[$col] = true;
            $usedFields[$field] = true;
        }

        // Garantir que 'name' está mapeado — se não, pegar a coluna mais provável
        if (!isset($usedFields['name'])) {
            $bestNameCol = null;
            $bestNameScore = 0;
            foreach ($scores as $colIdx => $fieldScores) {
                if (!isset($usedCols[$colIdx]) && ($fieldScores['name'] ?? 0) > $bestNameScore) {
                    $bestNameScore = $fieldScores['name'];
                    $bestNameCol = $colIdx;
                }
            }
            // Se ainda não achou, usar a primeira coluna não usada com textos
            if ($bestNameCol === null) {
                foreach ($scores as $colIdx => $fieldScores) {
                    if (!isset($usedCols[$colIdx])) {
                        $bestNameCol = $colIdx;
                        break;
                    }
                }
            }
            if ($bestNameCol !== null) {
                // Remover o que estava atribuído a essa coluna
                if (isset($mapping[$bestNameCol])) {
                    unset($usedFields[$mapping[$bestNameCol]]);
                }
                $mapping[$bestNameCol] = 'name';
                $usedFields['name'] = true;
                $usedCols[$bestNameCol] = true;
            }
        }

        ksort($mapping);
        return $mapping;
    }

    /**
     * Aplica o mapeamento para transformar linhas brutas em leads estruturados.
     *
     * @param array $rows Linhas brutas do CSV
     * @param array $mapping colIdx => fieldName
     * @return array{leads: array, errors: int, skipped: array}
     */
    public static function applyMapping(array $rows, array $mapping): array
    {
        $leads = [];
        $errors = 0;
        $skipped = [];

        foreach ($rows as $rowNum => $row) {
            $data = [];
            foreach ($mapping as $colIdx => $field) {
                $val = trim($row[$colIdx] ?? '');
                if ($val !== '') {
                    $data[$field] = $val;
                }
            }

            $name = $data['name'] ?? '';
            if (empty($name)) {
                $errors++;
                if (count($skipped) < 5) {
                    $skipped[] = "Linha " . ($rowNum + 2) . ": nome vazio";
                }
                continue;
            }

            // Compor address a partir de city/state se address estiver vazio
            if (empty($data['address'])) {
                $parts = array_filter([
                    $data['city'] ?? '',
                    $data['state'] ?? '',
                ]);
                if (!empty($parts)) {
                    $data['address'] = implode(', ', $parts);
                }
            } elseif (!empty($data['city']) || !empty($data['state'])) {
                // Append city/state ao address se não estiverem já contidos
                $addrLower = mb_strtolower($data['address']);
                $extras = [];
                if (!empty($data['city']) && !str_contains($addrLower, mb_strtolower($data['city']))) {
                    $extras[] = $data['city'];
                }
                if (!empty($data['state']) && !str_contains($addrLower, mb_strtolower($data['state']))) {
                    $extras[] = $data['state'];
                }
                if (!empty($extras)) {
                    $data['address'] .= ', ' . implode(' - ', $extras);
                }
            }

            // Montar notes a partir de campos extras (position, notes originais)
            $notesParts = [];
            if (!empty($data['position'])) $notesParts[] = 'Cargo: ' . $data['position'];
            if (!empty($data['notes'])) $notesParts[] = $data['notes'];

            $leads[] = [
                'name'    => $name,
                'segment' => $data['segment'] ?? 'Não classificado',
                'website' => $data['website'] ?? '',
                'phone'   => $data['phone'] ?? '',
                'email'   => $data['email'] ?? '',
                'address' => $data['address'] ?? '',
                'notes'   => implode(' | ', $notesParts),
            ];
        }

        return ['leads' => $leads, 'errors' => $errors, 'skipped' => $skipped];
    }
}
