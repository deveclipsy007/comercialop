# Migration Validation Protocol — Operon Intelligence CRM

> **Versão:** 1.0 | **Data:** 2026-03-10 | **Status:** Documento Vivo — atualizar a cada fase
>
> Este protocolo define os critérios de paridade entre a versão React/TypeScript e a versão PHP/Laravel.
> **Uma funcionalidade só é considerada "migrada" quando TODOS os itens do checklist correspondente estão marcados.**

---

## Índice

1. [Objetivo do Protocolo](#1-objetivo-do-protocolo)
2. [Regras de Validação](#2-regras-de-validação)
3. [Matriz de Paridade React → PHP](#3-matriz-de-paridade-react--php)
4. [Checklists por Módulo](#4-checklists-por-módulo)
5. [Checklist de Banco de Dados](#5-checklist-de-banco-de-dados)
6. [Checklist SQLite × MySQL](#6-checklist-sqlite--mysql)
7. [Checklist Auth e Segurança](#7-checklist-auth-e-segurança)
8. [Checklist UX e Navegação](#8-checklist-ux-e-navegação)
9. [Checklist de Qualidade de Código](#9-checklist-de-qualidade-de-código)
10. [Casos de Teste Mínimos](#10-casos-de-teste-mínimos)
11. [Critérios de Aceite por Fase](#11-critérios-de-aceite-por-fase)
12. [Registro de Pendências](#12-registro-de-pendências)

---

## 1. Objetivo do Protocolo

Este protocolo garante que a migração React → PHP:

1. **Não perde nenhuma funcionalidade** do sistema original
2. **Mantém paridade de regras de negócio** (scores, tokens, análises)
3. **Preserva a experiência do usuário** (design, fluxos, feedbacks)
4. **Não introduz regressões** (bugs novos causados pela migração)
5. **Valida compatibilidade** SQLite (dev) ↔ MySQL (produção)

### Como Usar Este Documento

- Cada checklist tem colunas: `[ ] Item | Responsável | Versão testada | Status`
- Use `✅` para aprovado, `❌` para reprovado, `⚠️` para aprovado com ressalvas
- Registrar a data de validação em cada item
- Itens `❌` bloqueiam o avanço para a próxima fase

---

## 2. Regras de Validação

### 2.1 Regra de Ouro — Comparação Side-by-Side

Para cada funcionalidade migrada, executar o mesmo fluxo em:
- **React** (localhost:5173 ou build estático)
- **PHP** (localhost:8000 ou ambiente de homologação)

O resultado deve ser **funcionalmente idêntico** (não necessariamente visual pixel-perfect).

### 2.2 Critério de Paridade Funcional

```
PASS = a funcionalidade produz o mesmo resultado para o mesmo input
FAIL = qualquer divergência no resultado, mesmo que parcial
WARN = resultado correto mas com diferença de UX aceitável (registrar)
```

### 2.3 Tolerâncias Aceitas

| Aspecto | Tolerância |
|---------|-----------|
| Tempo de resposta | PHP pode ser +200ms vs React (IndexedDB é in-memory) |
| Layout visual | Diferenças menores de espaçamento (±4px) são aceitadas |
| Mensagens de IA | Conteúdo pode variar (não-determinístico) mas estrutura deve ser igual |
| Animações CSS | Animações podem ser simplificadas em Blade sem bloqueio |
| Toast timing | ±500ms aceitável |

### 2.4 O que NUNCA é tolerado

- ❌ Perda de dados de lead durante qualquer operação
- ❌ Token debitado sem análise completada
- ❌ Token não debitado quando análise for concluída
- ❌ Tenant A acessando dados do Tenant B
- ❌ AdminPanel acessível sem PIN
- ❌ Chaves de API expostas no HTML/JS público
- ❌ Score calculado incorretamente
- ❌ Importação CSV com perda ou corrupção de dados

---

## 3. Matriz de Paridade React → PHP

### 3.1 Status de Módulos

Use esta tabela para tracking geral do projeto:

| Módulo React | Arquivo(s) React | Equivalente PHP | Status |
|--------------|-----------------|-----------------|--------|
| VaultView | `VaultView.tsx` | `LeadVault.php` (Livewire) | ⬜ Pendente |
| LeadFullView | `LeadFullView.tsx` | `leads/show.blade.php` | ⬜ Pendente |
| Dashboard | `Dashboard.tsx` | `dashboard/index.blade.php` | ⬜ Pendente |
| SpinHubView | `SpinHubView.tsx` | `SpinHub.php` (Livewire) | ⬜ Pendente |
| LyxosCopilotView | `LyxosCopilotView.tsx` | `copilot/index.blade.php` | ⬜ Pendente |
| ImportView | `ImportView.tsx` | `ImportLeads.php` (Livewire) | ⬜ Pendente |
| AgendaView | `AgendaView.tsx` | `AgendaCalendar.php` (Livewire) | ⬜ Pendente |
| AccountCostView | `AccountCostView.tsx` | `settings/account-cost.blade.php` | ⬜ Pendente |
| AppSettingsView | `AppSettingsView.tsx` | `settings/app.blade.php` | ⬜ Pendente |
| AdminPanel | `AdminPanel.tsx` | `admin/panel.blade.php` | ⬜ Pendente |
| AgencyBrainModal | `AgencyBrainModal.tsx` | `settings/agency-brain.blade.php` | ⬜ Pendente |
| TenantSelector | `TenantSelector.tsx` | Blade dropdown + Alpine.js | ⬜ Pendente |
| Análise Operon | `operonService.ts` | `OperonAnalysisService.php` | ⬜ Pendente |
| Token Economy | `tokenService.ts` | `TokenService.php` | ⬜ Pendente |
| Roteamento IA | `aiProviderService.ts` | `AiProviderService.php` | ⬜ Pendente |
| Enriquecimento | `enrichmentService.ts` | `EnrichmentService.php` | ⬜ Pendente |
| Score | `scoreService.ts` | `ScoreService.php` | ⬜ Pendente |
| Importação | `importService.ts` | `ImportService.php` | ⬜ Pendente |
| Multi-tenant | `tenantService.ts` | `TenantMiddleware.php` | ⬜ Pendente |

**Legenda:** ⬜ Pendente | 🔄 Em progresso | ✅ Validado | ❌ Reprovado | ⚠️ Com ressalvas

---

## 4. Checklists por Módulo

### 4.1 Módulo: VaultView (Lista de Leads)

#### 4.1.1 CRUD Básico
- [ ] Listar leads do tenant ativo (paginado, 25 por página)
- [ ] Busca por nome, segmento, e-mail funciona em tempo real
- [ ] Filtro por `PipelineStatus` funciona (dropdown)
- [ ] Filtro por `companySize` funciona
- [ ] Ordenação por score (desc) funciona
- [ ] View Kanban: leads agrupados por status, colunas corretas
- [ ] View Kanban: arrastar lead de coluna → atualiza `pipeline_status` no banco
- [ ] View Grid: cards de lead com score, nome, segmento
- [ ] View Lista: tabela com colunas configuráveis
- [ ] Toggle de view (Kanban/Grid/Lista) persiste na sessão
- [ ] Criar novo lead via modal/form funciona
- [ ] Editar lead via inline ou modal funciona
- [ ] Excluir lead: confirmação → remove do banco → desaparece da lista
- [ ] Lead excluído não aparece em nenhuma outra view

#### 4.1.2 Seleção em Lote
- [ ] Checkbox individual por lead funciona
- [ ] "Selecionar todos" (filtrados) funciona
- [ ] Counter de selecionados atualiza em tempo real
- [ ] "Analisar selecionados" dispara jobs para cada lead
- [ ] "Excluir selecionados" com confirmação remove todos
- [ ] Deselect all limpa seleção

#### 4.1.3 Importação
- [ ] Upload CSV aceita arquivo UTF-8 (com e sem BOM)
- [ ] Upload JSON aceita array de leads
- [ ] Preview mostra primeiras 5 linhas antes de confirmar
- [ ] Campos desconhecidos são ignorados sem erro
- [ ] Campos mapeados corretamente (ver seção 4.8)
- [ ] CNPJ duplicado: comportamento definido (skip/merge) e consistente
- [ ] Leads importados aparecem na lista imediatamente
- [ ] Toast de sucesso com contagem de leads importados

**Validação:** `[ ] PASS` | `[ ] FAIL` | Data: _______ | Testador: _______

---

### 4.2 Módulo: LeadFullView (Detalhe do Lead)

#### 4.2.1 Dados Básicos
- [ ] Todos os campos do lead são exibidos corretamente
- [ ] Edição inline de campos básicos (nome, segmento, e-mail) funciona
- [ ] CNPJ formatado corretamente (00.000.000/0001-00)
- [ ] Website exibido como link clicável
- [ ] Score exibido como barra de progresso + número

#### 4.2.2 Análise Operon (4 dimensões)
- [ ] Card "Diagnóstico de Perda" exibe análise quando disponível
- [ ] Card "Potencial Comercial" exibe análise quando disponível
- [ ] Card "Autoridade Local" exibe análise quando disponível
- [ ] Card "Script de Abordagem" exibe análise quando disponível
- [ ] Botão "Analisar" dispara job → spinner durante processamento
- [ ] Toast de sucesso quando análise concluída
- [ ] Toast de erro quando análise falha (saldo insuficiente, erro de API)
- [ ] Status `generatedAt` exibido em cada card
- [ ] Regenerar análise individual funciona

#### 4.2.3 SPIN Framework (integrado ao lead)
- [ ] 4 seções S/P/I/N exibidas
- [ ] Perguntas de IA exibidas por seção
- [ ] Edição inline de perguntas customizadas funciona
- [ ] Completeness % calculado corretamente
- [ ] Gerar perguntas via IA funciona (toast + spinner)

#### 4.2.4 Análise Profunda (DeepAnalysis)
- [ ] Botão "Análise Profunda" disponível
- [ ] Spinner durante processamento (pode levar 30-60s)
- [ ] 6 seções exibidas: executivo, SWOT, proposta, objeções, urgência, próximos passos
- [ ] Custo de tokens debitado corretamente (20 tokens)
- [ ] Toast de sucesso/erro

#### 4.2.5 Histórico e Observações
- [ ] Timeline de histórico exibida em ordem cronológica
- [ ] Observações adicionadas com data/hora
- [ ] Edição/exclusão de observações funciona

#### 4.2.6 Transcrições
- [ ] Upload de transcrição de texto funciona
- [ ] Análise de transcrição via IA funciona
- [ ] Insights de transcrição exibidos

**Validação:** `[ ] PASS` | `[ ] FAIL` | Data: _______ | Testador: _______

---

### 4.3 Módulo: Dashboard

#### 4.3.1 Métricas
- [ ] Total de leads no tenant ativo
- [ ] Leads por status (contagem por `PipelineStatus`)
- [ ] Score médio do pipeline
- [ ] Taxa de conversão (CLOSED / total)
- [ ] Leads analisados vs não-analisados
- [ ] Todas as métricas filtradas pelo tenant ativo

#### 4.3.2 Gráficos
- [ ] Gráfico de funil (por status) renderiza corretamente
- [ ] Gráfico de distribuição de score renderiza
- [ ] Gráfico de leads por segmento renderiza
- [ ] Filtro de período (últimos 7d, 30d, 90d, personalizado) funciona
- [ ] Dados atualizam ao trocar período

#### 4.3.3 Insight Cards
- [ ] InsightCards gerados por IA exibidos
- [ ] Cards com badge de prioridade (HIGH/MEDIUM/LOW)
- [ ] Gerar novos insights funciona (debita tokens)

**Validação:** `[ ] PASS` | `[ ] FAIL` | Data: _______ | Testador: _______

---

### 4.4 Módulo: SPIN Hub

- [ ] Lista de leads com SPIN disponível
- [ ] Selecionar lead carrega as 4 dimensões SPIN
- [ ] View Cards: cada dimensão em card expansível
- [ ] View Tabela: dimensões em colunas, leads em linhas
- [ ] Completeness % exibido por lead e globalmente
- [ ] Edição inline via textarea funciona e salva no banco
- [ ] "Ver mais / Ver menos" para textos longos funciona
- [ ] Gerar perguntas IA por dimensão funciona
- [ ] Toggle de view (cards/tabela) funciona

**Validação:** `[ ] PASS` | `[ ] FAIL` | Data: _______ | Testador: _______

---

### 4.5 Módulo: Lyxos Copilot

- [ ] Interface de chat exibida corretamente
- [ ] Input de texto funciona + enviar mensagem
- [ ] Resposta da IA exibida (stream ou completa)
- [ ] Context do copilot inclui lista completa de leads do tenant
- [ ] Tool: "buscar lead" retorna dados do lead correto
- [ ] Tool: "listar links" retorna websites dos leads
- [ ] Tool: "verificar CNPJ" chama Brasil API corretamente
- [ ] Tool: "criar tarefa" salva na tabela `tasks`
- [ ] Voice input: botão de microfone funciona (Web Speech API)
- [ ] Voice input: transcrição aparece no campo de texto
- [ ] Histórico de conversa mantido durante a sessão
- [ ] Badge "Operon Intelligence" visível (não expõe modelo real)

**Validação:** `[ ] PASS` | `[ ] FAIL` | Data: _______ | Testador: _______

---

### 4.6 Módulo: Agenda

- [ ] Calendário exibido (visão mês/semana/dia)
- [ ] Tarefas do tenant ativo aparecem no calendário
- [ ] Criar tarefa via formulário funciona
- [ ] Editar tarefa funciona
- [ ] Excluir tarefa com confirmação funciona
- [ ] Status de tarefa (TODO/IN_PROGRESS/DONE/CANCELLED) atualizável
- [ ] Prioridade (LOW/MEDIUM/HIGH/URGENT) exibida
- [ ] Filtro por lead funciona
- [ ] Follow-up steps da cadência visíveis no calendário

**Validação:** `[ ] PASS` | `[ ] FAIL` | Data: _______ | Testador: _______

---

### 4.7 Módulo: Token Economy

- [ ] Quota correta por tier (Starter=100, Pro=500, Elite=2000)
- [ ] `used_today` incrementa corretamente após cada análise
- [ ] Reset à meia-noite (America/Sao_Paulo) funciona
- [ ] Análise bloqueada quando `used_today >= daily_limit`
- [ ] Toast "Cota diária esgotada" quando bloqueado
- [ ] AccountCostView mostra barra de progresso (tokens restantes)
- [ ] AccountCostView mostra histórico de token_entries
- [ ] Histórico mostra: data/hora, operação, tokens consumidos
- [ ] Custo real R$ exibido separado dos tokens Operon
- [ ] AdminPanel mostra `model_used` real no monitoramento

#### Pesos de Tokens (validar cada um)
| Operação | Input | Output | Total esperado |
|----------|-------|--------|---------------|
| lead_analysis | 2 | 5 | 7 |
| deep_analysis | 5 | 15 | 20 |
| deal_insights | 1 | 4 | 5 |
| script_variations | 1 | 6 | 7 |
| operon_intelligence | 3 | 8 | 11 |
| audio_strategy | 2 | 10 | 12 |
| spin_questions | 1 | 3 | 4 |
| copilot_message | 1 | 2 | 3 |

- [ ] lead_analysis debita exatamente 7 tokens
- [ ] deep_analysis debita exatamente 20 tokens
- [ ] spin_questions debita exatamente 4 tokens
- [ ] copilot_message debita exatamente 3 tokens
- [ ] operon_intelligence debita exatamente 11 tokens

**Validação:** `[ ] PASS` | `[ ] FAIL` | Data: _______ | Testador: _______

---

### 4.8 Módulo: Importação CSV/JSON

#### Mapeamento de Campos CSV → Lead
| Campo CSV | Campo PHP/DB | Obrigatório |
|-----------|-------------|-------------|
| `name` | `leads.name` | ✅ Sim |
| `segment` | `leads.segment` | Não |
| `cnpj` | `leads.cnpj` | Não |
| `email` | `leads.email` | Não |
| `phone` | `leads.phone` | Não |
| `website` | `leads.website` | Não |
| `address` | `leads.address` | Não |
| `city` | `leads.address->city` | Não |
| `state` | `leads.address->state` | Não |
| `contact_name` | `leads.contact->name` | Não |
| `contact_role` | `leads.contact->role` | Não |
| `observations` | `lead_observations.text` | Não |
| `status` | `leads.pipeline_status` | Não |

- [ ] Todos os campos mapeados corretamente
- [ ] Campo `name` ausente → linha ignorada com aviso
- [ ] Encoding UTF-8 sem BOM funciona
- [ ] Encoding UTF-8 com BOM funciona
- [ ] Arquivo com separador `;` (padrão BR) funciona
- [ ] Arquivo com separador `,` funciona
- [ ] 1.000 leads em um único CSV importados sem timeout
- [ ] Progresso de importação exibido

**Validação:** `[ ] PASS` | `[ ] FAIL` | Data: _______ | Testador: _______

---

### 4.9 Módulo: AdminPanel

- [ ] Atalho `Ctrl+Shift+A` redireciona para `/admin/auth`
- [ ] Modal de PIN exibido antes de acessar o painel
- [ ] PIN correto → acesso permitido
- [ ] PIN incorreto → mensagem de erro, não entra
- [ ] PIN armazenado de forma segura (env, não em DB público)
- [ ] Sessão de admin expira após X minutos de inatividade
- [ ] **Aba IA & Infraestrutura:**
  - [ ] Seletor de provider (Gemini/OpenAI/Grok) funciona
  - [ ] Campo de API key com show/hide funciona
  - [ ] Modelo OpenAI configurável
  - [ ] Routing strategy configurável
  - [ ] Botão "Testar Conexão" retorna latência e status
  - [ ] Configurações salvas persistem após reload
- [ ] **Aba Token Economy:**
  - [ ] Tier selector (Starter/Pro/Elite) funciona
  - [ ] Barra de progresso atual correta
  - [ ] Tabela de token_entries com `model_used` real (admin only)
  - [ ] Botão "Resetar Ledger" zera `used_today`
- [ ] **Aba Módulos:**
  - [ ] 6 toggles de skills disponíveis
  - [ ] Toggle salva em `agency_settings.enabled_skills`
  - [ ] Feature desabilitada → botão correspondente some/fica cinza
- [ ] **Aba Monitoramento:**
  - [ ] Tabela de intelligence_logs com: timestamp, operation, tokens, modelo real, latência
  - [ ] Botão "Limpar Log" funciona
  - [ ] Resumo de custos R$ totais

**Validação:** `[ ] PASS` | `[ ] FAIL` | Data: _______ | Testador: _______

---

### 4.10 Módulo: Multi-Tenant

- [ ] Tenant "default" sempre existe (nunca deletável)
- [ ] Criar nova operação → aparece no dropdown
- [ ] Trocar operação → lista de leads atualizada
- [ ] Leads do Tenant A não aparecem quando Tenant B está ativo
- [ ] Deletar operação → move leads para 'default' (ou configura comportamento)
- [ ] TenantSelector exibe nome da operação ativa
- [ ] TenantSelector exibe "Carregando..." durante fetch inicial
- [ ] Trocar tenant → toast de confirmação

**Validação:** `[ ] PASS` | `[ ] FAIL` | Data: _______ | Testador: _______

---

### 4.11 Módulo: Enriquecimento

- [ ] Brasil API: input de CNPJ → dados retornados e salvos no lead
- [ ] Brasil API: CNPJ inválido → mensagem de erro, lead não corrompido
- [ ] PageSpeed API: URL → performance score salvo
- [ ] PageSpeed API: URL inválida → erro tratado
- [ ] Google Places: endereço → coordenadas/mapa
- [ ] Score recalculado após enriquecimento
- [ ] APIs externas com timeout configurado (max 10s)
- [ ] Falha em API externa não impede operação

**Validação:** `[ ] PASS` | `[ ] FAIL` | Data: _______ | Testador: _______

---

## 5. Checklist de Banco de Dados

### 5.1 Schema

- [ ] Todas as 17 migrations executam sem erro em MySQL
- [ ] Todas as 17 migrations executam sem erro em SQLite
- [ ] Rollback de cada migration funciona sem deixar estado inconsistente
- [ ] Foreign keys definidas corretamente (leads.tenant_id → tenants.id, etc.)
- [ ] Indexes de performance criados (tenant_id + status, tenant_id + score)
- [ ] `updated_at` atualiza automaticamente via Eloquent (MySQL: ON UPDATE; SQLite: via Observer)

### 5.2 CRUD de Dados

- [ ] CREATE lead persiste todos os campos
- [ ] READ lead retorna todos os campos (sem perda de dados JSON)
- [ ] UPDATE lead atualiza apenas os campos fornecidos
- [ ] DELETE lead remove: lead + todas as tabelas filho (cascata)
- [ ] Transação: análise salva atomicamente (falha → rollback)

### 5.3 Dados JSON/Array

- [ ] Campo `address` (JSON) lido/gravado corretamente em MySQL
- [ ] Campo `address` (JSON → TEXT) lido/gravado corretamente em SQLite
- [ ] Campo `enabled_skills` (JSON) funciona em ambos os bancos
- [ ] `lead_observations` (array) retornado como coleção Eloquent
- [ ] `lead_transcriptions` retornado e ordenado por data

### 5.4 Performance

- [ ] Query de leads paginada (25/página) responde em < 300ms com 10.000 leads
- [ ] Busca textual (LIKE) responde em < 500ms com 10.000 leads
- [ ] Dashboard aggregates (COUNT, AVG) respondem em < 1s com 50.000 leads
- [ ] N+1 eliminado: usar `with()` / `load()` para relacionamentos

**Validação:** `[ ] PASS` | `[ ] FAIL` | Data: _______ | Testador: _______

---

## 6. Checklist SQLite × MySQL

### 6.1 Tipos de Dados

| Tipo | MySQL | SQLite | Comportamento PHP testado |
|------|-------|--------|--------------------------|
| UUID (CHAR 36) | [ ] | [ ] | `Str::uuid()` funciona |
| BOOLEAN | [ ] | [ ] | Cast `bool` correto |
| JSON | [ ] | [ ] | Cast `array` correto |
| TIMESTAMP | [ ] | [ ] | Carbon funciona |
| ENUM | [ ] | [ ] | Validação no Model |
| TEXT longo | [ ] | [ ] | Sem truncamento |
| TINYINT | [ ] | [ ] | Score 0-100 correto |

### 6.2 Comportamentos Críticos

- [ ] `ON UPDATE CURRENT_TIMESTAMP`: MySQL nativo; SQLite via Eloquent `$timestamps = true` — funciona igual
- [ ] `CASCADE DELETE`: ativo em ambos (SQLite: `PRAGMA foreign_keys=ON` configurado)
- [ ] `LIKE` case-insensitive: MySQL por padrão; SQLite é case-sensitive para ASCII — **CRÍTICO**: testar busca com letras maiúsculas/minúsculas
- [ ] `AUTOINCREMENT`: não usado (UUID) — sem problema
- [ ] Transações: testadas em ambos com rollback explícito

### 6.3 Testes de Compatibilidade

```bash
# Executar em SQLite (dev)
php artisan test --env=testing

# Executar em MySQL (staging)
DB_CONNECTION=mysql php artisan test

# Comparar resultados: devem ser idênticos
```

- [ ] Todos os testes PHPUnit passam em SQLite
- [ ] Todos os testes PHPUnit passam em MySQL
- [ ] Zero divergências entre os dois ambientes

### 6.4 Migração de Dados React → PHP

- [ ] Script de exportação IndexedDB criado (`export-indexeddb.js`)
- [ ] Script de importação PHP criado (`php artisan operon:import-json {file}`)
- [ ] Exportação gera JSON válido com todos os campos
- [ ] Importação PHP insere sem perda de campos
- [ ] UUIDs preservados (não regerar IDs durante migração)
- [ ] Timestamps preservados (não usar `now()` no import)
- [ ] Contagem de leads antes = contagem depois da migração
- [ ] Score de cada lead igual antes e depois
- [ ] Análises Operon preservadas integralmente

**Validação:** `[ ] PASS` | `[ ] FAIL` | Data: _______ | Testador: _______

---

## 7. Checklist Auth e Segurança

### 7.1 AdminPanel (PIN Gate)

- [ ] Rota `/admin/*` requer PIN correto na sessão
- [ ] PIN correto não exposto em HTML/JS do cliente
- [ ] PIN lido de `config('operon.admin_pin')` → `env('ADMIN_PIN')` (nunca hardcoded)
- [ ] Sessão de admin tem TTL configurado (ex: 30 min)
- [ ] 3 tentativas incorretas de PIN → bloqueio temporário (rate limit)
- [ ] AdminPanel inacessível por URL direta sem PIN
- [ ] Logout do AdminPanel limpa sessão de admin

### 7.2 Chaves de API

- [ ] `GEMINI_API_KEY` em `.env` (nunca em código ou HTML)
- [ ] `OPENAI_API_KEY` em `.env`
- [ ] `GROK_API_KEY` em `.env`
- [ ] `GOOGLE_MAPS_KEY` em `.env`
- [ ] `BRASIL_API_KEY` em `.env` (se necessário)
- [ ] Nenhuma chave exposta em `window.*` ou `<script>` público
- [ ] Chaves acessíveis apenas server-side (via PHP Services)
- [ ] `.env` no `.gitignore`
- [ ] `.env.example` presente com campos sem valores reais

### 7.3 Isolamento de Tenants

- [ ] `TenantMiddleware` seta `tenant_id` na sessão
- [ ] Scope global no Model `Lead::query()` sempre filtra por `tenant_id`
- [ ] Rota `GET /leads/{lead}` verifica `lead.tenant_id === session.tenant_id`
- [ ] Rota `DELETE /leads/{lead}` idem
- [ ] Rota `PATCH /leads/{lead}` idem
- [ ] API de análise: job só executa para leads do tenant correto
- [ ] Token quota por tenant (não global)
- [ ] intelligence_log por tenant

### 7.4 CSRF e Formulários

- [ ] Token CSRF em todos os forms Blade
- [ ] Livewire usa CSRF automaticamente
- [ ] API routes (SSE) protegidas por sessão ou token
- [ ] Upload de arquivo: validação de tipo (CSV/JSON apenas)
- [ ] Upload de arquivo: limite de tamanho configurado

### 7.5 XSS e Injection

- [ ] Saídas em Blade usam `{{ }}` (escapado) — nunca `{!! !!}` com input do usuário
- [ ] Conteúdo de análise IA em `{!! !!}` apenas se sanitizado antes
- [ ] Queries sempre via Eloquent ou bindings PDO (nunca concatenação de SQL)
- [ ] Inputs CSV/JSON validados antes do insert

**Validação:** `[ ] PASS` | `[ ] FAIL` | Data: _______ | Testador: _______

---

## 8. Checklist UX e Navegação

### 8.1 Design System (paridade com React)

- [ ] Paleta de cores idêntica: `operon-teal` (#0F7C6E), `operon-energy` (#18C29C), `operon-charcoal` (#111820), `operon-offwhite` (#F5F5F0), `brand-red` (#E63946)
- [ ] Dark mode funcional (`dark:` classes Tailwind)
- [ ] Toggle de tema (light/dark) persiste em localStorage
- [ ] Fonte e tipografia consistentes
- [ ] Componentes de badge, score bar, toast visualmente corretos

### 8.2 Navegação

- [ ] Sidebar com todas as rotas do menu (VaultView, Dashboard, SpinHub, Copilot, Agenda, Configurações)
- [ ] Rota ativa com destaque visual na sidebar
- [ ] TenantSelector acessível na sidebar ou header
- [ ] Breadcrumb ou indicador de seção atual
- [ ] Botão "Voltar" funciona em todas as views de detalhe
- [ ] Sem links quebrados (404) nas rotas principais

### 8.3 Feedback ao Usuário

- [ ] Toast de sucesso aparece após: criar lead, analisar lead, importar, salvar config
- [ ] Toast de erro aparece após: falha de API, quota esgotada, campo inválido
- [ ] Toast desaparece após 4 segundos (igual ao React)
- [ ] Spinner/loading state durante: análise IA, enriquecimento, importação
- [ ] Skeleton loading (ou "Carregando...") durante fetch inicial de leads
- [ ] Botão desabilitado durante processamento (evitar duplo clique)

### 8.4 Responsividade

- [ ] VaultView usável em tablet (768px)
- [ ] LeadFullView usável em tablet
- [ ] Dashboard usável em tablet
- [ ] Mobile: navegação via menu hamburger (se aplicável)

### 8.5 Acessibilidade Básica

- [ ] Todos os botões têm `title` ou `aria-label` descritivo
- [ ] Formulários têm `label` associado a cada `input`
- [ ] Focus trap em modais
- [ ] Navegação por teclado funciona nos principais fluxos

**Validação:** `[ ] PASS` | `[ ] FAIL` | Data: _______ | Testador: _______

---

## 9. Checklist de Qualidade de Código

### 9.1 PHP

- [ ] PHP 8.2+ (usar `readonly`, `enum`, `match`, `first-class callables`)
- [ ] PSR-12 (formatação de código) via `./vendor/bin/pint`
- [ ] PHPStan nível 6+ (sem erros de tipagem)
- [ ] Nenhum `var_dump()` ou `dd()` no código de produção
- [ ] Sem `@` (operador de supressão de erros)
- [ ] Sem `eval()`
- [ ] Exceptions específicas em vez de genéricas

### 9.2 Services e Jobs

- [ ] Cada service tem responsabilidade única (SRP)
- [ ] Jobs são idempotentes (re-executar não duplica dados)
- [ ] Timeout em todos os requests HTTP externos (max 30s para IA, 10s para APIs)
- [ ] Retry com backoff exponencial para chamadas de IA (max 3 tentativas)
- [ ] Logging de erros de IA (sem expor chaves)
- [ ] Cleanup de jobs falhos configurado

### 9.3 Livewire

- [ ] Propriedades públicas que armazenam models usam `#[Locked]` quando necessário
- [ ] `wire:confirm` em todas as ações destrutivas
- [ ] `wire:loading` em botões de ação demorada
- [ ] Sem N+1 em propriedades computadas Livewire

### 9.4 Banco de Dados

- [ ] Migrations reversíveis (down() implementado)
- [ ] Indexes em todas as colunas usadas em WHERE frequente
- [ ] Sem `SELECT *` em queries de produção
- [ ] Paginação em todas as listagens (nunca `->all()` em produção)
- [ ] Eager loading em relationships (`.with()`)

### 9.5 Testes

- [ ] Cobertura mínima de 70% nos Services críticos
- [ ] Feature tests para cada rota principal
- [ ] Testes de borda: campos vazios, strings longas, UUIDs inválidos
- [ ] Factory definida para cada Model
- [ ] Seeder de dados demo para testes manuais

**Validação:** `[ ] PASS` | `[ ] FAIL` | Data: _______ | Testador: _______

---

## 10. Casos de Teste Mínimos

### 10.1 Testes de Integração (PHPUnit/Pest)

```php
// TC-001: Criar lead
test('pode criar lead com campos mínimos', function () {
    $tenant = Tenant::factory()->create();
    $response = $this->actingAs(...)
        ->post('/leads', ['name' => 'Acme Corp', 'tenant_id' => $tenant->id]);
    $response->assertRedirect();
    $this->assertDatabaseHas('leads', ['name' => 'Acme Corp']);
});

// TC-002: Isolamento de tenant
test('lead de outro tenant não acessível', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $lead = Lead::factory()->create(['tenant_id' => $tenantA->id]);
    session(['tenant_id' => $tenantB->id]);
    $response = $this->get("/leads/{$lead->id}");
    $response->assertForbidden();
});

// TC-003: Débito de tokens
test('análise debita tokens corretamente', function () {
    $tenant = Tenant::factory()->create();
    TokenQuota::factory()->create(['tenant_id' => $tenant->id, 'used_today' => 0, 'daily_limit' => 100]);
    // ... trigger análise
    $this->assertEquals(7, TokenQuota::find($tenant->id)->used_today); // lead_analysis = 7 tokens
});

// TC-004: Quota esgotada bloqueia análise
test('análise bloqueada quando quota esgotada', function () {
    $tenant = Tenant::factory()->create();
    TokenQuota::factory()->create(['tenant_id' => $tenant->id, 'used_today' => 100, 'daily_limit' => 100]);
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id]);
    $response = $this->post("/leads/{$lead->id}/analysis/operon");
    $response->assertStatus(429); // Too Many Requests
});

// TC-005: Importação CSV
test('importação csv mapeia campos corretamente', function () {
    $csv = "name,segment,email\nAcme Corp,Tecnologia,acme@test.com";
    // ... post para /leads/import com o CSV
    $this->assertDatabaseHas('leads', ['name' => 'Acme Corp', 'segment' => 'Tecnologia']);
});

// TC-006: Reset diário de tokens
test('reset diário zera used_today', function () {
    $quota = TokenQuota::factory()->create(['used_today' => 50, 'reset_at' => now()->subHour()]);
    app(TokenService::class)->resetDailyIfNeeded();
    $this->assertEquals(0, $quota->fresh()->used_today);
});

// TC-007: AdminPanel sem PIN
test('admin panel inacessível sem pin', function () {
    $response = $this->get('/admin');
    $response->assertRedirect('/admin/auth');
});

// TC-008: Score calculado corretamente
test('score aumenta com cnpj verificado', function () {
    $lead = Lead::factory()->create(['score' => 0]);
    app(ScoreService::class)->recalculate($lead->fresh());
    // cnpj verificado deve adicionar pontos
    $this->assertGreaterThan(0, $lead->fresh()->score);
});
```

### 10.2 Testes Manuais de Regressão

| ID | Cenário | Resultado React | Resultado PHP | Status |
|----|---------|----------------|--------------|--------|
| MT-001 | Importar CSV com 100 leads | 100 leads na lista | ________ | ⬜ |
| MT-002 | Analisar lead com Gemini | 4 cards preenchidos | ________ | ⬜ |
| MT-003 | Trocar tenant → leads mudam | Lista atualiza | ________ | ⬜ |
| MT-004 | Esgotar quota → análise bloqueada | Toast "cota esgotada" | ________ | ⬜ |
| MT-005 | AdminPanel Ctrl+Shift+A → PIN | PIN modal aparece | ________ | ⬜ |
| MT-006 | CNPJ válido → dados preenchidos | Dados da empresa | ________ | ⬜ |
| MT-007 | Deep analysis (30-60s) | 6 seções preenchidas | ________ | ⬜ |
| MT-008 | SPIN Hub edição inline | Texto salvo | ________ | ⬜ |
| MT-009 | Copilot "liste todos os leads" | Lista retornada | ________ | ⬜ |
| MT-010 | Deletar lead → some de todas as views | Removido | ________ | ⬜ |
| MT-011 | Dark mode toggle persiste reload | Dark mantido | ________ | ⬜ |
| MT-012 | Bulk analyze 10 leads | 10 análises disparadas | ________ | ⬜ |

---

## 11. Critérios de Aceite por Fase

### Fase 0 — Setup

**DONE quando:**
- [ ] `php artisan migrate` executa sem erros em SQLite E MySQL
- [ ] `php artisan db:seed` cria tenant default e dados demo
- [ ] `npm run dev` sobe Tailwind CSS sem erro
- [ ] Rota `/` responde 200 com layout base

### Fase 1 — Core CRM

**DONE quando:**
- [ ] TC-001 passa ✅
- [ ] TC-002 passa ✅ (isolamento de tenant)
- [ ] MT-001 passa ✅ (importação CSV)
- [ ] MT-003 passa ✅ (troca de tenant)
- [ ] MT-010 passes ✅ (deletar lead)
- [ ] Zero itens FAIL no checklist 4.1 e 4.10

### Fase 2 — Análise IA + Tokens

**DONE quando:**
- [ ] TC-003 passa ✅ (débito de tokens)
- [ ] TC-004 passa ✅ (quota esgotada bloqueia)
- [ ] TC-006 passa ✅ (reset diário)
- [ ] MT-002 passa ✅ (analisar lead Gemini)
- [ ] MT-004 passa ✅ (quota esgotada)
- [ ] Todos os pesos de tokens validados (checklist 4.7)
- [ ] Badge "Operon Intelligence" visível, modelo real NÃO exposto

### Fase 3 — Enriquecimento + Score

**DONE quando:**
- [ ] TC-008 passa ✅ (score com CNPJ)
- [ ] MT-006 passa ✅ (CNPJ → dados)
- [ ] Zero itens FAIL no checklist 4.11

### Fase 4 — SPIN

**DONE quando:**
- [ ] MT-008 passes ✅ (edição inline)
- [ ] Zero itens FAIL no checklist 4.4

### Fase 5 — Dashboard

**DONE quando:**
- [ ] Todas as métricas calculadas via SQL (não hardcoded)
- [ ] Gráficos renderizam com dados reais
- [ ] Filtros de período funcionam

### Fase 6 — Follow-up + Agenda

**DONE quando:**
- [ ] Zero itens FAIL no checklist 4.6

### Fase 7 — AdminPanel

**DONE quando:**
- [ ] TC-007 passa ✅
- [ ] MT-005 passa ✅
- [ ] Zero itens FAIL no checklist 4.9 e 7.1

### Fase 8 — Copilot

**DONE quando:**
- [ ] MT-009 passa ✅
- [ ] Zero itens FAIL no checklist 4.5

### Fase 9 — Migração de Dados

**DONE quando:**
- [ ] Contagem: leads migrados = leads no React (±0%)
- [ ] Análises Operon: 100% preservadas
- [ ] Scores: diferença máxima de ±2 pontos
- [ ] Zero leads corrompidos (campos NULL que tinham valor)
- [ ] Tenants migrados corretamente

### Fase 10 — Produção

**DONE quando:**
- [ ] SSL configurado e ativo
- [ ] Tempo de resposta médio < 500ms (p95)
- [ ] Zero erros 500 nos primeiros 24h
- [ ] Backup automático configurado e testado
- [ ] Log de erros limpo (sem avisos críticos)
- [ ] Todos os testes passando em ambiente de produção

---

## 12. Registro de Pendências

Use esta tabela para registrar itens que falharam ou precisam de atenção especial:

| ID | Data | Módulo | Problema | Severidade | Responsável | Prazo | Status |
|----|------|--------|----------|-----------|-------------|-------|--------|
| P-001 | | | | 🔴 Bloqueante | | | ⬜ |
| P-002 | | | | 🟡 Importante | | | ⬜ |
| P-003 | | | | 🟢 Melhoria | | | ⬜ |

**Classificação de Severidade:**
- 🔴 Bloqueante: impede avançar para próxima fase
- 🟡 Importante: deve ser resolvido antes do deploy em produção
- 🟢 Melhoria: pode ir para backlog sem bloquear

### 12.1 Problemas Conhecidos (pré-migração)

| ID | Problema no React | Deve ser corrigido no PHP |
|----|-----------------|--------------------------|
| K-001 | Sem autenticação real (sistema aberto) | ✅ Sim — implementar auth Laravel |
| K-002 | Chaves de API em localStorage (expostas ao JS) | ✅ Sim — mover para .env + server-side |
| K-003 | IndexedDB: dados perdidos ao limpar o browser | ✅ Resolvido — MySQL como fonte de verdade |
| K-004 | Rate limiting apenas no frontend (bypassável) | ✅ Sim — middleware PHP |
| K-005 | Queue em memória (perde estado no reload) | ✅ Resolvido — Laravel Queue |
| K-006 | `sessionStorage` perdia AgencySettings (bug corrigido em Mar/2026) | ✅ Sim — usar DB em vez de storage |
| K-007 | CNPJ duplicado sem tratamento definido | ⚠️ Definir comportamento antes da migração |
| K-008 | Voice copilot sem fallback em browsers sem Web Speech API | 🟡 Implementar fallback de texto |

---

> **Última atualização:** 2026-03-10
> **Próxima revisão prevista:** Ao término de cada fase de migração
>
> Este documento deve ser atualizado a cada sessão de validação. Qualquer item `❌ FAIL` deve ser registrado em Registro de Pendências antes de continuar.
