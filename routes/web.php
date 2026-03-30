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
use App\Controllers\WhatsAppController;
use App\Controllers\MeridianController;
use App\Controllers\ApiController;
use App\Controllers\PlaybookController;
use App\Controllers\FormController;
use App\Controllers\EmailController;
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
$router->get('/atlas',          [AtlasController::class, 'index']);
$router->post('/atlas/geocode', [AtlasController::class, 'geocode']);

// ── Hunter ───────────────────────────────────────────────────
$router->get('/hunter',  [HunterController::class, 'index']);
$router->post('/hunter', [HunterController::class, 'search']);
$router->post('/hunter/analyze', [HunterController::class, 'analyze']);
$router->post('/hunter/save',    [HunterController::class, 'toggleSave']);
$router->post('/hunter/import',  [HunterController::class, 'importCrm']);

// ── Meridian (Análise de Nichos) ────────────────────────────────
$router->get('/meridian',           [MeridianController::class, 'index']);
$router->post('/meridian/analyze',  [MeridianController::class, 'analyze']);
$router->post('/meridian/history',  [MeridianController::class, 'history']);
$router->post('/meridian/delete',   [MeridianController::class, 'delete']);

// ── Genesis (Importação) ─────────────────────────────────────
$router->get('/genesis',  fn() => View::render('genesis/index', ['active' => 'genesis']));
$router->post('/genesis',         [LeadController::class, 'import']);
$router->post('/genesis/analyze', [LeadController::class, 'analyzeCSV']);

// ── Agenda ───────────────────────────────────────────────────
$router->get('/agenda', [AgendaController::class, 'index']);
$router->post('/agenda/event', [AgendaController::class, 'storeEvent']);
$router->post('/agenda/event/:id/delete', [AgendaController::class, 'deleteEvent']);

// ── Follow-up ────────────────────────────────────────────────
$router->get('/follow-up', [\App\Controllers\FollowupController::class, 'index']);
$router->post('/follow-up/create', [\App\Controllers\FollowupController::class, 'store']);
$router->post('/follow-up/format-message', [\App\Controllers\FollowupController::class, 'formatMessage']);
$router->post('/follow-up/:id/complete', [\App\Controllers\FollowupController::class, 'complete']);
$router->post('/follow-up/:id/delete',   [\App\Controllers\FollowupController::class, 'delete']);

// ── Knowledge Base (RAG) ──────────────────────────────────────
$router->get('/knowledge',                          [KnowledgeController::class, 'index']);
$router->post('/knowledge/profile',                 [KnowledgeController::class, 'saveProfile']);
$router->post('/knowledge/reindex',                 [KnowledgeController::class, 'reindex']);
$router->get('/knowledge/status',                   [KnowledgeController::class, 'getStatus']);
$router->post('/knowledge/document/:id/delete',     [KnowledgeController::class, 'deleteDocument']);
$router->post('/knowledge/extract-document',        [KnowledgeController::class, 'extractDocument']);

// ── Deep Intelligence ────────────────────────────────────────
$router->post('/intelligence/run', [\App\Controllers\DeepIntelligenceController::class, 'runIntelligence']);
$router->post('/intelligence/social-profiles', [\App\Controllers\DeepIntelligenceController::class, 'socialProfiles']);

// ── Call Hub (Transcrição e Áudio) ───────────────────────────
$router->post('/calls/upload', [\App\Controllers\CallController::class, 'upload']);
$router->get('/calls/status', [\App\Controllers\CallController::class, 'status']);

// ── SPIN Hub & Scripts de Abordagem ──────────────────────────
$router->get('/spin',                   [SpinController::class, 'index']);
$router->post('/spin',                  [SpinController::class, 'generate']);
$router->post('/spin/refine',           [SpinController::class, 'refineScript']);
$router->post('/spin/playbook/upload',  [SpinController::class, 'uploadPlaybook']);
$router->post('/spin/playbook/delete',  [SpinController::class, 'deletePlaybook']);
$router->post('/spin/playbook/toggle',  [SpinController::class, 'togglePlaybook']);

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

// Gestão de Chaves de API
$router->get('/admin/ai-keys',          [AdminController::class, 'aiKeys']);
$router->post('/admin/ai-keys/save',    [AdminController::class, 'saveAiKey']);
$router->post('/admin/ai-keys/delete',  [AdminController::class, 'deleteAiKey']);
$router->post('/admin/ai-keys/test',    [AdminController::class, 'testAiKey']);

// Configuração de Provedores por Operação
$router->get('/admin/providers',        [AdminController::class, 'providerConfigs']);
$router->post('/admin/providers/save',  [AdminController::class, 'saveProviderConfig']);
$router->post('/admin/providers/delete',[AdminController::class, 'deleteProviderConfig']);

// Dashboard de Consumo
$router->get('/admin/consumption',      [AdminController::class, 'consumption']);
$router->get('/admin/consumption/api',  [AdminController::class, 'consumptionApi']);

$router->post('/admin/users/:id/link-tenant',   [AdminController::class, 'linkTenant']);
$router->post('/admin/users/:id/unlink-tenant', [AdminController::class, 'unlinkTenant']);

// Tenant Individual Management
$router->get('/admin/tenant/:id',              [AdminController::class, 'tenantDetail']);
$router->post('/admin/tenant/:id/update',      [AdminController::class, 'updateTenant']);

// Playbook Admin
$router->get('/admin/playbook',                       [PlaybookController::class, 'adminPanel']);
$router->post('/admin/playbook/module',               [PlaybookController::class, 'adminCreateModule']);
$router->post('/admin/playbook/module/:id/update',    [PlaybookController::class, 'adminUpdateModule']);
$router->post('/admin/playbook/module/:id/delete',    [PlaybookController::class, 'adminDeleteModule']);
$router->post('/admin/playbook/module/:id/toggle',    [PlaybookController::class, 'adminTogglePublish']);
$router->post('/admin/playbook/module/reorder',       [PlaybookController::class, 'adminReorderModules']);
$router->post('/admin/playbook/block',                [PlaybookController::class, 'adminCreateBlock']);
$router->post('/admin/playbook/block/:id/update',     [PlaybookController::class, 'adminUpdateBlock']);
$router->post('/admin/playbook/block/:id/delete',     [PlaybookController::class, 'adminDeleteBlock']);
$router->post('/admin/playbook/block/reorder',        [PlaybookController::class, 'adminReorderBlocks']);

// ── Email Module ────────────────────────────────────────────
$router->get('/emails',                          [EmailController::class, 'index']);
$router->get('/emails/templates',                [EmailController::class, 'templates']);
$router->get('/emails/campaigns',                [EmailController::class, 'campaigns']);
$router->get('/emails/campaign/:id',             [EmailController::class, 'campaignDetail']);
$router->get('/emails/lead/:id',                 [EmailController::class, 'leadEmails']);

$router->post('/emails/account',                 [EmailController::class, 'createAccount']);
$router->post('/emails/account/:id/update',      [EmailController::class, 'updateAccount']);
$router->post('/emails/account/:id/delete',      [EmailController::class, 'deleteAccount']);
$router->post('/emails/account/test',            [EmailController::class, 'testAccount']);

$router->post('/emails/template',                [EmailController::class, 'createTemplate']);
$router->post('/emails/template/:id/update',     [EmailController::class, 'updateTemplate']);
$router->post('/emails/template/:id/delete',     [EmailController::class, 'deleteTemplate']);

$router->post('/emails/campaign',                [EmailController::class, 'createCampaign']);
$router->post('/emails/campaign/:id/update',     [EmailController::class, 'updateCampaign']);
$router->post('/emails/campaign/:id/delete',     [EmailController::class, 'deleteCampaign']);
$router->post('/emails/campaign/:id/step',       [EmailController::class, 'createStep']);
$router->post('/emails/campaign/:id/execute',    [EmailController::class, 'executeCampaign']);
$router->post('/emails/step/:id/update',         [EmailController::class, 'updateStep']);
$router->post('/emails/step/:id/delete',         [EmailController::class, 'deleteStep']);

$router->post('/emails/send',                    [EmailController::class, 'sendEmail']);
$router->post('/emails/ai/generate',             [EmailController::class, 'aiGenerate']);
$router->get('/email/track/open/:token',         [EmailController::class, 'trackOpen']);

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
$router->post('/settings',          [UserSettingsController::class, 'saveSettings']);
$router->post('/settings/notifications', [UserSettingsController::class, 'saveNotifications']);
$router->post('/settings/custom-field',        [UserSettingsController::class, 'saveCustomField']);
$router->post('/settings/custom-field/delete', [UserSettingsController::class, 'deleteCustomField']);
$router->get('/integrations',       [UserSettingsController::class, 'integrations']);

// ── WhatsApp ────────────────────────────────────────────────
$router->get('/whatsapp',                        [WhatsAppController::class, 'index']);
$router->post('/whatsapp/setup',                 [WhatsAppController::class, 'setup']);
$router->post('/whatsapp/connect',               [WhatsAppController::class, 'connect']);
$router->get('/whatsapp/status',                 [WhatsAppController::class, 'getStatus']);
$router->post('/whatsapp/sync',                  [WhatsAppController::class, 'sync']);
$router->post('/whatsapp/disconnect',            [WhatsAppController::class, 'disconnect']);
$router->get('/whatsapp/conversations',          [WhatsAppController::class, 'conversations']);
$router->get('/whatsapp/notifications',         [WhatsAppController::class, 'notifications']);
$router->get('/whatsapp/conversation/:id',          [WhatsAppController::class, 'conversation']);
$router->get('/whatsapp/conversation/:id/messages', [WhatsAppController::class, 'conversationMessages']);
$router->post('/whatsapp/conversation/:id/link',    [WhatsAppController::class, 'linkLead']);
$router->post('/whatsapp/conversation/:id/unlink',[WhatsAppController::class, 'unlinkLead']);
$router->post('/whatsapp/conversation/:id/analyze',[WhatsAppController::class, 'analyze']);
// Intelligence Hub
$router->post('/whatsapp/conversation/:id/summary',       [WhatsAppController::class, 'summary']);
$router->post('/whatsapp/conversation/:id/next-message',   [WhatsAppController::class, 'nextMessage']);
$router->post('/whatsapp/conversation/:id/strategic',      [WhatsAppController::class, 'strategicAnalysis']);
$router->post('/whatsapp/conversation/:id/interest-score', [WhatsAppController::class, 'interestScore']);
$router->post('/whatsapp/conversation/:id/prepare-send',  [WhatsAppController::class, 'prepareSend']);
$router->get('/whatsapp/webhook',                [WhatsAppController::class, 'webhookHandler']);
$router->post('/whatsapp/webhook',               [WhatsAppController::class, 'webhookHandler']);

// ── Playbook de Vendas ──────────────────────────────────────
$router->get('/playbook',                    [PlaybookController::class, 'index']);
$router->post('/playbook/progress',          [PlaybookController::class, 'toggleProgress']);
$router->get('/playbook/admin',              [PlaybookController::class, 'admin']);
$router->post('/playbook/module',            [PlaybookController::class, 'createModule']);
$router->post('/playbook/module/:id/update', [PlaybookController::class, 'updateModule']);
$router->post('/playbook/module/:id/delete', [PlaybookController::class, 'deleteModule']);
$router->post('/playbook/module/:id/toggle', [PlaybookController::class, 'togglePublish']);
$router->post('/playbook/module/reorder',    [PlaybookController::class, 'reorderModules']);
$router->post('/playbook/block',             [PlaybookController::class, 'createBlock']);
$router->post('/playbook/block/:id/update',  [PlaybookController::class, 'updateBlock']);
$router->post('/playbook/block/:id/delete',  [PlaybookController::class, 'deleteBlock']);
$router->post('/playbook/block/reorder',     [PlaybookController::class, 'reorderBlocks']);

// ── Formulários Dinâmicos de Qualificação ───────────────────
$router->get('/forms',                         [FormController::class, 'index']);
$router->get('/forms/new',                     [FormController::class, 'builder']);
$router->get('/forms/:id/builder',             [FormController::class, 'builder']);
$router->post('/forms/:id/update',             [FormController::class, 'updateForm']);
$router->post('/forms/:id/delete',             [FormController::class, 'deleteForm']);
$router->post('/forms/:id/duplicate',          [FormController::class, 'duplicateForm']);
$router->post('/forms/:id/questions',          [FormController::class, 'saveQuestions']);
$router->post('/forms/:id/toggle',             [FormController::class, 'toggleStatus']);
$router->get('/forms/:id/fill',                [FormController::class, 'internalFill']);
$router->post('/forms/:id/submit',             [FormController::class, 'internalSubmit']);
$router->get('/forms/:id/responses',           [FormController::class, 'responses']);
$router->post('/forms/:id/ai/generate',        [FormController::class, 'aiGenerateQuestions']);
$router->post('/forms/:id/ai/refine',          [FormController::class, 'aiRefine']);
$router->get('/forms/lead/:id/responses',      [FormController::class, 'leadResponses']);

// ── Formulário Público (sem auth) ───────────────────────────
$router->get('/f/:slug',                       [FormController::class, 'publicForm']);
$router->post('/f/:slug/submit',               [FormController::class, 'publicSubmit']);

// ── Copilot (Full Page) ─────────────────────────────────────
$router->get('/copilot', function() {
    \App\Core\Session::requireAuth();
    $tenantId = \App\Core\Session::get('tenant_id');
    $leads = \App\Core\Database::select(
        "SELECT id, name, segment, phone, email, pipeline_status, priority_score, human_context
         FROM leads WHERE tenant_id = ? ORDER BY updated_at DESC LIMIT 200",
        [$tenantId]
    );
    // Pipeline stats for funnel
    $funnelStats = \App\Core\Database::select(
        "SELECT pipeline_status, COUNT(*) as count FROM leads WHERE tenant_id = ? GROUP BY pipeline_status",
        [$tenantId]
    );
    \App\Core\View::render('copilot/index', [
        'active' => 'copilot',
        'leads' => $leads,
        'funnelStats' => $funnelStats,
    ]);
});

// ── API interna ──────────────────────────────────────────────
$router->get('/api/tokens',          [ApiController::class, 'tokens']);
$router->get('/api/leads',           [ApiController::class, 'leads']);
$router->post('/api/copilot',        [ApiController::class, 'copilot']);

// ── Operon Capture — Extension API (Bearer Token Auth) ──────
use App\Controllers\ExtensionApiController;

$router->post('/api/ext/auth',       [ExtensionApiController::class, 'auth']);
$router->post('/api/ext/logout',     [ExtensionApiController::class, 'logout']);
$router->post('/api/ext/capture',    [ExtensionApiController::class, 'capture']);
$router->post('/api/ext/capture-bulk', [ExtensionApiController::class, 'captureBulk']);
$router->post('/api/ext/check',      [ExtensionApiController::class, 'check']);
$router->post('/api/ext/check-bulk', [ExtensionApiController::class, 'checkBulk']);
$router->post('/api/ext/analyze-page', [ExtensionApiController::class, 'analyzePage']);
$router->post('/api/ext/qualify',    [ExtensionApiController::class, 'qualify']);
$router->post('/api/ext/analyze-visual', [ExtensionApiController::class, 'analyzeVisual']);
$router->post('/api/ext/copilot',    [ExtensionApiController::class, 'copilot']);
$router->post('/api/ext/save-analysis', [ExtensionApiController::class, 'saveAnalysis']);
$router->post('/api/ext/switch-tenant', [ExtensionApiController::class, 'switchTenant']);
$router->get('/api/ext/me',          [ExtensionApiController::class, 'me']);
$router->get('/api/ext/segments',    [ExtensionApiController::class, 'segments']);
