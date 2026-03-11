<?php

try {
    $db = new PDO('sqlite:database/operon.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get current columns
    $stmt = $db->query('PRAGMA table_info(users)');
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    
    $added = [];
    if (!in_array('wl_color', $columns)) {
        $db->exec('ALTER TABLE users ADD COLUMN wl_color TEXT DEFAULT "#a3e635"');
        $added[] = 'wl_color';
    }
    if (!in_array('wl_logo', $columns)) {
        $db->exec('ALTER TABLE users ADD COLUMN wl_logo TEXT DEFAULT NULL');
        $added[] = 'wl_logo';
    }
    if (!in_array('wl_features', $columns)) {
        $db->exec('ALTER TABLE users ADD COLUMN wl_features TEXT DEFAULT NULL');
        $added[] = 'wl_features';
    }
    if (!in_array('wl_allow_setup', $columns)) {
        $db->exec('ALTER TABLE users ADD COLUMN wl_allow_setup INTEGER DEFAULT 0');
        $added[] = 'wl_allow_setup';
    }

    echo "Missing columns added: " . implode(', ', $added) . "\n";
    echo "Current columns: " . implode(', ', $columns) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
