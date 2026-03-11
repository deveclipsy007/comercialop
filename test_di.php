<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap/app.php';

try {
    \App\Core\MigrationRunner::run();
    echo "Migrations OK\n";
    $mgr = new \App\Services\DeepIntelligence\DeepIntelligenceManager();
    print_r($mgr->getAvailableIntelligences());
} catch (\Throwable $e) {
    echo $e->getMessage() . "\n" . $e->getTraceAsString();
}
