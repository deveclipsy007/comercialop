<?php

declare(strict_types=1);

use App\Controllers\DashboardController;
use App\Controllers\LeadController;
use App\Controllers\AtlasController;
use App\Controllers\HunterController;
use App\Controllers\SpinController;
use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\AgendaController;
use App\Controllers\KnowledgeController;
use App\Controllers\UserSettingsController;
use App\Core\View;

// ── Autenticação ─────────────────────────────────────────────
$router->get('/login',  [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);

// ── Dashboard (Nexus) ────────────────────────────────────────
$router->get('/', [DashboardController::class, 'index']);

// ── Vault (Leads) ────────────────────────────────────────────
$router->get('/vault',              [LeadController::class, 'vault']);
$router->get('/vault/:id',          [LeadController::class, 'show']);
$router->post('/vault',             [LeadController::class, 'store']);
$router->post('/vault/:id/update',  [LeadController::class, 'update']);
$router->post('/vault/:id/delete',  [LeadController::class, 'destroy']);
$router->post('/vault/:id/stage',   [LeadController::class, 'updateStage']);
$router->post('/vault/:id/analyze', [LeadController::class, 'analyze']);
$router->post('/vault/:id/deep',    [LeadController::class, 'deepAnalysis']);
$router->post('/vault/:id/insights',[LeadController::class, 'deepAnalysisInsights']);
$router->post('/vault/:id/operon',  [LeadController::class, 'operon4D']);
$router->post('/vault/:id/context', [LeadController::class, 'updateContext']);
$router->post('/vault/:id/tags',    [LeadController::class, 'updateTags']);
$router->post('/vault/:id/note',    [LeadController::class, 'addNote']);
$router->post('/vault/:id/attachment', [LeadController::class, 'uploadAttachment']);

// ── Atlas de Vendas ──────────────────────────────────────────
$router->get('/atlas', [AtlasController::class, 'index']);

// ── Hunter ───────────────────────────────────────────────────
$router->get('/hunter',  [HunterController::class, 'index']);
$router->post('/hunter', [HunterController::class, 'search']);
$router->post('/hunter/analyze', [HunterController::class, 'analyze']);
$router->post('/hunter/save',    [HunterController::class, 'toggleSave']);
$router->post('/hunter/import',  [HunterController::class, 'importCrm']);

// ── Genesis (Importação) ─────────────────────────────────────
$router->get('/genesis',  fn() => View::render('genesis/index', ['active' => 'genesis']));
$router->post('/genesis', [LeadController::class, 'import']);

// ── Agenda ───────────────────────────────────────────────────
$router->get('/agenda', [AgendaController::class, 'index']);
$router->post('/agenda/event', [AgendaController::class, 'storeEvent']);
$router->post('/agenda/event/:id/delete', [AgendaController::class, 'deleteEvent']);

// ── Follow-up ────────────────────────────────────────────────
$router->get('/follow-up', [\App\Controllers\FollowupController::class, 'index']);
$router->post('/follow-up/create', [\App\Controllers\FollowupController::class, 'store']);
$router->post('/follow-up/format-message', [\App\Controllers\FollowupController::class, 'formatMessage']);
$router->post('/follow-up/:id/complete', [\App\Controllers\FollowupController::class, 'complete']);

// ── Knowledge Base (RAG) ──────────────────────────────────────
$router->get('/knowledge',                          [KnowledgeController::class, 'index']);
$router->post('/knowledge/profile',                 [KnowledgeController::class, 'saveProfile']);
$router->post('/knowledge/reindex',                 [KnowledgeController::class, 'reindex']);
$router->get('/knowledge/status',                   [KnowledgeController::class, 'getStatus']);
$router->post('/knowledge/document/:id/delete',     [KnowledgeController::class, 'deleteDocument']);

// ── Deep Intelligence ────────────────────────────────────────
$router->post('/intelligence/run', [\App\Controllers\DeepIntelligenceController::class, 'runIntelligence']);

// ── Call Hub (Transcrição e Áudio) ───────────────────────────
$router->post('/calls/upload', [\App\Controllers\CallController::class, 'upload']);
$router->get('/calls/status', [\App\Controllers\CallController::class, 'status']);

// ── SPIN Hub ─────────────────────────────────────────────────
$router->get('/spin',  [SpinController::class, 'index']);
$router->post('/spin', [SpinController::class, 'generate']);

// ── Admin ────────────────────────────────────────────────────
$router->get('/admin/login',  [\App\Controllers\AdminAuthController::class, 'showLogin']);
$router->post('/admin/login', [\App\Controllers\AdminAuthController::class, 'login']);

$router->get('/admin',              [AdminController::class, 'index']);
$router->post('/admin/save',        [AdminController::class, 'save']);
$router->get('/admin/users',        [AdminController::class, 'users']);
$router->get('/admin/users/:id',    [AdminController::class, 'userDetail']);
$router->post('/admin/users/toggle',[AdminController::class, 'toggleUser']);
$router->post('/admin/users/:id/whitelabel', [AdminController::class, 'updateUserWhiteLabel']);
$router->get('/admin/logs',         [AdminController::class, 'logs']);
$router->get('/admin/ai-config',    [AdminController::class, 'aiConfigs']);
$router->post('/admin/ai/save',     [AdminController::class, 'saveAiConfigs']);
$router->post('/admin/users/:id/link-tenant',   [AdminController::class, 'linkTenant']);
$router->post('/admin/users/:id/unlink-tenant', [AdminController::class, 'unlinkTenant']);

// ── Multiempresa (Conexto) ───────────────────────────────────
$router->post('/context/switch',    [UserSettingsController::class, 'switchCompany']);
$router->post('/context/create',    [UserSettingsController::class, 'createCompany']);

// ── Configurações do Usuário ────────────────────────────────
$router->get('/costs',              [UserSettingsController::class, 'costs']);
$router->get('/profile',            [UserSettingsController::class, 'profile']);
$router->post('/profile',           [UserSettingsController::class, 'updateProfile']);
$router->post('/profile/whitelabel',[UserSettingsController::class, 'updateUserWhiteLabel']);
$router->get('/logs',               [UserSettingsController::class, 'logs']);
$router->get('/settings',           [UserSettingsController::class, 'settings']);
$router->get('/integrations',       [UserSettingsController::class, 'integrations']);

// ── API interna ──────────────────────────────────────────────
$router->get('/api/tokens',          fn() => (new \App\Controllers\ApiController())->tokens());
$router->get('/api/leads',           fn() => (new \App\Controllers\ApiController())->leads());
$router->post('/api/copilot',        fn() => (new \App\Controllers\ApiController())->copilot());
