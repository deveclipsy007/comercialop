<?php
/**
 * Playbook de Vendas — Visualização para a Equipe
 * Interface de consulta e acompanhamento do playbook comercial.
 */
$modules = $modules ?? [];
$completedIds = $completedIds ?? [];
$blockTypes = $blockTypes ?? [];
$user = \App\Core\Session::get('auth_user') ?? [];
$isAdmin = in_array($user['role'] ?? '', ['admin', 'agent']);
?>

<div class="min-h-screen">

    <!-- Hero Header -->
    <div class="relative overflow-hidden border-b border-stroke">
        <div class="absolute inset-0 bg-gradient-to-br from-lime/5 via-transparent to-emerald-500/5"></div>
        <div class="relative max-w-7xl mx-auto px-4 md:px-8 py-10 md:py-14">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                <div>
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-12 h-12 rounded-2xl bg-lime/10 border border-lime/20 flex items-center justify-center">
                            <span class="material-symbols-outlined text-lime text-2xl">auto_stories</span>
                        </div>
                        <div>
                            <h1 class="text-2xl md:text-3xl font-bold text-text">Playbook de Vendas</h1>
                            <p class="text-sm text-muted mt-0.5">Manual operacional e base de conhecimento comercial</p>
                        </div>
                    </div>
                </div>
                <?php if ($isAdmin): ?>
                <a href="/playbook/admin" class="flex items-center gap-2 h-10 px-5 rounded-pill bg-surface2 border border-stroke text-sm text-muted hover:text-text hover:border-white/10 transition-all">
                    <span class="material-symbols-outlined text-sm">edit</span>
                    Editar Playbook
                </a>
                <?php endif; ?>
            </div>

            <?php if (!empty($modules)): ?>
            <!-- Progress Overview -->
            <?php
            $totalBlocks = 0;
            $totalCompleted = 0;
            foreach ($modules as $m) {
                $totalBlocks += count($m['blocks'] ?? []);
                $totalCompleted += $m['progress']['completed'] ?? 0;
            }
            $globalPercent = $totalBlocks > 0 ? round(($totalCompleted / $totalBlocks) * 100) : 0;
            ?>
            <div class="mt-8 flex flex-wrap items-center gap-6">
                <div class="flex items-center gap-3">
                    <div class="w-48 h-2 bg-surface3 rounded-full overflow-hidden">
                        <div class="h-full bg-lime rounded-full transition-all duration-500" style="width: <?= $globalPercent ?>%"></div>
                    </div>
                    <span class="text-sm font-bold text-lime"><?= $globalPercent ?>%</span>
                </div>
                <div class="flex items-center gap-4 text-xs text-muted">
                    <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-sm text-lime">library_books</span> <?= count($modules) ?> módulos</span>
                    <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-sm text-emerald-400">task_alt</span> <?= $totalCompleted ?>/<?= $totalBlocks ?> concluídos</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($modules)): ?>
    <!-- Empty State -->
    <div class="flex flex-col items-center justify-center py-24 text-center max-w-md mx-auto">
        <div class="w-20 h-20 rounded-2xl bg-surface2 border border-stroke flex items-center justify-center mb-6">
            <span class="material-symbols-outlined text-4xl text-muted">auto_stories</span>
        </div>
        <h2 class="text-xl font-bold text-text mb-2">Playbook em construção</h2>
        <p class="text-sm text-muted mb-6">O playbook comercial ainda está sendo estruturado. Em breve toda a operação estará disponível aqui.</p>
        <?php if ($isAdmin): ?>
        <a href="/playbook/admin" class="flex items-center gap-2 h-11 px-6 rounded-pill bg-lime text-bg text-sm font-bold hover:brightness-110 transition-all">
            <span class="material-symbols-outlined text-sm">add</span>
            Montar Playbook
        </a>
        <?php endif; ?>
    </div>

    <?php else: ?>

    <div class="max-w-7xl mx-auto px-4 md:px-8 py-8">

        <!-- Module Navigation (Sidebar + Content) -->
        <div class="flex flex-col lg:flex-row gap-6">

            <!-- Left: Module Index -->
            <nav class="lg:w-72 flex-shrink-0">
                <div class="lg:sticky lg:top-4 space-y-2">
                    <p class="text-[10px] font-bold text-muted uppercase tracking-widest mb-3 px-3">Módulos</p>
                    <?php foreach ($modules as $i => $module): ?>
                    <a href="#module-<?= e($module['id']) ?>"
                       class="module-nav-item flex items-center gap-3 px-4 py-3 rounded-xl transition-all hover:bg-surface2 group border border-transparent hover:border-stroke"
                       data-target="module-<?= e($module['id']) ?>">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background: <?= e($module['color'] ?? '#E1FB15') ?>15">
                            <span class="material-symbols-outlined text-base" style="color: <?= e($module['color'] ?? '#E1FB15') ?>"><?= e($module['icon'] ?? 'menu_book') ?></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <span class="text-sm font-semibold text-text block truncate"><?= e($module['title']) ?></span>
                            <div class="flex items-center gap-2 mt-1">
                                <div class="flex-1 h-1 bg-surface3 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full transition-all" style="width: <?= $module['progress']['percent'] ?? 0 ?>%; background: <?= e($module['color'] ?? '#E1FB15') ?>"></div>
                                </div>
                                <span class="text-[10px] text-subtle"><?= $module['progress']['percent'] ?? 0 ?>%</span>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </nav>

            <!-- Right: Module Content -->
            <div class="flex-1 space-y-8">
                <?php foreach ($modules as $i => $module): ?>
                <section id="module-<?= e($module['id']) ?>" class="scroll-mt-6">
                    <!-- Module Header -->
                    <div class="flex items-center gap-4 mb-5 pb-4 border-b border-stroke">
                        <div class="w-11 h-11 rounded-xl flex items-center justify-center" style="background: <?= e($module['color'] ?? '#E1FB15') ?>15; border: 1px solid <?= e($module['color'] ?? '#E1FB15') ?>30">
                            <span class="material-symbols-outlined text-xl" style="color: <?= e($module['color'] ?? '#E1FB15') ?>"><?= e($module['icon'] ?? 'menu_book') ?></span>
                        </div>
                        <div class="flex-1">
                            <h2 class="text-xl font-bold text-text"><?= e($module['title']) ?></h2>
                            <?php if (!empty($module['description'])): ?>
                            <p class="text-sm text-muted mt-0.5"><?= e($module['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-muted"><?= $module['progress']['completed'] ?? 0 ?>/<?= $module['progress']['total'] ?? 0 ?></span>
                            <div class="w-20 h-1.5 bg-surface3 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all" style="width: <?= $module['progress']['percent'] ?? 0 ?>%; background: <?= e($module['color'] ?? '#E1FB15') ?>"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Blocks -->
                    <?php if (empty($module['blocks'])): ?>
                    <div class="text-center py-8 text-muted text-sm">
                        <span class="material-symbols-outlined text-2xl mb-2 block">hourglass_empty</span>
                        Conteúdo deste módulo em breve.
                    </div>
                    <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($module['blocks'] as $block):
                            $isCompleted = in_array($block['id'], $completedIds);
                            $type = $block['type'] ?? 'text';
                            $typeInfo = $blockTypes[$type] ?? ['label' => 'Texto', 'icon' => 'article'];
                        ?>
                        <div class="playbook-block bg-surface border border-stroke rounded-2xl overflow-hidden transition-all hover:border-white/10 <?= $isCompleted ? 'opacity-80' : '' ?>" data-block-id="<?= e($block['id']) ?>">
                            <!-- Block Header -->
                            <div class="flex items-center gap-3 px-5 py-4 cursor-pointer" onclick="toggleBlock('<?= e($block['id']) ?>')">
                                <button onclick="event.stopPropagation(); markProgress('<?= e($block['id']) ?>', '<?= e($module['id']) ?>', <?= $isCompleted ? '0' : '1' ?>, this)"
                                        class="w-6 h-6 rounded-lg border-2 flex items-center justify-center flex-shrink-0 transition-all
                                        <?= $isCompleted ? 'bg-emerald-500 border-emerald-500' : 'border-stroke hover:border-lime/40' ?>">
                                    <?php if ($isCompleted): ?>
                                    <span class="material-symbols-outlined text-xs text-white">check</span>
                                    <?php endif; ?>
                                </button>
                                <div class="w-8 h-8 rounded-lg bg-surface2 flex items-center justify-center flex-shrink-0">
                                    <span class="material-symbols-outlined text-base text-muted"><?= e($typeInfo['icon']) ?></span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-subtle"><?= e($typeInfo['label']) ?></span>
                                    <h3 class="text-sm font-semibold text-text <?= $isCompleted ? 'line-through text-muted' : '' ?>"><?= e($block['title'] ?: 'Conteúdo') ?></h3>
                                </div>
                                <span class="material-symbols-outlined text-lg text-muted block-chevron transition-transform" id="bchevron-<?= e($block['id']) ?>">expand_more</span>
                            </div>

                            <!-- Block Content (collapsed by default) -->
                            <div id="bcontent-<?= e($block['id']) ?>" class="hidden border-t border-stroke">
                                <div class="px-5 py-5">
                                    <?php if ($type === 'video'): ?>
                                        <?php
                                        $videoUrl = trim($block['content'] ?? '');
                                        $embedUrl = '';
                                        if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $videoUrl, $m)) {
                                            $embedUrl = 'https://www.youtube.com/embed/' . $m[1];
                                        } elseif (preg_match('/vimeo\.com\/(\d+)/', $videoUrl, $m)) {
                                            $embedUrl = 'https://player.vimeo.com/video/' . $m[1];
                                        } elseif (preg_match('/loom\.com\/share\/([a-zA-Z0-9]+)/', $videoUrl, $m)) {
                                            $embedUrl = 'https://www.loom.com/embed/' . $m[1];
                                        }
                                        ?>
                                        <?php if ($embedUrl): ?>
                                        <div class="aspect-video rounded-xl overflow-hidden bg-surface2 border border-stroke">
                                            <iframe src="<?= e($embedUrl) ?>" class="w-full h-full" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                                        </div>
                                        <?php else: ?>
                                        <a href="<?= e($videoUrl) ?>" target="_blank" class="flex items-center gap-3 p-4 bg-surface2 rounded-xl border border-stroke hover:border-lime/30 transition-all">
                                            <span class="material-symbols-outlined text-xl text-lime">play_circle</span>
                                            <span class="text-sm text-text font-medium">Assistir vídeo</span>
                                            <span class="text-xs text-muted ml-auto"><?= e($videoUrl) ?></span>
                                        </a>
                                        <?php endif; ?>

                                    <?php elseif ($type === 'document'): ?>
                                        <a href="<?= e(trim($block['content'] ?? '#')) ?>" target="_blank" class="flex items-center gap-3 p-4 bg-surface2 rounded-xl border border-stroke hover:border-lime/30 transition-all">
                                            <span class="material-symbols-outlined text-xl text-blue-400">description</span>
                                            <div>
                                                <span class="text-sm text-text font-medium block"><?= e($block['title'] ?: 'Abrir documento') ?></span>
                                                <span class="text-xs text-muted"><?= e(trim($block['content'] ?? '')) ?></span>
                                            </div>
                                            <span class="material-symbols-outlined text-sm text-muted ml-auto">open_in_new</span>
                                        </a>

                                    <?php elseif ($type === 'checklist'): ?>
                                        <div class="space-y-2">
                                            <?php foreach (explode("\n", trim($block['content'] ?? '')) as $item):
                                                $item = trim($item);
                                                if ($item === '') continue;
                                            ?>
                                            <label class="flex items-center gap-3 px-4 py-2.5 bg-surface2/50 rounded-xl cursor-pointer hover:bg-surface2 transition-all">
                                                <input type="checkbox" class="w-4 h-4 rounded border-stroke text-lime focus:ring-lime/30 bg-surface3">
                                                <span class="text-sm text-text"><?= e($item) ?></span>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>

                                    <?php elseif ($type === 'script'): ?>
                                        <div class="relative">
                                            <pre class="p-4 bg-surface2 rounded-xl border border-stroke text-sm text-text overflow-x-auto whitespace-pre-wrap font-mono leading-relaxed"><?= e($block['content'] ?? '') ?></pre>
                                            <button onclick="copyScript(this)" class="absolute top-3 right-3 p-2 rounded-lg bg-surface3 hover:bg-lime/10 text-muted hover:text-lime transition-all" title="Copiar script">
                                                <span class="material-symbols-outlined text-sm">content_copy</span>
                                            </button>
                                        </div>

                                    <?php elseif ($type === 'tip'): ?>
                                        <div class="flex gap-3 p-4 bg-amber-500/5 border border-amber-500/20 rounded-xl">
                                            <span class="material-symbols-outlined text-xl text-amber-400 flex-shrink-0 mt-0.5">lightbulb</span>
                                            <div class="text-sm text-text leading-relaxed playbook-prose"><?= nl2br(e($block['content'] ?? '')) ?></div>
                                        </div>

                                    <?php else: ?>
                                        <div class="text-sm text-text leading-relaxed playbook-prose"><?= nl2br(e($block['content'] ?? '')) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </section>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.playbook-prose p { margin-bottom: 0.5rem; }
.playbook-prose strong { color: var(--tw-text-opacity, #F5F5F5); font-weight: 600; }
.scroll-mt-6 { scroll-margin-top: 1.5rem; }
.module-nav-item.active { background: rgba(225, 251, 21, 0.05); border-color: rgba(225, 251, 21, 0.15); }
</style>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

function toggleBlock(blockId) {
    const content = document.getElementById('bcontent-' + blockId);
    const chevron = document.getElementById('bchevron-' + blockId);
    if (content.classList.contains('hidden')) {
        content.classList.remove('hidden');
        chevron.style.transform = 'rotate(180deg)';
    } else {
        content.classList.add('hidden');
        chevron.style.transform = '';
    }
}

function markProgress(blockId, moduleId, completed, btn) {
    fetch('/playbook/progress', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `_csrf=${encodeURIComponent(csrfToken)}&block_id=${blockId}&module_id=${moduleId}&completed=${completed}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) location.reload();
    });
}

function copyScript(btn) {
    const pre = btn.parentElement.querySelector('pre');
    navigator.clipboard.writeText(pre.textContent).then(() => {
        const icon = btn.querySelector('.material-symbols-outlined');
        icon.textContent = 'check';
        setTimeout(() => { icon.textContent = 'content_copy'; }, 1500);
    });
}

// Smooth scroll for navigation
document.querySelectorAll('.module-nav-item').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.getElementById(this.dataset.target);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            document.querySelectorAll('.module-nav-item').forEach(l => l.classList.remove('active'));
            this.classList.add('active');
        }
    });
});

// Highlight active module on scroll
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const id = entry.target.id;
            document.querySelectorAll('.module-nav-item').forEach(link => {
                link.classList.toggle('active', link.dataset.target === id);
            });
        }
    });
}, { threshold: 0.3 });

document.querySelectorAll('section[id^="module-"]').forEach(section => {
    observer.observe(section);
});
</script>
