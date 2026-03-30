<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Models\PlaybookBlock;
use App\Models\PlaybookModule;
use App\Models\PlaybookProgress;

class PlaybookController
{
    public function __construct()
    {
        $this->ensureTablesExist();
    }

    // ─── User View ───────────────────────────────────────────────

    /**
     * GET /playbook — Visualização do playbook para toda a equipe.
     */
    public function index(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        $user = Session::get('auth_user');

        $modules = PlaybookModule::allByTenant($tenantId, true);

        // Carregar blocos para cada módulo
        foreach ($modules as &$module) {
            $module['blocks'] = PlaybookBlock::allByModule($module['id'], $tenantId);
            $module['progress'] = PlaybookProgress::getModuleProgress($user['id'], $tenantId, $module['id']);
        }
        unset($module);

        $completedIds = PlaybookProgress::getCompletedBlockIds($user['id'], $tenantId);

        View::render('playbook/index', [
            'active' => 'playbook',
            'modules' => $modules,
            'completedIds' => $completedIds,
            'blockTypes' => PlaybookBlock::TYPES,
        ]);
    }

    /**
     * POST /playbook/progress — Marcar/desmarcar bloco como lido.
     */
    public function toggleProgress(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        $user = Session::get('auth_user');

        $blockId = $_POST['block_id'] ?? '';
        $moduleId = $_POST['module_id'] ?? '';
        $completed = (int) ($_POST['completed'] ?? 0);

        if ($completed) {
            PlaybookProgress::markCompleted($tenantId, $user['id'], $moduleId, $blockId);
        } else {
            PlaybookProgress::unmarkCompleted($user['id'], $blockId);
        }

        View::json(['ok' => true]);
    }

    // ─── Admin CRUD ──────────────────────────────────────────────

    /**
     * GET /playbook/admin — Painel de administração do playbook.
     */
    public function admin(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        $user = Session::get('auth_user');

        if (!in_array($user['role'] ?? '', ['admin', 'agent'])) {
            Session::flash('error', 'Sem permissão para acessar o editor do playbook.');
            View::redirect('/playbook');
            return;
        }

        $modules = PlaybookModule::allByTenant($tenantId);
        foreach ($modules as &$module) {
            $module['blocks'] = PlaybookBlock::allByModule($module['id'], $tenantId);
            $module['block_count'] = count($module['blocks']);
        }
        unset($module);

        View::render('playbook/admin', [
            'active' => 'playbook',
            'modules' => $modules,
            'blockTypes' => PlaybookBlock::TYPES,
        ]);
    }

    /**
     * POST /playbook/module — Criar módulo.
     */
    public function createModule(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $id = PlaybookModule::create($tenantId, [
            'title' => $_POST['title'] ?? 'Novo Módulo',
            'description' => $_POST['description'] ?? '',
            'icon' => $_POST['icon'] ?? 'menu_book',
            'color' => $_POST['color'] ?? '#E1FB15',
        ]);

        if (!empty($_POST['ajax'])) {
            View::json(['ok' => true, 'id' => $id]);
            return;
        }

        Session::flash('success', 'Módulo criado com sucesso.');
        View::redirect('/playbook/admin');
    }

    /**
     * POST /playbook/module/:id/update — Atualizar módulo.
     */
    public function updateModule(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        PlaybookModule::update($id, $tenantId, [
            'title' => $_POST['title'] ?? null,
            'description' => $_POST['description'] ?? null,
            'icon' => $_POST['icon'] ?? null,
            'color' => $_POST['color'] ?? null,
            'is_published' => $_POST['is_published'] ?? null,
        ]);

        if (!empty($_POST['ajax'])) {
            View::json(['ok' => true]);
            return;
        }

        Session::flash('success', 'Módulo atualizado.');
        View::redirect('/playbook/admin');
    }

    /**
     * POST /playbook/module/:id/delete — Excluir módulo.
     */
    public function deleteModule(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        PlaybookModule::delete($id, $tenantId);

        if (!empty($_POST['ajax'])) {
            View::json(['ok' => true]);
            return;
        }

        Session::flash('success', 'Módulo excluído.');
        View::redirect('/playbook/admin');
    }

    /**
     * POST /playbook/module/reorder — Reordenar módulos.
     */
    public function reorderModules(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $ids = json_decode($_POST['order'] ?? '[]', true);
        if (is_array($ids)) {
            PlaybookModule::reorder($tenantId, $ids);
        }

        View::json(['ok' => true]);
    }

    /**
     * POST /playbook/block — Criar bloco.
     */
    public function createBlock(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');
        $moduleId = $_POST['module_id'] ?? '';

        if (!$moduleId || !PlaybookModule::find($moduleId, $tenantId)) {
            View::json(['error' => 'Módulo não encontrado.'], 404);
            return;
        }

        $id = PlaybookBlock::create($tenantId, $moduleId, [
            'type' => $_POST['type'] ?? 'text',
            'title' => $_POST['title'] ?? '',
            'content' => $_POST['content'] ?? '',
            'metadata' => $_POST['metadata'] ?? '{}',
        ]);

        if (!empty($_POST['ajax'])) {
            View::json(['ok' => true, 'id' => $id]);
            return;
        }

        Session::flash('success', 'Bloco adicionado.');
        View::redirect('/playbook/admin');
    }

    /**
     * POST /playbook/block/:id/update — Atualizar bloco.
     */
    public function updateBlock(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        PlaybookBlock::update($id, $tenantId, [
            'type' => $_POST['type'] ?? null,
            'title' => $_POST['title'] ?? null,
            'content' => $_POST['content'] ?? null,
            'metadata' => $_POST['metadata'] ?? null,
        ]);

        if (!empty($_POST['ajax'])) {
            View::json(['ok' => true]);
            return;
        }

        Session::flash('success', 'Bloco atualizado.');
        View::redirect('/playbook/admin');
    }

    /**
     * POST /playbook/block/:id/delete — Excluir bloco.
     */
    public function deleteBlock(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        PlaybookBlock::delete($id, $tenantId);

        if (!empty($_POST['ajax'])) {
            View::json(['ok' => true]);
            return;
        }

        Session::flash('success', 'Bloco excluído.');
        View::redirect('/playbook/admin');
    }

    /**
     * POST /playbook/block/reorder — Reordenar blocos dentro de um módulo.
     */
    public function reorderBlocks(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $moduleId = $_POST['module_id'] ?? '';
        $ids = json_decode($_POST['order'] ?? '[]', true);

        if (is_array($ids) && $moduleId) {
            PlaybookBlock::reorder($moduleId, $tenantId, $ids);
        }

        View::json(['ok' => true]);
    }

    /**
     * POST /playbook/module/:id/toggle — Publicar/despublicar módulo.
     */
    public function togglePublish(string $id): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        $module = PlaybookModule::find($id, $tenantId);
        if (!$module) {
            View::json(['error' => 'Módulo não encontrado.'], 404);
            return;
        }

        $newState = $module['is_published'] ? 0 : 1;
        PlaybookModule::update($id, $tenantId, ['is_published' => $newState]);

        View::json(['ok' => true, 'is_published' => $newState]);
    }

    /**
     * GET /admin/playbook — Editor do playbook no painel admin.
     */
    public function adminPanel(): void
    {
        if (!Session::get('admin_auth')) {
            View::redirect('/admin/login');
            return;
        }

        $tenantId = Session::adminTenantId();

        $modules = PlaybookModule::allByTenant($tenantId);
        foreach ($modules as &$module) {
            $module['blocks'] = PlaybookBlock::allByModule($module['id'], $tenantId);
            $module['block_count'] = count($module['blocks']);
        }
        unset($module);

        View::render('playbook/admin_panel', [
            'active' => 'admin_playbook',
            'modules' => $modules,
            'blockTypes' => PlaybookBlock::TYPES,
        ], 'layout.admin');
    }

    /**
     * POST /admin/playbook/module — Criar módulo pelo admin.
     */
    public function adminCreateModule(): void
    {
        if (!Session::get('admin_auth')) { http_response_code(403); return; }
        $tenantId = Session::adminTenantId();

        $id = PlaybookModule::create($tenantId, [
            'title' => $_POST['title'] ?? 'Novo Módulo',
            'description' => $_POST['description'] ?? '',
            'icon' => $_POST['icon'] ?? 'menu_book',
            'color' => $_POST['color'] ?? '#E1FB15',
        ]);

        if (!empty($_POST['ajax'])) { View::json(['ok' => true, 'id' => $id]); return; }
        Session::flash('success', 'Módulo criado com sucesso.');
        View::redirect('/admin/playbook');
    }

    /**
     * POST /admin/playbook/module/:id/update
     */
    public function adminUpdateModule(string $id): void
    {
        if (!Session::get('admin_auth')) { http_response_code(403); return; }
        $tenantId = Session::adminTenantId();

        PlaybookModule::update($id, $tenantId, [
            'title' => $_POST['title'] ?? null,
            'description' => $_POST['description'] ?? null,
            'icon' => $_POST['icon'] ?? null,
            'color' => $_POST['color'] ?? null,
            'is_published' => $_POST['is_published'] ?? null,
        ]);

        if (!empty($_POST['ajax'])) { View::json(['ok' => true]); return; }
        Session::flash('success', 'Módulo atualizado.');
        View::redirect('/admin/playbook');
    }

    /**
     * POST /admin/playbook/module/:id/delete
     */
    public function adminDeleteModule(string $id): void
    {
        if (!Session::get('admin_auth')) { http_response_code(403); return; }
        $tenantId = Session::adminTenantId();
        PlaybookModule::delete($id, $tenantId);

        if (!empty($_POST['ajax'])) { View::json(['ok' => true]); return; }
        Session::flash('success', 'Módulo excluído.');
        View::redirect('/admin/playbook');
    }

    /**
     * POST /admin/playbook/module/:id/toggle
     */
    public function adminTogglePublish(string $id): void
    {
        if (!Session::get('admin_auth')) { http_response_code(403); return; }
        $tenantId = Session::adminTenantId();

        $module = PlaybookModule::find($id, $tenantId);
        if (!$module) { View::json(['error' => 'Módulo não encontrado.'], 404); return; }

        $newState = $module['is_published'] ? 0 : 1;
        PlaybookModule::update($id, $tenantId, ['is_published' => $newState]);
        View::json(['ok' => true, 'is_published' => $newState]);
    }

    /**
     * POST /admin/playbook/block
     */
    public function adminCreateBlock(): void
    {
        if (!Session::get('admin_auth')) { http_response_code(403); return; }
        $tenantId = Session::adminTenantId();
        $moduleId = $_POST['module_id'] ?? '';

        if (!$moduleId || !PlaybookModule::find($moduleId, $tenantId)) {
            View::json(['error' => 'Módulo não encontrado.'], 404); return;
        }

        $id = PlaybookBlock::create($tenantId, $moduleId, [
            'type' => $_POST['type'] ?? 'text',
            'title' => $_POST['title'] ?? '',
            'content' => $_POST['content'] ?? '',
            'metadata' => $_POST['metadata'] ?? '{}',
        ]);

        if (!empty($_POST['ajax'])) { View::json(['ok' => true, 'id' => $id]); return; }
        Session::flash('success', 'Bloco adicionado.');
        View::redirect('/admin/playbook');
    }

    /**
     * POST /admin/playbook/block/:id/update
     */
    public function adminUpdateBlock(string $id): void
    {
        if (!Session::get('admin_auth')) { http_response_code(403); return; }
        $tenantId = Session::adminTenantId();

        PlaybookBlock::update($id, $tenantId, [
            'type' => $_POST['type'] ?? null,
            'title' => $_POST['title'] ?? null,
            'content' => $_POST['content'] ?? null,
            'metadata' => $_POST['metadata'] ?? null,
        ]);

        if (!empty($_POST['ajax'])) { View::json(['ok' => true]); return; }
        Session::flash('success', 'Bloco atualizado.');
        View::redirect('/admin/playbook');
    }

    /**
     * POST /admin/playbook/block/:id/delete
     */
    public function adminDeleteBlock(string $id): void
    {
        if (!Session::get('admin_auth')) { http_response_code(403); return; }
        $tenantId = Session::adminTenantId();
        PlaybookBlock::delete($id, $tenantId);

        if (!empty($_POST['ajax'])) { View::json(['ok' => true]); return; }
        Session::flash('success', 'Bloco excluído.');
        View::redirect('/admin/playbook');
    }

    /**
     * POST /admin/playbook/module/reorder
     */
    public function adminReorderModules(): void
    {
        if (!Session::get('admin_auth')) { http_response_code(403); return; }
        $tenantId = Session::adminTenantId();
        $ids = json_decode($_POST['order'] ?? '[]', true);
        if (is_array($ids)) PlaybookModule::reorder($tenantId, $ids);
        View::json(['ok' => true]);
    }

    /**
     * POST /admin/playbook/block/reorder
     */
    public function adminReorderBlocks(): void
    {
        if (!Session::get('admin_auth')) { http_response_code(403); return; }
        $tenantId = Session::adminTenantId();
        $moduleId = $_POST['module_id'] ?? '';
        $ids = json_decode($_POST['order'] ?? '[]', true);
        if (is_array($ids) && $moduleId) PlaybookBlock::reorder($moduleId, $tenantId, $ids);
        View::json(['ok' => true]);
    }

    // ─── Migration Helper ────────────────────────────────────────

    private function ensureTablesExist(): void
    {
        try {
            $exists = Database::selectFirst(
                "SELECT name FROM sqlite_master WHERE type='table' AND name='playbook_modules'",
                []
            );
            if ($exists) return;

            $sqlPath = defined('ROOT_PATH')
                ? ROOT_PATH . '/database/migrations/016_playbook.sql'
                : dirname(__DIR__, 2) . '/database/migrations/016_playbook.sql';

            if (!file_exists($sqlPath)) {
                error_log('[PlaybookController] Migration file not found: ' . $sqlPath);
                return;
            }

            $sql = file_get_contents($sqlPath);
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                fn($s) => $s !== '' && !str_starts_with($s, '--')
            );

            foreach ($statements as $stmt) {
                Database::execute($stmt, []);
            }
        } catch (\Throwable $e) {
            error_log('[PlaybookController] Migration error: ' . $e->getMessage());
        }
    }
}
