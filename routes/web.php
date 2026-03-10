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
$router->post('/vault/:id/analyze',  [LeadController::class, 'analyze']);
$router->post('/vault/:id/deep',     [LeadController::class, 'deepAnalysis']);
$router->post('/vault/:id/insights', [LeadController::class, 'deepAnalysisInsights']);
$router->post('/vault/:id/operon',   [LeadController::class, 'operon4D']);
$router->post('/vault/:id/context',  [LeadController::class, 'updateContext']);
$router->post('/vault/:id/tags',     [LeadController::class, 'updateTags']);

// ── Atlas de Vendas ──────────────────────────────────────────
$router->get('/atlas', [AtlasController::class, 'index']);

// ── Hunter ───────────────────────────────────────────────────
$router->get('/hunter',  [HunterController::class, 'index']);
$router->post('/hunter', [HunterController::class, 'search']);

// ── Genesis (Importação) ─────────────────────────────────────
$router->get('/genesis',  fn() => View::render('genesis/index', ['active' => 'genesis']));
$router->post('/genesis', [LeadController::class, 'import']);

// ── Agenda ───────────────────────────────────────────────────
$router->get('/agenda', [AgendaController::class, 'index']);

// ── Follow-up ────────────────────────────────────────────────
$router->get('/follow-up', fn() => View::render('followup/index', ['active' => 'followup']));

// ── SPIN Hub ─────────────────────────────────────────────────
$router->get('/spin',  [SpinController::class, 'index']);
$router->post('/spin', [SpinController::class, 'generate']);

// ── Admin ────────────────────────────────────────────────────
$router->get('/admin',        [AdminController::class, 'index']);
$router->post('/admin/save',  [AdminController::class, 'save']);

// ── API interna ──────────────────────────────────────────────
$router->get('/api/tokens',          fn() => (new \App\Controllers\ApiController())->tokens());
$router->get('/api/leads',           fn() => (new \App\Controllers\ApiController())->leads());
$router->post('/api/copilot',        fn() => (new \App\Controllers\ApiController())->copilot());
