<?php
require __DIR__ . '/public/index.php'; // Boots app

$leadId = 'lead_005';
$tenantId = 'tenant_operon';

// Manually bypass CSRF and Auth for CLI debug
\App\Core\Session::set('user_id', 'admin_123');
\App\Core\Session::set('tenant_id', $tenantId);

$controller = new \App\Controllers\LeadController();

echo "--- UPDATE STAGE ---\n";
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['_csrf'] = \App\Core\Session::get('_csrf'); // bypass validation failure

// Fake raw input
$json = json_encode(['stage' => 'contacted', '_csrf' => $_POST['_csrf']]);
file_put_contents('php://memory', $json);
// Note: Can't easily mock php://input here without extensions, so let's call the Lead model directly to see if the DB update works.

echo "Direct DB Update: ";
$ok = \App\Models\Lead::updateStage($leadId, $tenantId, 'contacted');
var_dump($ok);

// Let's also test the context update directly
echo "Direct Context Update: ";
$lead = \App\Models\Lead::findByTenant($leadId, $tenantId);
$ctx = $lead['human_context'] ?? [];
$ctx['temperature'] = 'HOT';
$ok2 = \App\Models\Lead::update($leadId, $tenantId, ['human_context' => $ctx]);
var_dump($ok2);

