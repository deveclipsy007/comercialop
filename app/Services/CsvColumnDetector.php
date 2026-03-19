<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Motor heurístico de detecção de colunas para CSVs desorganizados.
 *
 * Analisa o conteúdo real de cada coluna (não apenas o cabeçalho)
 * para inferir qual campo de lead cada coluna representa.
 *
 * Campos básicos: name, email, phone, website, segment, address, city, state, position, notes
 * Campos enriquecidos (v2): google_maps_url, rating, review_count, reviews,
 *                            opening_hours, closing_hours, category,
 *                            social_instagram, social_facebook, social_linkedin
 */
class CsvColumnDetector
{
    // ═══ REGISTRY — Campos reconhecidos e seus metadados ═══════════════
    // Estrutura modular: para adicionar campos basta inserir aqui

    /**
     * Registro de todos os campos reconhecíveis pelo detector.
     * Cada campo contém:
     *   - label: Nome legível para a UI
     *   - icon: Material Symbols icon
     *   - group: Grupo visual na UI (basic, location, business, social, extra)
     *   - storable: Se é salvo diretamente na tabela leads (vs. composto)
     */
    public const FIELD_REGISTRY = [
        // ── Básicos ──
        'name'       => ['label' => 'Nome / Empresa',    'icon' => 'person',         'group' => 'basic',    'storable' => true],
        'email'      => ['label' => 'Email',             'icon' => 'mail',           'group' => 'basic',    'storable' => true],
        'phone'      => ['label' => 'Telefone',          'icon' => 'phone',          'group' => 'basic',    'storable' => true],
        'website'    => ['label' => 'Website',           'icon' => 'language',        'group' => 'basic',    'storable' => true],
        'segment'    => ['label' => 'Segmento',          'icon' => 'category',        'group' => 'basic',    'storable' => true],
        'position'   => ['label' => 'Cargo',             'icon' => 'badge',           'group' => 'basic',    'storable' => false],
        'notes'      => ['label' => 'Observações',       'icon' => 'notes',           'group' => 'basic',    'storable' => false],

        // ── Localização ──
        'address'    => ['label' => 'Endereço',          'icon' => 'location_on',     'group' => 'location', 'storable' => true],
        'city'       => ['label' => 'Cidade',            'icon' => 'location_city',   'group' => 'location', 'storable' => false],
        'state'      => ['label' => 'Estado/UF',         'icon' => 'map',             'group' => 'location', 'storable' => false],
        'google_maps_url' => ['label' => 'Link Google Maps', 'icon' => 'pin_drop',   'group' => 'location', 'storable' => true],

        // ── Negócio / Avaliações ──
        'category'       => ['label' => 'Categoria',       'icon' => 'storefront',  'group' => 'business', 'storable' => true],
        'rating'         => ['label' => 'Nota / Avaliação', 'icon' => 'star',        'group' => 'business', 'storable' => true],
        'review_count'   => ['label' => 'Qtd. Avaliações',  'icon' => 'reviews',     'group' => 'business', 'storable' => true],
        'reviews'        => ['label' => 'Reviews / Depoimentos', 'icon' => 'rate_review', 'group' => 'business', 'storable' => true],
        'opening_hours'  => ['label' => 'Horário de Abertura',   'icon' => 'schedule',    'group' => 'business', 'storable' => true],
        'closing_hours'  => ['label' => 'Horário de Fechamento', 'icon' => 'schedule',    'group' => 'business', 'storable' => true],

        // ── Redes Sociais ──
        'social_instagram' => ['label' => 'Instagram',  'icon' => 'photo_camera', 'group' => 'social', 'storable' => false],
        'social_facebook'  => ['label' => 'Facebook',   'icon' => 'thumb_up',     'group' => 'social', 'storable' => false],
        'social_linkedin'  => ['label' => 'LinkedIn',   'icon' => 'work',         'group' => 'social', 'storable' => false],
    ];

    // Grupos para organizar a UI
    public const FIELD_GROUPS = [
        'basic'    => ['label' => 'Dados Básicos',        'icon' => 'person',      'color' => '#a3e635'],
        'location' => ['label' => 'Localização',          'icon' => 'location_on', 'color' => '#38bdf8'],
        'business' => ['label' => 'Negócio & Avaliações', 'icon' => 'storefront',  'color' => '#f59e0b'],
        'social'   => ['label' => 'Redes Sociais',        'icon' => 'share',       'color' => '#a78bfa'],
    ];

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
        'name'     => ['name', 'nome', 'empresa', 'razao social', 'razao', 'company', 'company name', 'nome da empresa', 'nome empresa', 'fantasia', 'nome fantasia', 'razão social', 'nome completo', 'full name', 'responsavel', 'responsável', 'title', 'titulo', 'business name'],
        'email'    => ['email', 'e mail', 'e-mail', 'correio', 'mail', 'electronic mail'],
        'phone'    => ['phone', 'telefone', 'tel', 'celular', 'fone', 'whatsapp', 'contato', 'numero', 'mobile', 'cell', 'tel fixo', 'tel celular', 'whats', 'phone number'],
        'website'  => ['website', 'site', 'url', 'pagina', 'web', 'homepage', 'página', 'web site'],
        'segment'  => ['segment', 'segmento', 'nicho', 'setor', 'ramo', 'area', 'industry', 'categoria negocio', 'tipo negocio', 'atividade', 'área'],
        'address'  => ['address', 'endereco', 'endereço', 'logradouro', 'rua', 'localizacao', 'localização', 'bairro', 'full address', 'street'],
        'city'     => ['cidade', 'city', 'municipio', 'município', 'localidade'],
        'state'    => ['estado', 'state', 'uf', 'provincia', 'província', 'region', 'regiao', 'região'],
        'position' => ['cargo', 'position', 'funcao', 'função', 'titulo', 'título', 'job', 'job title', 'role', 'ocupacao', 'ocupação', 'profissao', 'profissão'],
        'notes'    => ['observacao', 'observação', 'obs', 'notes', 'nota', 'notas', 'comentario', 'comentário', 'descricao', 'descrição', 'description', 'info', 'informacao', 'informação'],

        // ── Novos campos V2 ──
        'google_maps_url' => ['google maps', 'maps', 'link google', 'google maps url', 'maps url', 'link maps', 'google maps link', 'url google', 'localizacao google', 'link google maps', 'maps link', 'place url', 'google url'],
        'rating'          => ['rating', 'nota', 'avaliacao', 'avaliação', 'score', 'stars', 'estrelas', 'nota media', 'nota média', 'average rating', 'classificacao', 'classificação', 'overall rating'],
        'review_count'    => ['reviews', 'avaliacoes', 'avaliações', 'qtd avaliacoes', 'total reviews', 'total avaliacoes', 'num reviews', 'numero de avaliacoes', 'review count', 'number of reviews', 'total ratings'],
        'reviews'         => ['review', 'depoimento', 'depoimentos', 'testemunho', 'testemunhos', 'comentarios clientes', 'customer reviews', 'feedback', 'opinioes', 'opiniões', 'testimonials'],
        'opening_hours'   => ['horario abertura', 'horário abertura', 'horario', 'horário', 'horario funcionamento', 'horário funcionamento', 'opening hours', 'open hours', 'abre', 'hours', 'working hours', 'business hours', 'open'],
        'closing_hours'   => ['horario fechamento', 'horário fechamento', 'fecha', 'closing hours', 'close', 'close hours', 'horario fechar', 'fecha as'],
        'category'        => ['category', 'tipo', 'tipo de negocio', 'tipo de negócio', 'business type', 'ramo de atividade', 'place type', 'type'],

        // ── Redes sociais ──
        'social_instagram' => ['instagram', 'insta', 'ig', 'perfil instagram', '@instagram', 'link instagram'],
        'social_facebook'  => ['facebook', 'fb', 'face', 'pagina facebook', 'link facebook', 'perfil facebook'],
        'social_linkedin'  => ['linkedin', 'linked in', 'li', 'perfil linkedin', 'link linkedin'],
    ];

    // Sufixos empresariais comuns no Brasil
    private const COMPANY_SUFFIXES = ['ltda', 'me', 'epp', 'eireli', 's/a', 's.a', 'sa', 'inc', 'llc', 'ltd', 'corp', 'co', 'group', 'grupo', 'cia', 'holding'];

    // Cargos/posições comuns
    private const POSITION_KEYWORDS = ['diretor', 'gerente', 'analista', 'coordenador', 'supervisor', 'assistente', 'estagiário', 'consultor', 'vendedor', 'representante', 'sócio', 'proprietário', 'dono', 'ceo', 'cfo', 'cto', 'coo', 'vp', 'presidente', 'manager', 'developer', 'engineer', 'designer', 'specialist', 'head', 'lead', 'founder', 'owner', 'partner'];

    private array $rows = [];
    private array $headers = [];
    private int $totalRows = 0;

    /**
     * Retorna os labels de todos os campos reconhecíveis.
     */
    public static function getFieldLabels(): array
    {
        $labels = [];
        foreach (self::FIELD_REGISTRY as $key => $meta) {
            $labels[$key] = $meta['label'];
        }
        return $labels;
    }

    /**
     * Retorna as chaves de todos os campos reconhecíveis.
     */
    public static function getAvailableFields(): array
    {
        return array_keys(self::FIELD_REGISTRY);
    }

    /**
     * Retorna os ícones de todos os campos.
     */
    public static function getFieldIcons(): array
    {
        $icons = [];
        foreach (self::FIELD_REGISTRY as $key => $meta) {
            $icons[$key] = $meta['icon'];
        }
        return $icons;
    }

    /**
     * Retorna o registro agrupado por grupo para renderizar a UI.
     */
    public static function getFieldsByGroup(): array
    {
        $grouped = [];
        foreach (self::FIELD_GROUPS as $groupKey => $groupMeta) {
            $grouped[$groupKey] = [
                'label' => $groupMeta['label'],
                'icon'  => $groupMeta['icon'],
                'color' => $groupMeta['color'],
                'fields' => [],
            ];
        }
        foreach (self::FIELD_REGISTRY as $fieldKey => $fieldMeta) {
            $group = $fieldMeta['group'];
            if (isset($grouped[$group])) {
                $grouped[$group]['fields'][$fieldKey] = $fieldMeta;
            }
        }
        return $grouped;
    }

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

        // Contar campos por grupo para stats
        $mappedByGroup = [];
        foreach ($mapping as $colIdx => $field) {
            $group = self::FIELD_REGISTRY[$field]['group'] ?? 'extra';
            if (!isset($mappedByGroup[$group])) $mappedByGroup[$group] = 0;
            $mappedByGroup[$group]++;
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
            'mapping'    => $mapping,          // colIdx => fieldName
            'confidence' => $confidence,       // colIdx => 0-100
            'preview'    => $preview,          // array de leads mapeados
            'headers'    => $this->headers,    // cabeçalhos originais
            'sample_rows' => $sampleRows,      // primeiras linhas brutas
            'stats'      => [
                'total_rows'       => $this->totalRows,
                'total_columns'    => $colCount,
                'mapped_columns'   => count($mapping),
                'unmapped_columns' => $colCount - count($mapping),
                'headers_are_data' => $headersLookLikeData,
                'mapped_by_group'  => $mappedByGroup,
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
        // Inicializar com todos os campos do registry
        $scores = [];
        foreach (array_keys(self::FIELD_REGISTRY) as $field) {
            $scores[$field] = 0;
        }

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
                if (preg_match('/[\+\(\)\-\s]/', $v) && preg_match('/\d{2,}/', $v)) {
                    $phoneCount++;
                } elseif ($digitLen >= 10 && $digitLen <= 13 && preg_match('/^\+?\d[\d\s\-\(\)\.]*$/', $v)) {
                    $phoneCount++;
                } elseif (preg_match('/^\(?\d{2}\)?\s?\d{4,5}[\-\s]?\d{4}$/', $v)) {
                    $phoneCount++;
                }
            }
        }
        $scores['phone'] = ($phoneCount / $total) * 90;

        // WEBSITE: URLs e domínios (NÃO google maps, NÃO redes sociais)
        $urlCount = 0;
        foreach ($values as $v) {
            $vLower = strtolower($v);
            // Excluir URLs de Google Maps e redes sociais (serão detectadas por outros campos)
            if ($this->isGoogleMapsUrl($vLower)) continue;
            if ($this->isSocialUrl($vLower)) continue;
            if (preg_match('/^https?:\/\//i', $v)) { $urlCount++; continue; }
            if (preg_match('/^www\./i', $v)) { $urlCount++; continue; }
            if (preg_match('/^[a-z0-9][\w\-]*\.(com|net|org|br|io|co|app|dev|me|tech|store|shop|digital|online|site|blog|info|biz)/i', $v)) { $urlCount++; continue; }
        }
        $scores['website'] = ($urlCount / $total) * 90;

        // GOOGLE MAPS URL: links do Google Maps
        $mapsCount = 0;
        foreach ($values as $v) {
            if ($this->isGoogleMapsUrl(strtolower($v))) {
                $mapsCount++;
            }
        }
        $scores['google_maps_url'] = ($mapsCount / $total) * 95;

        // RATING: nota numérica 1-5 (com decimais)
        $ratingCount = 0;
        foreach ($values as $v) {
            $v = str_replace(',', '.', trim($v));
            if (preg_match('/^\d\.?\d?$/', $v)) {
                $num = (float) $v;
                if ($num >= 1.0 && $num <= 5.0) {
                    $ratingCount++;
                }
            }
        }
        $scores['rating'] = ($ratingCount / $total) * 80;

        // REVIEW_COUNT: números inteiros (geralmente >= 1)
        $reviewCountCount = 0;
        foreach ($values as $v) {
            $v = trim($v);
            $cleaned = str_replace(['.', ',', ' '], '', $v);
            if (preg_match('/^\d+$/', $cleaned)) {
                $num = (int) $cleaned;
                if ($num >= 0 && $num <= 100000) {
                    $reviewCountCount++;
                }
            }
        }
        // Só pontua se não foi rating (rating tem decimais, review_count não tem ou é grande)
        $avgValue = 0;
        $intCount = 0;
        foreach ($values as $v) {
            $cleaned = str_replace(['.', ',', ' '], '', trim($v));
            if (preg_match('/^\d+$/', $cleaned)) {
                $avgValue += (int)$cleaned;
                $intCount++;
            }
        }
        $avgValue = $intCount > 0 ? $avgValue / $intCount : 0;
        // Se média > 5, provavelmente é contagem, não rating
        if ($avgValue > 5 && $reviewCountCount / $total > 0.5) {
            $scores['review_count'] = ($reviewCountCount / $total) * 75;
        } elseif ($reviewCountCount / $total > 0.5) {
            $scores['review_count'] = ($reviewCountCount / $total) * 30;
        }

        // REVIEWS (testemunhos/depoimentos): textos longos com aspas ou narrativos
        $reviewTextCount = 0;
        foreach ($values as $v) {
            $len = mb_strlen($v);
            if ($len > 30) {
                // Textos longos com aspas, pontuação, ou frases narrativas
                $hasQuotes = str_contains($v, '"') || str_contains($v, "'") || str_contains($v, '"') || str_contains($v, '"');
                $hasSentence = preg_match('/[A-ZÀ-Ú][a-zà-ú]+\s+[a-zà-ú]+/u', $v);
                if ($hasQuotes || ($hasSentence && $len > 50)) {
                    $reviewTextCount++;
                }
            }
        }
        if ($reviewTextCount > 0) {
            $scores['reviews'] = ($reviewTextCount / $total) * 70;
        }

        // OPENING_HOURS: padrões de horário
        $openHoursCount = 0;
        foreach ($values as $v) {
            if ($this->looksLikeHours($v)) {
                $openHoursCount++;
            }
        }
        $scores['opening_hours'] = ($openHoursCount / $total) * 80;

        // CLOSING_HOURS: similar a opening, mas com menor prioridade
        // (será diferenciado pelo header hint)
        $scores['closing_hours'] = ($openHoursCount / $total) * 40;

        // CATEGORY: textos curtos, repetitivos (como segmento mas diferente)
        // Diferenciado de segment pelo header
        $uniqueValues = array_unique(array_map('mb_strtolower', $values));
        $uniqueRatio = count($uniqueValues) / max($total, 1);

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
        $twoCharCount = count(array_filter($values, fn($v) => mb_strlen(trim($v)) === 2));
        if ($twoCharCount / $total > 0.7 && $stateCount / $total > 0.5) {
            $scores['state'] += 10;
        }

        // CITY: heurística — textos curtos, sem @, sem números dominantes
        $cityLikeCount = 0;
        foreach ($values as $v) {
            $len = mb_strlen($v);
            if ($len >= 3 && $len <= 40 && !str_contains($v, '@') && !preg_match('/^[\d\+\(\)\-\s\.]+$/', $v)) {
                $letterRatio = mb_strlen(preg_replace('/[^a-zA-ZÀ-ÿ\s]/u', '', $v)) / max($len, 1);
                if ($letterRatio > 0.8 && $len > 2) {
                    $cityLikeCount++;
                }
            }
        }
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
        $scores['name'] = ($nameCount / $total) * 35;

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

        // SEGMENT: textos curtos, repetitivos
        if ($uniqueRatio < 0.3 && count($uniqueValues) >= 2) {
            $scores['segment'] += 30;
        } elseif ($uniqueRatio < 0.5 && count($uniqueValues) >= 2) {
            $scores['segment'] += 15;
        }
        $segmentLikeCount = 0;
        foreach ($values as $v) {
            $len = mb_strlen($v);
            if ($len >= 3 && $len <= 50 && !str_contains($v, '@') && !preg_match('/^[\d\+\(\)\-\s\.]+$/', $v)) {
                $segmentLikeCount++;
            }
        }
        $scores['segment'] += ($segmentLikeCount / $total) * 15;

        // CATEGORY: similar a segment mas com prioridade menor (diferenciada por header)
        if ($uniqueRatio < 0.4 && count($uniqueValues) >= 2) {
            $scores['category'] = 15;
        }

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
        if ($avgLen > 25) $scores['address'] += 10;

        // NOTES: textos longos, diversificados
        if ($avgLen > 40 && $uniqueRatio > 0.8) {
            $scores['notes'] = 30;
        }

        // SOCIAL INSTAGRAM: @handles ou instagram.com URLs
        $igCount = 0;
        foreach ($values as $v) {
            $vLower = mb_strtolower(trim($v));
            if (str_contains($vLower, 'instagram.com') || 
                (preg_match('/^@[a-z0-9_.]+$/i', $vLower) && !str_contains($vLower, '@') === false)) {
                $igCount++;
            }
        }
        $scores['social_instagram'] = ($igCount / $total) * 85;

        // SOCIAL FACEBOOK: facebook.com URLs
        $fbCount = 0;
        foreach ($values as $v) {
            if (str_contains(strtolower($v), 'facebook.com') || str_contains(strtolower($v), 'fb.com')) {
                $fbCount++;
            }
        }
        $scores['social_facebook'] = ($fbCount / $total) * 85;

        // SOCIAL LINKEDIN: linkedin.com URLs
        $liCount = 0;
        foreach ($values as $v) {
            if (str_contains(strtolower($v), 'linkedin.com')) {
                $liCount++;
            }
        }
        $scores['social_linkedin'] = ($liCount / $total) * 85;

        // ═══ 2. BÔNUS POR CABEÇALHO ═══
        $normalizedHeader = $this->normalizeHeader($header);
        foreach (self::HEADER_HINTS as $field => $aliases) {
            foreach ($aliases as $alias) {
                if ($normalizedHeader === $alias || str_contains($normalizedHeader, $alias)) {
                    $scores[$field] += 35;
                    break 2;
                }
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
     * Verifica se um valor parece uma URL do Google Maps.
     */
    private function isGoogleMapsUrl(string $v): bool
    {
        return str_contains($v, 'google.com/maps') ||
               str_contains($v, 'maps.google') ||
               str_contains($v, 'goo.gl/maps') ||
               str_contains($v, 'maps.app.goo.gl') ||
               preg_match('/google\.\w+\/maps/i', $v) === 1;
    }

    /**
     * Verifica se uma URL é de rede social.
     */
    private function isSocialUrl(string $v): bool
    {
        return str_contains($v, 'instagram.com') ||
               str_contains($v, 'facebook.com') ||
               str_contains($v, 'fb.com') ||
               str_contains($v, 'linkedin.com');
    }

    /**
     * Verifica se um valor parece um horário de funcionamento.
     */
    private function looksLikeHours(string $v): bool
    {
        $v = mb_strtolower(trim($v));
        // Padrão HH:MM ou HH:MM-HH:MM
        if (preg_match('/\d{1,2}:\d{2}/', $v)) return true;
        // Padrão "seg a sex", "segunda a sexta", "mon-fri"
        if (preg_match('/(seg|ter|qua|qui|sex|sab|dom|segunda|terça|quarta|quinta|sexta|sábado|domingo|mon|tue|wed|thu|fri|sat|sun)/i', $v)) return true;
        // Padrão "24h", "24 horas"
        if (preg_match('/24\s*h/i', $v)) return true;
        // Padrão "8h às 18h"
        if (preg_match('/\d+h/i', $v)) return true;
        return false;
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
            if ($bestNameCol === null) {
                foreach ($scores as $colIdx => $fieldScores) {
                    if (!isset($usedCols[$colIdx])) {
                        $bestNameCol = $colIdx;
                        break;
                    }
                }
            }
            if ($bestNameCol !== null) {
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
     * Versão V2: suporta campos enriquecidos além dos básicos.
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

            // Compor social_presence a partir de campos sociais
            $socialPresence = [];
            if (!empty($data['social_instagram'])) $socialPresence['instagram'] = $data['social_instagram'];
            if (!empty($data['social_facebook']))  $socialPresence['facebook']  = $data['social_facebook'];
            if (!empty($data['social_linkedin']))  $socialPresence['linkedin']  = $data['social_linkedin'];

            // Processar reviews (pode vir como texto separado por pipes ou quebras)
            $reviewsData = null;
            if (!empty($data['reviews'])) {
                $reviewText = $data['reviews'];
                // Se tem separadores, dividir
                if (str_contains($reviewText, '|')) {
                    $reviewsData = array_map('trim', explode('|', $reviewText));
                } else {
                    $reviewsData = [$reviewText];
                }
            }

            // Processar rating
            $rating = null;
            if (!empty($data['rating'])) {
                $ratingStr = str_replace(',', '.', $data['rating']);
                $rating = (float) $ratingStr;
                if ($rating < 0 || $rating > 5) $rating = null;
            }

            // Processar review_count
            $reviewCount = null;
            if (!empty($data['review_count'])) {
                $cleaned = str_replace(['.', ',', ' '], '', $data['review_count']);
                $reviewCount = (int) $cleaned;
            }

            $lead = [
                // Campos básicos
                'name'    => $name,
                'segment' => $data['segment'] ?? $data['category'] ?? 'Não classificado',
                'website' => $data['website'] ?? '',
                'phone'   => $data['phone'] ?? '',
                'email'   => $data['email'] ?? '',
                'address' => $data['address'] ?? '',
                'notes'   => implode(' | ', $notesParts),

                // Campos enriquecidos V2
                'google_maps_url' => $data['google_maps_url'] ?? '',
                'rating'          => $rating,
                'review_count'    => $reviewCount,
                'reviews'         => $reviewsData,
                'opening_hours'   => $data['opening_hours'] ?? '',
                'closing_hours'   => $data['closing_hours'] ?? '',
                'category'        => $data['category'] ?? '',

                // Social (merge no social_presence JSON)
                'social_presence' => !empty($socialPresence) ? $socialPresence : null,
            ];

            $leads[] = $lead;
        }

        return ['leads' => $leads, 'errors' => $errors, 'skipped' => $skipped];
    }
}
