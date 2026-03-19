<?php
/**
 * Migration: Genesis V2 — Novos campos para importação rica
 * 
 * Versão corrigida: Carrega o .env para identificar o banco correto.
 */

define('ROOT_PATH', __DIR__);
define('APP_PATH', ROOT_PATH . '/app');

require_once APP_PATH . '/Helpers/helpers.php';
require_once APP_PATH . '/Core/Database.php';

use App\Core\Database;

// Carregador simples de .env
if (file_exists(ROOT_PATH . '/.env')) {
    $lines = file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            putenv(trim($parts[0]) . '=' . trim($parts[1]));
        }
    }
}

echo "═══════════════════════════════════════════════════\n";
echo "  Genesis V2 Migration — Novos campos de leads\n";
echo "  Banco: " . env('DB_DATABASE', 'database/operon.db') . "\n";
echo "═══════════════════════════════════════════════════\n\n";

$pdo = Database::connection();

$columns = [
    ['google_maps_url',     'TEXT'],
    ['rating',              'REAL'],
    ['review_count',        'INTEGER'],
    ['reviews',             'TEXT'],
    ['opening_hours',       'TEXT'],
    ['closing_hours',       'TEXT'],
    ['category',            'TEXT'],
    ['enrichment_data',     'TEXT'],
];

$added = 0;
$skipped = 0;

$existingCols = [];
$stmt = $pdo->query("PRAGMA table_info(leads)");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $existingCols[] = $row['name'];
}

foreach ($columns as [$colName, $type]) {
    if (in_array($colName, $existingCols, true)) {
        echo "  ⏭  {$colName} — já existe\n";
        $skipped++;
        continue;
    }

    try {
        $pdo->exec("ALTER TABLE leads ADD COLUMN {$colName} {$type}");
        echo "  ✅ {$colName} ({$type}) — adicionada\n";
        $added++;
    } catch (\Exception $e) {
        echo "  ❌ {$colName} — ERRO: {$e->getMessage()}\n";
    }
}

echo "\n───────────────────────────────────────────────────\n";
echo "  Resultado: {$added} adicionadas, {$skipped} já existiam\n";
echo "───────────────────────────────────────────────────\n";
