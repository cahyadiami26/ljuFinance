<?php

declare(strict_types=1);

namespace Lju\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Lapisan akses database: PDO tunggal, prepared statement, helper CRUD.
 * Semua query aplikasi WAJIB melewati kelas ini.
 */
final class Db
{
    private static ?PDO $pdo = null;
    private static ?PDO $sso = null;
    private static ?bool $ssoAvailable = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = (string) Config::get('db.host', 'localhost');
        $port = (int) Config::get('db.port', 3306);
        $name = (string) Config::get('db.name', '');
        $charset = (string) Config::get('db.charset', 'utf8mb4');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

        try {
            self::$pdo = new PDO($dsn, (string) Config::get('db.user', ''), (string) Config::get('db.pass', ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Koneksi database gagal. Periksa konfigurasi pada config/config.local.php. (' . $e->getMessage() . ')',
                0,
                $e
            );
        }

        return self::$pdo;
    }

    /**
     * Koneksi read-only ke database SSO (tabel users).
     * Mengembalikan null bila database SSO tidak tersedia.
     */
    public static function sso(): ?PDO
    {
        if (self::$ssoAvailable === false) {
            return null;
        }
        if (self::$sso instanceof PDO) {
            return self::$sso;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) Config::get('db.host', 'localhost'),
            (int) Config::get('db.port', 3306),
            (string) Config::get('sso.db', ''),
            (string) Config::get('db.charset', 'utf8mb4')
        );

        try {
            self::$sso = new PDO($dsn, (string) Config::get('db.user', ''), (string) Config::get('db.pass', ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            self::$ssoAvailable = false;
            return null;
        }

        return self::$sso;
    }

    public static function available(): bool
    {
        try {
            self::pdo();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array<int|string,mixed> $params */
    public static function q(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * @param array<int|string,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    public static function all(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll();
    }

    /**
     * @param array<int|string,mixed> $params
     * @return array<string,mixed>|null
     */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::q($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<int|string,mixed> $params */
    public static function val(string $sql, array $params = [], mixed $default = null): mixed
    {
        $value = self::q($sql, $params)->fetchColumn();
        return $value === false ? $default : $value;
    }

    /** @param array<int|string,mixed> $params */
    public static function exec(string $sql, array $params = []): int
    {
        return self::q($sql, $params)->rowCount();
    }

    /** @param array<string,mixed> $data */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn ($c) => '`' . $c . '`', $cols)),
            implode(', ', array_map(static fn ($c) => ':' . $c, $cols))
        );
        self::q($sql, self::bindings($data));
        return (int) self::pdo()->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<int|string,mixed> $params
     */
    public static function update(string $table, array $data, string $where, array $params = []): int
    {
        $sets = implode(', ', array_map(static fn ($c) => '`' . $c . '` = :' . $c, array_keys($data)));
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, $sets, $where);
        return self::exec($sql, array_merge(self::bindings($data), $params));
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private static function bindings(array $data): array
    {
        $bound = [];
        foreach ($data as $key => $value) {
            $bound[':' . $key] = is_bool($value) ? (int) $value : $value;
        }
        return $bound;
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();

        // Transaksi bersarang memakai transaksi luar yang sudah aktif; rollback
        // pada transaksi luar otomatis membatalkan seluruh perubahan di dalamnya.
        if ($pdo->inTransaction()) {
            return $callback();
        }

        $pdo->beginTransaction();
        try {
            $result = $callback();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Cache sederhana untuk SHOW TABLES agar dashboard tahan terhadap modul yang belum diimport. */
    public static function tableExists(string $table): bool
    {
        static $cache = [];
        if (!array_key_exists($table, $cache)) {
            $cache[$table] = (bool) self::val(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                [$table]
            );
        }
        return $cache[$table];
    }
}
