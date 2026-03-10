# PROGRESS — Operon Intelligence Platform

## Data de início: 2026-03-10
## Stack: PHP 8.2+ (custom framework leve) + Tailwind CSS CDN + SQLite (dev) / PostgreSQL (prod)
## Status Geral: ✅ MVP Completo — Pronto para testar em browser

---

## Entendimento do Sistema

O Operon Intelligence Platform é um CRM de IA para vendas B2B de alto ticket com:
- **9 módulos principais**: Nexus (Dashboard), Vault (Leads Kanban), Atlas (Mapa), Hunter, Genesis, Agenda, Follow-up, SPIN Hub, Admin
- **Token Economy**: cotas diárias por tier (starter/pro/elite) consumidas por operações de IA
- **4D Intelligence**: 4 análises paralelas de IA por lead (diagnóstico, potencial, autoridade, script)
- **Multi-tenant**: isolamento total por tenant_id em todas as queries
- **AI Providers**: Gemini (principal), OpenAI (fallback), Grok (alternativo) — sempre exibido como "Operon Intelligence"
- **Design**: "Intelligence Dark Tech" com sidebar #0A1D2A, fundo #19171A, energia #18C29C, CTA #E11D48

## Arquitetura Escolhida

Custom PHP micro-framework (sem Laravel para evitar dependências externas na fase inicial):
- `app/Core/Router.php` — roteamento simples por path
- `app/Core/View.php` — renderização de templates PHP com output buffering
- `app/Core/Database.php` — PDO com suporte SQLite/PostgreSQL
- Controller → Service → Model (sem Repository separado na fase 1, adicionar na fase 3+)

---

## Fase 1: Setup do Projeto — ✅ Concluído

**O que foi feito:**
- `PROGRESS.md` criado
- `DECISIONS.md` criado
- `.env.example` com todas as variáveis necessárias
- `composer.json` com dependências mínimas
- `public/index.php` — entry point com router
- `public/.htaccess` — rewrite para SPA-like routing
- `public/css/app.css` — scrollbar, animações, blobs
- `public/js/app.js` — dark mode toggle, sidebar collapse
- `app/Core/Router.php` — roteador simples
- `app/Core/View.php` — renderizador de templates
- `app/Core/App.php` — bootstrap da aplicação
- `app/Core/Database.php` — PDO wrapper
- `app/Core/Session.php` — gerenciamento de sessão
- `config/app.php` — configurações gerais
- `config/services.php` — keys de APIs externas
- `config/operon.php` — pesos de tokens, limites por tier
- `routes/web.php` — todas as rotas da aplicação

**Decisões tomadas:**
- Custom micro-framework ao invés de Laravel para rodar sem `composer install` inicial
- SQLite para dev (arquivo local, zero config)
- Tailwind CDN com config inline para evitar build step

---

## Fase 2: Database — ✅ Concluído
- [x] schema.sql com todas as tabelas (tenants, users, leads, lead_activities, token_quotas, token_logs, agency_settings, followups)
- [x] seeds.sql com dados demo (tenant, admin, 6 leads, 2 followups)
- [x] Lead model com STAGES, create(tenantId, data), pipelineStats() flat

## Fase 3: Autenticação + Multi-tenant — ✅ Concluído
- [x] AuthController (login/logout com CSRF)
- [x] Session::requireAuth() em todos os controllers
- [x] tenant_id em todas as queries

## Fase 4: CRUD de Leads + Vault — ✅ Concluído
- [x] vault/index.php (Kanban + Lista com filtros)
- [x] vault/show.php (detalhe do lead com análise, contexto, tags, pipeline)
- [x] CRUD: store, update, destroy, updateStage, updateContext, updateTags

## Fase 5: Token Economy — ✅ Concluído
- [x] TokenService::consume() com reset diário
- [x] Barra de tokens no sidebar
- [x] TokenQuota model

## Fase 6: Integrações de IA — ✅ Concluído
- [x] GeminiProvider com google_search grounding
- [x] LeadAnalysisService (qualificação, 4D, SPIN, scripts)
- [x] AIResponseParser

## Fase 7-16: Todos os Módulos — ✅ Concluído
- [x] Dashboard Nexus (KPIs, funil, hot leads)
- [x] Atlas (mapa Leaflet.js)
- [x] Hunter (prospecção com IA)
- [x] SPIN Hub (perguntas + scripts)
- [x] Agenda (follow-ups)
- [x] Genesis (importação CSV)
- [x] Admin (configurações da agência)
- [x] Copilot (modal AI chat)
- [x] 404 error page

## Próximos Passos para Rodar
1. Copiar `.env.example` → `.env` e configurar `GEMINI_API_KEY` e `DB_DATABASE`
2. Inicializar DB: `sqlite3 storage/database.sqlite < database/schema.sql && sqlite3 storage/database.sqlite < database/seeds.sql`
3. Apontar servidor PHP para `public/`: `php -S localhost:8000 -t public/`
4. Acessar: http://localhost:8000 — login: admin@operon.ai / operon123

---

## Todos os Arquivos Criados

| Arquivo | Status |
|---------|--------|
| `public/index.php` | ✅ |
| `public/.htaccess` | ✅ |
| `public/css/app.css` | ✅ |
| `public/js/app.js` | ✅ |
| `app/Core/Router.php` | ✅ |
| `app/Core/View.php` | ✅ |
| `app/Core/App.php` | ✅ |
| `app/Core/Database.php` | ✅ |
| `app/Core/Session.php` | ✅ |
| `config/app.php` | ✅ |
| `config/services.php` | ✅ |
| `config/operon.php` | ✅ |
| `routes/web.php` | ✅ |
| `app/Controllers/AuthController.php` | ✅ |
| `app/Controllers/DashboardController.php` | ✅ |
| `app/Controllers/LeadController.php` | ✅ (+updateContext, +updateTags) |
| `app/Controllers/AtlasController.php` | ✅ |
| `app/Controllers/HunterController.php` | ✅ |
| `app/Controllers/SpinController.php` | ✅ |
| `app/Controllers/AgendaController.php` | ✅ (bug DB::getInstance fixado) |
| `app/Controllers/AdminController.php` | ✅ |
| `app/Controllers/ApiController.php` | ✅ |
| `app/Models/Lead.php` | ✅ (bugs fixados: estágios lowercase, DB estático) |
| `app/Models/User.php` | ✅ |
| `app/Models/TokenQuota.php` | ✅ |
| `app/Services/TokenService.php` | ✅ |
| `app/Services/AI/GeminiProvider.php` | ✅ |
| `app/Services/AI/OpenAIProvider.php` | ✅ |
| `app/Services/LeadAnalysisService.php` | ✅ |
| `app/Services/SmartContextService.php` | ✅ |
| `app/Services/PageSpeedService.php` | ✅ |
| `app/Helpers/AIResponseParser.php` | ✅ |
| `app/Helpers/helpers.php` | ✅ |
| `database/schema.sql` | ✅ |
| `database/seeds.sql` | ✅ |
| `resources/views/layout/app.php` | ✅ |
| `resources/views/layout/minimal.php` | ✅ |
| `resources/views/auth/login.php` | ✅ |
| `resources/views/dashboard/nexus.php` | ✅ |
| `resources/views/vault/index.php` | ✅ |
| `resources/views/vault/show.php` | ✅ |
| `resources/views/atlas/index.php` | ✅ |
| `resources/views/hunter/index.php` | ✅ |
| `resources/views/spin/index.php` | ✅ |
| `resources/views/admin/index.php` | ✅ |
| `resources/views/genesis/index.php` | ✅ |
| `resources/views/agenda/index.php` | ✅ |
| `resources/views/followup/index.php` | ✅ |
| `resources/views/errors/404.php` | ✅ |
