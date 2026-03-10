# DECISIONS — Operon Intelligence Platform

---

## 2026-03-10 — Custom Micro-Framework ao invés de Laravel

**Contexto:** MASTER_PROMPT e SENIOR_DEV_PROMPT recomendam Laravel 11. Porém, o cliente precisa de um sistema funcional sem precisar de `composer install` para ver as primeiras telas.

**Decisão:** Usar custom micro-framework PHP para Fase 1 (UI + routing básico). Migrar para Laravel na Fase 3+ quando o banco de dados e autenticação forem necessários.

**Alternativas rejeitadas:**
- Laravel 11 full: requer Composer + muitas dependências para fase inicial
- Symfony: curva de aprendizado mais alta sem benefício claro para o tamanho do projeto

**Consequências:**
- Fase 1-2 funciona sem `composer install`
- Fase 3+ requer migração para autoloading baseado em Composer
- Padrão Controller → Service → Model mantido igual ao especificado

---

## 2026-03-10 — SQLite para Desenvolvimento

**Contexto:** PostgreSQL é o banco de produção, mas requer instalação/configuração.

**Decisão:** SQLite como banco de desenvolvimento local (arquivo em `database/operon.db`). Zero configuração. PostgreSQL em produção.

**Alternativas rejeitadas:**
- MySQL: requer instalação de servidor
- PostgreSQL local: mais pesado para dev

**Consequências:**
- `database/schema.sql` compatível com ambos
- `Database.php` usa PDO (transparente para ambos)
- Algumas features PostgreSQL (JSONB, arrays nativos) usadas como TEXT JSON no SQLite

---

## 2026-03-10 — Tailwind CSS via CDN com Config Inline

**Contexto:** MASTER_PROMPT menciona "Tailwind CSS CDN ou build".

**Decisão:** CDN com config inline em todas as views (consistente com os arquivos code*.html de referência).

**Alternativas rejeitadas:**
- Build step com Vite/PostCSS: aumenta complexidade de setup
- Purge CSS: desnecessário para sistema interno

**Consequências:**
- Script de config Tailwind repetido no layout principal
- Cores customizadas (operon-teal, operon-energy, primary, etc.) definidas via `tailwind.config`
- Animations customizadas (blob, orbit, popIn) via `<style>` tag adicional no layout

---

## 2026-03-10 — Template Engine: PHP puro com Output Buffering

**Contexto:** MASTER_PROMPT menciona "Blade, Twig, ou equivalente PHP".

**Decisão:** PHP puro com `ob_start()`/`ob_get_clean()` para template inheritance. Sem dependência de Twig/Blade.

**Alternativas rejeitadas:**
- Twig: requer Composer
- Blade standalone: requer Laravel ou pacote separado

**Consequências:**
- Views são arquivos `.php` com HTML + `<?= $variable ?>` para saída
- Layout principal incluído via `View::render($template, $data, $layout)`
- XSS prevention via `htmlspecialchars()` em todo output de dados do usuário

---

## 2026-03-10 — AI Provider Abstraction

**Contexto:** Sistema suporta Gemini, OpenAI e Grok. UI deve mostrar sempre "Operon Intelligence".

**Decisão:** Interface `AIProviderInterface` com implementações `GeminiProvider`, `OpenAIProvider`, `GrokProvider`. Config `config/services.php` determina provider ativo.

**Consequências:**
- Trocar provider = mudar uma linha no `.env`
- `OPERON_PROVIDER=gemini` → usa Gemini; `OPERON_PROVIDER=openai` → usa OpenAI
- Frontend NUNCA recebe o nome real do modelo — sempre "Operon Intelligence"

---

## 2026-03-10 — Mapa Atlas: Leaflet.js com OpenStreetMap

**Contexto:** `screenmaps.png` mostra mapa interativo com dark theme e pins de leads.

**Decisão:** Leaflet.js (gratuito, open source) com tiles CartoDB Dark Matter (dark theme gratuito) para o Atlas de Vendas.

**Alternativas rejeitadas:**
- Google Maps: requer API key paga, JavaScript Maps SDK complexo
- Mapbox: requer API key com cotas

**Consequências:**
- Mapa funciona sem API key para uso básico
- Geocoding via Nominatim (OpenStreetMap) ou endereços pré-definidos no seed
