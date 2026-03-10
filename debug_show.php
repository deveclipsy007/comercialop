<?php
require __DIR__ . '/public/index.php'; // Boots app

// Manually bypass Auth for CLI debug
\App\Core\Session::start();
\App\Core\Session::set('user_id', 'admin_123');
\App\Core\Session::set('tenant_id', 'tenant_operon');

$controller = new \App\Controllers\LeadController();

// Capture output
ob_start();
$controller->show('lead_005');
$html = ob_get_clean();

file_put_contents(__DIR__ . '/debug_show.html', $html);
echo "Rendered show.html saved to debug_show.html\n";

