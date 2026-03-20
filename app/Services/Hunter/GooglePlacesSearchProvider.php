<?php

declare(strict_types=1);

namespace App\Services\Hunter;

class GooglePlacesSearchProvider implements HunterSearchProviderInterface
{
    public function __construct(
        private readonly ?GooglePlacesService $places = null
    ) {
    }

    public function search(string $segment, string $location, array $filters = []): array
    {
        $service = $this->places ?? new GooglePlacesService();
        return $service->searchBusinesses($segment, $location, $filters);
    }
}
