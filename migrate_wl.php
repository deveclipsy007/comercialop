<?php
$db = new PDO('sqlite:database/database.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$queries = [
    "ALTER TABLE users ADD COLUMN wl_color TEXT DEFAULT '#a3e635'",
    "ALTER TABLE users ADD COLUMN wl_logo TEXT",
    "ALTER TABLE users ADD COLUMN wl_features TEXT",
    "ALTER TABLE users ADD COLUMN wl_allow_setup INTEGER DEFAULT 0",
];

foreach ($queries as $q) {
    try {
        $db->exec($q);
        echo "Executed: $q\n";
    } catch (Exception $e) {
        echo "Error on $q: " . $e->getMessage() . "\n";
    }
}
echo "Done.\n";
