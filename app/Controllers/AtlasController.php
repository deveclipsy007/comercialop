<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Core\Session;
use App\Core\Database;
use App\Models\Lead;
use App\Services\Hunter\GooglePlacesService;

class AtlasController
{
    public function __construct()
    {
        $this->ensureColumns();
    }

    public function index(): void
    {
        Session::requireAuth();
        $tenantId = Session::get('tenant_id');

        // Leads with address for map plotting
        $leads = Lead::allByTenant($tenantId, ['limit' => 200, 'order' => 'priority_score DESC']);

        // Filter for ones with address, build GeoJSON-like data
        $mapLeads = array_filter($leads, fn($l) => !empty($l['address']));
        $mapLeads = array_values($mapLeads);

        // Check if Google Places is configured (for geocoding button)
        $placesConfigured = false;
        try {
            $places = new GooglePlacesService(null, $tenantId);
            $placesConfigured = $places->isConfigured();
        } catch (\Throwable $e) {}

        // Count leads needing geocoding
        $needsGeocoding = count(array_filter($mapLeads, fn($l) => empty($l['latitude'])));

        // Stats for the sidebar
        $stats = [
            'total'    => count($leads),
            'mapped'   => count($mapLeads),
            'geocoded' => count($mapLeads) - $needsGeocoding,
            'high'     => count(array_filter($leads, fn($l) => ($l['priority_score'] ?? 0) >= 70)),
            'segments' => $this->countBySegment($leads),
        ];

        View::render('atlas/index', [
            'active'           => 'atlas',
            'leads'            => $leads,
            'mapLeads'         => $mapLeads,
            'mapLeadsJson'     => json_encode($mapLeads),
            'stats'            => $stats,
            'placesConfigured' => $placesConfigured,
            'needsGeocoding'   => $needsGeocoding,
        ]);
    }

    /**
     * Geocode leads that don't have coordinates yet.
     * Uses Google Geocoding API via GooglePlacesService.
     */
    public function geocode(): void
    {
        Session::requireAuth();
        Session::verifyCsrf();
        $tenantId = Session::get('tenant_id');

        try {
            $places = new GooglePlacesService(null, $tenantId);
            $places->assertConfigured();
        } catch (\Throwable $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Google Places API não configurada. Cadastre a chave em Admin > Chaves de API.']);
            return;
        }

        // Get leads with address but no coordinates
        $leads = Database::select(
            'SELECT id, address FROM leads
             WHERE tenant_id = ? AND address IS NOT NULL AND address <> ""
               AND (latitude IS NULL OR longitude IS NULL)
             LIMIT 25',
            [$tenantId]
        );

        $geocoded = 0;
        $errors   = 0;

        foreach ($leads as $lead) {
            try {
                $coords = $places->geocodeLocation($lead['address']);
                if ($coords) {
                    Lead::update($lead['id'], $tenantId, [
                        'latitude'    => $coords['lat'],
                        'longitude'   => $coords['lng'],
                        'geocoded_at' => date('Y-m-d H:i:s'),
                    ]);
                    $geocoded++;
                } else {
                    $errors++;
                }
            } catch (\Throwable $e) {
                $errors++;
                error_log('[Atlas Geocode] Error for lead ' . $lead['id'] . ': ' . $e->getMessage());
            }

            // Small delay to respect API rate limits
            usleep(100_000); // 100ms
        }

        // Count remaining
        $remaining = Database::selectFirst(
            'SELECT COUNT(*) as cnt FROM leads
             WHERE tenant_id = ? AND address IS NOT NULL AND address <> ""
               AND (latitude IS NULL OR longitude IS NULL)',
            [$tenantId]
        );

        header('Content-Type: application/json');
        echo json_encode([
            'success'   => true,
            'geocoded'  => $geocoded,
            'errors'    => $errors,
            'remaining' => (int) ($remaining['cnt'] ?? 0),
        ]);
    }

    private function countBySegment(array $leads): array
    {
        $counts = [];
        foreach ($leads as $lead) {
            $seg = $lead['segment'] ?? 'Outros';
            $counts[$seg] = ($counts[$seg] ?? 0) + 1;
        }
        arsort($counts);
        return array_slice($counts, 0, 8, true);
    }

    private function ensureColumns(): void
    {
        try {
            $cols = Database::select("PRAGMA table_info(leads)");
            $colNames = array_column($cols, 'name');
            if (!in_array('latitude', $colNames, true)) {
                Database::execute('ALTER TABLE leads ADD COLUMN latitude REAL');
                Database::execute('ALTER TABLE leads ADD COLUMN longitude REAL');
                Database::execute('ALTER TABLE leads ADD COLUMN geocoded_at TEXT');
            }
        } catch (\Throwable $e) {
            error_log('[Atlas] ensureColumns: ' . $e->getMessage());
        }
    }
}
