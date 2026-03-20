<?php

declare(strict_types=1);

namespace App\Services\Hunter;

/**
 * Compat wrapper kept to avoid accidental fallback to prompt-based search.
 * Hunter search must remain grounded on Google Places / Maps data only.
 */
class GeminiPlacesSearchProvider implements HunterSearchProviderInterface
{
    public function __construct(
        private readonly ?GooglePlacesSearchProvider $provider = null
    ) {
    }

    public function search(string $segment, string $location, array $filters = []): array
    {
        $provider = $this->provider ?? new GooglePlacesSearchProvider();
        return $provider->search($segment, $location, $filters);
    }
}
