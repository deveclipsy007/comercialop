<?php
// database/migrations/migrate_multitenant.php
declare(strict_types=1);

try {
    $dbPath = __DIR__ . '/../operon.db';
    if (!file_exists($dbPath)) {
        die("Database file not found at: {$dbPath}\n");
    }

    $db = new PDO("sqlite:{$dbPath}");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Starting Multi-Tenant Migration...\n";

    // 1. Create pivot table
    echo "1. Creating tenant_user table...\n";
    $db->exec('
        CREATE TABLE IF NOT EXISTS tenant_user (
            id TEXT PRIMARY KEY,
            user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            tenant_id TEXT NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            role TEXT NOT NULL DEFAULT \'agent\',
            created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
            UNIQUE(user_id, tenant_id)
        )
    ');

    // 2. Add columns to users table
    echo "2. Adding multi-tenant columns to users table...\n";
    $stmt = $db->query('PRAGMA table_info(users)');
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    
    if (!in_array('max_tenants', $columns)) {
        $db->exec('ALTER TABLE users ADD COLUMN max_tenants INTEGER DEFAULT 1');
        echo " - Added max_tenants column\n";
    }
    if (!in_array('can_create_tenants', $columns)) {
        $db->exec('ALTER TABLE users ADD COLUMN can_create_tenants INTEGER DEFAULT 0');
        echo " - Added can_create_tenants column\n";
    }

    // 3. Backfill data
    echo "3. Backfilling existing user-tenant relationships...\n";
    $users = $db->query('SELECT id, tenant_id, role FROM users')->fetchAll(PDO::FETCH_ASSOC);
    $insertedCount = 0;

    $insertStmt = $db->prepare('
        INSERT OR IGNORE INTO tenant_user (id, user_id, tenant_id, role) 
        VALUES (?, ?, ?, ?)
    ');

    foreach ($users as $user) {
        if (!empty($user['tenant_id'])) {
            $pivotId = uniqid('tu_');
            $insertStmt->execute([
                $pivotId,
                $user['id'],
                $user['tenant_id'],
                $user['role'] ?? 'agent'
            ]);
            if ($insertStmt->rowCount() > 0) {
                $insertedCount++;
            }
        }
    }

    echo " - Migrated {$insertedCount} users to tenant_user pivot table.\n";

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "Migration Failed: " . $e->getMessage() . "\n";
}
