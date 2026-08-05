<?php
/**
 * backup.php — sandaran automatik database kejohanan.
 *
 *  CLI (cron):   php backup.php --cron       -> jana sandaran, simpan 48 terkini
 *  ADMIN:
 *    GET ?action=senarai   -> senarai fail sandaran
 *    GET ?action=jana      -> jana sandaran sekarang
 *    GET ?action=muat&fail=xxx.sql.gz  -> muat turun sandaran
 *    GET ?action=csv&jenis=pendaftaran|pasukan|pemain  -> muat turun CSV
 *
 * Fail sandaran disimpan dalam api/backups/ yang dilindungi .htaccess
 * (tiada sesiapa boleh muat turun terus tanpa log masuk admin).
 */

declare(strict_types=1);

$MOD_CRON = (PHP_SAPI === 'cli');

require __DIR__ . '/lib/boot.php';

const SIMPAN_MAX = 48;   // simpan 48 sandaran terkini

function folderBackup(): string
{
    $dir = __DIR__ . '/backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $ht = $dir . '/.htaccess';
    if (!file_exists($ht)) {
        @file_put_contents($ht,
            "# Sandaran database — larang akses terus sepenuhnya.\n"
            . "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n"
            . "Options -Indexes\n");
    }
    if (!file_exists($dir . '/index.html')) @file_put_contents($dir . '/index.html', '');
    return $dir;
}

/** Hasilkan dump SQL penuh (semua jadual + data). */
function janaDump(): string
{
    $pdo = db();
    $keluar = "-- Sandaran Sistem Kejohanan Futsal Merdeka Kepala Batas 2026\n"
            . '-- Dijana: ' . date('Y-m-d H:i:s') . "\n"
            . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

    $jadual = [];
    foreach ($pdo->query('SHOW TABLES') as $r) {
        $jadual[] = array_values($r)[0];
    }

    foreach ($jadual as $t) {
        $cipta = $pdo->query('SHOW CREATE TABLE `' . $t . '`')->fetch();
        $keluar .= "\n-- ---------- $t ----------\n";
        $keluar .= "DROP TABLE IF EXISTS `$t`;\n" . array_values($cipta)[1] . ";\n";

        $baris = $pdo->query('SELECT * FROM `' . $t . '`')->fetchAll(PDO::FETCH_ASSOC);
        if (!$baris) continue;

        $lajur = array_map(fn($c) => '`' . $c . '`', array_keys($baris[0]));
        $kumpulan = array_chunk($baris, 100);
        foreach ($kumpulan as $blok) {
            $nilaiBaris = [];
            foreach ($blok as $b) {
                $n = [];
                foreach ($b as $v) {
                    $n[] = $v === null ? 'NULL' : $pdo->quote((string)$v);
                }
                $nilaiBaris[] = '(' . implode(',', $n) . ')';
            }
            $keluar .= 'INSERT INTO `' . $t . '` (' . implode(',', $lajur) . ") VALUES\n"
                     . implode(",\n", $nilaiBaris) . ";\n";
        }
    }

    $keluar .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";
    return $keluar;
}

function simpanSandaran(): array
{
    $dir  = folderBackup();
    $sql  = janaDump();
    $nama = 'merdeka_' . date('Ymd_His') . '.sql';

    if (function_exists('gzencode')) {
        $nama .= '.gz';
        $data = gzencode($sql, 6);
    } else {
        $data = $sql;
    }

    $laluan = $dir . '/' . $nama;
    if (@file_put_contents($laluan, $data) === false) {
        throw new RuntimeException('Tidak dapat menulis fail sandaran. Semak kebenaran folder api/backups.');
    }
    @chmod($laluan, 0600);

    // Buang sandaran lama
    $semua = glob($dir . '/merdeka_*.sql*') ?: [];
    usort($semua, fn($a, $b) => filemtime($b) <=> filemtime($a));
    foreach (array_slice($semua, SIMPAN_MAX) as $lama) @unlink($lama);

    setTetapan('backup_akhir', date('Y-m-d H:i:s'));

    return ['fail' => $nama, 'saiz' => strlen($data), 'jumlah_disimpan' => min(count($semua), SIMPAN_MAX)];
}

// =====================================================================
if ($MOD_CRON) {
    try {
        $r = simpanSandaran();
        echo "Sandaran: {$r['fail']} (" . round($r['saiz'] / 1024, 1) . " KB)\n";
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, 'Sandaran gagal: ' . $e->getMessage() . "\n");
        exit(1);
    }
}

$action = (string)inp('action', 'senarai');

// ---------------------------------------------------------------------
if ($action === 'senarai') {
    wajibAdmin();
    $dir = folderBackup();
    $semua = glob($dir . '/merdeka_*.sql*') ?: [];
    usort($semua, fn($a, $b) => filemtime($b) <=> filemtime($a));

    $out = [];
    foreach ($semua as $f) {
        $out[] = [
            'fail' => basename($f),
            'saiz' => filesize($f),
            'masa' => date('Y-m-d H:i:s', filemtime($f)),
        ];
    }

    // Kiraan data hidup untuk ketenangan fikiran
    $kira = [];
    foreach (['pendaftaran' => 'pendaftaran', 'pasukan_dinamakan' => "teams WHERE nama <> ''",
              'pemain' => 'players', 'perlawanan_tamat' => "matches WHERE status = 'done'"] as $label => $q) {
        try { $kira[$label] = (int)db()->query('SELECT COUNT(*) FROM ' . $q)->fetchColumn(); }
        catch (Throwable $e) { $kira[$label] = 0; }
    }

    ok([
        'sandaran'      => $out,
        'backup_akhir'  => tetapan('backup_akhir', ''),
        'kiraan'        => $kira,
        'boleh_tulis'   => is_writable($dir),
    ]);
}

// ---------------------------------------------------------------------
if ($action === 'jana') {
    $admin = wajibAdmin();
    $r = simpanSandaran();
    audit($admin, 'backup_jana', $r);
    ok($r);
}

// ---------------------------------------------------------------------
if ($action === 'muat') {
    $admin = wajibAdmin();
    $fail = basename((string)inp('fail', ''));
    if (!preg_match('/^merdeka_\d{8}_\d{6}\.sql(\.gz)?$/', $fail)) {
        fail('Nama fail tidak sah.');
    }
    $laluan = folderBackup() . '/' . $fail;
    if (!is_file($laluan)) fail('Fail sandaran tidak dijumpai.', 404);

    audit($admin, 'backup_muat_turun', ['fail' => $fail]);

    header_remove('Content-Type');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $fail . '"');
    header('Content-Length: ' . filesize($laluan));
    readfile($laluan);
    exit;
}

// ---------------------------------------------------------------------
if ($action === 'csv') {
    $admin = wajibAdmin();
    $jenis = (string)inp('jenis', 'pendaftaran');

    $pertanyaan = [
        'pendaftaran' => 'SELECT id, nama AS nama_pasukan, pengurus, telefon, status,
                                 CASE WHEN team_id IS NULL THEN "" ELSE "ada" END AS slot_ditetapkan,
                                 logo, created_at AS masa_daftar
                          FROM pendaftaran ORDER BY id',
        'pasukan'     => 'SELECT CONCAT(kumpulan, slot) AS slot, nama AS nama_pasukan,
                                 pengurus, telefon, logo
                          FROM teams ORDER BY kumpulan, slot',
        'pemain'      => 'SELECT CONCAT(t.kumpulan, t.slot) AS slot, t.nama AS pasukan,
                                 p.no_jersi, p.nama AS pemain
                          FROM players p JOIN teams t ON t.id = p.team_id
                          ORDER BY t.kumpulan, t.slot, p.id',
    ];
    if (!isset($pertanyaan[$jenis])) fail('Jenis CSV tidak dikenali.');

    $baris = db()->query($pertanyaan[$jenis])->fetchAll(PDO::FETCH_ASSOC);
    audit($admin, 'csv_muat_turun', ['jenis' => $jenis, 'baris' => count($baris)]);

    header_remove('Content-Type');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="merdeka_' . $jenis . '_' . date('Ymd_His') . '.csv"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");            // BOM supaya Excel baca huruf Melayu betul
    if ($baris) {
        fputcsv($out, array_keys($baris[0]));
        foreach ($baris as $b) fputcsv($out, $b);
    } else {
        fputcsv($out, ['tiada data']);
    }
    fclose($out);
    exit;
}

fail('Tindakan tidak dikenali.', 404);
