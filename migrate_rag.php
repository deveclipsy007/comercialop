<?php
/**
 * migrate_rag.php — Migração de agency_settings → company_profiles + indexação RAG
 *
 * Execução única (CLI ou browser via http://localhost/migrate_rag.php):
 *   php migrate_rag.php
 *
 * O que faz:
 *   1. Aplica database/migrations/004_rag_module.sql (cria as 5 novas tabelas)
 *   2. Para cada tenant com agency_settings cadastrado:
 *      a. Cria/atualiza um company_profile a partir dos dados da agency_settings
 *      b. Dispara a indexação RAG (chunking + embeddings)
 *   3. Exibe relatório final
 *
 * Pode ser executado múltiplas vezes com segurança (idempotente).
 */

declare(strict_types=1);

// ── Bootstrap mínimo ────────────────────────────────────────────────────────
define('BASE_PATH', __DIR__);

require_once BASE_PATH . '/app/Core/Database.php';
require_once BASE_PATH . '/app/Helpers/helpers.php';

// Carrega helpers de config sem o framework completo
if (!function_exists('config')) {
    function config(string $key, $default = null) {
        static $configs = [];
        [$file, $dotKey] = array_pad(explode('.', $key, 2), 2, null);
        if (!isset($configs[$file])) {
            $path = BASE_PATH . "/config/{$file}.php";
            $configs[$file] = file_exists($path) ? require $path : [];
        }
        if ($dotKey === null) return $configs[$file] ?? $default;
        return $configs[$file][$dotKey] ?? $default;
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $val = $_ENV[$key] ?? getenv($key) ?? null;
        return $val !== false && $val !== null ? $val : $default;
    }
}

// Carrega .env se existir
$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}

// Autoload manual dos modelos e serviços necessários
$autoloadMap = [
    'App\\Core\\Database'                            => 'app/Core/Database.php',
    'App\\Models\\CompanyProfile'                    => 'app/Models/CompanyProfile.php',
    'App\\Models\\KnowledgeDocument'                 => 'app/Models/KnowledgeDocument.php',
    'App\\Models\\KnowledgeChunk'                    => 'app/Models/KnowledgeChunk.php',
    'App\\Models\\KnowledgeEmbedding'                => 'app/Models/KnowledgeEmbedding.php',
    'App\\Models\\AnalysisTrace'                     => 'app/Models/AnalysisTrace.php',
    'App\\Helpers\\VectorMath'                       => 'app/Helpers/VectorMath.php',
    'App\\Services\\AI\\EmbeddingProvider'           => 'app/Services/AI/EmbeddingProvider.php',
    'App\\Services\\Knowledge\\ChunkingService'      => 'app/Services/Knowledge/ChunkingService.php',
    'App\\Services\\Knowledge\\KnowledgeIndexingService' => 'app/Services/Knowledge/KnowledgeIndexingService.php',
    'App\\Services\\Knowledge\\RAGRetrievalService'  => 'app/Services/Knowledge/RAGRetrievalService.php',
    'App\\Services\\Knowledge\\KnowledgeContextBuilder' => 'app/Services/Knowledge/KnowledgeContextBuilder.php',
];

spl_autoload_register(function (string $class) use ($autoloadMap, $envFile) {
    if (isset($autoloadMap[$class])) {
        require_once BASE_PATH . '/' . $autoloadMap[$class];
    }
});

use App\Core\Database;
use App\Models\CompanyProfile;
use App\Services\Knowledge\KnowledgeIndexingService;

// ── Init ────────────────────────────────────────────────────────────────────
set_time_limit(0);
ini_set('memory_limit', '256M');

$isCli = PHP_SAPI === 'cli';
$nl    = $isCli ? "\n" : "<br>\n";
$bold  = fn(string $s) => $isCli ? "\033[1m{$s}\033[0m" : "<strong>{$s}</strong>";
$green = fn(string $s) => $isCli ? "\033[32m{$s}\033[0m" : "<span style='color:green'>{$s}</span>";
$red   = fn(string $s) => $isCli ? "\033[31m{$s}\033[0m" : "<span style='color:red'>{$s}</span>";
$yellow= fn(string $s) => $isCli ? "\033[33m{$s}\033[0m" : "<span style='color:orange'>{$s}</span>";

$log = function(string $msg) use ($nl): void { echo $msg . $nl; flush(); };

if (!$isCli) {
    echo '<pre style="background:#111;color:#eee;padding:20px;font-family:monospace;font-size:13px;">';
}

$log($bold('╔══════════════════════════════════════════════╗'));
$log($bold('║   Operon RAG Migration — 004_rag_module      ║'));
$log($bold('╚══════════════════════════════════════════════╝'));
$log('');

// ── Step 1: Aplicar migration SQL ───────────────────────────────────────────
$log($bold('[ Step 1 ] Aplicando migration 004_rag_module.sql…'));

$sqlFile = BASE_PATH . '/database/migrations/004_rag_module.sql';
if (!file_exists($sqlFile)) {
    $log($red('ERRO: Arquivo de migration não encontrado: ' . $sqlFile));
    exit(1);
}

try {
    Database::init();
    $sql = file_get_contents($sqlFile);

    // Remove comentários de linha e executa statement por statement
    $statements = array_filter(
        array_map('trim', explode(';', preg_replace('/--[^\n]*/', '', $sql))),
        fn($s) => $s !== ''
    );

    foreach ($statements as $stmt) {
        Database::execute($stmt, []);
    }
    $log($green('  ✓ Migration aplicada (' . count($statements) . ' statements)'));
} catch (\Throwable $e) {
    $log($red('  ✗ Falha na migration: ' . $e->getMessage()));
    exit(1);
}

$log('');

// ── Step 2: Listar tenants com agency_settings ──────────────────────────────
$log($bold('[ Step 2 ] Buscando tenants com agency_settings…'));

try {
    $rows = Database::select('SELECT * FROM agency_settings', []);
} catch (\Throwable $e) {
    $log($yellow('  Tabela agency_settings não existe ou está vazia. Pulando migração de dados.'));
    $rows = [];
}

if (empty($rows)) {
    $log($yellow('  Nenhum registro encontrado. Migração de dados ignorada.'));
} else {
    $log($green('  ✓ ' . count($rows) . ' tenant(s) encontrado(s)'));
}

$log('');

// ── Step 3: Migrar cada tenant ──────────────────────────────────────────────
$log($bold('[ Step 3 ] Migrando e indexando cada tenant…'));
$log('');

$indexer   = new KnowledgeIndexingService();
$succeeded = 0;
$failed    = 0;

foreach ($rows as $row) {
    $tenantId = $row['tenant_id'] ?? null;
    if (!$tenantId) {
        $log($yellow("  ⚠ Registro sem tenant_id — ignorado"));
        continue;
    }

    $log("  Tenant: {$bold($tenantId)}");

    // Converte agency_settings → company_profile data
    $profileData = CompanyProfile::fromAgencySettings($row);

    // Upsert no company_profile
    try {
        $profileId = CompanyProfile::upsert($tenantId, $profileData);
        $log("    → Perfil criado/atualizado: {$profileId}");
    } catch (\Throwable $e) {
        $log($red("    ✗ Falha ao criar perfil: " . $e->getMessage()));
        $failed++;
        continue;
    }

    // Dispara indexação RAG
    try {
        $result = $indexer->indexTenant($tenantId);

        if ($result['success']) {
            $log($green("    ✓ Indexado: {$result['chunks_indexed']} chunks, {$result['docs_created']} docs"));
            $succeeded++;
        } else {
            $log($yellow("    ⚠ Perfil salvo, indexação parcial: " . ($result['error'] ?? 'sem embedding API')));
            $log($yellow("      (O perfil está disponível via fallback legado)"));
            $succeeded++; // conta como sucesso parcial
        }
    } catch (\Throwable $e) {
        $log($yellow("    ⚠ Indexação falhou (perfil salvo): " . $e->getMessage()));
        $succeeded++; // dados migrados, só RAG falhou
    }

    $log('');
}

// ── Relatório Final ─────────────────────────────────────────────────────────
$log($bold('╔══════════════════════════════════════════════╗'));
$log($bold('║   Relatório Final                            ║'));
$log($bold('╠══════════════════════════════════════════════╣'));
$log($bold("║  Tenants migrados:  {$succeeded}"));
if ($failed > 0) {
    $log($bold("║  Falhas:            {$red((string)$failed)}"));
}
$log($bold('╚══════════════════════════════════════════════╝'));
$log('');
$log($green('Migração concluída. Você pode deletar este arquivo.'));

if (!$isCli) { echo '</pre>'; }
