<?php

declare(strict_types=1);

namespace App\Services\Hunter;

class WebsiteSignalScanner
{
    public function scan(?string $website): array
    {
        $url = $this->normalizeUrl($website);
        if ($url === null) {
            return [
                'email' => null,
                'instagram' => null,
                'facebook' => null,
                'linkedin' => null,
                'scanned_url' => null,
                'contact_page_scanned' => null,
            ];
        }

        $homepage = $this->fetchHtml($url);
        if ($homepage === null) {
            return [
                'email' => null,
                'instagram' => null,
                'facebook' => null,
                'linkedin' => null,
                'scanned_url' => $url,
                'contact_page_scanned' => null,
            ];
        }

        $signals = $this->extractSignals($homepage, $url);

        if ($signals['email'] === null || $signals['instagram'] === null) {
            $contactUrl = $this->detectContactPage($homepage, $url);
            if ($contactUrl !== null) {
                $contactHtml = $this->fetchHtml($contactUrl);
                if ($contactHtml !== null) {
                    $contactSignals = $this->extractSignals($contactHtml, $contactUrl);
                    $signals = [
                        'email' => $signals['email'] ?? $contactSignals['email'],
                        'instagram' => $signals['instagram'] ?? $contactSignals['instagram'],
                        'facebook' => $signals['facebook'] ?? $contactSignals['facebook'],
                        'linkedin' => $signals['linkedin'] ?? $contactSignals['linkedin'],
                        'scanned_url' => $url,
                        'contact_page_scanned' => $contactUrl,
                    ];
                }
            }
        }

        return $signals;
    }

    private function extractSignals(string $html, string $baseUrl): array
    {
        $emails = [];
        if (preg_match_all('/mailto:([^"\'\\s>]+)/i', $html, $mailtoMatches)) {
            foreach ($mailtoMatches[1] as $value) {
                $email = $this->sanitizeEmail($value);
                if ($email !== null) {
                    $emails[] = $email;
                }
            }
        }

        if (empty($emails) && preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $html, $emailMatches)) {
            foreach ($emailMatches[0] as $value) {
                $email = $this->sanitizeEmail($value);
                if ($email !== null) {
                    $emails[] = $email;
                }
            }
        }

        $links = $this->extractLinks($html, $baseUrl);

        return [
            'email' => $emails[0] ?? null,
            'instagram' => $this->firstMatchingLink($links, 'instagram.com'),
            'facebook' => $this->firstMatchingLink($links, 'facebook.com'),
            'linkedin' => $this->firstMatchingLink($links, 'linkedin.com'),
            'scanned_url' => $baseUrl,
            'contact_page_scanned' => null,
        ];
    }

    private function detectContactPage(string $html, string $baseUrl): ?string
    {
        $links = $this->extractLinks($html, $baseUrl);
        foreach ($links as $link) {
            if (preg_match('#/(contato|contact|fale-conosco|faleconosco|atendimento)\b#i', $link) === 1) {
                return $link;
            }
        }

        return null;
    }

    private function extractLinks(string $html, string $baseUrl): array
    {
        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches);
        $links = [];
        foreach ($matches[1] ?? [] as $href) {
            $url = $this->resolveUrl($href, $baseUrl);
            if ($url !== null) {
                $links[] = $url;
            }
        }

        return array_values(array_unique($links));
    }

    private function firstMatchingLink(array $links, string $needle): ?string
    {
        foreach ($links as $link) {
            if (stripos($link, $needle) !== false) {
                return $link;
            }
        }

        return null;
    }

    private function fetchHtml(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; OperonHunter/1.0; +https://operon.local)',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (PHP_VERSION_ID < 80500) {
            curl_close($ch);
        }

        if ($raw === false || $status >= 400) {
            return null;
        }

        return is_string($raw) ? $raw : null;
    }

    private function sanitizeEmail(string $value): ?string
    {
        $email = trim(strtolower(str_replace(['mailto:', '?subject=', '%20'], ['', '', ' '], $value)));
        $email = preg_replace('/\?.*$/', '', $email ?? '');
        if (!is_string($email) || $email === '') {
            return null;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function normalizeUrl(?string $url): ?string
    {
        $value = trim((string) $url);
        if ($value === '') {
            return null;
        }

        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . $value;
        }

        return filter_var($value, FILTER_VALIDATE_URL) ? $value : null;
    }

    private function resolveUrl(string $href, string $baseUrl): ?string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
            return null;
        }

        if (preg_match('#^https?://#i', $href)) {
            return filter_var($href, FILTER_VALIDATE_URL) ? $href : null;
        }

        if (str_starts_with($href, '//')) {
            return 'https:' . $href;
        }

        if (str_starts_with($href, '/')) {
            $parts = parse_url($baseUrl);
            if (empty($parts['scheme']) || empty($parts['host'])) {
                return null;
            }

            return $parts['scheme'] . '://' . $parts['host'] . $href;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
    }
}
