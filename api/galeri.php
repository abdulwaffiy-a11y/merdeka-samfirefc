<?php
/**
 * galeri.php — galeri gambar kejohanan.
 *
 *  GET   ?action=senarai            -> awam: senarai gambar
 *  POST   action=naik  (multipart)  -> admin: muat naik (boleh banyak sekali gus)
 *  POST   action=buang { id }       -> admin
 *  POST   action=kapsyen { id, kapsyen }
 *
 * Direka untuk gambar terus dari telefon / WhatsApp:
 *  - terima JPG / PNG / WEBP / HEIC-yang-sudah-ditukar
 *  - auto-putar ikut data EXIF (gambar telefon selalu terbalik)
 *  - besarkan/kecilkan kepada lebar maks 1600px + thumbnail 480px
 *  - buang metadata (lokasi GPS dsb.) semasa enkod semula
 */

declare(strict_types=1);

require __DIR__ . '/lib/boot.php';

const GAL_MAX_BAIT   = 12582912;   // 12MB satu gambar (gambar telefon besar)
const GAL_LEBAR      = 1600;
const GAL_THUMB      = 480;
const GAL_MAX_JUMLAH = 200;

function folderGaleri(): string
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

/** Cipta jadual galeri jika belum wujud (elak perlu pasang semula database). */
function pastikanJadualGaleri(): void
{
    db()->exec(
        'CREATE TABLE IF NOT EXISTS galeri (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            fail        VARCHAR(120) NOT NULL,
            thumb       VARCHAR(120) NOT NULL DEFAULT \'\',
            kapsyen     VARCHAR(160) NOT NULL DEFAULT \'\',
            lebar       INT NOT NULL DEFAULT 0,
            tinggi      INT NOT NULL DEFAULT 0,
            dimuat_oleh VARCHAR(100) NOT NULL DEFAULT \'\',
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_galeri_masa (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/** Baca imej + betulkan orientasi EXIF. */
function bacaImej(string $path, int $jenis)
{
    $im = @imagecreatefromstring((string)file_get_contents($path));
    if (!$im) return null;

    if ($jenis === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $o = (int)($exif['Orientation'] ?? 1);
        if ($o === 3)      $im = imagerotate($im, 180, 0);
        elseif ($o === 6)  $im = imagerotate($im, -90, 0);
        elseif ($o === 8)  $im = imagerotate($im, 90, 0);
    }
    return $im;
}

function simpanSkala($im, int $lebarMax, string $sasaran): array
{
    $w = imagesx($im); $h = imagesy($im);
    $skala = $w > $lebarMax ? $lebarMax / $w : 1.0;
    $nw = max(1, (int)round($w * $skala));
    $nh = max(1, (int)round($h * $skala));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagejpeg($dst, $sasaran, 84);
    imagedestroy($dst);
    return [$nw, $nh];
}

$action = (string)inp('action', 'senarai');

// ------------------------------------------------------------------ awam
if ($action === 'senarai') {
    pastikanJadualGaleri();
    $rows = db()->query('SELECT id, fail, thumb, kapsyen, lebar, tinggi, created_at
                         FROM galeri ORDER BY id DESC LIMIT 200')->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id'      => (int)$r['id'],
            'url'     => 'api/uploads/' . $r['fail'],
            'thumb'   => 'api/uploads/' . ($r['thumb'] !== '' ? $r['thumb'] : $r['fail']),
            'kapsyen' => $r['kapsyen'],
            'lebar'   => (int)$r['lebar'],
            'tinggi'  => (int)$r['tinggi'],
            'masa'    => $r['created_at'],
        ];
    }
    ok(['galeri' => $out]);
}

// ----------------------------------------------------------------- admin
if ($action === 'naik') {
    wajibPost();
    semakCsrf();
    $admin = wajibAdmin();
    pastikanJadualGaleri();

    if ((int)db()->query('SELECT COUNT(*) FROM galeri')->fetchColumn() >= GAL_MAX_JUMLAH) {
        fail('Galeri sudah penuh (' . GAL_MAX_JUMLAH . ' gambar). Buang beberapa gambar lama dahulu.');
    }
    if (empty($_FILES['gambar'])) {
        fail('Tiada gambar dihantar.');
    }

    // Sokong satu fail atau banyak fail (gambar[])
    $f = $_FILES['gambar'];
    $senarai = is_array($f['name'])
        ? array_map(fn($i) => [
            'name' => $f['name'][$i], 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i],
            'error' => $f['error'][$i], 'size' => $f['size'][$i],
          ], array_keys($f['name']))
        : [$f];

    if (count($senarai) > 20) fail('Maksimum 20 gambar sekali muat naik.');
    if (!function_exists('imagecreatefromstring')) {
        fail('Sambungan PHP "gd" tidak aktif di server. Aktifkan di cPanel > Select PHP Version.', 500);
    }

    $berjaya = [];
    $gagal   = [];
    $ins = db()->prepare(
        'INSERT INTO galeri (fail, thumb, kapsyen, lebar, tinggi, dimuat_oleh) VALUES (?, ?, ?, ?, ?, ?)'
    );

    foreach ($senarai as $g) {
        $namaAsal = (string)($g['name'] ?? 'gambar');
        if (($g['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        if ($g['error'] !== UPLOAD_ERR_OK)      { $gagal[] = "$namaAsal (ralat muat naik)"; continue; }
        if ((int)$g['size'] > GAL_MAX_BAIT)     { $gagal[] = "$namaAsal (melebihi 12MB)"; continue; }

        $info = @getimagesize($g['tmp_name']);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            $gagal[] = "$namaAsal (bukan imej JPG/PNG/WEBP)";
            continue;
        }

        $im = bacaImej($g['tmp_name'], $info[2]);
        if (!$im) { $gagal[] = "$namaAsal (tidak dapat dibaca)"; continue; }

        $asas  = 'g_' . bin2hex(random_bytes(7));
        $fBesar = $asas . '.jpg';
        $fThumb = $asas . '_t.jpg';

        [$w, $h] = simpanSkala($im, GAL_LEBAR, folderGaleri() . '/' . $fBesar);
        simpanSkala($im, GAL_THUMB, folderGaleri() . '/' . $fThumb);
        imagedestroy($im);

        $ins->execute([$fBesar, $fThumb, '', $w, $h, $admin['nama']]);
        $berjaya[] = $fBesar;
    }

    audit($admin, 'galeri_naik', ['berjaya' => count($berjaya), 'gagal' => $gagal]);

    if (!$berjaya && $gagal) fail('Tiada gambar berjaya dimuat naik: ' . implode(', ', $gagal));

    ok(['berjaya' => count($berjaya), 'gagal' => $gagal]);
}

if ($action === 'buang') {
    wajibPost();
    semakCsrf();
    $admin = wajibAdmin();
    pastikanJadualGaleri();

    $id = (int)inp('id', 0);
    $st = db()->prepare('SELECT fail, thumb FROM galeri WHERE id = ?');
    $st->execute([$id]);
    $g = $st->fetch();
    if (!$g) fail('Gambar tidak dijumpai.', 404);

    @unlink(folderGaleri() . '/' . basename($g['fail']));
    if ($g['thumb'] !== '') @unlink(folderGaleri() . '/' . basename($g['thumb']));
    db()->prepare('DELETE FROM galeri WHERE id = ?')->execute([$id]);

    audit($admin, 'galeri_buang', ['id' => $id]);
    ok();
}

if ($action === 'kapsyen') {
    wajibPost();
    semakCsrf();
    $admin = wajibAdmin();
    pastikanJadualGaleri();
    $id = (int)inp('id', 0);
    $k  = mb_substr(trim((string)inp('kapsyen', '')), 0, 160);
    db()->prepare('UPDATE galeri SET kapsyen = ? WHERE id = ?')->execute([$k, $id]);
    audit($admin, 'galeri_kapsyen', ['id' => $id]);
    ok();
}

fail('Tindakan tidak dikenali.', 404);
