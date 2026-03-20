<?php

declare(strict_types=1);

namespace App\Services\Hunter;

use App\Models\HunterResult;

class HunterEnrichmentService
{
    public function enrich(string $resultId, string $tenantId): bool
    {
        $result = HunterResult::findById($resultId, $tenantId);
        if (!$result) {
            return false;
        }

        $places = new GooglePlacesService(null, $tenantId);
        $raw = is_array($result['raw_source_data'] ?? null) ? $result['raw_source_data'] : [];
        $placeId = trim((string) ($result['place_id'] ?? $raw['place_id'] ?? ''));

        $updateData = [];
        $updatedRaw = $raw;

        if ($placeId !== '' && $places->isConfigured()) {
            $details = $places->fetchPlaceDetails($placeId, [
                'segment_query' => $result['segment'] ?? '',
                'location_query' => trim((string) (($result['city'] ?? '') . ' ' . ($result['state'] ?? ''))),
            ]);

            if ($details) {
                $updateData = array_merge($updateData, [
                    'name' => $details['name'] ?? $result['name'],
                    'segment' => $details['segment'] ?? $result['segment'],
                    'address' => $details['address'] ?? $result['address'],
                    'city' => $details['city'] ?? $result['city'],
                    'phone' => $details['phone'] ?? $result['phone'],
                    'website' => $details['website'] ?? $result['website'],
                    'google_rating' => $details['google_rating'] ?? $result['google_rating'],
                    'google_reviews' => $details['google_reviews'] ?? $result['google_reviews'],
                    'data_source' => 'google_places_api',
                ]);
                $updatedRaw = $this->mergeRawData($updatedRaw, $details['raw_source_data'] ?? []);
            }
        }

        $website = $updateData['website'] ?? $result['website'] ?? null;
        $scanner = new WebsiteSignalScanner();
        $scan = $scanner->scan(is_string($website) ? $website : null);
        if (!empty($scan['email'])) {
            $updateData['email'] = $scan['email'];
        }
        if (!empty($scan['instagram'])) {
            $updateData['instagram'] = $scan['instagram'];
        }

        $updatedRaw = $this->mergeWebsiteSignals($updatedRaw, $scan);
        $updateData['raw_source_data'] = $updatedRaw;

        return HunterResult::update($resultId, $tenantId, $updateData);
    }

    private function mergeRawData(array $current, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if (is_array($value)) {
                $current[$key] = $this->mergeRawData(is_array($current[$key] ?? null) ? $current[$key] : [], $value);
                continue;
            }

            if ($value !== null && $value !== '') {
                $current[$key] = $value;
            } elseif (!array_key_exists($key, $current)) {
                $current[$key] = $value;
            }
        }

        return $current;
    }

    private function mergeWebsiteSignals(array $raw, array $scan): array
    {
        $raw['website_scan'] = [
            'verified_at' => date('c'),
            'scanned_url' => $scan['scanned_url'] ?? null,
            'contact_page_scanned' => $scan['contact_page_scanned'] ?? null,
            'email' => $scan['email'] ?? null,
            'instagram' => $scan['instagram'] ?? null,
            'facebook' => $scan['facebook'] ?? null,
            'linkedin' => $scan['linkedin'] ?? null,
        ];

        $raw['field_statuses'] = is_array($raw['field_statuses'] ?? null) ? $raw['field_statuses'] : [];
        $raw['verification'] = is_array($raw['verification'] ?? null) ? $raw['verification'] : [];
        $raw['verification']['field_sources'] = is_array($raw['verification']['field_sources'] ?? null)
            ? $raw['verification']['field_sources']
            : [];
        $raw['digital_presence'] = is_array($raw['digital_presence'] ?? null) ? $raw['digital_presence'] : [];

        if (!empty($scan['email'])) {
            $raw['field_statuses']['email'] = 'confirmed';
            $raw['verification']['field_sources']['email'] = 'website_scan';
            $raw['digital_presence']['email'] = [
                'label' => 'Email público',
                'status' => 'confirmed',
                'source' => 'website_scan',
            ];
        } elseif (!isset($raw['field_statuses']['email'])) {
            $raw['field_statuses']['email'] = 'not_found';
        }

        if (!empty($scan['instagram'])) {
            $raw['field_statuses']['instagram'] = 'confirmed';
            $raw['verification']['field_sources']['instagram'] = 'website_scan';
            $raw['digital_presence']['instagram'] = [
                'label' => 'Instagram',
                'status' => 'confirmed',
                'source' => 'website_scan',
            ];
        } elseif (!isset($raw['field_statuses']['instagram'])) {
            $raw['field_statuses']['instagram'] = 'not_found';
        }

        $raw['digital_presence']['facebook'] = [
            'label' => 'Facebook',
            'status' => !empty($scan['facebook']) ? 'confirmed' : 'not_found',
            'source' => !empty($scan['facebook']) ? 'website_scan' : null,
        ];

        $raw['digital_presence']['linkedin'] = [
            'label' => 'LinkedIn',
            'status' => !empty($scan['linkedin']) ? 'confirmed' : 'not_found',
            'source' => !empty($scan['linkedin']) ? 'website_scan' : null,
        ];

        $notes = is_array($raw['import_notes'] ?? null) ? $raw['import_notes'] : [];
        if (!empty($scan['email'])) {
            $notes[] = 'Email público confirmado no site oficial.';
        } else {
            $notes[] = 'Email público não encontrado no site oficial.';
        }
        if (!empty($scan['instagram'])) {
            $notes[] = 'Instagram confirmado a partir do site oficial.';
        } else {
            $notes[] = 'Instagram não encontrado no site oficial.';
        }
        if (!empty($scan['facebook'])) {
            $notes[] = 'Facebook confirmado a partir do site oficial.';
        }
        if (!empty($scan['linkedin'])) {
            $notes[] = 'LinkedIn confirmado a partir do site oficial.';
        }
        $raw['import_notes'] = array_values(array_unique($notes));

        return $raw;
    }
}
