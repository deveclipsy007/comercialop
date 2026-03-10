# DESIGN_SYSTEM_PROMPT.md
## Sistema de Design — Operon Intelligence Platform

> **Propósito:** Este documento captura integralmente o sistema de design visual do Operon extraído do código React/TypeScript. Qualquer desenvolvedor front-end (ou IA) pode usar este documento para recriar a identidade visual em qualquer framework — Blade/Alpine.js, Next.js, Vue, etc. — sem precisar do código original.

---

## 🎨 Filosofia de Design

O Operon tem uma estética de **"Intelligence Dark Tech"**: software de inteligência de alto desempenho para profissionais de vendas. O design comunica:
- **Poder e sofisticação**: dark mode profissional, gradientes sutis, partículas de energia
- **Clareza operacional**: tipografia limpa, hierarquia visual clara, sem ornamentos desnecessários
- **Futurismo confiável**: animações suaves (não chamativos), glow effects discretos
- **Identidade de produto SaaS**: layout fixo, sidebar colapsável, sem scroll de página inteira

**Princípio fundamental:** Cada tela parece uma interface de missão crítica — não um painel de marketing.

---

## 🎨 Paleta de Cores

### Sistema de Cores (Tailwind Custom Tokens)

O projeto usa dois sistemas de nomes: `brand.*` (legado) e `operon.*` (novo padrão).

#### Cores Brand (legado, ainda em uso)
| Token | Valor Hex | Uso principal |
|-------|-----------|---------------|
| `brand-red` | `#E11D48` | Cor primária de ação, CTA, hover states, seleções |
| `brand-redLight` | `#FFE4E6` | Background suave de alertas/estados de erro |
| `brand-redDark` | `#9F1239` | Red aprofundado para hover em contexto escuro |
| `brand-lime` | `#84CC16` | Destaque/highlight secundário, sucesso em dark mode |
| `brand-bg` | `#F3F4F6` | Background principal em light mode |
| `brand-card` | `#FFFFFF` | Superfície de cards em light mode |
| `brand-text` | `#1F2937` | Texto primário em light mode |
| `brand-subtext` | `#6B7280` | Texto secundário, labels, meta-info |
| `brand-dark` | `#19171a` | Background principal em dark mode |
| `brand-surface` | `#232026` | Superfície de cards em dark mode (ligeiramente mais clara) |

#### Cores Operon (novo padrão, substituem brand em componentes novos)
| Token | Valor inferido | Uso principal |
|-------|---------------|---------------|
| `operon-energy` | `#18C29C` (teal/turquoise) | Cor de energia: botões primários em dark, glows, badges de destaque, ícones ativos |
| `operon-teal` | `~#0A1D2A` (dark navy teal) | Background dark do sidebar, logo container, cards de destaque escuros |
| `operon-charcoal` | `~#19171a` (equivale brand-dark) | Background de página em dark mode |
| `operon-offwhite` | `~#F3F4F6` (equivale brand-bg) | Background de página em light mode |

> **Nota de implementação:** Definir essas cores no `tailwind.config.js`:
> ```js
> colors: {
>   operon: {
>     energy:    '#18C29C',
>     teal:      '#0A1D2A',
>     charcoal:  '#19171a',
>     offwhite:  '#F3F4F6',
>   },
>   brand: {
>     red:       '#E11D48',
>     redLight:  '#FFE4E6',
>     redDark:   '#9F1239',
>     lime:      '#84CC16',
>     bg:        '#F3F4F6',
>     card:      '#FFFFFF',
>     text:      '#1F2937',
>     subtext:   '#6B7280',
>     dark:      '#19171a',
>     surface:   '#232026',
>   }
> }
> ```

#### Paleta de Suporte (Tailwind padrão, fortemente usada)
| Cor | Uso no Operon |
|-----|---------------|
| `violet-500/600` | Token Economy badges, gradientes de créditos IA |
| `indigo-400/600` | Gradientes complementares ao violet (créditos) |
| `emerald-400/500` | Status "saldo OK" na barra de tokens |
| `amber-400` | Status "baixo" na barra de tokens (warning) |
| `red-400/500` | Status "esgotado", ações destrutivas, erros |
| `slate-*` | Toda a escala de neutros: texto, borders, separadores |

---

## 🖋️ Tipografia

### Font Family
```html
<!-- Google Fonts — carregar sempre -->
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
```

```css
/* CSS Global */
body {
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}
```

### Escala Tipográfica

| Classe | Uso |
|--------|-----|
| `text-[10px] tracking-[0.2em] uppercase font-bold` | Labels de seção, badges de status, cabeçalhos de tabela, subtítulos de categoria |
| `text-xs` | Meta-informação, timestamps, tooltips, legenda |
| `text-sm font-medium` | Texto de corpo, itens de nav, labels de campo |
| `text-sm font-semibold` | Itens interativos, menu items em hover |
| `text-base font-bold` | Nome de lead em cards, títulos de modal secondary |
| `text-lg font-bold` | Títulos de seção dentro de view |
| `text-xl font-bold` | Título de view/page header |
| `text-2xl font-bold` | Logo/brand name na sidebar |
| `text-3xl font-black` | Page title principal |
| `text-4xl font-black` | Métricas de destaque (ex: total acumulado R$) |

### Letramento especial
- **Logo:** `text-xl tracking-tighter font-bold` + subtítulo `text-[10px] tracking-[0.3em] font-light`
- **Badges de status:** `text-[10px] font-black uppercase tracking-widest`
- **Labels de input:** sempre uppercase com letter-spacing
- **Valores monetários:** `font-mono` ou `font-black` com drop-shadow glow

---

## 🌙 Dark Mode

### Sistema
```js
darkMode: 'class' // Tailwind config
```

O dark mode é controlado pela classe `dark` na tag `<html>`. Toggle via JavaScript:
```js
// Toggle
document.documentElement.classList.toggle('dark');
// Salvar preferência
localStorage.setItem('theme', 'dark'); // ou 'light'
// Aplicar na carga
const theme = localStorage.getItem('theme') || 'light';
document.documentElement.classList.toggle('dark', theme === 'dark');
```

### Transição suave entre temas
```css
body {
    transition: background-color 0.5s ease, color 0.5s ease;
}
```

### Padrões de cor por modo

| Elemento | Light Mode | Dark Mode |
|----------|-----------|----------|
| Body background | `bg-brand-bg` (#F3F4F6) | `dark:bg-brand-dark` (#19171a) |
| Sidebar | `bg-white` | `dark:bg-[#0B0F12]` |
| Card/Surface | `bg-white` | `dark:bg-brand-surface` (#232026) |
| Border sutil | `border-gray-100` | `dark:border-white/5` |
| Texto primário | `text-slate-900` | `dark:text-white` |
| Texto secundário | `text-slate-500` | `dark:text-slate-400` |
| Input background | `bg-white` | `dark:bg-brand-surface` |
| Header (topbar) | `bg-brand-bg/50` | `dark:bg-[#0B0F12]/80 backdrop-blur-xl` |

---

## 🏗️ Layout Principal

### Estrutura Global (flex row, full viewport)
```
┌─────────────────────────────────────────────────────┐
│              <html class="dark/light">               │
│  ┌──────────────────────────────────────────────┐   │
│  │  <body class="overflow-hidden h-screen">     │   │
│  │  ┌──────────┐  ┌─────────────────────────┐  │   │
│  │  │ Sidebar  │  │   Main Content Area      │  │   │
│  │  │ (aside)  │  │  ┌───────────────────┐   │  │   │
│  │  │ 288px ou │  │  │   Top Bar (h-20)  │   │  │   │
│  │  │ 80px     │  │  └───────────────────┘   │  │   │
│  │  │ colapsado│  │  ┌───────────────────┐   │  │   │
│  │  │          │  │  │   View Content    │   │  │   │
│  │  │          │  │  │   (flex-1 overflow│   │  │   │
│  │  │          │  │  │    -hidden)       │   │  │   │
│  │  └──────────┘  │  └───────────────────┘   │  │   │
│  │                └─────────────────────────┘  │   │
│  └──────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────┘
```

```html
<!-- Estrutura raiz -->
<div class="flex h-full w-full bg-operon-offwhite dark:bg-operon-charcoal transition-colors duration-500 overflow-hidden font-sans">
    <aside class="relative flex flex-col h-full bg-white dark:bg-[#0B0F12] border-r border-gray-100 dark:border-white/5 z-20 flex-shrink-0 transition-all duration-500 ease-in-out shadow-sm w-72">
        <!-- Sidebar content -->
    </aside>
    <main class="flex-1 flex flex-col h-full overflow-hidden relative transition-colors duration-500">
        <!-- Top bar + content -->
    </main>
</div>
```

---

## 🗂️ Componentes do Sistema

### 1. Sidebar

**Largura:** `w-72` (288px) expandida | `w-20` (80px) colapsada
**Transição:** `transition-all duration-500 ease-in-out`

**Botão de colapso:**
```html
<button class="absolute -right-3 top-9 z-50 bg-white dark:bg-operon-teal border border-gray-200 dark:border-white/10 rounded-full p-1.5 text-slate-400 hover:text-operon-energy shadow-md transition-transform hover:scale-110">
    <!-- ChevronLeft icon — rotate-180 quando colapsado -->
</button>
```

**Logo/Brand:**
```html
<div class="p-8 pb-4 flex items-center">
    <!-- Logo container -->
    <div class="w-10 h-10 bg-operon-teal text-operon-energy rounded-xl flex items-center justify-center shadow-lg shadow-teal-900/20 shrink-0">
        <!-- SVG logo animado -->
    </div>
    <!-- Nome (oculto quando colapsado) -->
    <div class="flex flex-col -space-y-1 ml-3">
        <span class="text-xl tracking-tighter font-bold">OPERON</span>
        <span class="text-[10px] tracking-[0.3em] font-light text-operon-energy ml-0.5">AGENTS</span>
    </div>
</div>
```

**Nav Item (inativo):**
```html
<button class="w-full flex items-center space-x-3 px-4 py-3 rounded-xl transition-all duration-200 text-sm font-medium text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-operon-offwhite dark:hover:bg-white/5">
    <Icon size={18} class="shrink-0" />
    <span class="truncate tracking-tight">Label</span>
</button>
```

**Nav Item (ativo):**
```html
<button class="... bg-operon-teal text-operon-energy shadow-lg font-bold">
    <!-- mesmas classes acima, mas com bg-operon-teal ativo -->
</button>
```

**Badge de contagem:**
```html
<!-- Ativo -->
<span class="ml-auto text-[10px] px-2 py-0.5 rounded-full bg-operon-energy/20 text-operon-energy">150</span>
<!-- Inativo -->
<span class="ml-auto text-[10px] px-2 py-0.5 rounded-full bg-gray-100 dark:bg-white/5 text-slate-500">150</span>
```

**Botão AI Copilot (destaque especial):**
```html
<!-- Ativo -->
<button class="... bg-operon-energy text-operon-teal shadow-xl font-bold">
<!-- Inativo -->
<button class="... text-slate-400 hover:text-operon-energy bg-slate-50 dark:bg-white/5">
    <!-- Ícone com ponto pulsante de status -->
    <div class="relative">
        <BotIcon size={18} />
        <span class="absolute -top-1 -right-1 w-2 h-2 bg-operon-energy rounded-full animate-pulse"></span>
    </div>
    OPERON AI
</button>
```

**Card de CTA (Governance Brain):**
```html
<div class="bg-operon-teal rounded-2xl p-6 text-white text-center shadow-lg mb-4 border border-operon-energy/20">
    <p class="text-xs font-light tracking-[0.2em] mb-1 opacity-70 uppercase">Operon</p>
    <p class="text-sm font-bold mb-4 tracking-tight">Governance Brain</p>
    <button class="bg-operon-energy text-operon-teal px-4 py-2 rounded-xl text-xs font-bold w-full hover:brightness-110 transition-all shadow-sm">
        Execute Analysis
    </button>
</div>
```

---

### 2. Top Bar (Header)

```html
<header class="h-20 bg-brand-bg/50 dark:bg-[#0B0F12]/80 backdrop-blur-xl flex items-center justify-between px-8 shrink-0 z-40 transition-colors duration-500 border-b border-gray-100 dark:border-white/5">
```

**Campo de busca:**
```html
<div class="relative w-full max-w-md group">
    <SearchIcon class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500 group-focus-within:text-brand-red transition-colors" />
    <input class="w-full pl-12 pr-4 py-3 bg-white dark:bg-brand-surface border border-transparent dark:border-white/5 rounded-2xl text-sm font-medium focus:outline-none focus:ring-2 focus:ring-brand-red/10 dark:focus:ring-brand-red/20 transition-all shadow-sm text-slate-600 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-600" placeholder="Search for..." />
</div>
```

**Botões de ação no header:**
```html
<button class="p-3 text-slate-500 dark:text-slate-400 hover:text-brand-red dark:hover:text-brand-red bg-white dark:bg-brand-surface rounded-xl shadow-sm hover:shadow-md transition-all border border-transparent dark:border-white/5">
    <Icon size={20} />
</button>
```

---

### 3. Cards

#### Card padrão (light/dark)
```html
<div class="bg-white dark:bg-brand-surface rounded-2xl border border-gray-100 dark:border-white/5 shadow-sm p-6">
    <!-- conteúdo -->
</div>
```

#### Card com header glassmorphism (páginas de conta)
```html
<div class="bg-white/5 p-8 rounded-3xl border border-white/10 backdrop-blur-xl relative overflow-hidden">
    <!-- Gradiente decorativo absoluto -->
    <div class="absolute inset-0 bg-gradient-to-br from-operon-energy/5 to-transparent opacity-50"></div>
    <!-- Conteúdo relativo -->
    <div class="relative z-10">...</div>
</div>
```

#### Card de métrica com glow
```html
<div class="relative bg-white/5 rounded-3xl border border-white/10 backdrop-blur-xl p-6 overflow-hidden group">
    <div class="absolute inset-0 bg-gradient-to-br from-violet-600/10 to-indigo-600/10 rounded-3xl"></div>
    <div class="relative z-10">
        <p class="text-slate-500 text-[10px] uppercase tracking-[0.2em] font-black mb-1">Label</p>
        <p class="text-4xl font-black text-operon-energy drop-shadow-[0_0_15px_rgba(24,194,156,0.3)]">
            <span class="text-sm font-bold text-operon-energy/60 mr-1">R$</span>
            1.234,00
        </p>
    </div>
</div>
```

---

### 4. Botões

#### Primary (CTA)
```html
<button class="bg-operon-energy text-operon-teal px-4 py-2 rounded-xl text-xs font-bold hover:brightness-110 transition-all shadow-sm">
    Execute Analysis
</button>
```

#### Secondary/Ghost
```html
<button class="px-4 py-2 border border-gray-200 dark:border-white/10 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-white/5 transition-all">
    Cancelar
</button>
```

#### Danger
```html
<button class="flex items-center gap-2 px-4 py-2 hover:bg-white/10 dark:hover:bg-slate-100 rounded-lg transition-colors text-sm font-bold text-red-400 dark:text-red-600">
    <TrashIcon size={16} /> Excluir
</button>
```

#### Icon button
```html
<button class="p-3 text-slate-500 dark:text-slate-400 hover:text-brand-red dark:hover:text-brand-red bg-white dark:bg-brand-surface rounded-xl shadow-sm hover:shadow-md transition-all border border-transparent dark:border-white/5">
    <Icon size={20} />
</button>
```

#### Toggle switch (Dark Mode)
```html
<!-- Light mode: bg-gray-300 | Dark mode: bg-operon-teal -->
<button class="relative w-10 h-6 rounded-full transition-colors duration-300 bg-gray-300 dark:bg-operon-teal">
    <!-- Bolinha branca deslizante -->
    <div class="absolute left-0.5 top-0.5 w-5 h-5 rounded-full bg-white shadow-md transform transition-transform duration-300 flex items-center justify-center translate-x-0 dark:translate-x-4">
        <!-- MoonIcon quando dark, SunIcon quando light -->
    </div>
</button>
```

---

### 5. Badges / Pills

#### Tier Badge
```html
<!-- Elite -->
<span class="text-[10px] font-black px-3 py-1.5 rounded-full border uppercase tracking-widest bg-violet-500/20 text-violet-300 border-violet-500/30">Elite</span>
<!-- Pro -->
<span class="... bg-teal-500/20 text-teal-300 border-teal-500/30">Pro</span>
<!-- Starter -->
<span class="... bg-slate-500/20 text-slate-300 border-slate-500/30">Starter</span>
```

#### Info pill
```html
<span class="text-[10px] font-bold text-slate-400 bg-white/5 px-3 py-1.5 rounded-full border border-white/10">
    Renova em 6h
</span>
```

#### Status badge (borda-red para destaque)
```html
<span class="bg-brand-red text-white text-xs font-bold px-2 py-0.5 rounded-md">
    3
</span>
```

---

### 6. Progress Bar (Token Economy)

```html
<!-- Container -->
<div>
    <div class="flex justify-between text-xs mb-2">
        <span class="text-slate-400 font-medium">Tokens disponíveis</span>
        <!-- Cor dinâmica baseada em estado -->
        <span class="font-black font-mono text-emerald-400">750 / 1000</span>
        <!-- text-amber-400 quando baixo, text-red-400 quando esgotado -->
    </div>
    <!-- Track -->
    <div class="w-full h-3 bg-white/10 rounded-full overflow-hidden">
        <!-- Fill — largura dinâmica (100 - usagePercent)% -->
        <div class="h-full rounded-full transition-all duration-700 bg-gradient-to-r from-violet-500 to-indigo-400"
             style="width: 75%">
        </div>
        <!-- bg-amber-400 quando isLow, bg-red-500 quando isDepleted -->
    </div>
</div>
```

---

### 7. Tabelas

```html
<div class="bg-black/20 rounded-2xl border border-white/5 overflow-hidden">
    <!-- Header da tabela -->
    <div class="px-5 py-3 border-b border-white/5 bg-white/5">
        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Histórico</h3>
    </div>
    <div class="overflow-x-auto max-h-56 overflow-y-auto">
        <table class="w-full text-left">
            <thead class="text-[10px] text-slate-500 uppercase font-black bg-white/5 tracking-widest sticky top-0">
                <tr>
                    <th class="px-5 py-3">Data/Hora</th>
                    <th class="px-5 py-3">Operação</th>
                    <th class="px-5 py-3 text-right">Tokens</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                <tr class="hover:bg-white/5 transition-colors">
                    <td class="px-5 py-3 text-xs text-slate-400 font-mono whitespace-nowrap">10/03 14:32</td>
                    <td class="px-5 py-3 text-sm text-white font-medium">Análise de Lead</td>
                    <td class="px-5 py-3 text-right text-xs font-black text-violet-300 font-mono">7</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
```

---

### 8. Inputs e Formulários

#### Input padrão
```html
<div class="space-y-1">
    <label class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">Label</label>
    <input class="w-full px-4 py-3 bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-xl text-sm text-slate-700 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-red/20 transition-all" />
</div>
```

#### Input com botão show/hide (senha/API key)
```html
<div class="relative">
    <input type="password" class="w-full px-4 py-3 pr-12 bg-white/5 border border-white/10 rounded-xl text-sm text-white font-mono" />
    <button class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white transition-colors">
        <!-- EyeIcon / EyeOffIcon -->
    </button>
</div>
```

#### Select
```html
<select class="w-full px-4 py-3 bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-xl text-sm text-slate-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-red/20 transition-all">
    <option value="gemini">Gemini</option>
    <option value="openai">OpenAI</option>
</select>
```

#### Textarea
```html
<textarea class="w-full px-4 py-3 bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-xl text-sm text-slate-700 dark:text-white resize-none focus:outline-none focus:ring-2 focus:ring-brand-red/20 transition-all" rows="4"></textarea>
```

---

### 9. Tabs (sistema de navegação dentro de modais/views)

```html
<!-- Container de tabs -->
<div class="flex bg-gray-100 dark:bg-white/5 rounded-2xl p-1 gap-1">
    <!-- Tab ativa -->
    <button class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition-all bg-white dark:bg-operon-teal text-slate-900 dark:text-operon-energy shadow-sm">
        <Icon size={14} /> Label
    </button>
    <!-- Tab inativa -->
    <button class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-white">
        <Icon size={14} /> Label
    </button>
</div>
```

---

### 10. Modais

```html
<!-- Overlay -->
<div class="fixed inset-0 bg-black/60 backdrop-blur-md z-50 flex items-center justify-center p-4">
    <!-- Container do modal -->
    <div class="bg-white dark:bg-[#0F1419] rounded-3xl shadow-[0_20px_60px_rgba(0,0,0,0.4)] w-full max-w-2xl max-h-[90vh] flex flex-col border border-gray-100 dark:border-white/10 overflow-hidden">
        <!-- Header do modal -->
        <div class="flex items-center justify-between px-8 py-6 border-b border-gray-100 dark:border-white/5 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-operon-teal text-operon-energy rounded-xl flex items-center justify-center">
                    <Icon size={20} />
                </div>
                <div>
                    <h2 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">Título</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Subtítulo descritivo</p>
                </div>
            </div>
            <button class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-white/5 rounded-xl transition-all">
                <XIcon size={20} />
            </button>
        </div>
        <!-- Corpo do modal (scrollable) -->
        <div class="flex-1 overflow-y-auto p-8 space-y-6 no-scrollbar">
            <!-- conteúdo -->
        </div>
        <!-- Footer do modal -->
        <div class="px-8 py-5 border-t border-gray-100 dark:border-white/5 flex justify-end gap-3 shrink-0">
            <button class="btn-secondary">Cancelar</button>
            <button class="btn-primary">Salvar</button>
        </div>
    </div>
</div>
```

---

### 11. Toast Notifications

```html
<!-- Container (fixed, canto inferior direito) -->
<div class="fixed bottom-6 right-6 z-[100] flex flex-col gap-3 pointer-events-none">
    <!-- Toast de sucesso -->
    <div class="pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-xl shadow-xl border border-white/10 bg-slate-900 dark:bg-white text-white dark:text-slate-900 animate-in slide-in-from-right-10">
        <CheckCircle2Icon size={18} />
        <p class="text-sm font-medium">Análise concluída com sucesso!</p>
        <button class="ml-2 hover:opacity-70"><XIcon size={14} /></button>
    </div>
    <!-- Toast de erro -->
    <div class="... bg-red-500 text-white">
        <AlertCircleIcon size={18} />
        <!-- ... -->
    </div>
</div>
```

---

### 12. Floating Action Bar (Bulk Actions)

```html
<!-- Barra flutuante na base da tela, centralizada -->
<div class="absolute bottom-8 left-1/2 transform -translate-x-1/2 bg-slate-900 dark:bg-white text-white dark:text-slate-900 px-6 py-3 rounded-2xl shadow-2xl flex items-center gap-6 ring-1 ring-white/10 dark:ring-black/5">
    <!-- Contador -->
    <div class="flex items-center gap-3 border-r border-white/20 dark:border-slate-200/20 pr-6">
        <span class="bg-brand-red text-white text-xs font-bold px-2 py-0.5 rounded-md">3</span>
        <span class="text-sm font-medium">Selecionados</span>
    </div>
    <!-- Ações -->
    <div class="flex items-center gap-2">
        <button class="... text-red-400 hover:text-red-300">Excluir</button>
        <button class="... text-slate-400 hover:text-white">Todos</button>
    </div>
    <!-- Fechar -->
    <button class="ml-2 p-1.5 hover:bg-white/20 rounded-full transition-colors"><XIcon size={16} /></button>
</div>
```

---

### 13. Intelligence / "Operon Intelligence" Badge

```html
<!-- Badge de identidade da IA — sempre visível no frontend para o usuário comum -->
<div class="flex items-center gap-2 px-4 py-2 rounded-2xl bg-gradient-to-r from-violet-600 to-indigo-600 text-white text-xs font-bold w-fit">
    <ZapIcon size={12} />
    Powered by Operon Intelligence
</div>
```

---

## ✨ Animações e Efeitos

### Keyframes definidos

```js
// tailwind.config.js — keyframes
keyframes: {
    blob: {
        '0%':   { transform: 'translate(0px, 0px) scale(1)' },
        '33%':  { transform: 'translate(30px, -50px) scale(1.1)' },
        '66%':  { transform: 'translate(-20px, 20px) scale(0.9)' },
        '100%': { transform: 'translate(0px, 0px) scale(1)' },
    },
    'border-spin': {
        '0%':   { transform: 'rotate(0deg)' },
        '100%': { transform: 'rotate(360deg)' },
    },
    'core-pulse': {
        '0%, 100%': { boxShadow: '0 0 20px rgba(225,29,72,.2), 0 0 40px rgba(132,204,22,.1)' },
        '50%':      { boxShadow: '0 0 40px rgba(225,29,72,.5), 0 0 80px rgba(132,204,22,.3)' },
    },
    'orbit': {
        '0%':   { transform: 'rotate(0deg)' },
        '100%': { transform: 'rotate(360deg)' },
    },
    cinematicFadeUp: {
        '0%':   { opacity: 0, transform: 'translateY(40px) scale(0.95)', filter: 'blur(10px)' },
        '100%': { opacity: 1, transform: 'translateY(0) scale(1)', filter: 'blur(0)' },
    },
    popIn: {
        '0%':   { opacity: 0, transform: 'scale(0.8)' },
        '100%': { opacity: 1, transform: 'scale(1)' },
    },
    blurIn: {
        '0%':   { opacity: 0, filter: 'blur(20px)' },
        '100%': { opacity: 1, filter: 'blur(0)' },
    },
    fadeIn: {
        '0%':   { opacity: 0 },
        '100%': { opacity: 1 },
    },
}
```

### Classes de animação e seus usos

| Classe | Uso |
|--------|-----|
| `animate-blob` | Blobs de fundo em dark mode (efeito de energia difusa) |
| `animate-pulse` | Ponto de status no ícone do AI Copilot |
| `animate-spin-slow` | Ícone de órbita no logo Operon |
| `animate-orbit` | Partícula orbitando no logo Operon |
| `animate-cinematic-fade-up` | Entrada principal de views (hero transitions) |
| `animate-pop-in` | Aparição de modais, tooltips, dropdowns |
| `animate-blur-in` | Cards e elementos de conteúdo em entrada |
| `animate-fade-in` | Transições simples de opacidade |
| `animate-border-spin` | Borda animada (CSS trick com conic-gradient) |

### Stagger delays (animação sequencial)
```html
<!-- Aplicar em listas de cards para entrar sequencialmente -->
<div class="stagger-1">...</div>  <!-- delay: 100ms -->
<div class="stagger-2">...</div>  <!-- delay: 200ms -->
<div class="stagger-3">...</div>  <!-- delay: 300ms -->
<div class="stagger-4">...</div>  <!-- delay: 400ms -->
<div class="stagger-5">...</div>  <!-- delay: 500ms -->
```

```css
/* CSS necessário para stagger */
.stagger-1 { animation-delay: 100ms; }
.stagger-2 { animation-delay: 200ms; }
.stagger-3 { animation-delay: 300ms; }
.stagger-4 { animation-delay: 400ms; }
.stagger-5 { animation-delay: 500ms; }
.animation-delay-2000 { animation-delay: 2s; }
.animation-delay-4000 { animation-delay: 4s; }
```

### Background de dark mode com blobs de energia
```html
<!-- Efeito de blobs de energia — apenas visível em dark mode -->
<div class="absolute inset-0 z-0 pointer-events-none opacity-0 dark:opacity-100 transition-opacity duration-1000 overflow-hidden bg-[#19171a]">
    <!-- Blob teal/energy (canto superior direito) -->
    <div class="absolute -top-[10%] -right-[10%] w-[50%] h-[50%] rounded-full bg-operon-energy/10 blur-[100px] animate-blob"></div>
    <!-- Blob lime (canto inferior esquerdo) -->
    <div class="absolute -bottom-[10%] -left-[10%] w-[50%] h-[50%] rounded-full bg-lime-500/10 blur-[100px] animate-blob animation-delay-2000"></div>
    <!-- Blob central de gradiente -->
    <div class="absolute top-[30%] left-[30%] w-[40%] h-[40%] rounded-full bg-gradient-to-br from-operon-energy/5 to-lime-500/10 blur-[80px] animate-blob animation-delay-4000"></div>
</div>
```

---

## 🌀 Efeito Animated Border

Borda girando que aparece no hover (dark mode apenas):
```html
<!-- Wrapper necessário -->
<div class="animated-border-wrapper rounded-2xl">
    <!-- Content com z-index -->
    <div class="animated-border-content rounded-2xl p-4">
        Conteúdo do card
    </div>
</div>
```

```css
.animated-border-wrapper {
    position: relative;
    overflow: hidden;
    z-index: 0;
    padding: 1px; /* cria o "inset" do borda */
}

.animated-border-wrapper::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 150%;
    height: 150%;
    background: conic-gradient(transparent, transparent, transparent, #E11D48, #84CC16, #E11D48);
    transform-origin: center;
    transform: translate(-50%, -50%) rotate(0deg);
    z-index: -2;
    opacity: 0;
    transition: opacity 0.3s ease;
}

/* Ativa no hover em dark mode */
.dark .animated-border-wrapper:hover::before,
.dark .animated-border-wrapper.active-border::before {
    animation: border-spin 4s linear infinite;
    opacity: 1;
}

.animated-border-content {
    position: relative;
    z-index: 1;
    height: 100%;
    width: 100%;
    background-color: #232026; /* surface color em dark */
    border-radius: inherit;
}
```

---

## 🎯 Sombras Customizadas

```js
// tailwind.config.js
boxShadow: {
    'soft':        '0 10px 40px -10px rgba(0, 0, 0, 0.05)',
    'card':        '0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02)',
    'glow-red':    '0 0 20px rgba(225, 29, 72, 0.35)',
    'glow-border': '0 0 15px rgba(225, 29, 72, 0.2)',
}
```

**Uso de arbitrary shadows:**
```html
<!-- Glow verde no valor de destaque -->
<p class="drop-shadow-[0_0_15px_rgba(24,194,156,0.3)]">R$ 1.234,00</p>

<!-- Glow no logo icon -->
<div class="shadow-[0_0_15px_#18C29C]"></div>

<!-- Modal shadow -->
<div class="shadow-[0_20px_60px_rgba(0,0,0,0.4)]"></div>

<!-- Violet glow para tokens -->
<div class="shadow-lg shadow-violet-500/20"></div>
```

---

## 📏 Border Radius

| Token | Valor | Uso |
|-------|-------|-----|
| `rounded-lg` | 8px | Inputs, botões secundários pequenos |
| `rounded-xl` | 12px | Botões, badges, icon buttons, nav items |
| `rounded-2xl` | 16px | Cards, dropdowns, tables |
| `rounded-3xl` | 24px | Modais, headers de seção grandes |
| `rounded-full` | 9999px | Badges circulares, toggle switch, avatar |

---

## 📜 Scrollbar Customizada

```css
/* Scrollbar global */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb {
    background: rgba(225, 29, 72, 0.2);
    border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover { background: rgba(225, 29, 72, 0.4); }

/* Dark mode */
.dark ::-webkit-scrollbar-thumb { background: rgba(225, 29, 72, 0.3); }
.dark ::-webkit-scrollbar-track { background: #19171a; }

/* Sem scrollbar (oculto mas funcional) */
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
```

Usar `no-scrollbar` em: sidebar nav, main content views, grids de cards — para dar sensação de aplicativo nativo sem scroll visual.

---

## 🔴 Selection Color

```html
<!-- Global no body: -->
<body class="selection:bg-brand-red selection:text-white">
```

---

## 🏛️ Padrões de Seção

### Cabeçalho de seção dentro de view
```html
<!-- Pattern usado em AccountCostView, AppSettingsView, etc. -->
<header class="p-8">
    <div class="flex items-center gap-4 mb-2">
        <!-- Ícone de seção -->
        <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-violet-600 to-indigo-600 flex items-center justify-center shadow-lg shadow-violet-500/20">
            <ZapIcon size={18} class="text-white" />
        </div>
        <div>
            <h2 class="text-sm font-black text-white uppercase tracking-wider">Nome da Seção</h2>
            <p class="text-xs text-slate-400">Descrição breve da seção</p>
        </div>
    </div>
</header>
```

### Label de seção/categoria (padrão de nav e forms)
```html
<p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-[0.2em] px-4 mb-2">
    Protocolos
</p>
```

---

## 🔌 Integrações de UI com Features

### Avatar com dropdown de perfil
```html
<button class="w-10 h-10 rounded-xl bg-slate-200 dark:bg-slate-800 overflow-hidden border-2 border-white dark:border-white/10 shadow-sm hover:border-operon-energy hover:shadow-lg hover:shadow-operon-energy/20 transition-all duration-300">
    <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=Felix" alt="User" class="w-full h-full object-cover" />
</button>
```

```html
<!-- Dropdown do perfil -->
<div class="absolute right-0 mt-3 w-64 bg-white dark:bg-[#0F1419] border border-gray-100 dark:border-white/10 rounded-2xl shadow-[0_20px_60px_rgba(0,0,0,0.4)] z-[9999] overflow-hidden">
    <!-- Header do dropdown com info do usuário -->
    <div class="p-5 border-b border-gray-100 dark:border-white/5 bg-gray-50/50 dark:bg-white/5">
        <div class="flex items-center gap-3">
            <!-- Avatar inicial (fallback para imagem) -->
            <div class="w-10 h-10 rounded-lg bg-operon-teal/20 text-operon-teal flex items-center justify-center font-bold text-xs ring-1 ring-operon-teal/30">FP</div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Root Admin</span>
                <span class="text-sm font-black text-slate-900 dark:text-white block">Felix Operon</span>
            </div>
        </div>
    </div>
    <!-- Items do dropdown -->
    <div class="p-2 space-y-1">
        <button class="w-full flex items-center gap-3 px-4 py-3 text-sm text-slate-600 dark:text-gray-400 hover:bg-slate-50 dark:hover:bg-white/5 rounded-xl transition-all group">
            <CircleDollarSignIcon size={18} class="group-hover:text-emerald-400 transition-colors" />
            <span class="font-semibold group-hover:text-slate-900 dark:group-hover:text-white">Custo de Conta</span>
        </button>
    </div>
</div>
```

---

## 📱 Responsividade

O Operon é uma **aplicação desktop-first** com viewport fixo (`overflow: hidden` no body). Não há scrolling de página — tudo acontece dentro dos containers.

**Breakpoints usados:**
- `md:` — em alguns headers de seção (flex column → flex row)
- Sem layouts mobile — app projetado para uso em desktop/tablet landscape

**Exceção:** alguns modais usam `max-w-2xl` ou `max-w-4xl` para se manter legíveis.

---

## 🎭 Tom Visual Geral

Para qualquer novo componente ou tela, seguir estes princípios:

1. **Nunca usar branco puro no dark mode** — usar `white/5`, `white/10`, `[#0F1419]`, `brand-surface`
2. **Texto cinza nunca é igual em light e dark** — sempre `text-slate-600 dark:text-slate-300`
3. **Bordas sempre sutis** — `border-gray-100 dark:border-white/5` ou `border-white/10`
4. **Glow verde apenas em elementos de destaque** — não usar em texto comum
5. **Vermelho (#E11D48) apenas para CTAs e estados de foco** — não como cor de fundo
6. **Espaçamento generoso** — `p-6`, `p-8`, `space-y-6`, `gap-4` são os defaults, nunca `p-1` ou `p-2` em containers
7. **Ícones de 18px no nav, 20px no header, 16px em botões compactos, 24px em destaques**
8. **Seleção de texto = vermelho** — reforça a identidade mesmo em seleções

---

## ✅ Checklist de Implementação de Nova Tela

Para qualquer nova view criada no projeto, verificar:

- [ ] Background usa `bg-operon-offwhite dark:bg-operon-charcoal`
- [ ] Header de 20 (`h-20`) com backdrop-blur se for uma topbar
- [ ] Cards com `rounded-2xl` ou `rounded-3xl`, nunca `rounded-md`
- [ ] Texto usa escala tipográfica documentada acima
- [ ] Badges de status usam o sistema de cores (emerald/amber/red conforme estado)
- [ ] Animação de entrada: `animate-in fade-in slide-in-from-bottom-4 duration-500`
- [ ] Scrollbar: `no-scrollbar` em containers internos, customizada globalmente
- [ ] Dark mode: todas as classes têm equivalente `dark:` definido
- [ ] Blobs de fundo de energia apenas em dark mode (pointer-events-none, opacity-0 light / dark:opacity-100)
- [ ] Sombras de cards são leves em light (`shadow-sm`) e sem sombra em dark (sombra substituda por borda `border-white/5`)

---

*Este documento representa o sistema de design extraído diretamente do código-fonte do Operon Intelligence Platform. Qualquer adição de componentes deve seguir estes padrões para manter a coerência visual do produto.*
