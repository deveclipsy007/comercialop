# PHP Migration Blueprint — Operon Intelligence CRM

> **Versão:** 1.0 | **Data:** 2026-03-10 | **Status:** Documentação — NÃO implementar ainda
>
> Este documento é um blueprint técnico completo para migração da plataforma React/TypeScript para PHP + Tailwind CSS + MySQL/SQLite. Gerado após auditoria completa do código-fonte existente.

---

## Índice

1. [Visão Geral do Sistema](#1-visão-geral-do-sistema)
2. [Inventário React](#2-inventário-react)
3. [Mapeamento Funcional](#3-mapeamento-funcional)
4. [Regras de Negócio Críticas](#4-regras-de-negócio-críticas)
5. [Problemas Técnicos Identificados](#5-problemas-técnicos-identificados)
6. [Estratégia de Reaproveitamento](#6-estratégia-de-reaproveitamento)
7. [Proposta de Arquitetura PHP](#7-proposta-de-arquitetura-php)
8. [Comparativo Monolito × API Separada](#8-comparativo-monolito--api-separada)
9. [Mapeamento React → PHP por Módulo](#9-mapeamento-react--php-por-módulo)
10. [Estrutura de Pastas PHP](#10-estrutura-de-pastas-php)
11. [Banco de Dados](#11-banco-de-dados)
12. [Compatibilidade SQLite × MySQL](#12-compatibilidade-sqlite--mysql)
13. [Rotas e Módulos](#13-rotas-e-módulos)
14. [Fases de Migração](#14-fases-de-migração)
15. [Checklist de Reconstrução](#15-checklist-de-reconstrução)
16. [Riscos e Mitigações](#16-riscos-e-mitigações)
17. [Recomendação Final](#17-recomendação-final)

---

## 1. Visão Geral do Sistema

### O que é o Operon Intelligence CRM

Plataforma de CRM inteligente para equipes de vendas consultivas ("closers"), com:
- **Gestão de leads** com enriquecimento automático via APIs externas (CNPJ, PageSpeed, Google Places)
- **Análise de IA** em 4 dimensões: diagnóstico de perda, potencial comercial, autoridade local, script de abordagem
- **SPIN Framework**: estruturação de argumentos de venda por lead
- **Lyxos Copilot**: assistente de voz/texto contextualizado com o inventário de leads
- **Token Economy**: cotas de uso de IA por closer/tier (Starter 100/dia, Pro 500/dia, Elite 2.000/dia)
- **Multi-tenant**: múltiplas "operações" isoladas por tenantId (IndexedDB)
- **AdminPanel** oculto (Ctrl+Shift+A + PIN): acesso a configurações técnicas de IA

### Stack Atual

| Camada | Tecnologia |
|--------|------------|
| Framework | React 18 + TypeScript 5 |
| Build | Vite 5 |
| Estilos | Tailwind CSS v3 |
| Estado | React Context API (LeadsContext, OperonProvider) |
| Persistência (dev) | IndexedDB v2 (4 stores) |
| Persistência (schema) | SQLite (21 tabelas — services/database/) |
| AI | Google Gemini 2.0/2.5 Flash, OpenAI GPT-4o, Grok (xAI) |
| APIs externas | Brasil API (CNPJ), Google PageSpeed, Google Places |
| UI Icons | Lucide-react |
| Gráficos | Recharts |
| Listas grandes | react-window (virtualização) |
| Autenticação | **NENHUMA** (sistema demo com perfil fixo "Felix Operon Root Admin") |

### Stack Alvo

| Camada | Tecnologia |
|--------|------------|
| Framework | PHP 8.2+ (Laravel 11 recomendado, ou PHP modular) |
| Frontend | Blade templates + Tailwind CSS v3/v4 |
| JS mínimo | Alpine.js ou Vanilla JS para interações |
| Banco principal | MySQL 8.0+ (produção) |
| Banco dev/testes | SQLite 3 |
| Cache | Redis (opcional) / File cache |
| Queue | Laravel Horizon / Banco + cron |
| AI | Google Gemini API, OpenAI API, Grok API (via HTTP puro) |
| Auth | Laravel Sanctum ou Jetstream |

---

## 2. Inventário React

### 2.1 Componentes (48 arquivos em `components/`)

#### Componentes de Navegação/Layout
| Arquivo | Responsabilidade |
|---------|-----------------|
| `App.tsx` | Router principal; view state machine; shortcuts de teclado |
| `Sidebar.tsx` | Menu lateral com rotas e tenant info |
| `TenantSelector.tsx` | Dropdown de seleção de operação ativa |

#### Views Principais
| Arquivo | Responsabilidade |
|---------|-----------------|
| `VaultView.tsx` | Lista de leads (Kanban / Grid / Lista); filtros; busca |
| `Dashboard.tsx` | Métricas, gráficos (Recharts), insights de IA |
| `SpinHubView.tsx` | Framework SPIN por lead; view cards/tabela |
| `LyxosCopilotView.tsx` | Copilot de voz + texto com tool calls de IA |
| `ImportView.tsx` | Upload CSV/JSON; preview; validação; importação em lote |
| `AgendaView.tsx` | Calendário de tarefas e follow-ups |
| `AccountCostView.tsx` | Custos R$ + tokens Operon (histórico) |
| `AppSettingsView.tsx` | Configurações do app (Maps key, tema, idioma) |

#### Modais e Painéis de Lead
| Arquivo | Responsabilidade |
|---------|-----------------|
| `LeadFullView.tsx` | Painel completo de lead (8 cards de análise) |
| `LeadSidePanel.tsx` | Painel lateral slide-in para lead |
| `DeepAnalysisModal.tsx` | Análise profunda com web scraping + IA |
| `AudioGeneratorModal.tsx` | Gerador de mensagens de áudio por estratégia |
| `HumanContextSection.tsx` | Seção de contexto humano (notas, observações) |
| `TranscriptionSection.tsx` | Transcrição e análise de chamadas |

#### Modais de Configuração
| Arquivo | Responsabilidade |
|---------|-----------------|
| `AdminPanel.tsx` | Painel admin oculto (Ctrl+Shift+A + PIN 4 dígitos); IA, tokens, módulos, monitoring |
| `AgencyBrainModal.tsx` | Configurações da agência: Ofertas, Persona, Arquivos RAG, Tabela de Preços |

#### Componentes Auxiliares
| Arquivo | Responsabilidade |
|---------|-----------------|
| `Icons.tsx` | Barrel de re-export Lucide-react |
| `InsightCard.tsx` | Card de insight estratégico |
| `FollowUpModal.tsx` | Modal de cadência de follow-up |
| `ProposalModal.tsx` | Gerador de proposta comercial |
| `ScoreAdjustmentModal.tsx` | Ajuste manual de score de lead |
| `ImportInstructionsModal.tsx` | Instruções de importação CSV |
| `KanbanView.tsx` | View Kanban do VaultView |
| `GridView.tsx` | View Grid do VaultView |
| `LeadListView.tsx` | View Lista do VaultView |
| `Toast.tsx` | Sistema de notificações temporárias |

### 2.2 Services (26 arquivos em `services/`)

| Arquivo | Responsabilidade |
|---------|-----------------|
| `dataService.ts` | CRUD IndexedDB (leads, exportação JSON/CSV) |
| `geminiService.ts` | Todas as chamadas Gemini: análise, insights, WhatsApp, SPIN |
| `operonService.ts` | 4 análises principais (diagnóstico, comercial, autoridade, script) |
| `aiProviderService.ts` | Roteador Gemini/OpenAI/Grok; `generateAIResponse()`; badge "Operon Intelligence" |
| `operonApiService.ts` | Camada de abstração unificada; registry de `intelligence_log` |
| `tokenService.ts` | Token Economy: quotas, debits, histórico, reset diário |
| `smartContextService.ts` | Contexto estratégico para IA: lead, agência, preços, RAG, oferta |
| `knowledgeService.ts` | `AgencySettings` + CRUD localStorage; `PricingEntry` CRUD |
| `tenantService.ts` | Multi-tenant CRUD; audit log; `getActiveTenantId()` |
| `costService.ts` | Custo real R$ de chamadas Gemini/Google |
| `settingsService.ts` | Chaves de API (localStorage) |
| `enrichmentService.ts` | Brasil API (CNPJ), PageSpeed, Google Places |
| `scoreService.ts` | Cálculo de score do lead (algoritmo) |
| `queueService.ts` | Fila de análises assíncronas (limitação de concorrência) |
| `cadenceService.ts` | Cadência de follow-up: passos, templates |
| `ragService.ts` | Indexação e busca em arquivos RAG |
| `exportService.ts` | Export CSV/JSON |
| `importService.ts` | Parser CSV/JSON para leads |
| `taskService.ts` | CRUD de tarefas (agenda) |
| `followUpService.ts` | Sequências de follow-up |
| `audioService.ts` | Integração de síntese de voz |
| `database/schema.sql` | DDL MySQL/SQLite (21 tabelas) |
| `database/sqliteRepository.ts` | Implementação IRepositoryFactory |
| `database/migrations/001_initial.sql` | Migration inicial |

### 2.3 Context e Hooks

| Arquivo | Responsabilidade |
|---------|-----------------|
| `context/LeadsContext.tsx` | Estado global central: leads, tenants, análises, UI state |
| `context/OperonProvider.tsx` | Estado de tokens: quota, remaining, refresh |
| `hooks/useTokenGuard.ts` | Interceptor pré-análise: valida saldo antes de consumir |

### 2.4 Types Principais (`types.ts`)

```typescript
// Enums
PipelineStatus: NEW | CONTACTED | ANALYZED | PROPOSAL | NEGOTIATION | CLOSED | LOST
CompanySize: MICRO | SMALL | MEDIUM | LARGE | ENTERPRISE
TaskStatus: TODO | IN_PROGRESS | DONE | CANCELLED
TaskPriority: LOW | MEDIUM | HIGH | URGENT
AIProvider: 'gemini' | 'openai' | 'grok'
TokenTier: 'starter' | 'pro' | 'elite'

// Interfaces
Lead { id, name, website, segment, cnpj, email, contact, address, companySize,
       pipelineStatus, score, observations[], transcriptions[], history[],
       aiAnalysis, operonAnalysis, deepAnalysis, spinFramework, humanContext, audioAssets }
AgencySettings { copilot, activeOfferId, offers[], pricingTable[], icpCriteria[],
                 differentials[], enabledSkills{}, provider, openaiApiKey, openaiModel, operon{} }
Tenant { id, name, slug, settings, createdAt, updatedAt }
TokenQuota { tier, dailyLimit, used, resetAt }
TokenEntry { id, timestamp, operation, tokensConsumed, modelUsed, closerId }
```

---

## 3. Mapeamento Funcional

### 3.1 Fluxos de Usuário Identificados

#### Fluxo 1: Importação e Análise
```
Upload CSV/JSON → Validação → Preview → Importar leads → VaultView
→ Selecionar leads → Analisar (batch) → Análise Operon (4 dimensões)
→ LeadFullView → cards de análise preenchidos
```

#### Fluxo 2: Enriquecimento de Lead
```
LeadFullView → CNPJ input → Brasil API → preenche dados fiscais
→ Website → PageSpeed API → score de performance
→ Endereço → Google Places → localização + mapa
→ Score automático recalculado
```

#### Fluxo 3: Análise Profunda (DeepAnalysis)
```
VaultView → botão "Análise Profunda" → DeepAnalysisModal
→ queueService.add() → scraping do site (Gemini URL Context)
→ IA gera 6 seções: executivo, SWOT, proposta, objeções, urgência, próximos passos
→ Lead atualizado com deepAnalysis
→ AccountCostView atualiza custo R$
```

#### Fluxo 4: SPIN Framework
```
VaultView → SpinHubView → selecionar lead
→ 4 dimensões: Situação, Problema, Implicação, Necessidade
→ IA gera perguntas + respostas por dimensão
→ Edição inline + completeness %
```

#### Fluxo 5: Copilot (Lyxos)
```
Sidebar → LyxosCopilotView → chat de texto ou voz
→ Tool calls: buscar lead, analisar lead, verificar CNPJ, criar tarefa
→ Context: inventário completo de leads via syncLeadsToMemory()
→ Resposta contextualizada em português
```

#### Fluxo 6: Follow-up Cadência
```
LeadFullView → aba Follow-up → selecionar cadência
→ FollowUpModal: visualizar steps (dia, canal, template)
→ Gerar mensagem personalizada por step via IA
→ Registrar próximo step na agenda
```

#### Fluxo 7: AdminPanel (oculto)
```
Ctrl+Shift+A → PIN modal (4 dígitos) → AdminPanel fullscreen
→ Aba IA: provider, API key, modelo, routing, testar conexão
→ Aba Tokens: tier, quota, ledger, reset
→ Aba Módulos: skill toggles
→ Aba Monitoramento: intelligence log (modelo real visível aqui)
```

### 3.2 Funcionalidades por Prioridade para PHP

| Prioridade | Módulo | Complexidade |
|------------|--------|-------------|
| 🔴 P1 | CRUD de leads (VaultView) | Média |
| 🔴 P1 | Análise Operon (4 dimensões) | Alta |
| 🔴 P1 | Multi-tenant | Média |
| 🔴 P1 | Importação CSV/JSON | Média |
| 🟡 P2 | Dashboard com gráficos | Média |
| 🟡 P2 | SPIN Framework | Média |
| 🟡 P2 | Token Economy | Alta |
| 🟡 P2 | Enriquecimento (CNPJ, PageSpeed) | Baixa |
| 🟢 P3 | Lyxos Copilot (voz) | Muito Alta |
| 🟢 P3 | DeepAnalysis (scraping) | Alta |
| 🟢 P3 | AudioGenerator | Alta |
| 🟢 P3 | AdminPanel | Média |

---

## 4. Regras de Negócio Críticas

### 4.1 Token Economy (obrigatório replicar com exatidão)

```
Pesos por operação (Operon Tokens):
  lead_analysis:        input=2,  output=5   → total 7
  deep_analysis:        input=5,  output=15  → total 20
  deal_insights:        input=1,  output=4   → total 5
  script_variations:    input=1,  output=6   → total 7
  operon_intelligence:  input=3,  output=8   → total 11
  audio_strategy:       input=2,  output=10  → total 12
  spin_questions:       input=1,  output=3   → total 4
  copilot_message:      input=1,  output=2   → total 3
  default:              input=1,  output=2   → total 3

Cotas diárias:
  starter: 100 tokens/dia
  pro:     500 tokens/dia
  elite:   2.000 tokens/dia

Reset: diário à meia-noite (America/Sao_Paulo)
```

### 4.2 Roteamento de IA (aiProviderService)

```
Routing Strategy:
  'cost'    → Gemini 2.0/2.5 Flash (mais barato)
  'speed'   → Grok grok-3-mini (mais rápido)
  'quality' → OpenAI GPT-4o (maior qualidade)

Badge público: SEMPRE "Operon Intelligence" (nunca expõe modelo real)
Modelo real: visível APENAS no AdminPanel → Monitoramento → intelligence_log
```

### 4.3 Score de Lead (scoreService)

O score é calculado automaticamente com base em:
- Preenchimento dos campos (completeness %)
- CNPJ verificado (+pontos)
- Site com PageSpeed score (+pontos por performance)
- Análise IA concluída (+pontos)
- DeepAnalysis concluída (+pontos)
- SPIN Framework completo (+pontos)
- Ajustes manuais via ScoreAdjustmentModal

### 4.4 Análise Operon (4 Dimensões)

Cada lead pode ter `operonAnalysis` com 4 subdimensões:
```
1. diagnosticoPerda    → Por que o lead pode não fechar
2. potencialComercial  → Potencial de receita estimado
3. autoridadeLocal     → Posicionamento de autoridade da empresa
4. scriptAbordagem     → Script de abordagem personalizado
```
Cada subdimensão tem: `{ status: 'approved'|'pending', content: string, generatedAt: ISO }`

### 4.5 SPIN Framework

```
S — Situação:  perguntas sobre contexto atual do lead
P — Problema:  perguntas para identificar dores
I — Implicação: perguntas sobre consequências do problema
N — Necessidade: perguntas para revelar necessidade do produto

Por lead: 4 objetos SPIN, cada um com:
  - aiQuestions: string[] (gerado por IA)
  - customQuestions: string[] (editado pelo usuário)
  - clarity: number (0-100, completeness %)
```

### 4.6 Multi-tenant

```
Cada lead pertence a um tenantId (UUID ou 'default')
O tenant ativo é armazenado em sessionStorage/localStorage
Troca de tenant → recarrega leads filtrados pelo novo tenantId
Tenant 'default' é protegido (não pode ser deletado)
```

### 4.7 Importação CSV

Campos reconhecidos no CSV:
```
name, segment, cnpj, email, phone, website, address, city, state,
contact_name, contact_role, observations, status
```
- Campos desconhecidos → ignorados
- CNPJ duplicado → skip ou merge (configurável)
- Encoding: UTF-8 (aceitar BOM)

---

## 5. Problemas Técnicos Identificados

### 5.1 Instabilidades do Sistema React Atual

| Problema | Causa | Impacto |
|----------|-------|---------|
| Tela branca | Regressão de código por outra IA (IndexedDB v1 vs v2) | Bloqueante |
| Análise não aparecia | `updateLead()` chamado sem `await` em handlers | Alto |
| Perdas de dados | `sessionStorage` em vez de `localStorage` para AgencySettings | Médio |
| Inconsistência de estado | Race conditions em análises paralelas | Médio |
| Sem auth | Sistema aberto, qualquer pessoa acessa | Alto |
| Sem backend | Todos os dados no browser (IndexedDB) | Alto |
| API keys expostas | Chaves no localStorage (acessível por JS) | Alto |
| Sem persistência servidor | Dados perdidos se limpar o browser | Crítico |

### 5.2 Limites Técnicos do IndexedDB

- Dados presos no browser do usuário (não compartilháveis)
- Sem transações robustas (sem rollback completo)
- Capacidade limitada (~500MB dependendo do browser)
- Sem backup automático
- Sem acesso server-side

### 5.3 Problemas de Concorrência

- `queueService` (fila em memória) perde estado no reload
- Rate limiting de IA apenas no front-end (bypassável)
- Sem controle de quota por usuário real

---

## 6. Estratégia de Reaproveitamento

### 6.1 O que REUTILIZAR diretamente

| Artefato | Reutilização |
|----------|-------------|
| `services/database/schema.sql` | DDL base para MySQL (com adaptações mínimas) |
| `services/database/migrations/001_initial.sql` | Migration inicial adaptada |
| Prompts de IA (dentro de geminiService.ts e operonService.ts) | Extrair strings de prompt → arquivos PHP |
| Lógica de score (scoreService.ts) | Traduzir para PHP puro |
| Lógica de token (tokenService.ts) | Traduzir para PHP (DB em vez de localStorage) |
| Regras de negócio SPIN | Traduzir para PHP |
| Tailwind CSS classes | Reutilizar diretamente nos templates Blade |
| Paleta de cores Operon | Copiar tailwind.config para PHP project |
| Estrutura de dados (types.ts) | Traduzir para PHP classes/DTOs |

### 6.2 O que REESCREVER do zero

| Artefato | Por quê |
|----------|---------|
| Context API (LeadsContext, OperonProvider) | PHP não tem estado reativo; substituir por session + DB |
| IndexedDB (dataService.ts) | Substituir por MySQL/SQLite via PDO/Eloquent |
| React components | Substituir por Blade templates |
| useState/useEffect | Substituir por lógica PHP + Alpine.js |
| queueService (in-memory) | Substituir por Laravel Queues/Horizon |
| Roteamento (view state machine) | Substituir por Laravel Router |

### 6.3 O que ADAPTAR

| Artefato | Adaptação |
|----------|-----------|
| `aiProviderService.ts` | PHP class `AiProviderService` com Guzzle HTTP |
| `enrichmentService.ts` | PHP class com Guzzle; mesmas APIs externas |
| `importService.ts` | PHP com `fgetcsv()` + validação |
| `cadenceService.ts` | PHP com lógica de passos + templates |
| `knowledgeService.ts` | PHP com tabela `agency_settings` no DB |
| `smartContextService.ts` | PHP class que monta contexto de prompt |

---

## 7. Proposta de Arquitetura PHP

### 7.1 Visão Geral

```
┌─────────────────────────────────────────────────────────────┐
│  BROWSER                                                     │
│  Blade Templates + Tailwind CSS + Alpine.js (mínimo)        │
│  Livewire (opcional, para reatividade sem full SPA)         │
└──────────────────────┬──────────────────────────────────────┘
                       │ HTTP/HTTPS
┌──────────────────────▼──────────────────────────────────────┐
│  LARAVEL 11 (ou PHP Modular)                                │
│                                                             │
│  Routes → Controllers → Services → Models → DB             │
│                                                             │
│  AiProviderService ────► Gemini API                         │
│  EnrichmentService ────► Brasil API, PageSpeed, Places      │
│  TokenService      ────► MySQL (tokens table)               │
│  QueueWorker       ────► Redis/DB Queue                     │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│  MySQL 8.0+ (produção) / SQLite 3 (dev)                     │
│  21 tabelas conforme schema existente                       │
└─────────────────────────────────────────────────────────────┘
```

### 7.2 Laravel vs PHP Modular

**Recomendação: Laravel 11** pelas razões:
- Eloquent ORM (migrations, relationships, query builder)
- Blade templates (similar a JSX, sem curva de aprendizado)
- Artisan CLI (geração de código, migrations, queues)
- Laravel Queue + Horizon (substitui queueService.ts)
- Laravel Sanctum (auth API tokens para AdminPanel)
- Vite integrado (pipeline Tailwind CSS nativo)
- Ecossistema maduro (testes, logs, rate limiting)

### 7.3 Stack Técnica Recomendada

```
PHP 8.2+
Laravel 11
MySQL 8.0 (produção) / SQLite 3.x (testes/dev)
Tailwind CSS v3 (via Vite integrado no Laravel)
Alpine.js v3 (interações UI sem React)
Livewire v3 (opcional: formulários reativos sem JS manual)
Laravel Queue + Horizon (análises assíncronas)
Redis (cache de resultados de IA, session)
Guzzle HTTP (chamadas para APIs de IA e externas)
PHPUnit + Pest (testes)
```

---

## 8. Comparativo Monolito × API Separada

### Opção A: Monolito Laravel (Blade + Server-Rendered)

```
pros:
  ✅ Mais simples de desenvolver e manter
  ✅ SEO nativo (server-rendered HTML)
  ✅ Sem problema de CORS
  ✅ Blade templates reutilizam CSS/design Operon direto
  ✅ Uma única base de código
  ✅ Mais fácil de fazer deploy (Nginx + PHP-FPM)
  ✅ Adequado ao tamanho do projeto

contras:
  ❌ UI menos reativa sem Livewire/Alpine
  ❌ Formulários complexos (SpinHub, LeadFullView) precisam de Livewire ou HTMX
  ❌ Copilot de voz precisa de WebSocket ou SSE separado
```

### Opção B: Laravel API + Frontend Separado (Inertia.js ou SPA leve)

```
pros:
  ✅ Frontend React existente pode ser aproveitado parcialmente
  ✅ Melhor UX para telas complexas
  ✅ Futuro: app mobile usa mesma API

contras:
  ❌ Complexidade: dois projetos para manter
  ❌ CORS, autenticação, tokens de sessão
  ❌ Mais difícil de fazer deploy
  ❌ Contradiz o objetivo "migrar para PHP"
```

### Opção C: Laravel + Livewire (Recomendada)

```
pros:
  ✅ Server-rendered com reatividade sem escrever JavaScript
  ✅ Componentes Livewire equivalem a componentes React (estado no servidor)
  ✅ Forms dinâmicos, busca em tempo real, paginação sem reload
  ✅ Una única base de código PHP
  ✅ Fácil de testar (PHPUnit)
  ✅ VaultView (filtros, busca, Kanban) implementável sem JS

contras:
  ❌ Livewire tem curva de aprendizado
  ❌ Performance: cada interação = request HTTP (OK para escala do projeto)
  ❌ Voice Copilot ainda precisa de JS separado
```

### ⭐ Decisão Recomendada

**Laravel 11 + Blade + Livewire v3 + Alpine.js** para o monolito principal.
Copilot de voz → componente Alpine.js com Web Speech API (client-only).

---

## 9. Mapeamento React → PHP por Módulo

### 9.1 VaultView (Lista de Leads)

| React | PHP/Blade |
|-------|-----------|
| `useState(leads)` | Controller → `Lead::query()` paginado |
| `searchTerm` filter | `WHERE name LIKE ? OR segment LIKE ?` |
| `PipelineStatus` filter | `WHERE pipeline_status = ?` |
| Kanban drag-drop | Livewire + Sortable.js ou Alpine.js |
| Grid view | Blade partial `@include('leads.grid')` |
| Lista view | Blade partial `@include('leads.list')` |
| `updateLead()` | `PATCH /leads/{id}` → `LeadController@update` |
| `deleteLead()` | `DELETE /leads/{id}` → `LeadController@destroy` |
| `addLeads()` | `POST /leads/import` → `LeadImportController` |

### 9.2 LeadFullView (Detalhe do Lead)

| React | PHP/Blade |
|-------|-----------|
| 8 cards de análise | 8 Blade sections ou Livewire tabs |
| `analyzeLead()` | `POST /leads/{id}/analyze` → dispatch job |
| `deepAnalyzeLead()` | `POST /leads/{id}/deep-analyze` → dispatch job |
| `updateLead()` async | `PATCH /leads/{id}` via Livewire |
| Score bar | Calculado no controller, renderizado no Blade |
| Histórico | `lead_history` table → timeline component |
| Observações | `lead_observations` table → form inline |

### 9.3 Dashboard

| React | PHP/Blade |
|-------|-----------|
| Recharts gráficos | Chart.js (CDN) ou Apex Charts |
| Métricas | Controller calcula via SQL aggregates |
| InsightCards | `insight_cards` table → Blade loop |
| Filtro por período | Query params `?from=&to=` |

### 9.4 SpinHubView

| React | PHP/Blade |
|-------|-----------|
| 4 dimensões SPIN | Livewire component `SpinComponent` |
| Edição inline | Livewire `wire:model` + textarea |
| Completeness % | Calculado no Model ou Job |
| AI questions | `POST /leads/{id}/spin/generate` → job |

### 9.5 LyxosCopilotView

| React | PHP/Blade |
|-------|-----------|
| Chat UI | Blade + Alpine.js + `fetch()` |
| Tool calls | PHP `AiToolDispatcher` (verifica intenção, executa) |
| Voice input | Web Speech API (JavaScript, client-only) |
| Context inject | `SmartContextService::buildForCopilot($leads)` |
| Streaming resposta | Server-Sent Events (PHP `ob_flush()` + `flush()`) |

### 9.6 ImportView

| React | PHP/Blade |
|-------|-----------|
| File upload | `<input type="file">` + Livewire file upload |
| CSV parser | PHP `fgetcsv()` + `str_getcsv()` |
| Preview | Livewire renderiza preview da tabela |
| Importação | `POST /leads/import` → `ImportLeadsJob` |
| Progresso | Livewire polling ou SSE |

### 9.7 AgendaView

| React | PHP/Blade |
|-------|-----------|
| Calendário | FullCalendar.js (CDN) |
| Tasks CRUD | `TaskController` → `tasks` table |
| Follow-ups | `followup_sequences` + `followup_steps` tables |

### 9.8 AdminPanel

| React | PHP/Blade |
|-------|-----------|
| PIN gate | Session-based auth (`/admin/auth`) |
| Aba IA | Form salva em `agency_settings` table |
| Aba Tokens | Dashboard via `token_entries` table |
| Aba Módulos | Toggle skills em `agency_settings.enabled_skills` JSON |
| Aba Monitoramento | `intelligence_log` table com paginação |

---

## 10. Estrutura de Pastas PHP

### 10.1 Laravel (Recomendado)

```
operon-crm/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── LeadController.php
│   │   │   ├── LeadAnalysisController.php
│   │   │   ├── LeadImportController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── SpinController.php
│   │   │   ├── CopilotController.php
│   │   │   ├── AgendaController.php
│   │   │   ├── TenantController.php
│   │   │   ├── AdminController.php
│   │   │   └── Settings/
│   │   │       ├── AgencySettingsController.php
│   │   │       └── TokenSettingsController.php
│   │   ├── Livewire/
│   │   │   ├── LeadVault.php        ← substitui VaultView.tsx
│   │   │   ├── LeadFullView.php     ← substitui LeadFullView.tsx
│   │   │   ├── SpinHub.php         ← substitui SpinHubView.tsx
│   │   │   ├── ImportLeads.php     ← substitui ImportView.tsx
│   │   │   ├── AgendaCalendar.php  ← substitui AgendaView.tsx
│   │   │   └── AdminPanel.php      ← substitui AdminPanel.tsx
│   │   └── Middleware/
│   │       ├── TenantMiddleware.php
│   │       └── AdminPinMiddleware.php
│   ├── Models/
│   │   ├── Lead.php
│   │   ├── Tenant.php
│   │   ├── Task.php
│   │   ├── FollowUpSequence.php
│   │   ├── FollowUpStep.php
│   │   ├── InsightCard.php
│   │   ├── TokenEntry.php
│   │   └── IntelligenceLog.php
│   ├── Services/
│   │   ├── AiProviderService.php       ← aiProviderService.ts
│   │   ├── OperonAnalysisService.php   ← operonService.ts
│   │   ├── GeminiService.php           ← geminiService.ts
│   │   ├── TokenService.php            ← tokenService.ts
│   │   ├── SmartContextService.php     ← smartContextService.ts
│   │   ├── EnrichmentService.php       ← enrichmentService.ts
│   │   ├── ScoreService.php            ← scoreService.ts
│   │   ├── ImportService.php           ← importService.ts
│   │   ├── CadenceService.php          ← cadenceService.ts
│   │   └── KnowledgeService.php        ← knowledgeService.ts
│   ├── Jobs/
│   │   ├── AnalyzeLeadJob.php
│   │   ├── DeepAnalyzeLeadJob.php
│   │   ├── GenerateSpinQuestionsJob.php
│   │   ├── EnrichLeadJob.php
│   │   └── ImportLeadsJob.php
│   ├── DTOs/
│   │   ├── LeadDTO.php
│   │   ├── AnalysisResultDTO.php
│   │   ├── TokenQuotaDTO.php
│   │   └── IntelligenceRequestDTO.php
│   └── Console/
│       └── Commands/
│           └── ResetDailyTokensCommand.php  ← reset meia-noite
├── database/
│   ├── migrations/
│   │   ├── 2026_03_01_000001_create_leads_table.php
│   │   ├── 2026_03_01_000002_create_tenants_table.php
│   │   ├── 2026_03_01_000003_create_lead_analyses_table.php
│   │   ├── 2026_03_01_000004_create_lead_deep_analyses_table.php
│   │   ├── 2026_03_01_000005_create_lead_operon_analyses_table.php
│   │   ├── 2026_03_01_000006_create_lead_spin_framework_table.php
│   │   ├── 2026_03_01_000007_create_lead_observations_table.php
│   │   ├── 2026_03_01_000008_create_lead_transcriptions_table.php
│   │   ├── 2026_03_01_000009_create_lead_history_table.php
│   │   ├── 2026_03_01_000010_create_tasks_table.php
│   │   ├── 2026_03_01_000011_create_followup_sequences_table.php
│   │   ├── 2026_03_01_000012_create_followup_steps_table.php
│   │   ├── 2026_03_01_000013_create_token_entries_table.php
│   │   ├── 2026_03_01_000014_create_intelligence_logs_table.php
│   │   ├── 2026_03_01_000015_create_insight_cards_table.php
│   │   ├── 2026_03_01_000016_create_agency_settings_table.php
│   │   └── 2026_03_01_000017_create_audit_logs_table.php
│   └── seeders/
│       ├── DefaultTenantSeeder.php
│       └── DemoLeadsSeeder.php
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   │   ├── app.blade.php       ← estrutura principal
│   │   │   └── admin.blade.php
│   │   ├── leads/
│   │   │   ├── index.blade.php     ← VaultView
│   │   │   ├── show.blade.php      ← LeadFullView
│   │   │   └── import.blade.php
│   │   ├── dashboard/
│   │   │   └── index.blade.php
│   │   ├── spin/
│   │   │   └── index.blade.php
│   │   ├── copilot/
│   │   │   └── index.blade.php
│   │   ├── agenda/
│   │   │   └── index.blade.php
│   │   ├── settings/
│   │   │   ├── app.blade.php
│   │   │   └── agency-brain.blade.php
│   │   └── admin/
│   │       ├── auth.blade.php      ← PIN gate
│   │       └── panel.blade.php
│   ├── css/
│   │   └── app.css                 ← Tailwind directives
│   └── js/
│       └── app.js                  ← Alpine.js bootstrap
├── routes/
│   ├── web.php
│   └── api.php                     ← apenas para Copilot SSE e análise async
├── config/
│   └── operon.php                  ← TOKEN_WEIGHTS, TIER_QUOTAS, etc.
└── tests/
    ├── Feature/
    │   ├── LeadCRUDTest.php
    │   ├── AnalysisFlowTest.php
    │   └── TokenEconomyTest.php
    └── Unit/
        ├── ScoreServiceTest.php
        └── ImportServiceTest.php
```

---

## 11. Banco de Dados

### 11.1 Tabelas Existentes (schema.sql → adaptar para Laravel migrations)

```sql
-- Core
companies           → leads (renomear; CRM trata como leads)
leads               → já existe
lead_analysis       → análise básica de IA (4 campos texto)
lead_deep_analysis  → análise profunda (6 seções JSON)
lead_operon_analysis → 4 dimensões Operon (JSON por dimensão)
lead_spin_framework → S/P/I/N por lead
lead_human_context  → notas humanas, tomador de decisão, objeções

-- Arrays de lead
lead_observations   → observações livres
lead_transcriptions → transcrições de chamadas
lead_history        → histórico de eventos
lead_score_adjustments → ajustes manuais de score
lead_audio_assets   → assets de áudio gerados

-- Multi-tenant + Configurações
tenants             → operações ativas
agency_settings     → config da agência (JSON ou colunas)
insight_cards       → insights estratégicos do Dashboard

-- Agenda
agenda_tasks        → tarefas por tenant + lead
followup_sequences  → sequências de follow-up
followup_steps      → passos individuais de cada sequência

-- Token Economy + Monitoring
token_entries       → débitos de tokens por operação
intelligence_logs   → log interno (modelo real, latência, custo)
audit_logs          → auditoria de ações admin
```

### 11.2 Campos Críticos de Lead

```sql
CREATE TABLE leads (
    id              CHAR(36) PRIMARY KEY,          -- UUID
    tenant_id       CHAR(36) NOT NULL,
    name            VARCHAR(255) NOT NULL,
    website         VARCHAR(500),
    segment         VARCHAR(255),
    cnpj            VARCHAR(18),                   -- formato 00.000.000/0001-00
    email           VARCHAR(255),
    phone           VARCHAR(30),
    address         TEXT,                          -- JSON: street, city, state, zip
    company_size    ENUM('MICRO','SMALL','MEDIUM','LARGE','ENTERPRISE'),
    pipeline_status ENUM('NEW','CONTACTED','ANALYZED','PROPOSAL',
                         'NEGOTIATION','CLOSED','LOST') DEFAULT 'NEW',
    score           TINYINT UNSIGNED DEFAULT 0,   -- 0-100
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_status (tenant_id, pipeline_status),
    INDEX idx_tenant_score (tenant_id, score DESC)
);
```

### 11.3 Token Economy no MySQL

```sql
CREATE TABLE token_entries (
    id              CHAR(36) PRIMARY KEY,
    tenant_id       CHAR(36) NOT NULL,
    closer_id       CHAR(36),                      -- futuro: auth por closer
    lead_id         CHAR(36),
    operation       VARCHAR(100) NOT NULL,
    tokens_consumed SMALLINT UNSIGNED NOT NULL,
    input_weight    TINYINT UNSIGNED,
    output_weight   TINYINT UNSIGNED,
    model_used      VARCHAR(100),                  -- real; visível só para admin
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant_date (tenant_id, created_at)
);

CREATE TABLE token_quotas (
    tenant_id       CHAR(36) PRIMARY KEY,
    tier            ENUM('starter','pro','elite') DEFAULT 'starter',
    daily_limit     SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    used_today      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    reset_at        TIMESTAMP NOT NULL,           -- próximo reset
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## 12. Compatibilidade SQLite × MySQL

### 12.1 Diferenças que afetam as migrations

| Aspecto | MySQL | SQLite | Solução Laravel |
|---------|-------|--------|-----------------|
| UUID | `CHAR(36)` | `TEXT` | `$table->uuid()` |
| ENUM | Nativo | Não existe | `$table->string()->in([...])` |
| BOOLEAN | `TINYINT(1)` | `INTEGER 0/1` | `$table->boolean()` |
| JSON | Tipo nativo | `TEXT` | `$table->json()` |
| AUTO_INCREMENT | `AUTO_INCREMENT` | `AUTOINCREMENT` | `$table->id()` |
| TIMESTAMP | Nativo | `TEXT` | `$table->timestamps()` |
| ON UPDATE | Suportado | Não suportado | `updated_at` via Eloquent |
| Foreign keys | Nativo | Pragma necessário | `PRAGMA foreign_keys=ON` |
| Full-text search | `FULLTEXT` | FTS5 extension | Usar `LIKE` simples |
| Transactions | Nativo | Nativo | PDO `beginTransaction()` |

### 12.2 Regras de compatibilidade no código PHP

```php
// ✅ Use Eloquent em vez de SQL raw quando possível
$leads = Lead::where('tenant_id', $tenantId)
             ->where('pipeline_status', 'ANALYZED')
             ->orderByDesc('score')
             ->get();

// ✅ Para ENUMs, use constantes em vez de DB ENUM (compatível com ambos)
// No Model:
const PIPELINE_STATUSES = ['NEW','CONTACTED','ANALYZED',...];

// ✅ UUID: use Str::uuid() do Laravel
$lead->id = Str::uuid()->toString();

// ✅ JSON: Eloquent casts (funciona em ambos)
protected $casts = [
    'address' => 'array',
    'enabled_skills' => 'array',
];

// ⚠️ EVITAR: SQL raw com sintaxe MySQL-específica
// ❌ DB::statement("ALTER TABLE leads MODIFY COLUMN ...");
// ✅ Use migrations para schema changes
```

### 12.3 Configuração por ambiente

```php
// config/database.php (simplificado)
'connections' => [
    'mysql' => [...], // produção
    'sqlite' => [
        'driver' => 'sqlite',
        'database' => database_path('database.sqlite'),
        'foreign_key_constraints' => true, // CRÍTICO: habilitar FK no SQLite
    ],
],
```

```ini
# .env (produção)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=operon_crm
DB_USERNAME=operon_user
DB_PASSWORD=...

# .env.testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

---

## 13. Rotas e Módulos

### 13.1 Web Routes (routes/web.php)

```php
// Dashboard
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Leads (VaultView)
Route::prefix('leads')->name('leads.')->group(function () {
    Route::get('/', [LeadController::class, 'index'])->name('index');
    Route::get('/{lead}', [LeadController::class, 'show'])->name('show');
    Route::post('/', [LeadController::class, 'store'])->name('store');
    Route::patch('/{lead}', [LeadController::class, 'update'])->name('update');
    Route::delete('/{lead}', [LeadController::class, 'destroy'])->name('destroy');
    Route::get('/import', [LeadImportController::class, 'index'])->name('import');
    Route::post('/import', [LeadImportController::class, 'store'])->name('import.store');
});

// Análise
Route::prefix('leads/{lead}/analysis')->group(function () {
    Route::post('/operon', [LeadAnalysisController::class, 'operon']);
    Route::post('/deep', [LeadAnalysisController::class, 'deep']);
    Route::post('/spin', [LeadAnalysisController::class, 'spin']);
    Route::post('/enrich', [LeadAnalysisController::class, 'enrich']);
});

// SPIN Hub
Route::get('/spin-hub', [SpinController::class, 'index'])->name('spin.index');
Route::patch('/spin-hub/{lead}', [SpinController::class, 'update'])->name('spin.update');

// Copilot
Route::get('/copilot', [CopilotController::class, 'index'])->name('copilot.index');
Route::post('/copilot/message', [CopilotController::class, 'message'])->name('copilot.message');

// Agenda
Route::resource('agenda', AgendaController::class)->only(['index', 'store', 'update', 'destroy']);

// Configurações
Route::prefix('settings')->name('settings.')->group(function () {
    Route::get('/', [AppSettingsController::class, 'index'])->name('index');
    Route::post('/', [AppSettingsController::class, 'update'])->name('update');
    Route::get('/agency-brain', [AgencyBrainController::class, 'index'])->name('agency-brain');
    Route::post('/agency-brain', [AgencyBrainController::class, 'update'])->name('agency-brain.update');
    Route::get('/account-cost', [AccountCostController::class, 'index'])->name('account-cost');
});

// Tenants
Route::resource('tenants', TenantController::class)->except(['show', 'edit']);

// Admin (PIN protegido)
Route::prefix('admin')->name('admin.')->middleware('admin.pin')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('index');
    Route::post('/ai-settings', [AdminController::class, 'updateAI'])->name('ai-settings');
    Route::post('/tokens/reset', [AdminController::class, 'resetTokens'])->name('tokens.reset');
    Route::post('/log/clear', [AdminController::class, 'clearLog'])->name('log.clear');
});
Route::get('/admin/auth', [AdminController::class, 'auth'])->name('admin.auth');
Route::post('/admin/auth', [AdminController::class, 'verifyPin'])->name('admin.auth.verify');
```

### 13.2 API Routes (routes/api.php)

```php
// Copilot streaming (SSE)
Route::get('/copilot/stream', [CopilotController::class, 'stream']);

// Análise async status
Route::get('/analysis/status/{jobId}', [LeadAnalysisController::class, 'status']);

// Webhooks (futuro)
// Route::post('/webhooks/operon', [WebhookController::class, 'handle']);
```

### 13.3 Console Commands

```php
// Agendador de comandos (app/Console/Kernel.php ou bootstrap/app.php)
Schedule::command('operon:reset-daily-tokens')->dailyAt('00:00')->timezone('America/Sao_Paulo');
Schedule::command('operon:clean-intelligence-log')->weekly();
```

---

## 14. Fases de Migração

### Fase 0 — Setup (1 semana)
- [ ] Criar projeto Laravel 11
- [ ] Configurar Vite + Tailwind CSS
- [ ] Instalar Alpine.js + Livewire v3
- [ ] Criar `.env` dev (SQLite) + `.env.production` (MySQL)
- [ ] Configurar migrations iniciais (5 tabelas core)
- [ ] Seeder do tenant default + dados demo
- [ ] CI/CD básico (GitHub Actions: lint + testes)

### Fase 1 — Core CRM (3 semanas)
- [ ] Módulo de leads (VaultView): Livewire component
  - [ ] Lista com filtros, busca, paginação
  - [ ] Kanban (Alpine.js + Sortable.js)
  - [ ] Grid e List view
- [ ] LeadFullView: show.blade.php + Livewire tabs
- [ ] Importação CSV/JSON
- [ ] Multi-tenant (TenantMiddleware)
- [ ] CRUD básico funcionando end-to-end

### Fase 2 — Análise de IA (2 semanas)
- [ ] `AiProviderService` (Gemini + OpenAI + Grok via Guzzle)
- [ ] `OperonAnalysisService` (4 dimensões)
- [ ] `AnalyzeLeadJob` (Laravel Queue)
- [ ] Token Economy (`TokenService`, `token_quotas` table)
- [ ] Badge "Operon Intelligence" (sem expor modelo real)
- [ ] AccountCostView (custos + tokens)
- [ ] Polling de status de análise

### Fase 3 — Enriquecimento + Score (1 semana)
- [ ] `EnrichmentService` (Brasil API CNPJ, PageSpeed, Places)
- [ ] `ScoreService` (algoritmo replicado do TypeScript)
- [ ] Enriquecimento async (job separado)
- [ ] ScoreAdjustmentModal → Livewire modal

### Fase 4 — SPIN Framework (1 semana)
- [ ] `SpinController` + Livewire `SpinHub`
- [ ] Edição inline (wire:model)
- [ ] `GenerateSpinQuestionsJob`
- [ ] Completeness % em tempo real

### Fase 5 — Dashboard (1 semana)
- [ ] Métricas SQL (COUNT, SUM, AVG por status)
- [ ] Chart.js para gráficos (substituindo Recharts)
- [ ] InsightCards (tabela + geração via IA)
- [ ] Filtros de período

### Fase 6 — Follow-up + Agenda (1 semana)
- [ ] `TaskController` + `AgendaCalendar` Livewire
- [ ] FullCalendar.js integrado
- [ ] `CadenceService` PHP
- [ ] FollowUpModal → Blade modal

### Fase 7 — AdminPanel (1 semana)
- [ ] PIN auth (session-based)
- [ ] 4 abas: IA, Tokens, Módulos, Monitoramento
- [ ] Salvar settings em `agency_settings` table
- [ ] `intelligence_logs` tabela + visualização

### Fase 8 — Copilot (2 semanas)
- [ ] `CopilotController` com SSE
- [ ] Tool dispatcher PHP
- [ ] Chat UI Alpine.js
- [ ] Voice (Web Speech API, client-only)
- [ ] Context injection (SmartContextService PHP)

### Fase 9 — Migração de Dados (1 semana)
- [ ] Script de exportação IndexedDB → JSON
- [ ] Script PHP de importação JSON → MySQL
- [ ] Validação e reconciliação de dados
- [ ] Checklist de paridade (ver MIGRATION_VALIDATION_PROTOCOL.md)

### Fase 10 — Produção (1 semana)
- [ ] Deploy em servidor PHP-FPM + Nginx
- [ ] Configurar MySQL 8.0
- [ ] SSL + domínio
- [ ] Monitoramento (Laravel Telescope)
- [ ] Backup automático

**Total estimado: ~14 semanas (3,5 meses)**

---

## 15. Checklist de Reconstrução

### 15.1 Por Módulo

#### Leads/VaultView
- [ ] Criar migration `leads` com todos os campos do `types.ts`
- [ ] Model `Lead.php` com casts, relationships, scopes
- [ ] `LeadController` (index, show, store, update, destroy)
- [ ] Livewire `LeadVault`: filtros reativos, busca, paginação
- [ ] View `leads/index.blade.php` (Kanban + Grid + List)
- [ ] View `leads/show.blade.php` (LeadFullView)
- [ ] Seleção múltipla para bulk actions

#### Análise Operon
- [ ] Migration `lead_operon_analyses`
- [ ] Service `OperonAnalysisService.php` com 4 métodos
- [ ] `AnalyzeLeadJob.php` dispatchable
- [ ] Debitar tokens antes de executar
- [ ] Salvar resultado no banco após conclusão
- [ ] Toast de sucesso/erro via Livewire

#### Token Economy
- [ ] Migration `token_quotas` + `token_entries`
- [ ] `TokenService.php` (consumeTokens, getRemainingTokens, getHistory)
- [ ] Command `operon:reset-daily-tokens`
- [ ] Middleware `CheckTokenQuota`
- [ ] UI: barra de progresso em AccountCostView

#### Multi-tenant
- [ ] Migration `tenants`
- [ ] `TenantMiddleware` (seta tenant ativo na sessão)
- [ ] Scope global no Model Lead: `Lead::where('tenant_id', session('tenant_id'))`
- [ ] `TenantController` (CRUD)
- [ ] TenantSelector (Blade dropdown com Alpine.js)

### 15.2 Infraestrutura

- [ ] PHP 8.2+ configurado
- [ ] Extensões: `pdo_mysql`, `pdo_sqlite`, `mbstring`, `json`, `curl`
- [ ] Composer instalado
- [ ] `VITE_GEMINI_API_KEY` em `.env` (não expor no client-side!)
- [ ] Queue driver configurado (database para dev, Redis para prod)
- [ ] Cron configurado para `php artisan schedule:run`
- [ ] Logging configurado (Laravel Telescope ou Monolog)

---

## 16. Riscos e Mitigações

### 16.1 Riscos Técnicos

| Risco | Probabilidade | Impacto | Mitigação |
|-------|--------------|---------|-----------|
| Streaming de IA (Copilot) difícil de implementar em PHP | Alta | Alto | SSE com `ob_flush()`; ou usar Reverb (Laravel WebSocket) |
| Livewire tem latência vs React | Média | Médio | Otimizar queries; usar `wire:loading` para feedback |
| Chart.js vs Recharts: diferenças de API | Baixa | Baixo | Mapear componentes um a um antes de migrar |
| Voice API (Web Speech) não funciona em todos os browsers | Média | Médio | Fallback para input de texto; polyfill |
| SQLite → MySQL: diferenças em ENUM/JSON | Alta | Alto | Usar Eloquent casts; evitar SQL raw |
| Migração de dados IndexedDB → MySQL pode perder dados | Média | Alto | Exportar como JSON primeiro; validar antes de importar |
| Prompts de IA podem ter comportamento diferente | Baixa | Médio | Testar todos os prompts com novos clients HTTP |
| Rate limiting das APIs (Gemini, OpenAI) | Média | Alto | Laravel Throttle + retry com backoff exponencial |

### 16.2 Riscos de Negócio

| Risco | Mitigação |
|-------|-----------|
| Downtime durante migração | Manter React em produção durante toda a fase de desenvolvimento PHP |
| Perda de features durante a migração | MIGRATION_VALIDATION_PROTOCOL.md como checklist de paridade |
| Resistência dos usuários a mudança de UX | Manter o mesmo design Tailwind; mesma paleta de cores |
| Custos de IA maiores no server-side | Rate limiting + caching de resultados no Redis |

---

## 17. Recomendação Final

### ⭐ Arquitetura Recomendada

**Laravel 11 + Blade + Livewire v3 + Alpine.js + MySQL**

**Justificativa:**
1. **Laravel** é o framework PHP mais maduro e com melhor ecossistema para CRM (Eloquent, Queue, Scheduling)
2. **Livewire** resolve o problema de reatividade sem manter dois stacks (evita Vue/React no backend)
3. **Alpine.js** cobre interações simples (dropdowns, modais, tabs) que não precisam de server-round-trip
4. **MySQL** como fonte de verdade garante persistência, backup, multi-usuário real
5. **SQLite** em dev/testes mantém velocidade de desenvolvimento e CI/CD sem dependência externa
6. O design **Tailwind CSS** existente é 100% reutilizável — zero redesign

### Ordem de Prioridade

1. 🔴 **CRUD de leads + multi-tenant** (fundação, tudo depende disso)
2. 🔴 **Análise Operon 4D + Token Economy** (diferencial central do produto)
3. 🟡 **Dashboard + Import** (usabilidade diária)
4. 🟡 **SPIN Framework + Enriquecimento** (complementar)
5. 🟢 **Copilot + AudioGenerator** (avançado, mais complexo)
6. 🟢 **AdminPanel** (operacional)

### Não fazer

- ❌ **Não migrar tudo de uma vez** — fases incrementais protegem o produto
- ❌ **Não usar SQL raw** onde Eloquent resolve — garante compatibilidade SQLite/MySQL
- ❌ **Não expor chaves de API no HTML/JS** — sempre backend-side em produção
- ❌ **Não tentar replicar exatamente o React** — abraçar o paradigma server-rendered

### Tempo Total Estimado

| Fase | Duração |
|------|---------|
| Setup | 1 semana |
| Core CRM | 3 semanas |
| Análise IA + Tokens | 2 semanas |
| Enriquecimento + Score | 1 semana |
| SPIN + Dashboard | 2 semanas |
| Follow-up + Agenda | 1 semana |
| AdminPanel | 1 semana |
| Copilot | 2 semanas |
| Migração de dados | 1 semana |
| Produção | 1 semana |
| **Total** | **~15 semanas** |

---

> **Próximo passo:** Revisar e aprovar este blueprint. Em seguida, consultar `MIGRATION_VALIDATION_PROTOCOL.md` para os critérios de paridade entre React e PHP antes de iniciar qualquer desenvolvimento.
