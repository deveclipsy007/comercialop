<?php

declare(strict_types=1);

namespace App\Services\Extension;

use App\Core\Database;

class LeadNormalizationService
{
    /**
     * Normaliza dados brutos capturados pela extensão.
     */
    public static function normalize(array $data): array
    {
        // Trim em todos os campos string
        $data = array_map(fn($v) => is_string($v) ? trim($v) : $v, $data);

        // Telefone brasileiro
        if (!empty($data['phone'])) {
            $data['phone'] = self::normalizePhone($data['phone']);
        }

        // Email
        if (!empty($data['email'])) {
            $data['email'] = self::normalizeEmail($data['email']);
        }

        // Website
        if (!empty($data['website'])) {
            $data['website'] = self::normalizeWebsite($data['website']);
        }

        // Nome — remove excesso de espaços
        if (!empty($data['name'])) {
            $data['name'] = preg_replace('/\s+/', ' ', trim($data['name']));
            $data['name'] = mb_substr($data['name'], 0, 200);
        }

        // Segment obrigatório
        if (empty($data['segment'])) {
            $data['segment'] = $data['category'] ?? 'Geral';
        }

        // Rating numérico
        if (isset($data['rating'])) {
            $data['rating'] = (float) str_replace(',', '.', (string)$data['rating']);
            if ($data['rating'] < 0 || $data['rating'] > 5) {
                $data['rating'] = null;
            }
        }

        // Review count
        if (isset($data['review_count'])) {
            $data['review_count'] = (int) preg_replace('/\D/', '', (string)$data['review_count']);
        }

        // Google Maps URL — limpa parâmetros desnecessários
        if (!empty($data['google_maps_url'])) {
            $data['google_maps_url'] = strtok($data['google_maps_url'], '&');
        }

        return $data;
    }

    /**
     * Normaliza telefone brasileiro.
     */
    public static function normalizePhone(string $phone): ?string
    {
        // Remove tudo que não é dígito ou +
        $clean = preg_replace('/[^\d+]/', '', $phone);

        // Remove + inicial e trata código do país
        if (str_starts_with($clean, '+55')) {
            $clean = substr($clean, 3);
        } elseif (str_starts_with($clean, '55') && strlen($clean) >= 12) {
            $clean = substr($clean, 2);
        } elseif (str_starts_with($clean, '+')) {
            $clean = substr($clean, 1);
        }

        // Formata: (XX) XXXXX-XXXX ou (XX) XXXX-XXXX
        if (strlen($clean) === 11) {
            return '(' . substr($clean, 0, 2) . ') ' . substr($clean, 2, 5) . '-' . substr($clean, 7);
        }
        if (strlen($clean) === 10) {
            return '(' . substr($clean, 0, 2) . ') ' . substr($clean, 2, 4) . '-' . substr($clean, 6);
        }

        // Se tem tamanho mínimo razoável, retorna limpo
        return strlen($clean) >= 8 ? $clean : null;
    }

    /**
     * Normaliza email.
     */
    public static function normalizeEmail(string $email): ?string
    {
        $email = strtolower(trim($email));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * Normaliza website URL.
     */
    public static function normalizeWebsite(string $url): ?string
    {
        $url = trim($url);
        if (empty($url)) return null;

        // Adiciona scheme se necessário
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        // Remove tracking params comuns
        $url = preg_replace('/[?&](utm_\w+|fbclid|gclid|ref)=[^&]*/', '', $url);
        $url = rtrim($url, '?&');

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /**
     * Verifica se já existe um lead duplicado no tenant.
     * Retorna o lead existente ou null.
     */
    public static function checkDuplicate(string $tenantId, array $data): ?array
    {
        // 1. Match por email (mais confiável)
        if (!empty($data['email'])) {
            $existing = Database::selectFirst(
                "SELECT id, name, pipeline_status, email FROM leads
                 WHERE tenant_id = ? AND LOWER(email) = LOWER(?)",
                [$tenantId, $data['email']]
            );
            if ($existing) return $existing;
        }

        // 2. Match por telefone (últimos 8 dígitos)
        if (!empty($data['phone'])) {
            $digits = preg_replace('/\D/', '', $data['phone']);
            if (strlen($digits) >= 8) {
                $suffix = substr($digits, -8);
                $existing = Database::selectFirst(
                    "SELECT id, name, pipeline_status, phone FROM leads
                     WHERE tenant_id = ? AND phone LIKE ?",
                    [$tenantId, '%' . $suffix]
                );
                if ($existing) return $existing;
            }
        }

        // 3. Match por Google Maps URL (exato)
        if (!empty($data['google_maps_url'])) {
            $existing = Database::selectFirst(
                "SELECT id, name, pipeline_status FROM leads
                 WHERE tenant_id = ? AND google_maps_url = ?",
                [$tenantId, $data['google_maps_url']]
            );
            if ($existing) return $existing;
        }

        // 4. Match por website (domínio)
        if (!empty($data['website'])) {
            $domain = parse_url($data['website'], PHP_URL_HOST);
            if ($domain) {
                $existing = Database::selectFirst(
                    "SELECT id, name, pipeline_status FROM leads
                     WHERE tenant_id = ? AND website LIKE ?",
                    [$tenantId, '%' . $domain . '%']
                );
                if ($existing) return $existing;
            }
        }

        // 5. Match por nome exato (case-insensitive)
        if (!empty($data['name'])) {
            $existing = Database::selectFirst(
                "SELECT id, name, pipeline_status FROM leads
                 WHERE tenant_id = ? AND LOWER(name) = LOWER(?)",
                [$tenantId, $data['name']]
            );
            if ($existing) return $existing;
        }

        return null;
    }
}
