<?php

declare(strict_types=1);

namespace App\Services\Hunter;

use App\Core\Session;
use App\Models\AiApiKey;

class GooglePlacesService
{
    private const PLACES_BASE_URL = 'https://places.googleapis.com/v1';
    private const GEOCODE_URL = 'https://maps.googleapis.com/maps/api/geocode/json';
    private const SEARCH_FIELD_MASK = 'places.id,places.displayName,places.formattedAddress,places.addressComponents,places.location,places.googleMapsUri,places.websiteUri,places.nationalPhoneNumber,places.internationalPhoneNumber,places.rating,places.userRatingCount,places.regularOpeningHours,places.currentOpeningHours,places.businessStatus,places.primaryTypeDisplayName,places.primaryType,places.types';
    private const DETAILS_FIELD_MASK = 'id,displayName,formattedAddress,addressComponents,location,googleMapsUri,websiteUri,nationalPhoneNumber,internationalPhoneNumber,rating,userRatingCount,regularOpeningHours,currentOpeningHours,businessStatus,primaryTypeDisplayName,primaryType,types';

    private string $apiKey;

    public function __construct(?string $apiKey = null, ?string $tenantId = null)
    {
        $resolvedTenantId = $tenantId ?: (Session::get('tenant_id') ?: null);
        $this->apiKey = trim((string) ($apiKey ?: AiApiKey::getDecryptedKey('google_places', $resolvedTenantId)));
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function assertConfigured(): void
    {
        if ($this->isConfigured()) {
            return;
        }

        throw new \RuntimeException(
            'Google Places API não está configurada. Cadastre a chave em Admin > Chaves de API > Google Maps Places.'
        );
    }

    public function testConnection(): array
    {
        $this->assertConfigured();

        $response = $this->request(
            'POST',
            self::PLACES_BASE_URL . '/places:searchText',
            [
                'X-Goog-Api-Key: ' . $this->apiKey,
                'X-Goog-FieldMask: places.id',
            ],
            [
                'textQuery' => 'restaurantes em São Paulo',
                'pageSize' => 1,
                'languageCode' => 'pt-BR',
                'regionCode' => 'BR',
            ]
        );

        if (!isset($response['places']) || !is_array($response['places'])) {
            throw new \RuntimeException('Google Places respondeu sem estrutura de places.');
        }

        return [
            'success' => true,
            'message' => 'Google Places respondeu corretamente à busca estruturada.',
        ];
    }

    public function searchBusinesses(string $segment, string $location, array $filters = []): array
    {
        $this->assertConfigured();

        $maxResults = max(1, min(20, (int) ($filters['max_results'] ?? 12)));
        $radiusKm = max(1, min(100, (int) ($filters['radius'] ?? 5)));
        $query = trim($segment . ' em ' . $location);

        $body = [
            'textQuery' => $query,
            'pageSize' => $maxResults,
            'languageCode' => 'pt-BR',
            'regionCode' => 'BR',
        ];

        $coords = $this->geocodeLocation($location);
        if ($coords) {
            $body['locationBias'] = [
                'circle' => [
                    'center' => [
                        'latitude' => $coords['lat'],
                        'longitude' => $coords['lng'],
                    ],
                    'radius' => $radiusKm * 1000,
                ],
            ];
        }

        $response = $this->request(
            'POST',
            self::PLACES_BASE_URL . '/places:searchText',
            [
                'X-Goog-Api-Key: ' . $this->apiKey,
                'X-Goog-FieldMask: ' . self::SEARCH_FIELD_MASK,
            ],
            $body
        );

        $places = is_array($response['places'] ?? null) ? $response['places'] : [];
        if (empty($places) && isset($body['locationBias'])) {
            unset($body['locationBias']);
            $response = $this->request(
                'POST',
                self::PLACES_BASE_URL . '/places:searchText',
                [
                    'X-Goog-Api-Key: ' . $this->apiKey,
                    'X-Goog-FieldMask: ' . self::SEARCH_FIELD_MASK,
                ],
                $body
            );
            $places = is_array($response['places'] ?? null) ? $response['places'] : [];
        }

        $results = [];
        foreach ($places as $place) {
            $normalized = $this->normalizePlace($place, [
                'segment_query' => $segment,
                'location_query' => $location,
                'radius_km' => $radiusKm,
                'search_query' => $query,
            ]);
            if ($normalized === null) {
                continue;
            }

            if (!$this->passesFilters($normalized, $filters)) {
                continue;
            }

            $dedupeKey = $normalized['place_id'] ?: md5(mb_strtolower(($normalized['name'] ?? '') . '|' . ($normalized['address'] ?? '')));
            $results[$dedupeKey] = $normalized;
        }

        return array_values($results);
    }

    public function fetchPlaceDetails(string $placeId, array $context = []): ?array
    {
        $placeId = trim($placeId);
        if ($placeId === '') {
            return null;
        }

        $this->assertConfigured();

        $response = $this->request(
            'GET',
            self::PLACES_BASE_URL . '/places/' . rawurlencode($placeId) . '?languageCode=pt-BR&regionCode=BR',
            [
                'X-Goog-Api-Key: ' . $this->apiKey,
                'X-Goog-FieldMask: ' . self::DETAILS_FIELD_MASK,
            ]
        );

        return $this->normalizePlace($response, $context);
    }

    private function normalizePlace(array $place, array $context = []): ?array
    {
        $name = trim((string) ($place['displayName']['text'] ?? ''));
        $placeId = trim((string) ($place['id'] ?? ''));
        if ($name === '' || $placeId === '') {
            return null;
        }

        [$city, $state] = $this->extractCityState($place['addressComponents'] ?? []);
        $website = $this->normalizeUrl($place['websiteUri'] ?? null);
        $mapsUrl = $this->normalizeUrl($place['googleMapsUri'] ?? null);
        $phone = $this->normalizePhone($place['nationalPhoneNumber'] ?? $place['internationalPhoneNumber'] ?? null);
        $category = trim((string) ($place['primaryTypeDisplayName']['text'] ?? $context['segment_query'] ?? ''));
        $rating = isset($place['rating']) ? (float) $place['rating'] : null;
        $reviewCount = isset($place['userRatingCount']) ? (int) $place['userRatingCount'] : null;
        $openingHours = $this->extractWeekdayDescriptions($place);
        $status = (string) ($place['businessStatus'] ?? '');
        $openNow = $place['currentOpeningHours']['openNow'] ?? null;
        $statusLabel = $this->buildStatusLabel($status, $openNow);
        $segment = trim((string) ($context['segment_query'] ?? $category));

        $fieldStatuses = [
            'name' => 'confirmed',
            'category' => $category !== '' ? 'confirmed' : 'not_found',
            'address' => !empty($place['formattedAddress']) ? 'confirmed' : 'not_found',
            'city' => $city !== '' ? 'confirmed' : 'not_found',
            'state' => $state !== '' ? 'confirmed' : 'not_found',
            'phone' => $phone !== null ? 'confirmed' : 'not_found',
            'website' => $website !== null ? 'confirmed' : 'not_found',
            'google_maps_url' => $mapsUrl !== null ? 'confirmed' : 'not_found',
            'google_rating' => $rating !== null ? 'confirmed' : 'not_found',
            'google_reviews' => $reviewCount !== null ? 'confirmed' : 'not_found',
            'opening_hours' => !empty($openingHours) ? 'confirmed' : 'not_found',
            'status' => $statusLabel !== null ? 'confirmed' : 'not_found',
            'email' => 'not_found',
            'instagram' => 'not_found',
        ];

        $digitalPresence = [
            'google_maps' => [
                'label' => 'Perfil no Google Maps',
                'status' => 'confirmed',
                'source' => 'google_places_api',
            ],
            'website' => [
                'label' => 'Site oficial',
                'status' => $website !== null ? 'confirmed' : 'not_found',
                'source' => $website !== null ? 'google_places_api' : null,
            ],
            'reviews' => [
                'label' => 'Avaliações no Google',
                'status' => ($reviewCount ?? 0) > 0 ? 'confirmed' : 'not_found',
                'source' => ($reviewCount ?? 0) > 0 ? 'google_places_api' : null,
            ],
            'phone' => [
                'label' => 'Telefone comercial',
                'status' => $phone !== null ? 'confirmed' : 'not_found',
                'source' => $phone !== null ? 'google_places_api' : null,
            ],
            'hours' => [
                'label' => 'Horário de funcionamento',
                'status' => !empty($openingHours) ? 'confirmed' : 'not_found',
                'source' => !empty($openingHours) ? 'google_places_api' : null,
            ],
            'email' => [
                'label' => 'Email público',
                'status' => 'not_found',
                'source' => null,
            ],
            'instagram' => [
                'label' => 'Instagram',
                'status' => 'not_found',
                'source' => null,
            ],
        ];

        $importNotes = array_values(array_filter([
            'Origem principal: Google Maps / Google Places API.',
            $website !== null ? 'Site oficial confirmado no Google Places.' : 'Site oficial não encontrado no Google Places.',
            $phone !== null ? 'Telefone comercial confirmado no Google Places.' : 'Telefone comercial não encontrado no Google Places.',
            $mapsUrl !== null ? 'Link do Google Maps confirmado.' : 'Link do Google Maps não retornado.',
            ($reviewCount ?? 0) > 0 ? 'Volume de avaliações do Google confirmado.' : 'Sem avaliações confirmadas no Google.',
        ]));

        $raw = [
            'place_id' => $placeId,
            'google_maps_url' => $mapsUrl,
            'category' => $category !== '' ? $category : null,
            'state' => $state !== '' ? $state : null,
            'status' => $status !== '' ? $status : null,
            'status_label' => $statusLabel,
            'open_now' => is_bool($openNow) ? $openNow : null,
            'opening_hours' => $openingHours,
            'opening_hours_text' => !empty($openingHours) ? implode(' | ', $openingHours) : null,
            'verification' => [
                'source' => 'google_places_api',
                'source_label' => 'Google Maps',
                'verified_at' => date('c'),
                'field_sources' => [
                    'name' => 'google_places_api',
                    'category' => 'google_places_api',
                    'address' => 'google_places_api',
                    'city' => 'google_places_api',
                    'state' => 'google_places_api',
                    'phone' => $phone !== null ? 'google_places_api' : null,
                    'website' => $website !== null ? 'google_places_api' : null,
                    'google_maps_url' => $mapsUrl !== null ? 'google_places_api' : null,
                    'google_rating' => $rating !== null ? 'google_places_api' : null,
                    'google_reviews' => $reviewCount !== null ? 'google_places_api' : null,
                    'opening_hours' => !empty($openingHours) ? 'google_places_api' : null,
                    'status' => $statusLabel !== null ? 'google_places_api' : null,
                ],
            ],
            'field_statuses' => $fieldStatuses,
            'digital_presence' => $digitalPresence,
            'import_notes' => $importNotes,
            'search_context' => [
                'segment_query' => $context['segment_query'] ?? null,
                'location_query' => $context['location_query'] ?? null,
                'radius_km' => $context['radius_km'] ?? null,
                'search_query' => $context['search_query'] ?? null,
            ],
            'chain_signals' => $this->detectChainSignals($name, $website),
            'source_snapshot' => [
                'display_name' => $name,
                'formatted_address' => $place['formattedAddress'] ?? null,
                'primary_type' => $place['primaryType'] ?? null,
                'types' => $place['types'] ?? [],
                'business_status' => $status !== '' ? $status : null,
            ],
        ];

        return [
            'name' => $name,
            'segment' => $segment !== '' ? $segment : null,
            'address' => $place['formattedAddress'] ?? null,
            'city' => $city !== '' ? $city : null,
            'state' => $state !== '' ? $state : null,
            'phone' => $phone,
            'website' => $website,
            'email' => null,
            'instagram' => null,
            'google_rating' => $rating,
            'google_reviews' => $reviewCount,
            'google_maps_url' => $mapsUrl,
            'category' => $category !== '' ? $category : null,
            'status' => $status !== '' ? $status : null,
            'status_label' => $statusLabel,
            'open_now' => is_bool($openNow) ? $openNow : null,
            'opening_hours' => $openingHours,
            'opening_hours_text' => !empty($openingHours) ? implode(' | ', $openingHours) : null,
            'latitude' => isset($place['location']['latitude']) ? (float) $place['location']['latitude'] : null,
            'longitude' => isset($place['location']['longitude']) ? (float) $place['location']['longitude'] : null,
            'place_id' => $placeId,
            'data_source' => 'google_places_api',
            'raw_source_data' => $raw,
        ];
    }

    private function passesFilters(array $result, array $filters): bool
    {
        $exclusions = array_map('strval', $filters['exclusions'] ?? []);

        if (in_array('sem-site', $exclusions, true) && empty($result['website'])) {
            return false;
        }

        if (in_array('franquias', $exclusions, true)) {
            $chainSignals = $result['raw_source_data']['chain_signals'] ?? [];
            if (!empty($chainSignals)) {
                return false;
            }
        }

        return true;
    }

    public function geocodeLocation(string $location): ?array
    {
        $response = $this->request(
            'GET',
            self::GEOCODE_URL . '?address=' . rawurlencode($location) . '&key=' . rawurlencode($this->apiKey)
        );

        if (($response['status'] ?? '') !== 'OK') {
            return null;
        }

        $locationData = $response['results'][0]['geometry']['location'] ?? null;
        if (!is_array($locationData) || !isset($locationData['lat'], $locationData['lng'])) {
            return null;
        }

        return [
            'lat' => (float) $locationData['lat'],
            'lng' => (float) $locationData['lng'],
        ];
    }

    private function request(string $method, string $url, array $headers = [], ?array $body = null): array
    {
        $ch = curl_init($url);
        $httpHeaders = array_merge(['Accept: application/json'], $headers);

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $httpHeaders,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
        ];

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }

        curl_setopt_array($ch, $options);

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (PHP_VERSION_ID < 80500) {
            curl_close($ch);
        }

        if ($raw === false) {
            throw new \RuntimeException('Falha na comunicação com Google Places: ' . $error);
        }

        $decoded = json_decode($raw, true);
        if ($status >= 400) {
            $message = $decoded['error']['message'] ?? substr($raw, 0, 220);
            throw new \RuntimeException('Google Places HTTP ' . $status . ': ' . $message);
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function extractCityState(array $components): array
    {
        $city = '';
        $state = '';

        foreach ($components as $component) {
            $types = $component['types'] ?? [];
            if ($city === '' && (in_array('administrative_area_level_2', $types, true) || in_array('locality', $types, true))) {
                $city = trim((string) ($component['longText'] ?? $component['shortText'] ?? ''));
            }
            if ($state === '' && in_array('administrative_area_level_1', $types, true)) {
                $state = trim((string) ($component['shortText'] ?? $component['longText'] ?? ''));
            }
        }

        return [$city, $state];
    }

    private function extractWeekdayDescriptions(array $place): array
    {
        $descriptions = $place['currentOpeningHours']['weekdayDescriptions']
            ?? $place['regularOpeningHours']['weekdayDescriptions']
            ?? [];

        if (!is_array($descriptions)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn($value): string => trim((string) $value),
            $descriptions
        )));
    }

    private function buildStatusLabel(string $businessStatus, mixed $openNow): ?string
    {
        if (is_bool($openNow)) {
            return $openNow ? 'Aberto agora' : 'Fechado agora';
        }

        return match ($businessStatus) {
            'OPERATIONAL' => 'Operando',
            'CLOSED_TEMPORARILY' => 'Temporariamente fechado',
            'CLOSED_PERMANENTLY' => 'Fechado permanentemente',
            default => null,
        };
    }

    private function normalizeUrl(mixed $value): ?string
    {
        $url = trim((string) $value);
        if ($url === '') {
            return null;
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    private function normalizePhone(mixed $value): ?string
    {
        $phone = trim((string) $value);
        return $phone !== '' ? $phone : null;
    }

    private function detectChainSignals(string $name, ?string $website): array
    {
        $signals = [];
        $haystacks = [$name, (string) $website];
        $patterns = [
            '/\bfranquia(s)?\b/i' => 'keyword_franquia',
            '/\brede(s)?\b/i' => 'keyword_rede',
            '/\bunidade\b/i' => 'keyword_unidade',
            '/\/unidades\b/i' => 'website_units_page',
            '/\/franqueado(s)?\b/i' => 'website_franchise_page',
        ];

        foreach ($patterns as $pattern => $signal) {
            foreach ($haystacks as $haystack) {
                if ($haystack !== '' && preg_match($pattern, $haystack) === 1) {
                    $signals[] = $signal;
                    break;
                }
            }
        }

        return array_values(array_unique($signals));
    }
}
