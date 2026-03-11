<?php

declare(strict_types=1);

namespace App\Services\Hunter;

interface HunterSearchProviderInterface
{
    /**
     * Executes a search and returns an array of structured results.
     * 
     * @param string $segment The target segment (e.g. "Dentists")
     * @param string $location The location constraint
     * @param array $filters Additional filters
     * @return array Array of associative arrays with raw company data
     */
    public function search(string $segment, string $location, array $filters = []): array;
}
