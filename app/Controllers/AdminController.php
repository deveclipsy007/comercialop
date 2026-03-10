<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Core\Database;
use App\Models\TokenQuota;

class AdminController
{
    public function index(): void
    {
        Session::requireAuth();

        // Only admin role can access
        if (Session::get('role') !== 'admin') {
            Session::flash('error', 'Acesso negado.');
            View::redirect('/');
        }

        $tenantId = Session::get('tenant_id');
        $db       = Database::getInstance();

        // Load agency settings
        $settings = $db->selectFirst(
            "SELECT * FROM agency_settings WHERE tenant_id = ?",
            [$tenantId]
        );

        // Parse JSON fields
        if ($settings) {
            $settings['differentials'] = json_decode($settings['differentials'] ?? '[]', true) ?? [];
            $settings['services']      = json_decode($settings['services'] ?? '[]', true) ?? [];
            $settings['cases']         = json_decode($settings['cases'] ?? '[]', true) ?? [];
        }

        $tokenBalance = TokenQuota::getBalance($tenantId);

        // Current env/config values for display
        $config = [
            'provider' => env('OPERON_PROVIDER', 'gemini'),
            'tier'     => $tokenBalance['tier'] ?? 'starter',
        ];

        View::render('admin/index', [
            'active'       => 'admin',
            'settings'     => $settings,
            'tokenBalance' => $tokenBalance,
            'config'       => $config,
        ]);
    }

    public function save(): void
    {
        Session::requireAuth();

        if (Session::get('role') !== 'admin') {
            http_response_code(403); return;
        }

        if (!Session::validateCsrf($_POST['_csrf'] ?? '')) {
            Session::flash('error', 'Token inválido.');
            View::redirect('/admin');
        }

        $tenantId = Session::get('tenant_id');
        $db       = Database::getInstance();

        $differentials = array_filter(array_map('trim', explode("\n", $_POST['differentials'] ?? '')));
        $servicesRaw   = $_POST['services'] ?? [];
        $services = [];
        foreach ($servicesRaw as $svc) {
            if (!empty($svc['name'])) {
                $services[] = ['name' => trim($svc['name']), 'price' => (int) ($svc['price'] ?? 0)];
            }
        }

        $data = [
            'agency_name'    => trim($_POST['agency_name'] ?? ''),
            'agency_city'    => trim($_POST['agency_city'] ?? ''),
            'agency_niche'   => trim($_POST['agency_niche'] ?? ''),
            'offer_summary'  => trim($_POST['offer_summary'] ?? ''),
            'icp_profile'    => trim($_POST['icp_profile'] ?? ''),
            'custom_context' => trim($_POST['custom_context'] ?? ''),
            'differentials'  => json_encode(array_values($differentials)),
            'services'       => json_encode($services),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];

        $existing = $db->selectFirst("SELECT id FROM agency_settings WHERE tenant_id = ?", [$tenantId]);

        if ($existing) {
            $sets  = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
            $vals  = array_values($data);
            $vals[] = $tenantId;
            $db->execute("UPDATE agency_settings SET {$sets} WHERE tenant_id = ?", $vals);
        } else {
            $data['id']        = 'settings_' . uniqid();
            $data['tenant_id'] = $tenantId;
            $cols = implode(', ', array_keys($data));
            $phs  = implode(', ', array_fill(0, count($data), '?'));
            $db->execute("INSERT INTO agency_settings ({$cols}) VALUES ({$phs})", array_values($data));
        }

        Session::flash('success', 'Configurações salvas! O contexto da agência foi atualizado para todas as análises de IA.');
        View::redirect('/admin');
    }
}
