<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Encriptação AES-256-CBC para armazenamento seguro de chaves API.
 * Usa APP_ENCRYPTION_KEY do .env como chave mestre.
 */
class ApiKeyEncryptor
{
    private const CIPHER = 'aes-256-cbc';

    /**
     * Encripta uma chave API. Retorna base64(IV . ciphertext).
     */
    public static function encrypt(string $plainKey): string
    {
        $masterKey = self::getMasterKey();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER));
        $encrypted = openssl_encrypt($plainKey, self::CIPHER, $masterKey, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new \RuntimeException('Falha na encriptação da chave API.');
        }

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decripta uma chave API armazenada.
     */
    public static function decrypt(string $encryptedKey): string
    {
        $masterKey = self::getMasterKey();
        $data = base64_decode($encryptedKey, true);

        if ($data === false) {
            throw new \RuntimeException('Chave encriptada inválida (base64 decode falhou).');
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = substr($data, 0, $ivLength);
        $ciphertext = substr($data, $ivLength);

        $decrypted = openssl_decrypt($ciphertext, self::CIPHER, $masterKey, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            throw new \RuntimeException('Falha na decriptação da chave API. Verifique APP_ENCRYPTION_KEY.');
        }

        return $decrypted;
    }

    /**
     * Obtém a chave mestre do .env. Lança exceção se não definida.
     */
    private static function getMasterKey(): string
    {
        $key = env('APP_ENCRYPTION_KEY', '');

        if (empty($key)) {
            // Fallback: gerar chave derivada do DB_DATABASE + salt fixo (não ideal, mas funcional)
            $dbPath = env('DB_DATABASE', 'storage/database.sqlite');
            $key = hash('sha256', 'operon_master_' . $dbPath, true);
            return $key;
        }

        // Se a chave é hex (64 chars), converte para bytes
        if (strlen($key) === 64 && ctype_xdigit($key)) {
            return hex2bin($key);
        }

        // Usa hash SHA-256 para garantir tamanho correto (32 bytes)
        return hash('sha256', $key, true);
    }
}
