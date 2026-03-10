# Prompts, Lógica e Algoritmos — Operon Intelligence CRM

> **Versão:** 1.0 | **Data:** 2026-03-10
>
> Este documento extrai do código-fonte React/TypeScript todos os prompts de IA, algoritmos de negócio e lógica crítica necessários para recriar o sistema em qualquer outra linguagem/stack sem precisar do código original.

---

## Índice

1. [System Prompt Base (Todas as Análises)](#1-system-prompt-base)
2. [Contexto Dinâmico de Agência (SmartContext)](#2-contexto-dinâmico-de-agência)
3. [Análise Operon — 4 Dimensões](#3-análise-operon--4-dimensões)
4. [Análise Profunda (DeepAnalysis)](#4-análise-profunda-deepanalysis)
5. [Qualificação de Lead (analyzeLeadWithAI)](#5-qualificação-de-lead)
6. [SPIN Framework](#6-spin-framework)
7. [Deal Insights (Proposta Comercial)](#7-deal-insights)
8. [Redes Sociais e Prospecção](#8-redes-sociais-e-prospecção)
9. [Scripts de Abordagem (Variações)](#9-scripts-de-abordagem-variações)
10. [Análise de Produtos, Clientes e Concorrentes](#10-análise-de-produtos-clientes-e-concorrentes)
11. [Áudio Strategy](#11-áudio-strategy)
12. [Copilot (Lyxos)](#12-copilot-lyxos)
13. [Algoritmo de Score](#13-algoritmo-de-score)
14. [Token Economy — Pesos e Lógica](#14-token-economy--pesos-e-lógica)
15. [Helper: cleanAndParseJSON](#15-helper-cleanandparsejson)
16. [APIs Externas](#16-apis-externas)

---

## 1. System Prompt Base

Este é o **system prompt global** injetado em todas as análises Operon. Deve ser o primeiro bloco do `system` em qualquer chamada de IA de análise de lead.

```
Você é um Consultor de Vendas Sênior especializado em identificar oportunidades de negócio.
Sua missão é analisar dados de empresas e gerar diagnósticos comerciais agressivos e diretos.
Foco: Identificar onde o lead está perdendo dinheiro e criar ganchos de abordagem prontos para uso.
Use SEMPRE o contexto real da agência (oferta, preços, cases) para personalizar os scripts e diagnósticos.
Seja conciso, objetivo e comercialmente impactante.
```

**Como usar no PHP:**
```php
$baseSystemPrompt = "Você é um Consultor de Vendas Sênior especializado...";
$agencyContext = $smartContext->buildOperonContext($lead);
$systemPrompt = $baseSystemPrompt . "\n\n" . $agencyContext;
```

---

## 2. Contexto Dinâmico de Agência

O **SmartContextService** monta o contexto que é injetado em TODOS os prompts. É o "núcleo estratégico" que faz a IA se comportar como a agência específica do usuário.

### 2.1 buildOperonContext (para análises Operon)

```
=== CONTEXTO DA AGÊNCIA (use para personalizar scripts e diagnósticos) ===
Oferta: {offer.title}
Preço base: {offer.basePrice}
Serviços: {offer.services.join(', ')}
Proposta única: {offer.uniqueProposal}

Diferenciais competitivos:
• {diferencial 1}
• {diferencial 2}

=== SERVIÇOS E PREÇOS ===
• {servico.name} ({servico.category}): {servico.priceRange}
  → Setup: {servico.setup}
  → Ideal para: {servico.idealProfile}
  → Entregáveis: {servico.deliverables[0..2]}

=== CASES DE SUCESSO (use como prova social nos scripts) ===
[Case de Sucesso] {titulo}:
{conteudo.slice(0, 600)}

=== METODOLOGIA (base para diagnósticos) ===
[Metodologia] {titulo}:
{conteudo.slice(0, 600)}

=== DADOS DO LEAD ===
Nome: {lead.name}
Segmento: {lead.segment}
Porte: {lead.size}
Maturidade Digital: {lead.digitalMaturityScore}/100
Site: {lead.website}
Localização: {lead.address}
Dores identificadas: {lead.analysis.painPoints.join('; ')}
Oportunidades: {lead.analysis.opportunities.join('; ')}
Contexto do vendedor: {lead.humanContext.context}
```

### 2.2 buildLeadContext (para análises gerais)

```
=== IDENTIDADE DA AGÊNCIA ===
{offerContext}
Diferenciais:
• {diferencial}

=== PERFIL DE CLIENTE IDEAL (ICP) ===
• {critério ICP}

=== TABELA DE PREÇOS ===
{pricingContext — máx 5 entradas}

=== CASOS E METODOLOGIA ===
{ragContext — categorias: Case de Sucesso, Metodologia, Portfolio — máx 4}

=== PERFIL DO LEAD ===
Nome: {lead.name}
Segmento: {lead.segment}
Porte: {lead.size}
Maturidade Digital: {lead.digitalMaturityScore}/100
Score de Fit: {lead.fitScore}/100
Site: {lead.website}
Localização: {lead.address}
Tags: {lead.tags.join(', ')}
Pontos fortes: {lead.analysis.strengths.join(', ')}
Pontos fracos: {lead.analysis.weaknesses.join(', ')}
Contexto manual do vendedor: {lead.humanContext.context}
```

---

## 3. Análise Operon — 4 Dimensões

A análise Operon é a **peça central do produto**. São 4 chamadas de IA sequenciais, cada uma retornando JSON com estrutura definida.

### 3.1 Diagnóstico de Perda

**Lógica de montagem do prompt:**
- Se o lead NÃO tem site → foco em "Invisibilidade Digital"
- Se tem site + dados PageSpeed → use dados técnicos reais
- Se tem site mas sem dados → foque em problemas genéricos de sites desatualizados

**User Prompt:**
```
Analise o lead "{lead.name}" ({lead.segment ou 'Segmento não informado'}).

[SE SEM SITE]:
PROBLEMA CRÍTICO: Esta empresa NÃO possui site.
Gere um diagnóstico de perda focado na "Invisibilidade Digital".
Explique quanto dinheiro estão perdendo por não terem presença online.

[SE COM PAGESPEED]:
Dados técnicos do site:
- Performance: {performanceScore}/100
- Tempo de carregamento: {loadTime}s
- Facebook Pixel: {hasFacebookPixel ? 'Instalado' : 'AUSENTE'}
- Google Analytics: {hasGoogleAnalytics ? 'Instalado' : 'AUSENTE'}

Identifique os problemas técnicos que estão custando caro (ex: site lento, falta de tracking).

[SE SEM PAGESPEED]:
Site informado mas não foi possível analisar tecnicamente.
Foque em problemas comuns de sites desatualizados.

Formato de resposta: Retorne APENAS um JSON válido (sem markdown, sem ```json) com esta estrutura:
{
  "titulo": "Título impactante",
  "status": "critico" | "atencao" | "moderado",
  "problemas": ["problema 1", "problema 2", "problema 3"],
  "impactoFinanceiro": {
    "perda_mensal_min": 5000,
    "perda_mensal_max": 15000,
    "descricao": "texto explicativo"
  },
  "urgencia": "alta" | "media" | "baixa",
  "acoes_imediatas": ["ação 1", "ação 2"]
}

Seja agressivo comercialmente. Use dados concretos. RETORNE APENAS O JSON.
```

---

### 3.2 Potencial Comercial

**Lógica de montagem:**
- `capitalSocial < 10.000` → perfil de entrada, serviços básicos
- `capitalSocial > 100.000` → alto ticket, serviços premium
- Entre → pacotes intermediários
- Sem CNPJ → analisa pelo segmento

**User Prompt:**
```
Analise o potencial comercial de "{lead.name}".

[SE COM CNPJ]:
Dados da empresa:
- Capital Social: R$ {cnpjData.capitalSocial}
- CNAE: {cnpjData.cnaePrincipal}
- Data de Abertura: {cnpjData.dataAbertura}
- Natureza Jurídica: {cnpjData.naturezaJuridica}

[PERFIL CALCULADO]:
- Capital < R$10k → PERFIL: Empresa de entrada. Sugira serviços básicos (SEO Local, Presença Digital).
- Capital > R$100k → PERFIL: Empresa de alto ticket. Sugira serviços premium (Gestão de Tráfego, CRM, Automação).
- Meio-termo → PERFIL: Empresa de médio porte. Sugira pacotes intermediários.

[SE COM GOOGLE MAPS]:
- Avaliação Google: {rating} ⭐ ({reviewCount} reviews)

[SE SEM CNPJ]:
CNPJ não disponível. Analise com base no segmento: {lead.segment}.

Formato de resposta: Retorne APENAS um JSON válido com esta estrutura:
{
  "classificacao": "Entrada" | "Médio" | "Alto Ticket",
  "score_potencial": 75,
  "poder_compra": "Descrição do poder de compra",
  "servicos_recomendados": [
    {"nome": "Serviço 1", "prioridade": "alta" | "media" | "baixa"}
  ],
  "valor_proposta": {
    "minimo": 3000,
    "maximo": 8000,
    "recorrente": true
  },
  "justificativa": "Por que esse valor"
}

Seja estratégico e baseie-se em dados reais. RETORNE APENAS O JSON.
```

---

### 3.3 Autoridade Local

**Lógica:**
- `rating < 3.5` → reputação local BAIXA
- `rating >= 4.5 && reviewCount > 50` → excelente reputação
- Caso contrário → boa base mas pode melhorar

**User Prompt:**
```
Analise a autoridade local de "{lead.name}".

[SE COM GOOGLE MAPS]:
Dados do Google Maps:
- Categoria: {category}
- Avaliação: {rating} ⭐
- Total de avaliações: {reviewCount}
- Localização: {lead.address}

[ANÁLISE CONDICIONAL]:
- rating < 3.5 → PROBLEMA: Reputação local BAIXA ou inexistente.
- rating >= 4.5 && reviewCount > 50 → FORÇA: Excelente reputação local consolidada.
- Outros → OPORTUNIDADE: Boa base mas pode melhorar.

[SEM GOOGLE MAPS]:
Dados do Google Maps não disponíveis. Foque na importância da presença local.

Formato de resposta: Retorne APENAS um JSON válido com esta estrutura:
{
  "status": "forte" | "moderado" | "fraco" | "inexistente",
  "score_autoridade": 65,
  "comparacao_setor": "Acima/Abaixo/Na média do setor",
  "metricas": {
    "avaliacao_atual": 4.2,
    "total_avaliacoes": 45,
    "media_setor": 4.0
  },
  "impacto_faturamento": "Descrição do impacto",
  "acoes_melhoria": [
    {"acao": "Ação 1", "impacto": "alto" | "medio" | "baixo"}
  ]
}

Use dados do Google Maps como prova social. RETORNE APENAS O JSON.
```

---

### 3.4 Script de Abordagem

**Nota:** Recebe os resultados das 3 análises anteriores como contexto.

**User Prompt:**
```
Crie um script de abordagem para WhatsApp para "{lead.name}".

Use os insights das análises anteriores:

DIAGNÓSTICO DE PERDA:
{diagnosticoPerda}

POTENCIAL COMERCIAL:
{potencialComercial}

AUTORIDADE LOCAL:
{autoridadeLocal}

Formato do script:
1. Abertura personalizada (mencione algo específico da empresa)
2. Gancho da dor (use o diagnóstico de perda)
3. Proposta de valor (baseada no potencial comercial)
4. Call-to-action direto

IMPORTANTE:
- Máximo 150 palavras
- Tom consultivo mas direto
- Pronto para copiar e colar no WhatsApp
- Sem emojis excessivos
- Foque na DOR e na SOLUÇÃO
```

---

### 3.5 Regenerar com Prompt Customizado

Para quando o usuário quer refinar uma análise específica:

```
REFINAMENTO DE ANÁLISE PARA O PILAR: {pillar}

Lead: {lead.name}
Solicitação do Usuário: {userPrompt}

Dados Adicionais:
- CNPJ: {cnpjData ? 'Disponível' : 'N/D'}
- PageSpeed: {pageSpeedData ? 'Disponível' : 'N/D'}

Mantenha o formato JSON original se for Diagnóstico, Potencial ou Autoridade.
Se for Script de Abordagem, retorne o texto formatado.
```

---

## 4. Análise Profunda (DeepAnalysis)

Chamada única que retorna um objeto `DeepAnalysis` com múltiplas seções. Usa o mesmo system prompt + operonContext.

**Passos de progresso para UX** (mostrar ao usuário enquanto carrega):
```
1. "Iniciando Deep Dive..."
2. "Auditando stack tecnológica..."
3. "Calculando TAM/SAM/SOM..."
4. "Gerando Battle Cards..."
5. "Desenhando arquitetura de solução..."
6. "Finalizando..."
```
Intervalo entre mensagens: 1.500ms

**User Prompt:**
```
REALIZAR ANÁLISE PROFUNDA B2B (DEEP DIVE) PARA:
Empresa: {lead.name}
Segmento: {lead.segment}
Site: {lead.website || 'N/A'}

Gere um relatório JSON completo seguindo a interface DeepAnalysis.

INSTRUÇÕES ESPECÍFICAS:
1. systemAudit: Identifique gaps reais onde automação (CRM, Chatbot, AI SDR) economizaria dinheiro. Calcule valores estimados.
2. brand: Defina um arquétipo de marca (ex: O Governante, O Herói) e tom de voz.
3. warRoom: Crie cards de batalha contra concorrentes genéricos do setor.
4. assets: Sugira 4 diagramas visuais (ex: 'Fluxo de Cadência', 'Matriz de Objeções') com prompts para gerá-los depois.
5. marketIntelligence: Gere análise de Porter e SWOT.

RETORNE APENAS JSON VÁLIDO.
```

**Estrutura esperada do JSON (DeepAnalysis):**
```json
{
  "systemAudit": {
    "gaps": [...],
    "totalAutomationPotentialMoney": "R$ 15.000/mês"
  },
  "brand": {
    "archetype": "O Herói",
    "voiceTone": "Direto e inspirador"
  },
  "warRoom": {
    "battleCards": [...]
  },
  "assets": [
    {"title": "Fluxo de Cadência", "prompt": "..."}
  ],
  "marketIntelligence": {
    "swot": {"strengths": [], "weaknesses": [], "opportunities": [], "threats": []},
    "porter": {...}
  }
}
```

---

## 5. Qualificação de Lead

Chamada de qualificação inicial com Google Search grounding.

**User Prompt:**
```
Analise esta empresa para qualificação de lead B2B.
Nome: {lead.name}
Segmento: {lead.segment}
Site Fornecido (Pode estar desatualizado ou vazio): {lead.website || "Não informado"}

IMPORTANTE: Use o Google Search para encontrar dados OFICIAIS:
1. O endereço real completo.
2. Telefone ou WhatsApp de contato.
3. A URL OFICIAL do Website (Se o fornecido estiver vazio, encontre-o).
4. Redes sociais (Instagram, LinkedIn).
5. Tempo estimado de mercado.

CRITÉRIOS RIGOROSOS DE SCORE (Seja realista):
- Score > 80: Apenas se tiver Site Profissional, Instagram Ativo, LinkedIn e bons reviews.
- Score < 40: Se não tiver site ou presença digital quase nula.
- NÃO infle a nota. Queremos vender serviços digitais, então precisamos identificar as FALHAS.

Retorne APENAS um JSON válido (sem markdown) com a seguinte estrutura:
{
  "priorityScore": number (0-100, seja rigoroso),
  "scoreExplanation": "Uma frase direta explicando o porquê da nota",
  "digitalMaturity": "Baixa" | "Média" | "Alta",
  "diagnosis": string[] (Liste 3 problemas críticos encontrados),
  "opportunities": string[],
  "urgencyLevel": "Baixa" | "Média" | "Alta",
  "fitScore": number (0-100),
  "summary": string,
  "extractedContact": {
    "phone": string,
    "whatsappAvailable": boolean,
    "address": string,
    "website": string,
    "websiteStatus": "Active" | "Inactive" | "NotFound"
  },
  "socialPresence": {
    "linkedin": string,
    "instagram": string,
    "facebook": string
  },
  "businessDetails": {
    "timeInMarket": string,
    "operatingHours": string
  }
}
```

**Config:** `tools: [{ googleSearch: {} }]` — usa Google Search para grounding

---

## 6. SPIN Framework

**User Prompt:**
```
ATUE COMO UM CONSULTOR DE VENDAS HIGH-TICKET ESPECIALISTA NO FRAMEWORK SPIN.

LEAD: {lead.name}
SEGMENTO: {lead.segment}
MATURIDADE: {lead.analysis.digitalMaturity || 'Média'}

TAREFA:
Crie 3 perguntas para cada fase do SPIN (Situação, Problema, Implicação, Necessidade de Solução)
para ajudar o vendedor a qualificar e fechar este lead.
As perguntas devem ser específicas para o nicho de {lead.segment}.

Retorne JSON estrito:
{
  "s": ["Pergunta 1", "Pergunta 2", "Pergunta 3"],
  "p": ["Pergunta 1", "Pergunta 2", "Pergunta 3"],
  "i": ["Pergunta 1", "Pergunta 2", "Pergunta 3"],
  "n": ["Pergunta 1", "Pergunta 2", "Pergunta 3"]
}
```

**Config:** `responseMimeType: "application/json"`

---

## 7. Deal Insights

Gera proposta de valor personalizada baseada nos serviços da agência.

**User Prompt:**
```
Você é um estrategista comercial sênior.
Lead: {lead.name}
Segmento: {lead.segment}
Maturidade: {lead.analysis.digitalMaturity || 'Desconhecida'}
Score: {lead.analysis.priorityScore || 50}

NOSSA OFERTA ATIVA (SERVIÇOS DISPONÍVEIS):
{offer.services.join(', ')}
PREÇO BASE: {offer.basePrice}
PROPOSTA DE VALOR: {offer.uniqueProposal}

TAREFA:
1. Estime um valor de contrato (ticket) realista para vender NOSSOS serviços para este lead (Em Reais BRL), baseado na nossa tabela.
2. Selecione os 3 serviços da nossa lista que eles mais precisam.
3. Crie uma "Proposta Única" (Hook) de 1 frase adaptando nossa proposta de valor para a dor deles.

Retorne JSON:
{
  "proposalValue": number (Ex: 3500),
  "services": ["Service 1", "Service 2"],
  "uniqueProposal": "Frase persuasiva"
}
```

---

## 8. Redes Sociais e Prospecção

### 8.1 Busca de Perfis Sociais (OSINT)

```
ATUE COMO UM INVESTIGADOR DIGITAL (OSINT).

TAREFA: Encontrar os perfis de rede social REAIS para a empresa:
Nome: "{lead.name}"
Cidade/Região: "{city}"
Segmento: "{lead.segment}"

INSTRUÇÕES CRÍTICAS (PARA EVITAR FALSOS NEGATIVOS):
1. Busque de forma ampla: "{lead.name} {city} instagram", "{lead.name} {lead.segment} instagram", etc.
2. ANALISE OS TÍTULOS DOS RESULTADOS: Muitas vezes o handle é diferente do nome.
3. FLEXIBILIDADE DE NOME: Se o nome for "Clínica Sorriso" e achar "Dra. Ana - Sorriso (@draana.sorriso)" na mesma cidade, ACEITE.
4. VALIDAÇÃO DE CONTEXTO: Se a bio mencionar a cidade ou profissão, é um match forte.
5. NÃO descarte se o nome não for 100% idêntico.

Retorne JSON estrito:
{
  "instagram": "URL completa",
  "linkedin": "URL completa",
  "facebook": "URL completa"
}
```

### 8.2 Estratégia de Redes Sociais

```
ATUE COMO UM GROWTH HACKER E ESTRATEGISTA DE REDES SOCIAIS "OUT OF THE BOX".

OBJETIVO: Criar 3 estratégias virais/de conversão altamente específicas para este negócio atrair clientes *agora*.

CONTEXTO DO LEAD:
Empresa: {lead.name}
Segmento: {lead.segment}
Localização: {lead.address}
Rede: {network}

INSTRUÇÕES:
1. IGNORE conselhos genéricos como "poste com constância" ou "use boas fotos".
2. Pense em "Growth Hacks": Iscas digitais, parcerias locais inusitadas, reels polêmicos ou educativos.
3. Se nicho local (ex: Dentista) → foque em geo-marketing.
4. Se B2B → foque em autoridade.

Retorne APENAS um JSON válido:
{
  "verdict": "Uma frase de impacto sobre o potencial inexplorado deles.",
  "strategies": [
    {
      "title": "Nome Criativo da Estratégia",
      "hook": "Exemplo de Hook/Título para post",
      "description": "Explicação tática de como executar.",
      "impact": "Alto/Médio/Viral"
    }
  ]
}
```

### 8.3 Hunter AI (Prospecção Ativa)

```
ATUE COMO UM AGENTE DE PROSPECÇÃO DE VENDAS (HUNTER).

Objetivo: Encontrar {quantity} empresas reais que correspondam à solicitação do usuário.
Solicitação do Usuário: "{userQuery}"

[FILTROS OPCIONAIS]:
- Se filtro "sem site": FILTRO OBRIGATÓRIO: Apenas empresas que NÃO possuem website.
- Se filtro "com site": FILTRO OBRIGATÓRIO: Apenas empresas que JÁ possuem website ativo.
- Se filtro "novas": PREFERÊNCIA: Empresas novas, recém-inauguradas ou com poucos reviews.
- Se filtro "estabelecidas": PREFERÊNCIA: Empresas consolidadas, com muitos reviews.

USE AS FERRAMENTAS (Google Search) para encontrar dados reais.

CRÍTICO - EXTRAÇÃO DE EMAIL:
Para cada empresa, tente encontrar email válido via site, redes sociais, snippets.
Procure por "contato@", "vendas@", "sac@", "adm@".

Para cada empresa extraia: nome, endereço, telefone, email, website, rating, reviewCount, segmento.

RETORNE APENAS JSON (Array de objetos):
[
  {
    "name": "Nome da Empresa",
    "address": "Endereço Completo",
    "phone": "(11) 99999-9999",
    "email": "contato@empresa.com" ou null,
    "website": "www.site.com.br" ou null,
    "rating": 4.8,
    "reviewCount": 120,
    "segment": "Clínica de Estética",
    "matchReason": "Motivo do match"
  }
]
```

---

## 9. Scripts de Abordagem (Variações)

Gera scripts para 4 canais diferentes.

**User Prompt:**
```
Crie variações de script de abordagem para este lead:
Nome: {lead.name}
Segmento: {lead.segment}
Score: {lead.analysis.priorityScore || 50}
Maturidade Digital: {lead.analysis.digitalMaturity || 'Média'}

Para cada canal, crie um script curto e persuasivo (máx 80 palavras):
- WhatsApp: casual, direto, com gancho de dor
- LinkedIn: profissional, baseado em valor
- Email: formal, com assunto impactante
- Cold Call: abertura de ligação fria (30 segundos)

Retorne JSON:
{
  "whatsapp": "Texto para WhatsApp",
  "linkedin": "Texto para LinkedIn",
  "email": "Assunto: ...\n\nCorpo do email...",
  "coldCall": "Roteiro de ligação fria"
}
```

---

## 10. Análise de Produtos, Clientes e Concorrentes

### 10.1 Ofertas do Lead

```
Analise os PRODUTOS E SERVIÇOS que esta empresa oferece:
Nome: {lead.name}
Segmento: {lead.segment}
Site: {lead.website || "Não informado"}

Identifique os 3 principais produtos ou serviços.
Retorne JSON:
{
  "items": [
    {
      "name": "Nome do Produto",
      "description": "Breve descrição",
      "relevance": 90,
      "source": "ai"
    }
  ],
  "summary": "Resumo geral"
}
```

### 10.2 Público-Alvo do Lead

```
Analise o PÚBLICO ALVO e SEGMENTOS DE CLIENTES desta empresa:
Nome: {lead.name}
Segmento: {lead.segment}
Site: {lead.website || "Não informado"}

Identifique os 3 principais perfis de clientes.
Retorne JSON:
{
  "items": [
    {
      "segment": "Nome do Segmento/Perfil",
      "description": "Por que eles compram desta empresa",
      "source": "ai"
    }
  ],
  "summary": "Resumo do ICP"
}
```

### 10.3 Concorrentes do Lead

```
Identifique os CONCORRENTES principais para esta empresa:
Nome: {lead.name}
Segmento: {lead.segment}
Localização: {lead.address || "Não informado"}

Liste 3 concorrentes (diretos ou indiretos).
Retorne JSON:
{
  "items": [
    {
      "name": "Nome do Concorrente",
      "positioning": "Como ele se diferencia",
      "type": "direto" | "indireto",
      "source": "ai"
    }
  ],
  "summary": "Resumo do cenário competitivo"
}
```

### 10.4 Score de Potencial de Vendas

```
Calcule um SCORE DE POTENCIAL DE VENDAS para esta empresa:
Nome: {lead.name}
Segmento: {lead.segment}
Site: {lead.website || "Não informado"}

Retorne JSON:
{
  "score": 85,
  "summary": "Explicação do score",
  "strengths": ["Ponto forte 1", "Ponto forte 2"],
  "risks": ["Risco 1", "Risco 2"]
}
```

---

## 11. Áudio Strategy

Gera roteiro e áudio (TTS) para 5 tipos de estratégia.

**Tipos de estratégia e seus prompts:**

| Tipo | Instrução |
|------|-----------|
| `simulation` | "Simule um diálogo realista de negociação onde o cliente levanta objeções de preço e o vendedor contorna." |
| `sales_pitch` | "Crie um pitch de vendas persuasivo e direto focado nas dores desse lead." |
| `fomo` | "Explique o custo da inação. O quanto eles estão perdendo por não contratar agora." |
| `objections` | "Liste as 3 maiores objeções prováveis e como respondê-las." |
| `casual` | "Uma mensagem de voz casual de WhatsApp para reativar o contato." |

**Fluxo:**
1. Gera roteiro (máx 100 palavras): `"Escreva um roteiro curto (max 100 palavras) para: {typePrompt}. Apenas o texto falado."`
2. Envia roteiro para TTS com voz `Fenrir` (assertiva)

**Replicar no PHP:** Passo 1 = Gemini/OpenAI para texto. Passo 2 = Google TTS API ou OpenAI TTS com a voz equivalente mais assertiva.

---

## 12. Copilot (Lyxos)

### 12.1 System Prompt do Copilot

```
Você é o Sales Copilot da {agencyName}.
Lead Atual: {lead.name} ({lead.segment}).
Objetivo: Ajudar o vendedor a fechar esse contrato.
Responda de forma tática, curta e direta.
```

### 12.2 Context de Inventário de Leads

O copilot recebe o inventário completo de leads no system prompt:

```
=== INVENTÁRIO DE LEADS ===
Total de leads: {total}

{leads.map(l => `
Lead #${index+1}:
  Nome: ${l.name}
  Segmento: ${l.segment}
  Status: ${l.pipelineStatus}
  Score: ${l.score}
  Email: ${l.email || 'N/D'}
  Tel: ${l.phone || 'N/D'}
  Site: ${l.website || 'N/D'}
`)}
```

### 12.3 Tool Calls do Copilot

O copilot suporta as seguintes "ferramentas" (executadas pelo PHP, não pela IA):

| Tool | Trigger | Ação PHP |
|------|---------|----------|
| `search_lead` | "buscar lead {nome}" | `Lead::where('name', 'like', "%{query}%")` |
| `analyze_lead` | "analisar lead" | dispara `AnalyzeLeadJob` |
| `check_cnpj` | "verificar cnpj {número}" | `EnrichmentService::fetchCNPJData()` |
| `create_task` | "criar tarefa" | insere em `tasks` table |
| `list_leads` | "listar leads" / "liste todos" | retorna lista do inventário |
| `get_links` | "liste links" / "liste sites" | filtra leads com `website != null` |

---

## 13. Algoritmo de Score

### 13.1 Fórmula Principal

```
scoreEfetivo = baseScore + stageBonus + manualAdjustments + contextBonus
scoreEfetivo = Math.min(100, Math.max(0, scoreEfetivo))
```

**Prioridade:**
1. Se `manualScoreOverride` existe → usa diretamente (ignorando tudo)
2. Caso contrário → soma os 4 componentes

### 13.2 BaseScore

```
baseScore = lead.analysis.priorityScore ?? lead.qualityScore ?? 0
```

### 13.3 Bônus por Estágio do Funil

| Status | Bônus |
|--------|-------|
| NEW | 0 |
| ANALYZED | +5 |
| CONTACTED | +15 |
| PROPOSAL | +25 |
| CLOSED | +40 |
| LOST | -10 |

### 13.4 Ajustes Rápidos Predefinidos

| Trigger | Label | Delta |
|---------|-------|-------|
| `client_showed_interest` | Demonstrou interesse | +10 |
| `responded` | Respondeu | +8 |
| `has_budget` | Tem orçamento | +15 |
| `requested_proposal` | Pediu proposta | +12 |
| `no_interest` | Sem interesse | -20 |
| `no_response` | Sem resposta | -5 |
| `competitor_chosen` | Escolheu concorrente | -15 |

### 13.5 Bônus por HumanContext

| Condição | Delta |
|----------|-------|
| timingStatus = IMMEDIATE | +10 |
| timingStatus = LONG_TERM | -10 |
| temperature = HOT | +10 |
| temperature = COLD | -15 |
| objectionCategory = PRICE | -15 |
| objectionCategory = COMPETITOR | -20 |

### 13.6 Observação Automática de Score

Toda mudança de score gera automaticamente uma observação do tipo `score_adjustment`:
```
"Score ajustado: {label} ({+/-delta}) → {oldScore} → {newScore} | {note}"
```

---

## 14. Token Economy — Pesos e Lógica

### 14.1 Tabela de Pesos

| Operação | input | output | Total |
|----------|-------|--------|-------|
| `lead_analysis` | 2 | 5 | **7** |
| `deep_analysis` | 5 | 15 | **20** |
| `deal_insights` | 1 | 4 | **5** |
| `script_variations` | 1 | 6 | **7** |
| `operon_intelligence` | 3 | 8 | **11** |
| `audio_strategy` | 2 | 10 | **12** |
| `spin_questions` | 1 | 3 | **4** |
| `copilot_message` | 1 | 2 | **3** |
| `lead_offerings_analysis` | 1 | 2 | **3** |
| `lead_clients_analysis` | 1 | 2 | **3** |
| `lead_competitors_analysis` | 1 | 2 | **3** |
| `lead_sales_potential_analysis` | 1 | 2 | **3** |
| `default` | 1 | 2 | **3** |

### 14.2 Cotas Diárias por Tier

| Tier | Limite diário |
|------|--------------|
| `starter` | 100 tokens/dia |
| `pro` | 500 tokens/dia |
| `elite` | 2.000 tokens/dia |

### 14.3 Lógica de consumeTokens (PHP)

```php
public function consumeTokens(string $operation, string $tenantId): bool
{
    $weights = config('operon.token_weights');
    $weight = $weights[$operation] ?? $weights['default'];
    $cost = $weight['input'] + $weight['output'];

    $quota = TokenQuota::where('tenant_id', $tenantId)->lockForUpdate()->first();

    // Reset diário se necessário
    if (now() >= Carbon::parse($quota->reset_at)) {
        $quota->used_today = 0;
        $quota->reset_at = now()->timezone('America/Sao_Paulo')->startOfDay()->addDay();
    }

    if ($quota->used_today + $cost > $quota->daily_limit) {
        return false; // Saldo insuficiente
    }

    $quota->used_today += $cost;
    $quota->save();

    // Registrar entry
    TokenEntry::create([
        'id' => Str::uuid(),
        'tenant_id' => $tenantId,
        'operation' => $operation,
        'tokens_consumed' => $cost,
        'input_weight' => $weight['input'],
        'output_weight' => $weight['output'],
        'model_used' => config('operon.active_model'), // NUNCA expor no frontend público
    ]);

    return true;
}
```

### 14.4 Regra de Reset

- Reset à meia-noite no timezone `America/Sao_Paulo`
- Verificar antes de CADA operação (não apenas no login)
- `reset_at` = próximo início de dia (00:00 São Paulo)

### 14.5 Badge Público (IMPORTANTE)

**Nunca expor o modelo real** para o usuário comum:
```php
// ✅ O que o usuário vê
public function getModelBadge(): string {
    return 'Operon Intelligence';
}

// 🔒 O que vai para o banco (apenas admin vê)
private function getActualModel(): string {
    return config('operon.provider') . ':' . config('operon.model'); // ex: gemini:gemini-2.5-flash
}
```

---

## 15. Helper: cleanAndParseJSON

Função crítica para parsear respostas de IA que podem conter markdown. Deve ser replicada em PHP.

**Lógica:**
1. Remove blocos de código ` ```json ... ``` ` ou ` ``` ... ``` `
2. Tenta `json_decode()` direto
3. Em caso de falha, tenta extrair o primeiro array `[...]` ou objeto `{...}` do texto
4. Em última instância, retorna array vazio `[]`

**PHP:**
```php
public static function cleanAndParseJSON(string $text): array
{
    if (empty($text)) return [];

    $cleaned = trim($text);

    // Remover blocos markdown
    if (str_contains($cleaned, '```')) {
        if (preg_match('/```(?:json)?([\s\S]*?)```/', $cleaned, $matches)) {
            $cleaned = trim($matches[1]);
        }
    }

    // Tentar parse direto
    $result = json_decode($cleaned, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $result;
    }

    // Tentar extrair array
    $startArr = strpos($text, '[');
    $endArr = strrpos($text, ']');
    if ($startArr !== false && $endArr !== false) {
        $result = json_decode(substr($text, $startArr, $endArr - $startArr + 1), true);
        if (json_last_error() === JSON_ERROR_NONE) return $result;
    }

    // Tentar extrair objeto
    $startObj = strpos($text, '{');
    $endObj = strrpos($text, '}');
    if ($startObj !== false && $endObj !== false) {
        $result = json_decode(substr($text, $startObj, $endObj - $startObj + 1), true);
        if (json_last_error() === JSON_ERROR_NONE) return $result;
    }

    return [];
}
```

---

## 16. APIs Externas

### 16.1 Brasil API (CNPJ)

```
GET https://brasilapi.com.br/api/cnpj/v1/{cnpjSemFormatacao}

Campos extraídos:
  razao_social         → cnpjData.razaoSocial
  capital_social       → cnpjData.capitalSocial (número)
  cnae_fiscal_descricao → cnpjData.cnaePrincipal
  data_inicio_atividade → cnpjData.dataAbertura
  natureza_juridica    → cnpjData.naturezaJuridica
  descricao_situacao_cadastral → cnpjData.situacao

Tratamento:
  - CNPJ deve ter 14 dígitos (remover formatação)
  - 404 ou erro → retornar null (não bloquear fluxo)
  - Sem auth (API pública)
```

### 16.2 Google PageSpeed Insights

```
GET https://www.googleapis.com/pagespeedonline/v5/runPagespeed
  ?url={encodeURIComponent(url)}
  &key={PAGESPEED_API_KEY}
  &category=PERFORMANCE

Campos extraídos:
  lighthouseResult.categories.performance.score * 100 → performanceScore (0-100)
  lighthouseResult.audits['largest-contentful-paint'].numericValue / 1000 → loadTime (segundos)
  Detecção de pixels via HTML snapshot:
    - hasFacebookPixel: html.includes('facebook.com/tr') || html.includes('fbq(')
    - hasGoogleAnalytics: html.includes('google-analytics.com') || html.includes('gtag(')

Limpeza de URL: garantir que começa com 'https://'
Sem key → retornar null graciosamente
```

### 16.3 Google Places / Maps

Usado para: `rating`, `reviewCount`, `category`, `address`
Integração via `Google Maps JavaScript API` no frontend ou `Places API` no backend.

### 16.4 Gemini API

```
Modelo padrão: "gemini-1.5-flash-latest"
Modelo para imagem: "gemini-1.5-pro-latest"
Modelo para áudio TTS: "gemini-1.5-flash-latest" com responseModalities: [AUDIO]
Voz TTS: "Fenrir" (assertiva)

Config para JSON garantido: responseMimeType: "application/json"
Config para busca: tools: [{ googleSearch: {} }]
```

### 16.5 OpenAI API

```
URL padrão: https://api.openai.com/v1
Modelo configurável: gpt-4o | gpt-4o-mini | o1
Header: Authorization: Bearer {OPENAI_API_KEY}
```

### 16.6 Grok (xAI) API

```
URL: https://api.x.ai/v1  (compatível com OpenAI SDK)
Modelo: grok-3-mini
Mesmos headers e formato do OpenAI
```

---

> **Nota final:** Todos os prompts acima estão prontos para uso direto em PHP. Basta substituir as variáveis `{campo}` pelos valores reais do lead/contexto. A estrutura de JSON esperada em cada prompt é o contrato que a IA deve honrar — use `cleanAndParseJSON()` para parsear com segurança.
