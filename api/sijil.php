<?php
/**
 * sijil.php — sijil penyertaan pemain & pasukan.
 *
 *  ADMIN
 *    GET  ?action=pautan            -> senarai 24 pasukan + pautan sijil
 *    POST  action=tandatangan       -> muat naik imej tandatangan (multipart)
 *    POST  action=buang_tandatangan
 *    POST  action=tetapan           -> { nama_penandatangan, jawatan_penandatangan }
 *
 *  AWAM (pengurus pasukan, guna token — tiada log masuk)
 *    GET ?t=TOKEN                   -> halaman senarai pemain + butang cetak
 *    GET ?t=TOKEN&cetak=semua       -> semua sijil pemain (satu muka setiap pemain)
 *    GET ?t=TOKEN&cetak=pasukan     -> sijil penyertaan pasukan
 *    GET ?t=TOKEN&cetak=pemain&id=N -> sijil seorang pemain
 *
 * Sijil dipaparkan sebagai halaman web yang direka untuk dicetak.
 * Tekan Cetak -> pilih "Simpan sebagai PDF" untuk simpan fail.
 */

declare(strict_types=1);

require __DIR__ . '/lib/boot.php';
require __DIR__ . '/lib/kejohanan.php';

const SIJIL_MAX_BAIT = 2097152;   // 2MB untuk imej tandatangan

function folderUploadSijil(): string
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

/** Token stabil bagi setiap pasukan — tiada lajur database diperlukan. */
function tokenPasukan(int $teamId): string
{
    global $CFG;
    return substr(hash_hmac('sha256', 'sijil-pasukan-' . $teamId, (string)($CFG['app_key'] ?? '')), 0, 16);
}

function pasukanDariToken(string $token): ?array
{
    if ($token === '') return null;
    foreach (muatPasukan() as $t) {
        if (hash_equals(tokenPasukan($t['id']), $token)) return $t;
    }
    return null;
}

function urlAsas(): string
{
    $skema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $skema . '://' . ($_SERVER['HTTP_HOST'] ?? '') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
}

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* ==================================================================== ADMIN */
$action = (string)inp('action', '');

if ($action === 'pautan') {
    wajibAdmin();
    $pasukan = muatPasukan();
    $pemainIkut = [];
    foreach (db()->query('SELECT team_id, COUNT(*) AS bil FROM players GROUP BY team_id') as $r) {
        $pemainIkut[(int)$r['team_id']] = (int)$r['bil'];
    }

    $out = [];
    foreach ($pasukan as $t) {
        if ($t['nama'] === '') continue;
        $tok = tokenPasukan($t['id']);
        $out[] = [
            'id'       => $t['id'],
            'slot'     => $t['kumpulan'] . $t['slot'],
            'nama'     => $t['nama'],
            'pengurus' => $t['pengurus'],
            'telefon'  => $t['telefon'],
            'pemain'   => $pemainIkut[$t['id']] ?? 0,
            'pautan'   => urlAsas() . '/sijil.php?t=' . $tok,
        ];
    }

    ok([
        'pasukan'      => $out,
        'tandatangan'  => tetapan('tandatangan', '') !== '' ? 'api/uploads/' . tetapan('tandatangan', '') : '',
        'nama_penandatangan'    => tetapan('nama_penandatangan', ''),
        'jawatan_penandatangan' => tetapan('jawatan_penandatangan', ''),
    ]);
}

if ($action === 'tandatangan') {
    wajibPost();
    semakCsrf();
    $admin = wajibAdmin();

    if (empty($_FILES['tandatangan']) || ($_FILES['tandatangan']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        fail('Tiada fail dihantar.');
    }
    $f = $_FILES['tandatangan'];
    if ($f['error'] !== UPLOAD_ERR_OK) fail('Muat naik gagal.');
    if ((int)$f['size'] > SIJIL_MAX_BAIT) fail('Imej tandatangan melebihi 2MB.');

    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        fail('Tandatangan mesti imej JPG, PNG atau WEBP. PNG latar telus paling kemas.');
    }

    $lama = tetapan('tandatangan', '');
    $nama = 'ttd_' . bin2hex(random_bytes(6)) . '.png';

    if (function_exists('imagecreatefromstring')) {
        $src = @imagecreatefromstring((string)file_get_contents($f['tmp_name']));
        if (!$src) fail('Imej tidak dapat dibaca.');
        $w = imagesx($src); $h = imagesy($src);
        $skala = $w > 600 ? 600 / $w : 1.0;
        $nw = (int)round($w * $skala); $nh = (int)round($h * $skala);
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $ok = imagepng($dst, folderUploadSijil() . '/' . $nama, 8);
        imagedestroy($src); imagedestroy($dst);
        if (!$ok) fail('Tidak dapat menyimpan tandatangan.', 500);
    } else {
        if (!@move_uploaded_file($f['tmp_name'], folderUploadSijil() . '/' . $nama)) {
            fail('Tidak dapat menyimpan tandatangan.', 500);
        }
    }

    setTetapan('tandatangan', $nama);
    if ($lama !== '' && $lama !== $nama) @unlink(folderUploadSijil() . '/' . basename($lama));

    audit($admin, 'sijil_tandatangan', ['fail' => $nama]);
    ok(['tandatangan' => 'api/uploads/' . $nama]);
}

if ($action === 'buang_tandatangan') {
    wajibPost();
    semakCsrf();
    $admin = wajibAdmin();
    $lama = tetapan('tandatangan', '');
    if ($lama !== '') @unlink(folderUploadSijil() . '/' . basename($lama));
    setTetapan('tandatangan', '');
    audit($admin, 'sijil_tandatangan_buang', []);
    ok();
}

if ($action === 'tetapan') {
    wajibPost();
    semakCsrf();
    $admin = wajibAdmin();
    foreach (['nama_penandatangan', 'jawatan_penandatangan'] as $k) {
        $v = inp($k, null);
        if ($v !== null) setTetapan($k, mb_substr((string)$v, 0, 120));
    }
    audit($admin, 'sijil_tetapan', []);
    ok();
}

/* ===================================================== HALAMAN AWAM (TOKEN) */
$token = (string)inp('t', '');
$pasukan = pasukanDariToken($token);

if (!$pasukan) {
    header_remove('Content-Type');
    header('Content-Type: text/html; charset=utf-8');
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="ms"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Pautan tidak sah</title><style>body{font-family:system-ui,sans-serif;background:#f5f5f4;'
       . 'display:grid;place-items:center;min-height:100vh;margin:0;padding:24px;text-align:center;color:#1c1917}'
       . 'div{max-width:420px}h1{color:#7B1E2B;font-size:20px}a{color:#7B1E2B}</style></head><body><div>'
       . '<h1>Pautan sijil tidak sah</h1>'
       . '<p style="color:#57534e;font-size:14px">Pautan ini tidak dikenali atau pasukan belum didaftarkan. '
       . 'Sila hubungi urus setia kejohanan.</p>'
       . '<p><a href="../">Kembali ke laman kejohanan</a></p></div></body></html>';
    exit;
}

// ---- data untuk sijil -------------------------------------------------
$st = db()->prepare('SELECT id, nama, no_jersi FROM players WHERE team_id = ? ORDER BY id');
$st->execute([$pasukan['id']]);
$pemain = $st->fetchAll();

$tet        = tetapanSemua();
$namaKej    = $tet['nama_kejohanan'] ?? 'KEJOHANAN FUTSAL MERDEKA KEPALA BATAS 2026';
$tarikhKej  = $tet['tarikh_kejohanan'] ?? '2026-08-30';
$lokasi     = $tet['lokasi'] ?? '';
$ttdFail    = $tet['tandatangan'] ?? '';
$ttdNama    = $tet['nama_penandatangan'] ?? "YB Dato' Seri Reezal Merican";
$ttdJawatan = $tet['jawatan_penandatangan'] ?? 'Penaja Kejohanan';

$bulan = ['','Januari','Februari','Mac','April','Mei','Jun','Julai','Ogos','September','Oktober','November','Disember'];
$tp = explode('-', $tarikhKej);
$tarikhTeks = count($tp) === 3 ? ((int)$tp[2] . ' ' . $bulan[(int)$tp[1]] . ' ' . $tp[0]) : $tarikhKej;

$kedudukan = kiraKedudukan(muatPasukan(), muatPerlawanan());
$akhir     = kedudukanAkhir(muatPerlawanan());

/** Pencapaian pasukan (jika kejohanan sudah tamat). */
$pencapaian = '';
if ($akhir['johan'] === $pasukan['id'])          $pencapaian = 'JOHAN';
elseif ($akhir['naib_johan'] === $pasukan['id']) $pencapaian = 'NAIB JOHAN';
elseif ($akhir['ketiga'] === $pasukan['id'])     $pencapaian = 'TEMPAT KETIGA';
elseif ($akhir['keempat'] === $pasukan['id'])    $pencapaian = 'TEMPAT KEEMPAT';

$cetak = (string)inp('cetak', '');

header_remove('Content-Type');
header('Content-Type: text/html; charset=utf-8');

/* ---------------------------------------------------------------- gaya */
function gayaSijil(): string
{
    return <<<'CSS'
@page { size: A4 landscape; margin: 0; }
* { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
body { margin: 0; background: #e7e5e4; font-family: Georgia, "Times New Roman", serif; color: #1c1917; }

.sijil {
  position: relative; width: 297mm; height: 210mm; margin: 8mm auto;
  background: #fffdf8; overflow: hidden; page-break-after: always;
  box-shadow: 0 2px 14px rgba(0,0,0,.14);
}
.sijil:last-child { page-break-after: auto; }

/* bingkai */
.sijil::before {
  content: ''; position: absolute; inset: 7mm; border: 2.2mm solid #7B1E2B; border-radius: 3mm;
}
.sijil::after {
  content: ''; position: absolute; inset: 10.5mm; border: 0.6mm solid #C9A227; border-radius: 2mm;
}
.hiasA, .hiasB { position: absolute; border-radius: 50%; pointer-events: none; }
.hiasA { width: 120mm; height: 120mm; right: -45mm; top: -55mm; background: radial-gradient(circle, rgba(201,162,39,.22), transparent 68%); }
.hiasB { width: 110mm; height: 110mm; left: -42mm; bottom: -48mm; background: radial-gradient(circle, rgba(19,34,68,.14), transparent 68%); }

.isi { position: relative; z-index: 2; height: 100%; padding: 20mm 24mm 16mm; text-align: center;
       display: flex; flex-direction: column; }

.logo { height: 24mm; margin: 0 auto 3mm; display: block; }
.penganjur { font-size: 3.3mm; letter-spacing: .32em; text-transform: uppercase; color: #7B1E2B; font-weight: 700; }
.tajukKej { font-size: 6.2mm; letter-spacing: .05em; color: #132244; margin: 2.5mm 0 1mm; font-weight: 700; }
.tarikhKej { font-size: 3.6mm; color: #78716c; font-style: italic; }

.garis { width: 42mm; height: 0.8mm; background: #C9A227; margin: 5mm auto; border-radius: 1mm; }

.jenisSijil { font-size: 4.6mm; letter-spacing: .3em; text-transform: uppercase; color: #7B1E2B; font-weight: 700; }
.diberi { font-size: 3.6mm; color: #57534e; margin-top: 4mm; }

.namaPenerima {
  font-size: 13mm; line-height: 1.12; color: #132244; font-weight: 700;
  margin: 3mm auto 2mm; max-width: 235mm; word-break: break-word;
}
.namaPenerima.kecil { font-size: 9.5mm; }
.namaPenerima.sangatKecil { font-size: 7.5mm; }

.subNama { font-size: 4.2mm; color: #57534e; }
.subNama strong { color: #7B1E2B; }

.pencapaian {
  display: inline-block; margin-top: 3mm; padding: 1.8mm 7mm; border: 0.6mm solid #C9A227;
  border-radius: 2mm; background: rgba(201,162,39,.12);
  font-size: 4.4mm; letter-spacing: .22em; font-weight: 700; color: #a9861d;
}

.kaki { margin-top: auto; display: flex; align-items: flex-end; justify-content: space-between; gap: 10mm; }
.kakiKiri { text-align: left; font-size: 3.2mm; color: #78716c; line-height: 1.5; max-width: 85mm; }
.ttdBlok { text-align: center; min-width: 72mm; }
.ttdImej { height: 17mm; display: block; margin: 0 auto 1mm; }
.ttdKosong { height: 17mm; }
.ttdGaris { border-top: 0.4mm solid #44403c; width: 72mm; margin: 0 auto 1.6mm; }
.ttdNama { font-size: 3.8mm; font-weight: 700; color: #132244; }
.ttdJawatan { font-size: 3.2mm; color: #78716c; margin-top: .6mm; }

/* Bar kawalan — tidak dicetak */
.bar { position: sticky; top: 0; z-index: 20; background: #132244; color: #fff; padding: 12px 16px;
       display: flex; flex-wrap: wrap; gap: 10px; align-items: center; font-family: system-ui, sans-serif; }
.bar h1 { font-size: 15px; margin: 0; flex: 1; min-width: 200px; }
.bar p { margin: 0; font-size: 12px; color: rgba(255,255,255,.7); width: 100%; }
.btn { background: #C9A227; color: #132244; border: 0; border-radius: 8px; padding: 9px 16px;
       font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-block; }
.btn.putih { background: rgba(255,255,255,.14); color: #fff; }
@media print { .bar { display: none !important; } body { background: #fff; }
  .sijil { margin: 0; box-shadow: none; width: 100%; height: 100vh; } }
CSS;
}

function kelasNama(string $nama): string
{
    $p = mb_strlen($nama);
    if ($p > 42) return 'namaPenerima sangatKecil';
    if ($p > 26) return 'namaPenerima kecil';
    return 'namaPenerima';
}

/** Satu sijil. */
function kadSijil(string $jenis, string $namaPenerima, string $sub, string $pencapaian,
                  array $konteks): string
{
    $logo = '../logo-samfire.png';
    $ttd  = $konteks['ttdFail'] !== '' ? 'uploads/' . $konteks['ttdFail'] : '';

    $h  = '<div class="sijil"><div class="hiasA"></div><div class="hiasB"></div><div class="isi">';
    $h .= '<img class="logo" src="' . $logo . '" alt="SAMFIRE FC">';
    $h .= '<div class="penganjur">Anjuran SAMFIRE FC &middot; Kerjasama PAKSY</div>';
    $h .= '<div class="tajukKej">' . e($konteks['namaKej']) . '</div>';
    $h .= '<div class="tarikhKej">' . e($konteks['tarikhTeks']);
    if ($konteks['lokasi'] !== '') $h .= ' &middot; ' . e($konteks['lokasi']);
    $h .= '</div>';
    $h .= '<div class="garis"></div>';
    $h .= '<div class="jenisSijil">' . e($jenis) . '</div>';
    $h .= '<div class="diberi">Dengan ini disahkan bahawa</div>';
    $h .= '<div class="' . kelasNama($namaPenerima) . '">' . e($namaPenerima) . '</div>';
    $h .= '<div class="subNama">' . $sub . '</div>';
    if ($pencapaian !== '') {
        $h .= '<div><span class="pencapaian">' . e($pencapaian) . '</span></div>';
    }
    $h .= '<div class="kaki">';
    $h .= '<div class="kakiKiri">Sijil ini dikeluarkan oleh urus setia<br>Kejohanan Futsal Merdeka Kepala Batas 2026<br>merdeka.samfirefc.com</div>';
    $h .= '<div class="ttdBlok">';
    $h .= $ttd !== '' ? '<img class="ttdImej" src="' . e($ttd) . '" alt="">' : '<div class="ttdKosong"></div>';
    $h .= '<div class="ttdGaris"></div>';
    $h .= '<div class="ttdNama">' . e($konteks['ttdNama']) . '</div>';
    $h .= '<div class="ttdJawatan">' . e($konteks['ttdJawatan']) . '</div>';
    $h .= '</div></div>';
    $h .= '</div></div>';
    return $h;
}

$konteks = [
    'namaKej' => $namaKej, 'tarikhTeks' => $tarikhTeks, 'lokasi' => $lokasi,
    'ttdFail' => $ttdFail, 'ttdNama' => $ttdNama, 'ttdJawatan' => $ttdJawatan,
];

/* ------------------------------------------------------- mod cetak */
if ($cetak !== '') {
    $kad = '';
    $tajukHalaman = '';

    if ($cetak === 'pasukan') {
        $tajukHalaman = 'Sijil Penyertaan Pasukan — ' . $pasukan['nama'];
        $kad = kadSijil('Sijil Penyertaan', $pasukan['nama'],
            'Telah menyertai kejohanan ini sebagai pasukan peserta<br>Kumpulan <strong>' . e($pasukan['kumpulan']) . '</strong>',
            $pencapaian, $konteks);

    } elseif ($cetak === 'pemain') {
        $idP = (int)inp('id', 0);
        $jumpa = null;
        foreach ($pemain as $p) if ((int)$p['id'] === $idP) $jumpa = $p;
        if (!$jumpa) { http_response_code(404); echo 'Pemain tidak dijumpai.'; exit; }
        $tajukHalaman = 'Sijil — ' . $jumpa['nama'];
        $kad = kadSijil('Sijil Penyertaan', $jumpa['nama'],
            'Pemain bagi pasukan <strong>' . e($pasukan['nama']) . '</strong>'
            . ($jumpa['no_jersi'] !== '' ? ' &middot; No. jersi ' . e($jumpa['no_jersi']) : ''),
            $pencapaian, $konteks);

    } else { // semua
        $tajukHalaman = 'Semua Sijil — ' . $pasukan['nama'];
        $kad = kadSijil('Sijil Penyertaan', $pasukan['nama'],
            'Telah menyertai kejohanan ini sebagai pasukan peserta<br>Kumpulan <strong>' . e($pasukan['kumpulan']) . '</strong>',
            $pencapaian, $konteks);
        foreach ($pemain as $p) {
            $kad .= kadSijil('Sijil Penyertaan', $p['nama'],
                'Pemain bagi pasukan <strong>' . e($pasukan['nama']) . '</strong>'
                . ($p['no_jersi'] !== '' ? ' &middot; No. jersi ' . e($p['no_jersi']) : ''),
                $pencapaian, $konteks);
        }
    }

    $bilKad = substr_count($kad, 'class="sijil"');
    ?><!DOCTYPE html>
<html lang="ms"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($tajukHalaman) ?></title>
<style><?= gayaSijil() ?></style>
</head><body>
<div class="bar">
  <h1><?= e($tajukHalaman) ?></h1>
  <button class="btn" onclick="window.print()">Cetak / Simpan PDF</button>
  <a class="btn putih" href="sijil.php?t=<?= e($token) ?>">Kembali</a>
  <p><?= $bilKad ?> muka surat &middot; Tekan <strong>Cetak</strong>, kemudian pilih destinasi <strong>&ldquo;Simpan sebagai PDF&rdquo;</strong>. Pastikan orientasi <strong>Landskap</strong> dan margin <strong>Tiada</strong>.</p>
</div>
<?= $kad ?>
</body></html><?php
    exit;
}

/* --------------------------------------------------- halaman senarai */
$asas = urlAsas();
?><!DOCTYPE html>
<html lang="ms"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Sijil Penyertaan — <?= e($pasukan['nama']) ?></title>
<link rel="icon" href="../favicon.ico">
<style>
 *{box-sizing:border-box}
 body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f5f5f4;color:#1c1917}
 .w{max-width:640px;margin:0 auto;padding:20px 16px 40px}
 .hero{background:linear-gradient(135deg,#661a25,#7B1E2B 45%,#132244);color:#fff;border-radius:16px;padding:22px 20px;position:relative;overflow:hidden}
 .hero img{position:absolute;right:12px;top:12px;height:64px;opacity:.9}
 .hero .kecil{font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:#e8d48a;font-weight:700}
 .hero h1{font-size:21px;margin:6px 0 2px;max-width:calc(100% - 78px);line-height:1.25}
 .hero p{margin:2px 0 0;font-size:13px;color:rgba(255,255,255,.78)}
 .kad{background:#fff;border:1px solid #e7e5e4;border-radius:14px;margin-top:14px;overflow:hidden}
 .kadTajuk{padding:13px 16px;border-bottom:1px solid #f5f5f4;font-weight:700;font-size:14px;display:flex;justify-content:space-between;align-items:center;gap:8px}
 .lencana{font-size:11px;font-weight:700;background:#f5f5f4;color:#57534e;padding:3px 9px;border-radius:999px}
 .baris{display:flex;align-items:center;gap:12px;padding:11px 16px;border-top:1px solid #f5f5f4}
 .baris:first-of-type{border-top:0}
 .no{width:28px;height:28px;border-radius:8px;background:#f5f5f4;display:grid;place-items:center;font-size:12px;font-weight:700;color:#78716c;flex:0 0 auto}
 .nm{flex:1;min-width:0;font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
 .btn{background:#7B1E2B;color:#fff;border:0;border-radius:9px;padding:9px 14px;font-size:13px;font-weight:700;
      cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;white-space:nowrap}
 .btn.emas{background:#C9A227;color:#132244}
 .btn.garis{background:#fff;color:#57534e;border:1px solid #d6d3d1}
 .btn.kecil{padding:6px 11px;font-size:12px}
 .utama{display:block;width:100%;text-align:center;padding:15px;font-size:15px;margin-top:14px}
 .nota{font-size:12px;color:#78716c;line-height:1.6;margin-top:16px}
 .kosong{padding:26px 16px;text-align:center;color:#78716c;font-size:14px}
 a{color:#7B1E2B}
</style>
</head><body>
<div class="w">

  <div class="hero">
    <img src="../logo-samfire.png" alt="SAMFIRE FC">
    <div class="kecil">Sijil Penyertaan</div>
    <h1><?= e($pasukan['nama']) ?></h1>
    <p>Kumpulan <?= e($pasukan['kumpulan']) ?> &middot; <?= e($namaKej) ?></p>
    <?php if ($pencapaian !== ''): ?>
      <p style="margin-top:8px"><span style="background:rgba(201,162,39,.25);color:#e8d48a;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700;letter-spacing:.12em"><?= e($pencapaian) ?></span></p>
    <?php endif; ?>
  </div>

  <a class="btn emas utama" href="sijil.php?t=<?= e($token) ?>&cetak=semua">
    Cetak Semua Sijil (<?= count($pemain) + 1 ?> muka surat)
  </a>

  <div class="kad">
    <div class="kadTajuk">Sijil Pasukan <span class="lencana">1 muka</span></div>
    <div class="baris">
      <div class="no">P</div>
      <div class="nm"><?= e($pasukan['nama']) ?></div>
      <a class="btn kecil garis" href="sijil.php?t=<?= e($token) ?>&cetak=pasukan">Cetak</a>
    </div>
  </div>

  <div class="kad">
    <div class="kadTajuk">Sijil Pemain <span class="lencana"><?= count($pemain) ?> orang</span></div>
    <?php if (!$pemain): ?>
      <div class="kosong">
        Senarai pemain belum dimasukkan.<br>
        Sila hubungi urus setia untuk melengkapkan nama pemain pasukan anda.
      </div>
    <?php else: foreach ($pemain as $i => $p): ?>
      <div class="baris">
        <div class="no"><?= $p['no_jersi'] !== '' ? e($p['no_jersi']) : ($i + 1) ?></div>
        <div class="nm"><?= e($p['nama']) ?></div>
        <a class="btn kecil garis" href="sijil.php?t=<?= e($token) ?>&cetak=pemain&id=<?= (int)$p['id'] ?>">Cetak</a>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <p class="nota">
    <strong>Cara simpan sebagai PDF:</strong> tekan butang Cetak, kemudian pada tetingkap cetakan
    pilih destinasi <strong>&ldquo;Simpan sebagai PDF&rdquo;</strong>. Pastikan orientasi <strong>Landskap</strong>
    dan margin ditetapkan <strong>Tiada</strong> supaya bingkai sijil penuh.
    <br><br>
    Pautan ini khusus untuk pasukan anda sahaja — jangan kongsi kepada pasukan lain.
    <br><br>
    <a href="../">&larr; Laman kejohanan</a>
  </p>

</div>
</body></html>
