<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            self::$connection = self::createConnection();
        }
        return self::$connection;
    }

    private static function createConnection(): PDO
    {
        $driver = env('DB_CONNECTION', 'sqlite');

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            if ($driver === 'sqlite') {
                $dbPath = ROOT_PATH . '/' . env('DB_DATABASE', 'database/operon.db');
                $dir    = dirname($dbPath);
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $pdo = new PDO("sqlite:{$dbPath}", null, null, $options);
                $pdo->exec('PRAGMA foreign_keys = ON;');
                $pdo->exec('PRAGMA journal_mode = WAL;');
                return $pdo;
            }

            if ($driver === 'pgsql') {
                $dsn = sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s',
                    env('DB_HOST', '127.0.0.1'),
                    env('DB_PORT', '5432'),
                    env('DB_DATABASE', 'operon')
                );
                return new PDO($dsn, env('DB_USERNAME'), env('DB_PASSWORD'), $options);
            }

            throw new \RuntimeException("Unsupported DB driver: {$driver}");
        } catch (PDOException $e) {
            throw new \RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Executa uma query preparada e retorna todos os resultados.
     */
    public static function select(string $sql, array $bindings = []): array
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll();
    }

    /**
     * Executa uma query preparada e retorna o primeiro resultado.
     */
    public static function selectFirst(string $sql, array $bindings = []): ?array
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($bindings);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Executa INSERT/UPDATE/DELETE. Retorna linhas afetadas.
     */
    public static function execute(string $sql, array $bindings = []): int
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    /**
     * Retorna o último ID inserido.
     */
    public static function lastInsertId(): string
    {
        return self::connection()->lastInsertId();
    }

    /**
     * Inicia uma transação.
     */
    public static function beginTransaction(): bool
    {
        return self::connection()->beginTransaction();
    }

    /**
     * Confirma a transação.
     */
    public static function commit(): bool
    {
        return self::connection()->commit();
    }

    /**
     * Reverte a transação.
     */
    public static function rollback(): bool
    {
        return self::connection()->rollBack();
    }
}
