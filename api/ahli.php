<?php
/**
 * ahli.php — pendaftaran keahlian SAMFIRE FC (yuran RM15, bayaran manual).
 *
 *  AWAM
 *    GET  ?action=info               -> yuran, butiran bayaran, jumlah ahli
 *    POST  action=hantar (multipart) -> borang keahlian
 *
 *  ADMIN
 *    GET  ?action=urus               -> senarai penuh
 *    POST  action=lulus  { id }
 *    POST  action=tolak  { id, catatan? }
 *    POST  action=padam  { id }      (Super Admin)
 *    POST  action=tetapan { yuran_ahli, bayar_kepada, bayar_bank, bayar_akaun, ahli_buka }
 *    GET  ?action=csv                -> muat turun senarai ahli
 *
 * Jadual dicipta sendiri — tiada pemasangan semula database diperlukan.
 */

declare(strict_types=1);

require __DIR__ . '/lib/boot.php';

const AHLI_MAX_IMEJ  = 5242880;   // 5MB
const AHLI_HAD_IP    = 5;         // pendaftaran per IP sejam
const AHLI_HAD_JUMLAH = 2000;

function folderAhli(): string
{
    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        @file_put_contents($dir . '/.htaccess',
            "<FilesMatch \"\\.(?i:php|phtml|php[0-9]|cgi|pl|py|sh)$\">\n"
            . "  <IfModule mod_authz_core.c>\n    Require all denied\n  </IfModule>\n"
            . "  <IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n  </IfModule>\n"
            . "</FilesMatch>\nOptions -Indexes -ExecCGI\n");
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

function pastikanJadualAhli(): void
{
    db()->exec(
        "CREATE TABLE IF NOT EXISTS ahli (
            id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            nama           VARCHAR(120) NOT NULL,
            nama_panggilan VARCHAR(60)  NOT NULL DEFAULT '',
            no_kp          VARCHAR(20)  NOT NULL,
            tarikh_lahir   DATE         NULL,
            jantina        ENUM('lelaki','perempuan') NOT NULL DEFAULT 'lelaki',
            telefon        VARCHAR(30)  NOT NULL,
            emel           VARCHAR(190) NOT NULL DEFAULT '',
            alamat         VARCHAR(200) NOT NULL DEFAULT '',
            bandar         VARCHAR(80)  NOT NULL DEFAULT '',
            negeri         VARCHAR(60)  NOT NULL DEFAULT '',
            poskod         VARCHAR(10)  NOT NULL DEFAULT '',
            posisi         VARCHAR(50)  NOT NULL DEFAULT '',
            no_jersi       VARCHAR(4)   NOT NULL DEFAULT '',
            pemain_idola   VARCHAR(120) NOT NULL DEFAULT '',
            gambar         VARCHAR(120) NOT NULL DEFAULT '',
            bukti_bayar    VARCHAR(120) NOT NULL DEFAULT '',
            status         ENUM('baru','lulus','tolak') NOT NULL DEFAULT 'baru',
            catatan        VARCHAR(200) NOT NULL DEFAULT '',
            ip             VARCHAR(45)  NOT NULL DEFAULT '',
            created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_ahli_kp (no_kp),
            KEY idx_ahli_status (status),
            KEY idx_ahli_ip (ip, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/** Simpan imej dimuat naik (auto-putar EXIF, kecilkan, buang metadata). */
function simpanImejAhli(array $f, string $awalan, int $lebarMax): ?string
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK) return null;
    if ((int)$f['size'] > AHLI_MAX_IMEJ) return null;

    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return null;
    if (!function_exists('imagecreatefromstring')) return null;

    $im = @imagecreatefromstring((string)file_get_contents($f['tmp_name']));
    if (!$im) return null;

    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($f['tmp_name']);
        $o = (int)($exif['Orientation'] ?? 1);
        if ($o === 3) $im = imagerotate($im, 180, 0);
        elseif ($o === 6) $im = imagerotate($im, -90, 0);
        elseif ($o === 8) $im = imagerotate($im, 90, 0);
    }

    $w = imagesx($im); $h = imagesy($im);
    $skala = $w > $lebarMax ? $lebarMax / $w : 1.0;
    $nw = max(1, (int)round($w * $skala)); $nh = max(1, (int)round($h * $skala));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $nama = $awalan . '_' . bin2hex(random_bytes(7)) . '.jpg';
    $ok = imagejpeg($dst, folderAhli() . '/' . $nama, 84);
    imagedestroy($im); imagedestroy($dst);
    return $ok ? $nama : null;
}

function butiranBayaran(): array
{
    return [
        'yuran'        => tetapan('yuran_ahli', 'RM15'),
        'bayar_kepada' => tetapan('bayar_kepada', 'SAMFIRE FOOTBALL CLUB'),
        'bayar_bank'   => tetapan('bayar_bank', ''),
        'bayar_akaun'  => tetapan('bayar_akaun', ''),
        'bayar_nota'   => tetapan('bayar_nota', 'Hantar bukti pembayaran kepada urus setia melalui WhatsApp selepas menghantar borang.'),
        'buka'         => tetapan('ahli_buka', '1') === '1',
    ];
}

$action = (string)inp('action', 'info');

/* ==================================================================== AWAM */
if ($action === 'info') {
    pastikanJadualAhli();
    $bil = (int)db()->query("SELECT COUNT(*) FROM ahli WHERE status = 'lulus'")->fetchColumn();
    ok(['bayaran' => butiranBayaran(), 'jumlah_ahli' => $bil]);
}

if ($action === 'hantar') {
    wajibPost();
    pastikanJadualAhli();

    $b = butiranBayaran();
    if (!$b['buka']) fail('Pendaftaran keahlian sedang ditutup. Sila hubungi urus setia SAMFIRE FC.', 403);

    if ((string)inp('website', '') !== '') fail('Pendaftaran tidak dapat diproses.');   // honeypot

    $ip = ipKlien();
    $st = db()->prepare('SELECT COUNT(*) FROM ahli WHERE ip = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= AHLI_HAD_IP) {
        fail('Terlalu banyak pendaftaran dari peranti ini. Sila cuba sejam lagi.', 429);
    }
    if ((int)db()->query('SELECT COUNT(*) FROM ahli')->fetchColumn() >= AHLI_HAD_JUMLAH) {
        fail('Pendaftaran keahlian telah penuh buat masa ini.', 409);
    }

    $nama      = mb_substr(trim((string)inp('nama', '')), 0, 120);
    $panggilan = mb_substr(trim((string)inp('nama_panggilan', '')), 0, 60);
    $kp        = preg_replace('/[^0-9]/', '', (string)inp('no_kp', ''));
    $lahir     = trim((string)inp('tarikh_lahir', ''));
    $jantina   = (string)inp('jantina', 'lelaki');
    $telefon   = mb_substr(trim((string)inp('telefon', '')), 0, 30);
    $emel      = mb_substr(trim((string)inp('emel', '')), 0, 190);
    $alamat    = mb_substr(trim((string)inp('alamat', '')), 0, 200);
    $bandar    = mb_substr(trim((string)inp('bandar', '')), 0, 80);
    $negeri    = mb_substr(trim((string)inp('negeri', '')), 0, 60);
    $poskod    = preg_replace('/[^0-9]/', '', (string)inp('poskod', ''));
    $posisi    = mb_substr(trim((string)inp('posisi', '')), 0, 50);
    $jersi     = preg_replace('/[^0-9]/', '', (string)inp('no_jersi', ''));
    $idola     = mb_substr(trim((string)inp('pemain_idola', '')), 0, 120);

    if (mb_strlen($nama) < 3) fail('Sila isi nama penuh.');
    if (strlen((string)$kp) < 6 || strlen((string)$kp) > 14) fail('No. kad pengenalan tidak sah (masukkan nombor sahaja).');
    if (!preg_match('/^[0-9 +\-]{9,20}$/', $telefon)) fail('Nombor telefon tidak sah (cth: 012-3456789).');
    if ($emel !== '' && !filter_var($emel, FILTER_VALIDATE_EMAIL)) fail('Format emel tidak sah.');
    if (!in_array($jantina, ['lelaki', 'perempuan'], true)) $jantina = 'lelaki';
    if ($lahir !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $lahir)) $lahir = '';
    if ($jersi !== '' && (int)$jersi > 99) $jersi = '';

    $st = db()->prepare('SELECT status FROM ahli WHERE no_kp = ?');
    $st->execute([$kp]);
    if ($ada = $st->fetch()) {
        fail($ada['status'] === 'lulus'
            ? 'No. kad pengenalan ini sudah berdaftar sebagai ahli.'
            : 'No. kad pengenalan ini sudah menghantar borang. Sila tunggu pengesahan urus setia.');
    }

    $gambar = !empty($_FILES['gambar']) ? simpanImejAhli($_FILES['gambar'], 'ahli', 800) : null;
    $bukti  = !empty($_FILES['bukti'])  ? simpanImejAhli($_FILES['bukti'], 'bayar', 1200) : null;

    $st = db()->prepare(
        'INSERT INTO ahli (nama, nama_panggilan, no_kp, tarikh_lahir, jantina, telefon, emel,
                           alamat, bandar, negeri, poskod, posisi, no_jersi, pemain_idola,
                           gambar, bukti_bayar, ip)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $st->execute([
        $nama, $panggilan, $kp, $lahir !== '' ? $lahir : null, $jantina, $telefon, $emel,
        $alamat, $bandar, $negeri, $poskod, $posisi, $jersi, $idola,
        $gambar ?? '', $bukti ?? '', $ip,
    ]);

    audit(null, 'ahli_hantar', ['nama' => $nama, 'gambar' => (bool)$gambar, 'bukti' => (bool)$bukti]);

    ok(['mesej' => 'Permohonan keahlian diterima! Urus setia akan menghubungi anda untuk pengesahan bayaran '
                 . $b['yuran'] . '.']);
}

/* =================================================================== ADMIN */
if ($action === 'urus') {
    wajibAdmin();
    pastikanJadualAhli();
    $rows = db()->query("SELECT * FROM ahli ORDER BY FIELD(status,'baru','lulus','tolak'), id DESC")->fetchAll();
    foreach ($rows as $i => $r) {
        $rows[$i]['gambar_url'] = $r['gambar'] !== '' ? 'api/uploads/' . $r['gambar'] : '';
        $rows[$i]['bukti_url']  = $r['bukti_bayar'] !== '' ? 'api/uploads/' . $r['bukti_bayar'] : '';
    }
    $kira = ['baru' => 0, 'lulus' => 0, 'tolak' => 0];
    foreach ($rows as $r) $kira[$r['status']]++;
    ok(['ahli' => $rows, 'kiraan' => $kira, 'bayaran' => butiranBayaran()]);
}

if ($action === 'lulus' || $action === 'tolak') {
    wajibPost();
    semakCsrf();
    $admin = wajibAdmin();
    pastikanJadualAhli();
    $id = (int)inp('id', 0);
    $catatan = mb_substr(trim((string)inp('catatan', '')), 0, 200);
    $baru = $action === 'lulus' ? 'lulus' : 'tolak';
    $st = db()->prepare('UPDATE ahli SET status = ?, catatan = ? WHERE id = ?');
    $st->execute([$baru, $catatan, $id]);
    if ($st->rowCount() === 0) fail('Rekod tidak dijumpai.', 404);
    audit($admin, 'ahli_' . $baru, ['id' => $id]);
    ok();
}

if ($action === 'padam') {
    wajibPost();
    semakCsrf();
    $admin = wajibSuper();
    pastikanJadualAhli();
    $id = (int)inp('id', 0);
    $st = db()->prepare('SELECT nama, gambar, bukti_bayar FROM ahli WHERE id = ?');
    $st->execute([$id]);
    $a = $st->fetch();
    if (!$a) fail('Rekod tidak dijumpai.', 404);
    foreach ([$a['gambar'], $a['bukti_bayar']] as $f) {
        if ($f !== '') @unlink(folderAhli() . '/' . basename($f));
    }
    db()->prepare('DELETE FROM ahli WHERE id = ?')->execute([$id]);
    audit($admin, 'ahli_padam', ['nama' => $a['nama']]);
    ok();
}

if ($action === 'tetapan') {
    wajibPost();
    semakCsrf();
    $admin = wajibAdmin();
    foreach (['yuran_ahli', 'bayar_kepada', 'bayar_bank', 'bayar_akaun', 'bayar_nota'] as $k) {
        $v = inp($k, null);
        if ($v !== null) setTetapan($k, mb_substr((string)$v, 0, 200));
    }
    $buka = inp('ahli_buka', null);
    if ($buka !== null) setTetapan('ahli_buka', ((bool)$buka) ? '1' : '0');
    audit($admin, 'ahli_tetapan', []);
    ok(['bayaran' => butiranBayaran()]);
}

if ($action === 'csv') {
    $admin = wajibAdmin();
    pastikanJadualAhli();

    // format=members -> lajur sama persis dengan jadual `members` samfire_fc,
    // sedia untuk diimport terus ke sistem keahlian SAMFIRE FC sedia ada.
    if ((string)inp('format', '') === 'members') {
        $rows = db()->query(
            "SELECT CONCAT('SAMFC-', YEAR(created_at), '-', LPAD(id,3,'0')) AS member_no,
                    1 AS category_id,
                    nama       AS full_name,
                    no_kp      AS ic_no,
                    emel       AS email,
                    telefon    AS phone,
                    tarikh_lahir AS dob,
                    CASE jantina WHEN 'perempuan' THEN 'F' ELSE 'M' END AS gender,
                    CONCAT_WS(', ', NULLIF(alamat,''), NULLIF(bandar,'')) AS address,
                    negeri     AS state,
                    poskod     AS postcode,
                    no_jersi   AS jersey_no,
                    posisi     AS position,
                    CASE status WHEN 'lulus' THEN 'active' WHEN 'tolak' THEN 'rejected' ELSE 'pending' END AS status,
                    DATE(created_at) AS member_since,
                    CONCAT_WS(' | ', NULLIF(CONCAT('Panggilan: ', nama_panggilan),'Panggilan: '),
                                     NULLIF(CONCAT('Idola: ', pemain_idola),'Idola: ')) AS notes
             FROM ahli ORDER BY id"
        )->fetchAll(PDO::FETCH_ASSOC);
        $namaFail = 'members_import_' . date('Ymd_His') . '.csv';
    } else {
        $rows = db()->query(
            'SELECT id, nama, nama_panggilan, no_kp, tarikh_lahir, jantina, telefon, emel,
                    alamat, bandar, poskod, negeri, posisi, no_jersi, pemain_idola, status, created_at
             FROM ahli ORDER BY id'
        )->fetchAll(PDO::FETCH_ASSOC);
        $namaFail = 'ahli_samfire_' . date('Ymd_His') . '.csv';
    }

    audit($admin, 'ahli_csv', ['baris' => count($rows), 'format' => inp('format', 'biasa')]);

    header_remove('Content-Type');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $namaFail . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    if ($rows) {
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $r) fputcsv($out, $r);
    } else {
        fputcsv($out, ['tiada data']);
    }
    fclose($out);
    exit;
}

fail('Tindakan tidak dikenali.', 404);
