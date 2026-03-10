# DESIGN_EXTRACTION_PROMPT.md
## Prompt de Extração de Design — Operon Intelligence Platform

> **Propósito:** Este documento é um prompt de alta precisão projetado para ser usado com IAs de visão (como Gemini 1.5 Pro ou Claude 3.5 Sonnet) para extrair o sistema de design e a arquitetura front-end a partir de capturas de tela.

---

### 🧠 Persona: Arquiteto de Design System & Senior UI Engineer

Você é um **Arquiteto de Design System e Engenheiro UI Sênior** especializado em interfaces de alto desempenho para sistemas de inteligência e SaaS empresarial. Sua missão é realizar uma "engenharia reversa" visual completa das imagens fornecidas para documentar a identidade visual do Operon.

---

### 🎨 Filosofia de Design: "Intelligence Dark Tech"

O Operon segue uma estética de **Inteligência de Missão Crítica**. O design não é apenas "bonito", ele deve transmitir poder, confiança e clareza.
- **Dark Mode Profissional**: Tons de carvão e azul marinho profundo.
- **Micro-interações de IA**: Elementos que "pulsa" ou têm "glow" para indicar processamento de IA.
- **Glassmorphism**: Uso sutil de transparência e desfoque de fundo (backdrop-blur) em modais e cards.

---

### 📋 Instruções de Análise (Passo a Passo)

Analise as imagens fornecidas e gere um relatório técnico detalhado cobrindo os seguintes pontos:

#### 1. Identidade Visual e Layout
- **Estrutura de Grade (Grid)**: Descreva o layout principal (ex: Sidebar fixa à esquerda, Topbar e área de conteúdo flexível).
- **Espaçamento (Padding/Margin)**: Identifique os padrões de respiro entre elementos.
- **Border Radius**: Identifique a curvatura dos cantos (ex: botões arredondados, cards moderadamente arredondados).

#### 2. Paleta de Cores (Cores Exatas)
- **Primary Action (Brand Red)**: Identifique o tom exato de vermelho/rosa usado para ações principais.
- **AI Energy (Operon Energy)**: Identifique o tom de verde/ciano usado para elementos de IA.
- **Neutral Scale**: Tons de fundo (Dark BG, Surface BG) e tons de texto (Primary, secondary, subtext).
- **Semantic Colors**: Cores para sucesso, erro, aviso e status (ex: badges de "IA Analisando").

#### 3. Tipografia
- **Font-Family**: Sugira fontes modernas (como Plus Jakarta Sans ou Inter) que correspondam ao visual.
- **Hierarchy**: Defina tamanhos (px), pesos (font-weight) e espaçamento de letras (letter-spacing) para Títulos, Corpo de Texto e Labels.

#### 4. Biblioteca de Componentes
Para cada componente visível, descreva seu estilo CSS e comportamento:
- **Buttons**: Tamanhos, cores, hover effects e ícones.
- **Cards**: Sombras, bordas, fundos e organização interna.
- **Sidebar & Navigation**: Ícones, estados ativos/inativos e badges de contagem.
- **Badges/Pills**: Estilos para "Status" e "Priority".
- **AI Specific Elements**: Gráficos de barra de tokens, glows de IA e indicadores de processamento.

#### 5. Inferência Arquitetural (Front-End)
Baseado na complexidade visual, descreva como esse front-end deve ser construído:
- **Estrutura de Componentes**: Quais componentes seriam "átomos", "moléculas" e "organismos".
- **Gerenciamento de Estado**: Como o estado da IA (processando, concluído) reflete na UI.
- **Animações (Framer Motion/Tailwind)**: Sugira animações de entrada (fade-up), pulsação de IA e transições de página.

---

### 📤 Formato de Saída Esperado

O resultado deve ser um documento Markdown estruturado, pronto para ser entregue a um desenvolvedor React ou Blade/Tailwind, contendo:
1.  **Guia de Estilo (Style Guide)** com tokens de cor e tipos.
2.  **Snippet CSS/Tailwind** para os principais componentes.
3.  **Mapa de Layout** da aplicação.
4.  **Checklist de Implementação** para garantir a fidelidade visual.

---

> **Ação Imediata:** Inicie a análise agora. Foque na precisão das cores e na hierarquia visual.
