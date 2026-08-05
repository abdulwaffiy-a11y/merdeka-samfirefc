<?php
/**
 * poster.php — muat naik / buang poster kejohanan (admin sahaja).
 *
 *  POST action=naik   (multipart: poster)  -> muat naik & optimum
 *  POST action=buang                       -> buang poster
 *
 * Saiz disyorkan 1080 x 1350 (nisbah 4:5). Apa-apa saiz diterima —
 * imej dikecilkan supaya lebar maksimum 1080px dan dienkod semula
 * (buang metadata & sebarang kandungan tersembunyi).
 */

declare(strict_types=1);

require __DIR__ . '/lib/boot.php';

const POSTER_MAX_BAIT = 4194304;   // 4MB sebelum diproses
const POSTER_LEBAR_MAX = 1080;

function folderUpload(): string
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

$action = (string)inp('action', '');

if ($action === 'naik') {
    wajibPost();
    semakCsrf();
    $admin = wajibAdmin();

    if (empty($_FILES['poster']) || ($_FILES['poster']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        fail('Tiada fail poster dihantar.');
    }
    $f = $_FILES['poster'];
    if ($f['error'] !== UPLOAD_ERR_OK) fail('Muat naik gagal. Sila cuba lagi.');
    if ((int)$f['size'] > POSTER_MAX_BAIT) fail('Poster melebihi 4MB. Sila kecilkan imej dahulu.');

    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        fail('Poster mesti fail imej JPG, PNG atau WEBP.');
    }

    $lama = tetapan('poster', '');
    $nama = 'poster_' . bin2hex(random_bytes(6)) . '.jpg';
    $sasaran = folderUpload() . '/' . $nama;

    if (function_exists('imagecreatefromstring')) {
        $src = @imagecreatefromstring((string)file_get_contents($f['tmp_name']));
        if (!$src) fail('Imej poster tidak dapat dibaca.');
        $w = imagesx($src); $h = imagesy($src);
        $skala = $w > POSTER_LEBAR_MAX ? POSTER_LEBAR_MAX / $w : 1.0;
        $nw = (int)round($w * $skala); $nh = (int)round($h * $skala);
        $dst = imagecreatetruecolor($nw, $nh);
        // latar putih (poster JPEG tiada alpha)
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $ok = imagejpeg($dst, $sasaran, 86);
        imagedestroy($src); imagedestroy($dst);
        if (!$ok) fail('Tidak dapat menyimpan poster.', 500);
        $dimensi = $nw . 'x' . $nh;
    } else {
        if (!@move_uploaded_file($f['tmp_name'], $sasaran)) fail('Tidak dapat menyimpan poster.', 500);
        $dimensi = $info[0] . 'x' . $info[1];
    }

    setTetapan('poster', $nama);
    if ($lama !== '' && $lama !== $nama) @unlink(folderUpload() . '/' . basename($lama));

    audit($admin, 'poster_naik', ['fail' => $nama, 'dimensi' => $dimensi]);
    ok(['poster' => 'api/uploads/' . $nama, 'dimensi' => $dimensi]);
}

if ($action === 'buang') {
    wajibPost();
    semakCsrf();
    $admin = wajibAdmin();
    $lama = tetapan('poster', '');
    if ($lama !== '') @unlink(folderUpload() . '/' . basename($lama));
    setTetapan('poster', '');
    audit($admin, 'poster_buang', []);
    ok();
}

fail('Tindakan tidak dikenali.', 404);
