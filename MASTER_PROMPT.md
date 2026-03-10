# MASTER PROMPT — Operon Intelligence Platform
## Prompt de Inicialização para Reconstrução Completa do Projeto

---

## 🎯 O QUE VOCÊ VAI CONSTRUIR

Você vai construir o **Operon Intelligence Platform** — um CRM de inteligência artificial para agências e profissionais de vendas B2B de alto ticket, totalmente do zero em **PHP + Tailwind CSS**.

Este sistema já existe como protótipo funcional em React/TypeScript. **Todos os documentos e imagens desta pasta capturam com exatidão absoluta** o comportamento, os prompts de IA, a arquitetura, o design visual pixel-perfect e as regras de negócio. Você **não precisa do código React** — tudo que precisa está aqui.

> ⚠️ **REGRA ZERO:** Leia **TODOS** os documentos e **TODAS** as imagens desta pasta antes de escrever uma única linha de código. Ignorar isto resultará em retrabalho.

---

## 📂 DOCUMENTOS OBRIGATÓRIOS — LEIA NESTA ORDEM EXATA

### 1. `PHP_MIGRATION_BLUEPRINT.md` — 🏗️ Arquitetura Completa
O mapa do tesouro: estrutura de diretórios PHP, Models com relações Eloquent, Services, Controllers, Jobs de fila, sistema de autenticação multi-tenant, **todas** as integrações externas (Gemini, OpenAI, Grok, Brasil API, PageSpeed), e o plano completo de migração fase por fase.

### 2. `MIGRATION_VALIDATION_PROTOCOL.md` — ✅ Checklist de QA
Protocolo de validação com checklist por fase. **Não avance para a próxima fase sem passar neste checklist.** Ele cobre: funcionalidade, segurança, performance e UX.

### 3. `PROMPTS_AND_LOGIC_REFERENCE.md` — 🧠 O Cérebro da IA
O coração do sistema:
- Todos os **system prompts de IA exatos** (Operon 4D, DeepAnalysis, SPIN, Hunter, sociais, scripts)
- O **algoritmo de score completo** com tabelas de bonus por stage
- O **sistema de Token Economy** com pesos por operação
- As implementações PHP dos helpers críticos (`parseAIResponse`, `consumeTokens`, `SmartContextService`)

### 4. `SENIOR_DEV_PROMPT.md` — 📋 Seu Contrato de Trabalho
Padrões de código inegociáveis: arquitetura limpa (Controller → Service → Repository), tipagem estrita, protocolo de documentação em MD, padrões de API RESTful, segurança multi-tenant, e as **8 regras de ouro** que nunca podem ser violadas.

### 5. `DESIGN_SYSTEM_PROMPT.md` — 🎨 Design System Pixel-Perfect
O sistema de design **completo e definitivo**: paleta de cores com valores hex exatos, tipografia (Plus Jakarta Sans), dark/light mode, **todos os componentes** documentados com HTML/Tailwind (sidebar, cards, modais, botões, badges, tabelas, inputs, toasts), animações com keyframes, efeito animated border, e checklist de implementação de nova tela.

### 6. `DESIGN_EXTRACTION_PROMPT.md` — 🔍 Extração Visual
Prompt especializado para analisar imagens/screenshots e extrair o design system. Use-o antes de implementar qualquer tela nova.

### 7. Imagens `.png` / `.jpg` nesta pasta — 📸 Referência Visual
Analise **cada imagem** presente nesta pasta. Elas mostram a interface real do sistema e são sua **referência visual definitiva**. As telas incluem:
- **`screendash.png`** — Dashboard principal "Nexus" com métricas, tabela de operações de IA, barra de tokens
- **`screenvalt.png`** — Vault de leads em modo Kanban com colunas de funil
- **`screenmaps.png`** — Atlas de Vendas com mapa interativo e cards de localização

### 8. Arquivos `code*.html` — 💻 Exemplos de Implementação
Estes HTMLs são exemplos funcionais de telas já implementadas com o design system correto. **Use-os como referência** de como os tokens de cor, espaçamento e componentes devem ser aplicados na prática.

---

## 🛠️ STACK TECNOLÓGICO — SEM NEGOCIAÇÃO

| Camada | Tecnologia | Detalhe |
|--------|-----------|---------|
| **Backend** | PHP moderno (arquitetura limpa) | Controllers finos, Services com lógica, Repositories para dados |
| **Frontend** | Tailwind CSS (CDN ou build) | Design system do `DESIGN_SYSTEM_PROMPT.md` |
| **Template** | Server-Side Rendering | Blade, Twig, ou equivalente PHP |
| **Banco** | PostgreSQL | SQLite para dev local |
| **Cache/Filas** | Redis | Obrigatório para Jobs de IA |
| **IA Principal** | Google Gemini | Com `google-search` grounding tool |
| **IA Secundária** | OpenAI (GPT-4o) | Fallback compatível |
| **IA Alternativa** | Grok (xAI) | Via OpenAI SDK → `https://api.x.ai/v1` |
| **Fonte** | Plus Jakarta Sans | Google Fonts — wght 300→800 |
| **Ícones** | Material Symbols Outlined | OU Lucide Icons (SVG) |

> 🚫 **PROIBIDO:** React, Vue, Angular, Svelte ou qualquer framework JS pesado. O frontend é **server-side rendered com Tailwind**. JavaScript vanilla apenas para interações (dark mode toggle, Alpine.js para reatividade simples).

---

## 🎨 IDENTIDADE VISUAL — "INTELLIGENCE DARK TECH"

O Operon não é "só um painel". É uma **interface de missão crítica** que transmite poder, sofisticação e futurismo confiável. Cada pixel importa.

### Paleta de Cores Exata (Memorize estas)

| Token | Hex | Uso | Onde aparece nas imagens |
|-------|-----|-----|--------------------------|
| `primary` / `brand-red` | `#E11D48` | CTA principal, hover, foco, nav item ativo no Nexus | Botão "Nexus" na sidebar, "Executar Análise", badge "Processando" |
| `operon-energy` | `#18C29C` | Energia de IA, glows, status online, sucesso | Ponto pulsante do OPERON AI, badge "Sucesso", barras de confiança |
| `operon-teal` | `#0A1D2A` | Background da sidebar | Sidebar em todas as telas |
| `background-dark` | `#19171A` | Background principal dark mode | Fundo de toda a aplicação |
| `brand-surface` | `#232026` | Cards em dark mode | Cards de métricas no dashboard |
| `brand-lime` | `#84CC16` | Destaque secundário, blob de energia | Blob inferior esquerdo no fundo |
| `operon-offwhite` | `#F3F4F6` | Background light mode | Fundo em light mode |

### Tipografia Exata

```
Fonte: Plus Jakarta Sans
Hierarquia:
  - Logo "OPERON AGENTS": text-xl font-extrabold tracking-tighter + glow teal
  - Subtítulo "INTELLIGENCE": text-xs tracking-widest uppercase text-slate-400
  - Título de página (ex: "Painel de Controle Nexus"): text-5xl font-black tracking-tighter
  - Labels de seção (ex: "PROTOCOLOS"): text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500
  - Texto de corpo: text-sm font-medium text-slate-400
  - Valores monetários (ex: "R$ 2.450.000"): text-3xl font-black text-white tracking-tight
  - Badges de status: text-[10px] font-black uppercase
  - Timestamps: text-xs font-mono text-slate-500
```

### Layout Global (visível em TODAS as screenshots)

```
┌─────────────────────────────────────────────────────────────┐
│ <html class="dark">                                         │
│ ┌────────────┬──────────────────────────────────────────┐   │
│ │            │  Top Bar (h-20, backdrop-blur-xl)         │   │
│ │  Sidebar   │  [🔍 Search] ......... [🔔][🌙] [User]  │   │
│ │  (w-72)    ├──────────────────────────────────────────┤   │
│ │  fixed     │                                          │   │
│ │  bg-teal   │  Main Content Area                       │   │
│ │            │  (flex-1, ml-72, overflow-hidden)         │   │
│ │  ┌──────┐  │                                          │   │
│ │  │Logo  │  │  ┌─────────┐ ┌─────────┐ ┌──────────┐   │   │
│ │  │Operon│  │  │ Card 1  │ │ Card 2  │ │ Card 3   │   │   │
│ │  └──────┘  │  │ metric  │ │ metric  │ │ metric   │   │   │
│ │  Nav items │  └─────────┘ └─────────┘ └──────────┘   │   │
│ │  • Nexus   │                                          │   │
│ │  • Vault   │  ┌──────────────────────────────────┐    │   │
│ │  • Atlas   │  │     Table / Content Area          │    │   │
│ │  • Hunter  │  │     (rounded-2xl, bg-white/5)     │    │   │
│ │  • Genesis │  └──────────────────────────────────┘    │   │
│ │  • Agenda  │                                          │   │
│ │  • Follow  │                                          │   │
│ │  • SPIN    │                                          │   │
│ │  • Admin   │                                          │   │
│ │            │                                          │   │
│ │  [AI Bot]  │                                          │   │
│ │  [Brain]   │                                          │   │
│ │  [Avatar]  │                                          │   │
│ └────────────┴──────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### Efeitos Visuais Obrigatórios

1. **Blobs de energia no fundo** (dark mode only): Dois blobs difusos (`blur-[120px]`) com `operon-energy/10` e `lime/10`, `pointer-events-none`
2. **Backdrop-blur na top bar**: `bg-[#0B0F12]/80 backdrop-blur-xl`
3. **Cards glassmorphism**: `bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl`
4. **Hover glow nos cards**: `hover:border-primary/30 transition-all`
5. **Glow no texto de logo**: `text-shadow: 0 0 15px rgba(24, 194, 156, 0.5)`
6. **Ponto pulsante do AI**: `size-2 bg-operon-energy rounded-full animate-pulse shadow-[0_0_8px_#18C29C]`
7. **Scrollbar vermelha translúcida**: 6px, `rgba(225,29,72,0.2)`, sem track visível
8. **Selection color**: `selection:bg-primary/30`

---

## 🏗️ COMO VOCÊ DEVE TRABALHAR — PROTOCOLO RÍGIDO

### Passo 1 — Ler tudo. Sem exceção.
Leia os 6 documentos MD + todas as imagens + os HTMLs de exemplo. Não comece a codar até ter lido tudo.

### Passo 2 — Crie `PROGRESS.md` antes de qualquer código
```markdown
# PROGRESS — Operon Intelligence Platform

## Data de início: [DATA]
## Stack: PHP + Tailwind CSS + PostgreSQL + Redis
## Entendimento do sistema: [Resumo do que você entendeu]

## Status Geral: 🔄 Em Progresso

### Fase 1: Setup — ⏳ Pendente
- [ ] Projeto PHP criado com estrutura limpa
- [ ] .env configurado (DB, Redis, API keys)
- [ ] Tailwind CSS carregado (CDN ou build)
- [ ] Plus Jakarta Sans + Material Symbols carregados
- [ ] Dark mode toggle funcional

### Fase 2: Database — ⏳ Pendente
[...]
```

### Passo 3 — Implemente fase por fase, nesta ordem EXATA

| # | Fase | Dependência | Validação |
|---|------|-------------|-----------|
| 1 | Setup do projeto + ambiente | Nenhuma | Página em branco com dark mode e sidebar carrega |
| 2 | Database (migrations + models) | Fase 1 | Todas as tabelas criadas, relações testadas |
| 3 | Autenticação + multi-tenant | Fase 2 | Login funcional, tenant_id em todas as queries |
| 4 | CRUD de Leads + Vault | Fase 3 | Tela Vault (Kanban + Lista) funcional como na imagem |
| 5 | Token Economy | Fase 3 | Barra de tokens com estados (ok/warning/depleted) |
| 6 | Integrações de IA (Gemini/OpenAI) | Fase 5 | Chamada de teste retorna JSON parseado |
| 7 | Lead Analysis + Deep Analysis | Fase 6 | Score de 0-100, análise completa via Queue |
| 8 | Operon 4D Intelligence | Fase 6 | 4 jobs paralelos (diagnóstico, potencial, autoridade, script) |
| 9 | SPIN Hub | Fase 6 | Perguntas SPIN geradas por segmento |
| 10 | Hunter (prospecção ativa) | Fase 6 | Busca e qualificação automática |
| 11 | Agenda + Follow-up | Fase 3 | CRUD de eventos, lembretes automáticos |
| 12 | Copilot (Chat IA com tools) | Fase 6 | Chat funcional com tool calls (search, navigate, analyze) |
| 13 | Atlas de Vendas (Mapa) | Fase 4 | Mapa com leads geolocalizados como na imagem |
| 14 | Dashboard Nexus | Fase 4-11 | Dashboard com métricas e tabela de operações IA |
| 15 | Admin Panel | Fase 3 | Gestão de tenants, API keys, configurações |
| 16 | Polish Final | Todas | Animações, transições, blobs, glows, responsividade |

### Passo 4 — Atualize `PROGRESS.md` em TEMPO REAL
Marque `✅` a cada fase concluída. Documente decisões no `DECISIONS.md`. **Nunca** deixe para o final.

---

## 📋 REGRAS INEGOCIÁVEIS — MEMORIZE

### 🏛️ Arquitetura (de `SENIOR_DEV_PROMPT.md`)
```
Controller → Service → Repository → Model
    ↓            ↓          ↓          ↓
  Request     Lógica      Query    Eloquent
  Response    de Negócio   Isolada  Relations
```

- **Controllers são finos** → recebem request, delegam para Service, retornam response. ZERO lógica.
- **Services têm toda a lógica** → análise, score, tokens, contexto de IA.
- **Repositories isolam queries** → Services nunca fazem queries diretamente.
- **Jobs/Queues para TODA chamada de IA** → nunca bloquear um request HTTP.

### 🔐 Segurança (INEGOCIÁVEL)
- **`tenant_id` em TODA query** → `Lead::byTenant($tenantId)->findOrFail($id)`
- **Chaves de API NUNCA no código** → apenas `.env` via `config()`
- **Validação em TODO input** → Form Request (ou equivalente)
- **Nunca expor stack traces** em produção

### 🤖 IA (O Coração do Sistema)
- **`parseAIResponse` SEMPRE** → IA nem sempre retorna JSON limpo (ver implementação em `PROMPTS_AND_LOGIC_REFERENCE.md`)
- **Rate limiting: 5s entre chamadas** da mesma operação/tenant
- **Log TODA chamada** → operation, provider, model, tenant_id, latência, tokens_in/out, status
- **"Operon Intelligence" SEMPRE** → NUNCA mostrar "Gemini", "GPT" ou "Grok" na interface. O produto é **Operon Intelligence**.

### 🎨 Visual (DO `DESIGN_SYSTEM_PROMPT.md`)
- **Nunca branco puro em dark mode** → use `white/5`, `white/10`, `brand-surface`
- **Cards SEMPRE `rounded-2xl` ou `rounded-3xl`** → nunca `rounded-md` ou `rounded-sm`
- **Botões `rounded-xl`** → consistência total
- **Bordas sutis** → `border-gray-100 dark:border-white/5`
- **Espaçamento generoso** → `p-6`, `p-8`, `gap-4`, `space-y-6` (nunca `p-1` ou `p-2` em containers)
- **Ícones: 18px nav, 20px header, 16px botões, 24px destaques**
- **Animação de entrada em TODA view** → fade-in, slide-up, pop-in
- **Stagger delay em listas** → cada card entra 100ms depois do anterior

---

## 🤖 OS MÓDULOS DE IA — O CORAÇÃO DO SISTEMA

Os prompts exatos estão em `PROMPTS_AND_LOGIC_REFERENCE.md`. Aqui está o mapa:

### 1. Lead Analysis (Qualificação) — Custo: 7 tokens
- Qualifica lead com score 0–100 usando Google Search grounding
- Retorna: `priorityScore`, `fitAnalysis`, `urgencyLevel`, `contactStrategy`, `proposedValue`, `tags`
- **Implementar como Job** → `AnalyzeLeadJob`

### 2. Deep Analysis (Análise Profunda) — Custo: 25 tokens
- 5 dimensões: systemAudit, brand, warRoom, assets, marketIntelligence
- Processo em steps com progresso ao usuário (progress bar ou skeleton)
- Retorna JSON multi-seção complexo

### 3. Operon 4D Intelligence — Custo: 10 tokens cada (4 jobs = 40 total)
- **4 análises paralelas**, cada uma em seu próprio Job:
  - `diagnosticoPerda` — onde o negócio perde dinheiro
  - `potencialComercial` — oportunidades não exploradas
  - `autoridadeLocal` — posicionamento vs. concorrência local
  - `scriptAbordagem` — script de vendas personalizado
- Usa `SmartContextService` para injetar contexto da agência (preços, cases, metodologia)

### 4. SPIN Framework — Custo: 5 tokens
- Gera perguntas SPIN (Situação/Problema/Implicação/Necessidade) personalizadas por segmento
- Retorna array de perguntas por dimensão

### 5. Hunter (Prospecção) — Custo: 15 tokens
- Busca e qualificação automática de prospects
- Usa Google Search grounding para pesquisa em tempo real

### 6. Copilot "Operon AI" — Custo: 3 tokens/mensagem
- Chat de IA com tool calls:
  - `search_leads` → buscar leads no vault
  - `get_lead_details` → detalhes de um lead
  - `update_lead_stage` → mover lead no funil
  - `run_analysis` → disparar análise
  - `navigate_to` → navegar para uma tela
- System prompt inclui inventário completo dos leads em tempo real
- **NUNCA** citar Gemini/GPT — é "Operon Intelligence"

---

## 💎 TOKEN ECONOMY — SISTEMA DE COTAS

### Tiers e Limites Diários
| Tier | Limite diário | Público-alvo |
|------|--------------|--------------|
| `starter` | 100 tokens | Teste/trial |
| `pro` | 500 tokens | Usuário padrão |
| `elite` | 2000 tokens | Power user/agência |

### Tabela de Custos (Pesos por Operação)
| Operação | Custo | Onde aparece |
|----------|-------|-------------|
| `lead_analysis` | 7 | Botão "Solicitar Análise" no Vault |
| `deep_analysis` | 25 | Botão "Análise Profunda" no lead |
| `operon_diagnostico` | 10 | Operon 4D tab |
| `operon_potencial` | 10 | Operon 4D tab |
| `operon_autoridade` | 10 | Operon 4D tab |
| `operon_script` | 10 | Operon 4D tab |
| `spin_questions` | 5 | SPIN Hub |
| `hunter` | 15 | Hunter view |
| `social_analysis` | 8 | Análise de redes sociais |
| `script_variations` | 6 | Variações de script |
| `audio_generation` | 12 | Geração de áudio |
| `deal_insights` | 8 | Insights de negócio |
| `copilot_message` | 3 | Cada mensagem do chat |

### Regras de Reset
- Reset à **meia-noite fuso America/Sao_Paulo**
- Histórico de consumo mantido por **30 dias**
- Barra visual de tokens no dashboard (verde/amarelo/vermelho conforme uso)

---

## 📊 SCORE ALGORITHM

```
effectiveScore = baseScore + stageBonus + manualAdjustments + contextBonus
```

Se `manualOverride` estiver definido, valor prevalece sobre o calculado.

| Stage | Bonus | Significado |
|-------|-------|-------------|
| `NEW` | 0 | Lead acabou de entrar |
| `ANALYZED` | +5 | IA qualificou |
| `CONTACTED` | +15 | Contato realizado |
| `PROPOSAL` | +25 | Proposta enviada |
| `CLOSED` | +40 | Negócio fechado |
| `LOST` | -10 | Negócio perdido |

Implementação completa com bônus de HumanContext em `PROMPTS_AND_LOGIC_REFERENCE.md`.

---

## 🌍 MULTI-TENANT — ISOLAMENTO TOTAL

- Cada tenant = um closer/vendedor dentro de uma agência
- `tenant_id` presente em **TODAS** as tabelas de dados
- **Isolamento completo**: tenant A **NUNCA** vê dados do tenant B
- A agência (admin) tem visibilidade cross-tenant
- Middleware de tenant aplicado em **TODAS** as rotas de dados

---

## 🔌 INTEGRAÇÕES EXTERNAS

### APIs de IA (sistema BYOK — Bring Your Own Key)
| Provider | Endpoint | Modelo recomendado |
|----------|----------|-------------------|
| Google Gemini | `generateContent` | `gemini-2.0-flash` com google-search grounding |
| OpenAI | Chat Completions | `gpt-4o` / `gpt-4o-mini` |
| Grok (xAI) | `https://api.x.ai/v1` (via OpenAI SDK) | `grok-2` |

### APIs de Enriquecimento
| API | Endpoint | Uso |
|-----|----------|-----|
| Brasil API (CNPJ) | `brasilapi.com.br/api/cnpj/v1/{cnpj}` | Dados empresariais |
| PageSpeed Insights | `googleapis.com/pagespeedonline/v5/runPagespeed?url={url}&strategy=mobile` | Análise técnica do site |

---

## 🗺️ TELAS DO SISTEMA — REFERÊNCIA VISUAL

Cada tela deve ser implementada seguindo exatamente o design das imagens. Use `DESIGN_SYSTEM_PROMPT.md` e os arquivos `code*.html` como referência.

### Telas Principais (nos screenshots)
| Tela | Arquivo de referência | Elementos-chave |
|------|-----------------------|-----------------|
| **Dashboard Nexus** | `screendash.png` + `code1exemplo.html` | 4 cards de métricas + tabela de operações IA + barra de tokens |
| **Vault (Leads)** | `screenvalt.png` + `code2exemplo.html` | Kanban com colunas (Prospecção/Qualificação/Proposta) + toggle Lista |
| **Atlas de Vendas** | `screenmaps.png` + `code3maps.html` | Mapa interativo + card de localização + resumo territorial |

### Telas a Implementar (sem screenshot, usar design system)
| Tela | Elementos-chave |
|------|-----------------|
| **Hunter** | Lista de prospects, botão de busca, cards de resultado |
| **Genesis** | Upload/importação de leads |
| **Agenda** | Calendário, eventos, compromissos |
| **Follow-up** | Lista de tarefas pendentes, lembretes |
| **SPIN Hub** | Perguntas SPIN por dimensão, accordion ou tabs |
| **Admin** | Configurações, API keys (com show/hide), gestão de tenants |
| **Lead Detail** | Modal ou view completa com todas as análises |
| **Copilot Chat** | Chat sidebar ou modal com histórico de mensagens |

---

## ✅ CHECKLIST DE ENTREGA — POR MÓDULO

Antes de declarar **qualquer** módulo como concluído, confirme TODOS os itens:

### Funcionalidade
- [ ] Funciona 100% sem erros no console/log
- [ ] Dados corretos retornados pela API
- [ ] Multi-tenant: dados isolados por tenant

### Segurança
- [ ] `tenant_id` scope em todas as queries
- [ ] Nenhuma chave de API hardcoded
- [ ] Validação em todo input de usuário
- [ ] Exceções de domínio (nunca `\Exception` genérica)

### IA
- [ ] Chamadas de IA em Jobs/Queues (nunca síncronas)
- [ ] `parseAIResponse` usado em toda resposta de IA
- [ ] Rate limiting implementado (5s entre chamadas)
- [ ] Log de toda chamada (operation, provider, latência, status)
- [ ] "Operon Intelligence" visível — nunca "Gemini" ou "GPT"

### Visual
- [ ] Dark mode funcionando com alternância suave (0.5s)
- [ ] Blobs de energia no fundo dark mode
- [ ] Cards com `rounded-2xl`/`rounded-3xl` e `bg-white/5 border border-white/10`
- [ ] Topbar com `backdrop-blur-xl` e `h-20`
- [ ] Animação de entrada aplicada na view (fade-in, slide-up)
- [ ] Scrollbar customizada (vermelha, 6px, sem track)
- [ ] `no-scrollbar` em containers internos
- [ ] Espaçamento generoso (`p-8`, `gap-4`)
- [ ] Tipografia Plus Jakarta Sans carregada e aplicada

### Documentação
- [ ] `PROGRESS.md` atualizado com fase marcada como ✅
- [ ] Decisões arquiteturais documentadas em `DECISIONS.md`

---

## 🚀 COMEÇE ASSIM

```
1. Leia TODOS os 7 documentos MD + imagens + HTMLs de exemplo
2. Crie PROGRESS.md com seu entendimento completo
3. Implemente fase por fase (nunca pular)
4. Atualize PROGRESS.md em tempo real
5. Valide cada fase com o checklist acima
6. Use DESIGN_SYSTEM_PROMPT.md para CADA tela nova
7. Analise as imagens antes de implementar qualquer view
```

**O objetivo final:** um sistema PHP + Tailwind **completamente funcional** que replique e melhore todas as funcionalidades do protótipo React, com a **mesma identidade visual pixel-perfect**, a **mesma lógica de IA**, e a **mesma experiência de usuário premium** — como uma interface de missão crítica que impressiona à primeira vista.

---

*Este prompt foi gerado a partir da análise completa do código-fonte React/TypeScript e do sistema de design do Operon Intelligence Platform. Última atualização: 10/03/2026.*
