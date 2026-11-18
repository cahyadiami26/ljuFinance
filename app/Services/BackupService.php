<?php

declare(strict_types=1);

namespace Lju\Services;

use Lju\Core\Db;

/**
 * Cadangan database tanpa dependensi eksternal.
 *
 * Menghasilkan file SQL mandiri (struktur + data) di `storage/backups`.
 * File tidak dapat diakses langsung karena `.htaccess` memblokir `.sql`;
 * unduhan selalu melalui controller ber-permission.
 */
final class BackupService
{
    private const POLA = '/^lju-finance-\d{8}-\d{6}(-\d+)?\.sql$/';

    public static function dir(): string
    {
        $dir = BASE_PATH . '/storage/backups';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Folder cadangan tidak dapat dibuat: ' . $dir);
        }
        return $dir;
    }

    /**
     * Buat cadangan baru.
     *
     * @return array{file:string,size:int,tables:int,rows:int,duration:float}
     */
    public static function buat(?string $catatan = null): array
    {
        $mulai = microtime(true);
        $dir = self::dir();
        $pdo = Db::pdo();

        $nama = 'lju-finance-' . date('Ymd-His') . '.sql';
        $path = $dir . '/' . $nama;
        $nomor = 1;
        while (is_file($path)) {
            $nama = 'lju-finance-' . date('Ymd-His') . '-' . $nomor . '.sql';
            $path = $dir . '/' . $nama;
            $nomor++;
        }

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('File cadangan tidak dapat dibuat.');
        }

        $tulis = static function (string $teks) use ($handle): void {
            if (fwrite($handle, $teks) === false) {
                throw new \RuntimeException('Gagal menulis file cadangan (disk penuh?).');
            }
        };

        $tabel = 0;
        $baris = 0;
        $database = (string) Db::val('SELECT DATABASE()', [], '');

        try {
            $tulis("-- Cadangan database LJU Finance\n");
            $tulis('-- Dibuat: ' . now() . " WIB\n");
            $tulis('-- Database: ' . $database . "\n");
            $tulis('-- Aplikasi: ' . (string) setting('company_name', 'PT. Lampung Jasa Utama') . "\n");
            if ($catatan !== null && trim($catatan) !== '') {
                $tulis('-- Catatan: ' . str_replace(["\r", "\n"], ' ', trim($catatan)) . "\n");
            }
            $tulis("--\n-- Pemulihan: mysql -u USER -p DATABASE < file.sql\n");
            $tulis("-- ============================================================\n\n");
            $tulis("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

            foreach (self::tables() as $table) {
                $tabel++;
                $create = Db::one('SHOW CREATE TABLE `' . str_replace('`', '', $table) . '`');
                $ddl = (string) ($create['Create Table'] ?? '');
                if ($ddl === '') {
                    continue;
                }

                $tulis('-- ------------------------------------------------------------' . "\n");
                $tulis('-- Tabel `' . $table . "`\n");
                $tulis('-- ------------------------------------------------------------' . "\n");
                $tulis('DROP TABLE IF EXISTS `' . $table . "`;\n");
                $tulis($ddl . ";\n\n");

                $statement = $pdo->query('SELECT * FROM `' . str_replace('`', '', $table) . '`');
                if ($statement === false) {
                    continue;
                }

                $kolom = [];
                $jumlahKolom = $statement->columnCount();
                for ($i = 0; $i < $jumlahKolom; $i++) {
                    $meta = $statement->getColumnMeta($i);
                    $kolom[] = '`' . str_replace('`', '', (string) ($meta['name'] ?? 'kolom' . $i)) . '`';
                }

                $buffer = [];
                while (($row = $statement->fetch(\PDO::FETCH_NUM)) !== false) {
                    $nilai = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $nilai[] = 'NULL';
                        } elseif (is_int($value) || is_float($value)) {
                            $nilai[] = (string) $value;
                        } else {
                            $nilai[] = $pdo->quote((string) $value);
                        }
                    }
                    $buffer[] = '(' . implode(',', $nilai) . ')';
                    $baris++;

                    if (count($buffer) >= 200) {
                        $tulis('INSERT INTO `' . $table . '` (' . implode(', ', $kolom) . ") VALUES\n"
                            . implode(",\n", $buffer) . ";\n");
                        $buffer = [];
                    }
                }
                if ($buffer !== []) {
                    $tulis('INSERT INTO `' . $table . '` (' . implode(', ', $kolom) . ") VALUES\n"
                        . implode(",\n", $buffer) . ";\n");
                }
                $tulis("\n");
            }

            $tulis("SET FOREIGN_KEY_CHECKS = 1;\n");
            $tulis('-- Selesai: ' . now() . " WIB\n");
        } finally {
            fclose($handle);
        }

        self::bersihkan();

        return [
            'file'     => $nama,
            'size'     => (int) filesize($path),
            'tables'   => $tabel,
            'rows'     => $baris,
            'duration' => round(microtime(true) - $mulai, 2),
        ];
    }

    /** @return array<int,string> */
    public static function tables(): array
    {
        $hasil = [];
        foreach (Db::all('SHOW TABLES') as $row) {
            $nama = (string) array_values($row)[0];
            if ($nama !== '') {
                $hasil[] = $nama;
            }
        }
        return $hasil;
    }

    /**
     * @return array<int,array{file:string,size:int,modified:int,waktu:string}>
     */
    public static function daftar(): array
    {
        $dir = self::dir();
        $hasil = [];
        foreach (glob($dir . '/*.sql') ?: [] as $path) {
            $nama = basename($path);
            if (!self::valid($nama)) {
                continue;
            }
            $hasil[] = [
                'file'     => $nama,
                'size'     => (int) filesize($path),
                'modified' => (int) filemtime($path),
                'waktu'    => date('d M Y H:i', (int) filemtime($path)),
            ];
        }

        usort($hasil, static fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);
        return $hasil;
    }

    /** @return array{jumlah:int,total:int,terakhir:?string} */
    public static function ringkasan(): array
    {
        $daftar = self::daftar();
        $total = 0;
        foreach ($daftar as $item) {
            $total += $item['size'];
        }

        return [
            'jumlah'   => count($daftar),
            'total'    => $total,
            'terakhir' => $daftar[0]['waktu'] ?? null,
        ];
    }

    public static function path(string $file): string
    {
        if (!self::valid($file)) {
            throw new \RuntimeException('Nama file cadangan tidak valid.');
        }
        $path = self::dir() . '/' . $file;
        if (!is_file($path)) {
            throw new \RuntimeException('File cadangan tidak ditemukan.');
        }
        return $path;
    }

    public static function hapus(string $file): void
    {
        @unlink(self::path($file));
    }

    /** Hapus cadangan lebih lama dari N hari (setting `backup_retention_days`). */
    public static function bersihkan(): int
    {
        $hari = max(1, (int) setting('backup_retention_days', 14));
        $batas = time() - ($hari * 86400);
        $hapus = 0;

        foreach (self::daftar() as $item) {
            if ($item['modified'] < $batas) {
                @unlink(self::dir() . '/' . $item['file']);
                $hapus++;
            }
        }

        return $hapus;
    }

    private static function valid(string $file): bool
    {
        return preg_match(self::POLA, $file) === 1;
    }
}
