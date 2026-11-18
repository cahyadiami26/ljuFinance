<?php

declare(strict_types=1);

namespace Lju\Services;

use Lju\Core\Db;
use PDO;
use RuntimeException;

/**
 * Sinkronisasi massal pengguna dari tabel `users` milik portal SSO.
 *
 * Hanya data kepegawaian yang ditarik. Role aplikasi, bidang/divisi lokal yang
 * sudah diisi, dan status persetujuan tidak diubah.
 */
final class UserSyncService
{
    /** Kolom portal yang dipetakan ke app_users. */
    private const KOLOM = [
        'id', 'nik', 'name', 'username', 'email', 'position',
        'division', 'department', 'role', 'avatar', 'photo', 'signature', 'is_active',
    ];

    /**
     * @return array{total:int,baru:int,diperbarui:int,dilewati:int,gagal:int,dipetakan:int,kesalahan:array<int,string>,durasi:float,source:string}
     */
    public static function sinkronkan(): array
    {
        $pdo = Db::sso();
        if (!$pdo instanceof PDO) {
            throw new RuntimeException(
                'Database portal SSO tidak dapat diakses. Periksa konfigurasi sso.db, host, dan kredensial database.'
            );
        }

        $tabel = (string) config('sso.users_table', 'users');
        if (!preg_match('/^[A-Za-z0-9_]+$/', $tabel)) {
            throw new RuntimeException('Nama tabel pengguna pada konfigurasi sso.users_table tidak valid.');
        }

        $kolom = self::kolom($pdo, $tabel);
        foreach (['id', 'name'] as $wajib) {
            if (!in_array($wajib, $kolom, true)) {
                throw new RuntimeException('Tabel `' . $tabel . '` tidak memiliki kolom `' . $wajib . '`.');
            }
        }

        $daftarKolom = implode(', ', array_map(static fn (string $k): string => '`' . $k . '`', $kolom));
        try {
            $rows = $pdo->query('SELECT ' . $daftarKolom . ' FROM `' . $tabel . '`')->fetchAll();
        } catch (\PDOException $e) {
            throw new RuntimeException('Data pengguna portal tidak dapat dibaca: ' . self::pesan($e));
        }

        $mulai = microtime(true);
        $hasil = [
            'total'      => count($rows),
            'baru'       => 0,
            'diperbarui' => 0,
            'dilewati'   => 0,
            'gagal'      => 0,
            'dipetakan'  => 0,
            'kesalahan'  => [],
            'durasi'     => 0.0,
            'source'     => $tabel,
        ];

        $sudahAda = [];
        foreach (Db::all('SELECT id FROM app_users') as $row) {
            $sudahAda[(int) $row['id']] = true;
        }

        Db::transaction(static function () use ($rows, &$hasil, &$sudahAda): void {
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $nama = trim((string) ($row['name'] ?? ''));

                if ($id <= 0 || $nama === '') {
                    $hasil['dilewati']++;
                    continue;
                }

                $data = [
                    'id'          => $id,
                    'nik'         => self::teks($row['nik'] ?? null, 50),
                    'name'        => self::teks($nama, 100) ?? $nama,
                    'username'    => self::teks($row['username'] ?? null, 50) ?: 'user' . $id,
                    'email'       => self::teks($row['email'] ?? null, 100),
                    'position'    => self::teks($row['position'] ?? null, 60),
                    'nama_bidang' => self::teks($row['division'] ?? null, 60),
                    'nama_divisi' => self::teks($row['department'] ?? null, 60),
                    'sso_role'    => self::teks($row['role'] ?? null, 20),
                    'avatar'      => self::teks($row['avatar'] ?? null, 255),
                    'photo'       => self::teks($row['photo'] ?? null, 255),
                    'signature'   => self::teks($row['signature'] ?? null, 255),
                    'is_active'   => isset($row['is_active']) ? (int) $row['is_active'] : 1,
                ];

                try {
                    Db::exec(
                        'INSERT INTO app_users (id, nik, name, username, email, position, nama_bidang, nama_divisi,
                                                sso_role, avatar, photo, signature, is_active, last_login_at, synced_at)
                         VALUES (:id, :nik, :name, :username, :email, :position, :nama_bidang, :nama_divisi,
                                 :sso_role, :avatar, :photo, :signature, :is_active, NULL, NOW())
                         ON DUPLICATE KEY UPDATE
                            nik = VALUES(nik), name = VALUES(name), email = VALUES(email), position = VALUES(position),
                            nama_bidang = VALUES(nama_bidang), nama_divisi = VALUES(nama_divisi),
                            sso_role = VALUES(sso_role), avatar = VALUES(avatar), photo = VALUES(photo),
                            signature = VALUES(signature), is_active = VALUES(is_active), synced_at = NOW()',
                        $data
                    );
                } catch (\PDOException $e) {
                    $hasil['gagal']++;
                    if (count($hasil['kesalahan']) < 3) {
                        $hasil['kesalahan'][] = '#' . $id . ' (' . $data['username'] . '): ' . self::pesan($e);
                    }
                    continue;
                }

                if (isset($sudahAda[$id])) {
                    $hasil['diperbarui']++;
                } else {
                    $hasil['baru']++;
                    $sudahAda[$id] = true;
                }
            }

            // Lengkapi relasi organisasi hanya bila belum pernah diisi secara lokal.
            $bidang = Db::exec(
                'UPDATE app_users u JOIN ms_bidang b ON b.nama_sso = u.nama_bidang
                 SET u.bidang_id = b.id WHERE u.bidang_id IS NULL'
            );
            $divisi = Db::exec(
                'UPDATE app_users u JOIN ms_divisi d ON d.nama_sso = u.nama_divisi
                 SET u.divisi_id = d.id WHERE u.divisi_id IS NULL'
            );
            $hasil['dipetakan'] = $bidang + $divisi;
        });

        $hasil['durasi'] = round(microtime(true) - $mulai, 2);

        return $hasil;
    }

    /** @return array<int,string> kolom portal yang tersedia dan relevan */
    private static function kolom(PDO $pdo, string $tabel): array
    {
        try {
            $rows = $pdo->query('SHOW COLUMNS FROM `' . $tabel . '`')->fetchAll();
        } catch (\PDOException $e) {
            throw new RuntimeException(
                'Tabel `' . $tabel . '` tidak dapat dibaca pada database portal (`'
                . (string) config('sso.db', '') . '`): ' . self::pesan($e)
            );
        }

        $tersedia = array_map(static fn (array $row): string => (string) ($row['Field'] ?? ''), $rows);

        return array_values(array_intersect(self::KOLOM, $tersedia));
    }

    private static function teks(mixed $nilai, int $maks): ?string
    {
        if ($nilai === null) {
            return null;
        }

        $teks = trim((string) $nilai);
        if ($teks === '') {
            return null;
        }

        return mb_substr($teks, 0, $maks);
    }

    private static function pesan(\PDOException $e): string
    {
        return mb_substr(trim((string) $e->getMessage()), 0, 180);
    }
}
