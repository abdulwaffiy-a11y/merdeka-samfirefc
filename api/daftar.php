<?php
/**
 * daftar.php — pendaftaran pasukan melalui borang awam.
 *
 *  AWAM (tiada log masuk):
 *   GET   ?action=senarai   -> senarai pasukan berdaftar (nama, status, logo)
 *   POST   action=hantar    -> hantar pendaftaran (multipart: nama, pengurus,
 *                              telefon, pemain (JSON), logo (fail <= 1MB))
 *
 *  ADMIN:
 *   GET   ?action=urus      -> senarai penuh + butiran
 *   POST   action=lulus     -> { id, team_id } salin ke slot pasukan kosong
 *   POST   action=tolak     -> { id, catatan? }
 *   POST   action=padam     -> { id }
 *   POST   action=buka      -> { buka: bool } buka/tutup pendaftaran
 *
 * Perlindungan spam: honeypot + had 3 pendaftaran per IP sejam + had 60 jumlah.
 * Logo: <= 1MB, jpg/png/webp sahaja, dienkod semula dengan GD (buang sebarang
 * kandungan tersembunyi), disimpan sebagai PNG 256px dalam api/uploads/.
 */

declare(strict_types=1);

require __DIR__ . '/lib/boot.php';
require __DIR__ . '/lib/kejohanan.php';

const LOGO_MAX_BAIT = 1048576;          // 1MB
const DAFTAR_HAD_IP = 3;                // per jam
const DAFTAR_HAD_JUMLAH = 60;

$action = (string)inp('action', 'senarai');

/* ------------------------------------------------------------- folder logo */
function folderLogo(): string
{
    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        @file_put_contents($dir . '/.htaccess',
            "# Fail imej sahaja — larang sebarang skrip\n"
            . "<FilesMatch \"\\.(?i:php|phtml|php[0-9]|cgi|pl|py|sh)$\">\n"
            . "  <IfModule mod_authz_core.c>\n    Require all denied\n  </IfModule>\n"
            . "  <IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n  </IfModule>\n"
            . "</FilesMatch>\n"
            . "Options -Indexes -ExecCGI\n");
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

/* ==================================================================== AWAM */
if ($action === 'senarai') {
    $rows = db()->query(
        "SELECT nama, logo, status, created_at FROM pendaftaran
          WHERE status IN ('baru','lulus') ORDER BY id"
    )->fetchAll();
    $buka  = tetapan('pendaftaran_buka', '1') === '1';
    $lulus = 0;
    foreach ($rows as $r) { if ($r['status'] === 'lulus') $lulus++; }
    ok([
        'buka'    => $buka,
        'jumlah'  => count($rows),
        'lulus'   => $lulus,
        'baki'    => max(0, 24 - $lulus),
        'senarai' => $rows,
    ]);
}

if ($action === 'hantar') {
    wajibPost();

    if (tetapan('pendaftaran_buka', '1') !== '1') {
        fail('Pendaftaran telah ditutup. Hubungi urus setia untuk pertanyaan.', 403);
    }

    // Honeypot — bot selalu isi semua ruangan
    if ((string)inp('website', '') !== '') {
        fail('Pendaftaran tidak dapat diproses.');
    }

    $ip = ipKlien();
    $st = db()->prepare('SELECT COUNT(*) FROM pendaftaran WHERE ip = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= DAFTAR_HAD_IP) {
        fail('Terlalu banyak pendaftaran dari peranti ini. Sila cuba sejam lagi.', 429);
    }
    if ((int)db()->query('SELECT COUNT(*) FROM pendaftaran')->fetchColumn() >= DAFTAR_HAD_JUMLAH) {
        fail('Pendaftaran telah penuh. Hubungi urus setia.', 409);
    }

    $nama     = mb_substr(trim((string)inp('nama', '')), 0, 80);
    $pengurus = mb_substr(trim((string)inp('pengurus', '')), 0, 80);
    $telefon  = mb_substr(trim((string)inp('telefon', '')), 0, 30);

    if (mb_strlen($nama) < 3)     fail('Sila isi nama pasukan (sekurang-kurangnya 3 aksara).');
    if (mb_strlen($pengurus) < 3) fail('Sila isi nama pengurus pasukan.');
    if (!preg_match('/^[0-9 +\-]{9,20}$/', $telefon)) fail('Sila isi nombor telefon yang sah (cth: 012-3456789).');

    // Pemain (JSON string dari borang) — maks 10
    $pemainRaw = (string)inp('pemain', '[]');
    $pemain = json_decode($pemainRaw, true);
    if (!is_array($pemain)) $pemain = [];
    $pemainBersih = [];
    foreach ($pemain as $p) {
        $n = mb_substr(trim((string)($p['nama'] ?? '')), 0, 80);
        if ($n === '') continue;
        $pemainBersih[] = ['nama' => $n, 'no_jersi' => mb_substr(trim((string)($p['no_jersi'] ?? '')), 0, 4)];
        if (count($pemainBersih) >= 10) break;
    }

    // Nama tidak boleh sama dengan pendaftaran aktif atau pasukan sedia ada
    $kunci = mb_strtolower($nama);
    $st = db()->prepare("SELECT COUNT(*) FROM pendaftaran WHERE status IN ('baru','lulus') AND LOWER(nama) = ?");
    $st->execute([$kunci]);
    if ((int)$st->fetchColumn() > 0) fail('Nama pasukan ini sudah didaftarkan.');
    $st = db()->prepare('SELECT COUNT(*) FROM teams WHERE LOWER(nama) = ?');
    $st->execute([$kunci]);
    if ((int)$st->fetchColumn() > 0) fail('Nama pasukan ini sudah wujud dalam kejohanan.');

    // ---- Logo (pilihan) ------------------------------------------------
    $namaLogo = '';
    if (!empty($_FILES['logo']) && ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['logo'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            fail('Muat naik logo gagal. Sila cuba lagi atau hantar tanpa logo.');
        }
        if ((int)$f['size'] > LOGO_MAX_BAIT) {
            fail('Logo melebihi 1MB. Sila kecilkan imej dahulu.');
        }
        $info = @getimagesize($f['tmp_name']);
        $jenisOk = $info && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true);
        if (!$jenisOk) {
            fail('Logo mesti fail imej JPG, PNG atau WEBP.');
        }
        if (function_exists('imagecreatefromstring')) {
            // Enkod semula — buang metadata & sebarang kandungan tersembunyi
            $src = @imagecreatefromstring((string)file_get_contents($f['tmp_name']));
            if (!$src) fail('Imej logo tidak dapat dibaca.');
            $w = imagesx($src); $h = imagesy($src);
            $sisi = 256;
            $dst = imagecreatetruecolor($sisi, $sisi);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $lut = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefill($dst, 0, 0, $lut);
            // muat dalam segi empat sama, kekal nisbah
            $skala = min($sisi / $w, $sisi / $h);
            $nw = (int)round($w * $skala); $nh = (int)round($h * $skala);
            imagecopyresampled($dst, $src, (int)(($sisi - $nw) / 2), (int)(($sisi - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
            $namaLogo = 'logo_' . bin2hex(random_bytes(8)) . '.png';
            $ok = imagepng($dst, folderLogo() . '/' . $namaLogo, 8);
            imagedestroy($src); imagedestroy($dst);
            if (!$ok) { $namaLogo = ''; }
        } else {
            // GD tiada — simpan asal (jenis sudah disahkan getimagesize)
            $ext = $info[2] === IMAGETYPE_PNG ? 'png' : ($info[2] === IMAGETYPE_WEBP ? 'webp' : 'jpg');
            $namaLogo = 'logo_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (!@move_uploaded_file($f['tmp_name'], folderLogo() . '/' . $namaLogo)) { $namaLogo = ''; }
        }
    }

    $st = db()->prepare(
        'INSERT INTO pendaftaran (nama, pengurus, telefon, pemain_json, logo, ip) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $st->execute([$nama, $pengurus, $telefon, json_encode($pemainBersih, JSON_UNESCAPED_UNICODE), $namaLogo, $ip]);

    audit(null, 'daftar_hantar', ['nama' => $nama, 'pemain' => count($pemainBersih), 'logo' => $namaLogo !== '']);

    ok([
        'mesej' => 'Pendaftaran diterima! Urus setia akan menghubungi pengurus pasukan untuk pengesahan yuran '
                 . tetapan('yuran', 'RM150') . '.',
    ]);
}

/* =================================================================== ADMIN */
if ($action === 'urus') {
    wajibAdmin();
    $rows = db()->query('SELECT * FROM pendaftaran ORDER BY FIELD(status, "baru", "lulus", "tolak"), id')->fetchAll();
    foreach ($rows as $i => $r) {
        $rows[$i]['pemain'] = json_decode((string)$r['pemain_json'], true) ?: [];
        unset($rows[$i]['pemain_json']);
    }
    // slot kosong untuk dropdown kelulusan
    $kosong = db()->query("SELECT id, kumpulan, slot FROM teams WHERE nama = '' ORDER BY kumpulan, slot")->fetchAll();
    ok(['senarai' => $rows, 'slot_kosong' => $kosong, 'buka' => tetapan('pendaftaran_buka', '1') === '1']);
}

if ($action === 'lulus') {
    // Luluskan pendaftaran -> masuk "kolam" pasukan sah.
    // Slot kumpulan (A1, B2, ...) ditentukan kemudian melalui UNDIAN KUMPULAN.
    wajibPost();
    semakCsrf();
    $admin = wajibAdmin();
    tolakJikaDikunci();

    $id = (int)inp('id', 0);
    $st = db()->prepare('SELECT nama, status FROM pendaftaran WHERE id = ?');
    $st->execute([$id]);
    $d = $st->fetch();
    if (!$d) fail('Pendaftaran tidak dijumpai.', 404);
    if ($d['status'] === 'lulus') fail('Pendaftaran ini sudah diluluskan.');

    db()->prepare("UPDATE pendaftaran SET status = 'lulus' WHERE id = ?")->execute([$id]);
    audit($admin, 'daftar_lulus', ['nama' => $d['nama']]);
    ok();
}

if ($action === 'tolak') {
    wajibPost();
    semakCsrf();
    $admin = wajibAdmin();
    $id = (int)inp('id', 0);
    $catatan = mb_substr(trim((string)inp('catatan', '')), 0, 200);
    $st = db()->prepare("UPDATE pendaftaran SET status = 'tolak', catatan = ? WHERE id = ? AND status = 'baru'");
    $st->execute([$catatan, $id]);
    if ($st->rowCount() === 0) fail('Hanya pendaftaran berstatus BARU boleh ditolak.');
    audit($admin, 'daftar_tolak', ['id' => $id, 'catatan' => $catatan]);
    ok();
}

if ($action === 'padam') {
    wajibPost();
    semakCsrf();
    $admin = wajibSuper();
    $id = (int)inp('id', 0);
    $st = db()->prepare('SELECT nama, logo, status FROM pendaftaran WHERE id = ?');
    $st->execute([$id]);
    $d = $st->fetch();
    if (!$d) fail('Tidak dijumpai.', 404);
    if ($d['status'] === 'lulus') fail('Pendaftaran yang sudah diluluskan tidak boleh dipadam. Kosongkan slot pasukan dahulu jika perlu.');
    if ($d['logo'] !== '') @unlink(folderLogo() . '/' . basename($d['logo']));
    db()->prepare('DELETE FROM pendaftaran WHERE id = ?')->execute([$id]);
    audit($admin, 'daftar_padam', ['nama' => $d['nama']]);
    ok();
}

if ($action === 'buka') {
    wajibPost();
    semakCsrf();
    $admin = wajibAdmin();
    $buka = (bool)inp('buka', true);
    setTetapan('pendaftaran_buka', $buka ? '1' : '0');
    audit($admin, 'daftar_buka', ['buka' => $buka]);
    ok(['buka' => $buka]);
}

fail('Tindakan tidak dikenali.', 404);
