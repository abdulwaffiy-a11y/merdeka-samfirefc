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
  background: #fffdf7; overflow: hidden; page-break-after: always;
  box-shadow: 0 2px 14px rgba(0,0,0,.14);
}
.sijil:last-child { page-break-after: auto; }

/* ---------- latar & bingkai ---------- */
.kanvas { position: absolute; inset: 0;
  background:
    radial-gradient(ellipse 120mm 70mm at 50% -12mm, rgba(201,162,39,.13), transparent 70%),
    radial-gradient(ellipse 150mm 80mm at 50% 118%, rgba(19,34,68,.09), transparent 72%),
    linear-gradient(#fffdf7, #fffaf0);
}
/* jalur halus tepi kiri — motif Jalur Gemilang */
.jalur { position: absolute; left: 0; top: 0; bottom: 0; width: 9mm;
  background: repeating-linear-gradient(180deg,
    #7B1E2B 0 7mm, #fffdf7 7mm 14mm); opacity: .9; }
.jalur::after { content: ''; position: absolute; left: 0; top: 0; width: 9mm; height: 56mm;
  background: #132244; }
.bintang { position: absolute; left: 1.6mm; top: 20mm; width: 5.8mm; height: 5.8mm; }

.bingkaiLuar { position: absolute; inset: 6mm 6mm 6mm 12mm; border: 1.4mm solid #7B1E2B; border-radius: 2mm; }
.bingkaiDalam { position: absolute; inset: 8.6mm 8.6mm 8.6mm 14.6mm; border: 0.45mm solid #C9A227; border-radius: 1.4mm; }

/* sudut hiasan */
.sudut { position: absolute; width: 16mm; height: 16mm; border: 0.7mm solid #C9A227; }
.sudut.tl { top: 11mm; left: 17mm; border-right: 0; border-bottom: 0; border-top-left-radius: 3mm; }
.sudut.tr { top: 11mm; right: 11mm; border-left: 0; border-bottom: 0; border-top-right-radius: 3mm; }
.sudut.bl { bottom: 11mm; left: 17mm; border-right: 0; border-top: 0; border-bottom-left-radius: 3mm; }
.sudut.br { bottom: 11mm; right: 11mm; border-left: 0; border-top: 0; border-bottom-right-radius: 3mm; }

/* tera air lencana */
.tera { position: absolute; left: 50%; top: 52%; transform: translate(-50%,-50%);
  width: 118mm; opacity: .052; }

/* ---------- kandungan ---------- */
.isi { position: relative; z-index: 3; height: 100%; padding: 15mm 22mm 13mm 28mm;
       text-align: center; display: flex; flex-direction: column; }

.pitaMerdeka { display: inline-block; margin: 0 auto; padding: 1.4mm 8mm 1.4mm 8mm;
  background: linear-gradient(90deg, #7B1E2B, #a02a3c 55%, #7B1E2B); color: #ffe9a8;
  font-size: 3.1mm; letter-spacing: .42em; text-transform: uppercase; font-weight: 700;
  border-radius: 1mm; box-shadow: 0 .6mm 0 rgba(0,0,0,.12); font-family: Arial, Helvetica, sans-serif; }

.kepala { display: flex; align-items: center; justify-content: center; gap: 6mm; margin-top: 3.5mm; }
.logo { height: 21mm; display: block; }
.kepalaTeks { text-align: left; }
.penganjur { font-size: 2.9mm; letter-spacing: .26em; text-transform: uppercase; color: #7B1E2B;
  font-weight: 700; font-family: Arial, Helvetica, sans-serif; }
.tajukKej { font-size: 5.6mm; letter-spacing: .02em; color: #132244; margin-top: 1mm; font-weight: 700; line-height: 1.15; }
.tarikhKej { font-size: 3.2mm; color: #78716c; font-style: italic; margin-top: .8mm; }

.pembahagi { display: flex; align-items: center; justify-content: center; gap: 2.5mm; margin: 5mm auto 0; width: 70mm; }
.pembahagi i { flex: 1; height: 0.5mm; background: linear-gradient(90deg, transparent, #C9A227); }
.pembahagi i:last-child { background: linear-gradient(90deg, #C9A227, transparent); }
.pembahagi b { width: 2.6mm; height: 2.6mm; background: #C9A227; transform: rotate(45deg); }

.jenisSijil { font-size: 4.4mm; letter-spacing: .38em; text-transform: uppercase; color: #7B1E2B;
  font-weight: 700; margin-top: 4mm; font-family: Arial, Helvetica, sans-serif; }
.diberi { font-size: 3.3mm; color: #78716c; margin-top: 3mm; font-style: italic; }

.namaPenerima { font-size: 13mm; line-height: 1.1; color: #132244; font-weight: 700;
  margin: 1.5mm auto 0; max-width: 225mm; word-break: break-word; }
.namaPenerima.kecil { font-size: 10mm; }
.namaPenerima.sangatKecil { font-size: 7.6mm; }
.garisNama { width: 120mm; height: 0.4mm; background: linear-gradient(90deg, transparent, #d6d3d1 20%, #d6d3d1 80%, transparent); margin: 2mm auto 0; }

.subNama { font-size: 3.9mm; color: #57534e; margin-top: 2.5mm; }
.subNama strong { color: #7B1E2B; }

.pencapaian { display: inline-block; margin-top: 3mm; padding: 1.6mm 7mm;
  border: 0.5mm solid #C9A227; border-radius: 1.4mm;
  background: linear-gradient(#fdf6e0, #f7ecc9);
  font-size: 4mm; letter-spacing: .24em; font-weight: 700; color: #8a6c15;
  font-family: Arial, Helvetica, sans-serif; }

/* jalur maklumat */
.jalurInfo { margin: auto auto 0; display: flex; gap: 0; align-items: stretch;
  border: 0.4mm solid #eadfc4; border-radius: 1.4mm; overflow: hidden; background: #fffcf2; }
.jalurInfo div { padding: 2mm 7mm; text-align: center; border-left: 0.4mm solid #eadfc4; }
.jalurInfo div:first-child { border-left: 0; }
.jalurInfo span { display: block; font-size: 2.5mm; letter-spacing: .2em; text-transform: uppercase;
  color: #a8a29e; font-family: Arial, Helvetica, sans-serif; }
.jalurInfo strong { display: block; font-size: 3.3mm; color: #44403c; margin-top: .6mm; }

.kaki { margin-top: 6mm; display: flex; align-items: flex-end; justify-content: space-between; gap: 8mm; }
.kakiKiri { text-align: left; font-size: 2.8mm; color: #a8a29e; line-height: 1.55; max-width: 78mm;
  font-family: Arial, Helvetica, sans-serif; }
.kakiKiri b { color: #78716c; letter-spacing: .06em; }
.ttdBlok { text-align: center; min-width: 70mm; }
.ttdImej { height: 15mm; display: block; margin: 0 auto .5mm; }
.ttdKosong { height: 15mm; }
.ttdGaris { border-top: 0.4mm solid #57534e; width: 70mm; margin: 0 auto 1.4mm; }
.ttdNama { font-size: 3.6mm; font-weight: 700; color: #132244; }
.ttdJawatan { font-size: 2.9mm; color: #78716c; margin-top: .5mm; }

/* jalur bawah */
.jalurBawah { position: absolute; left: 12mm; right: 6mm; bottom: 6mm; height: 1.6mm; z-index: 4;
  background: linear-gradient(90deg, #132244 0 18%, #7B1E2B 18% 46%, #C9A227 46% 54%, #7B1E2B 54% 82%, #132244 82% 100%); }

/* Bar kawalan — tidak dicetak */
.bar { position: sticky; top: 0; z-index: 20; background: #132244; color: #fff; padding: 12px 16px;
       display: flex; flex-wrap: wrap; gap: 10px; align-items: center; font-family: system-ui, sans-serif; }
.bar h1 { font-size: 15px; margin: 0; flex: 1; min-width: 200px; }
.bar p { margin: 0; font-size: 12px; color: rgba(255,255,255,.7); width: 100%; }
.btn { background: #C9A227; color: #132244; border: 0; border-radius: 8px; padding: 9px 16px;
       font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-block; }
.btn.putih { background: rgba(255,255,255,.14); color: #fff; }
@media print { .bar { display: none !important; } body { background: #fff; }
  .sijil { margin: 0; box-shadow: none; } }
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
                  array $konteks, array $info = [], string $siri = ''): string
{
    $logo = '../logo-samfire.png';
    $ttd  = $konteks['ttdFail'] !== '' ? 'uploads/' . $konteks['ttdFail'] : '';

    $bintang = '<svg class="bintang" viewBox="0 0 24 24" fill="#C9A227">'
             . '<path d="M12 1.6l2.9 6.4 6.9.7-5.2 4.7 1.5 6.9L12 16.9 5.9 20.3l1.5-6.9L2.2 8.7l6.9-.7z"/></svg>';

    $h  = '<div class="sijil">';
    $h .= '<div class="kanvas"></div>';
    $h .= '<div class="jalur"></div>' . $bintang;
    $h .= '<img class="tera" src="' . $logo . '" alt="">';
    $h .= '<div class="bingkaiLuar"></div><div class="bingkaiDalam"></div>';
    $h .= '<div class="sudut tl"></div><div class="sudut tr"></div><div class="sudut bl"></div><div class="sudut br"></div>';
    $h .= '<div class="jalurBawah"></div>';

    $h .= '<div class="isi">';
    $h .= '<div><span class="pitaMerdeka">Merdeka ' . e($konteks['tahun']) . '</span></div>';

    $h .= '<div class="kepala">';
    $h .= '<img class="logo" src="' . $logo . '" alt="SAMFIRE FC">';
    $h .= '<div class="kepalaTeks">';
    $h .= '<div class="penganjur">Anjuran SAMFIRE FC &middot; Kerjasama PAKSY</div>';
    $h .= '<div class="tajukKej">' . e($konteks['namaKej']) . '</div>';
    $h .= '<div class="tarikhKej">' . e($konteks['tarikhTeks']);
    if ($konteks['lokasi'] !== '') $h .= ' &middot; ' . e($konteks['lokasi']);
    $h .= '</div></div></div>';

    $h .= '<div class="pembahagi"><i></i><b></b><i></i></div>';
    $h .= '<div class="jenisSijil">' . e($jenis) . '</div>';
    $h .= '<div class="diberi">Dengan ini disahkan bahawa</div>';
    $h .= '<div class="' . kelasNama($namaPenerima) . '">' . e($namaPenerima) . '</div>';
    $h .= '<div class="garisNama"></div>';
    $h .= '<div class="subNama">' . $sub . '</div>';
    if ($pencapaian !== '') {
        $h .= '<div><span class="pencapaian">' . e($pencapaian) . '</span></div>';
    }

    if ($info) {
        $h .= '<div class="jalurInfo">';
        foreach ($info as $label => $nilai) {
            $h .= '<div><span>' . e((string)$label) . '</span><strong>' . e((string)$nilai) . '</strong></div>';
        }
        $h .= '</div>';
    }

    $h .= '<div class="kaki">';
    $h .= '<div class="kakiKiri">';
    if ($siri !== '') $h .= '<b>No. Sijil</b> ' . e($siri) . '<br>';
    $h .= 'Dikeluarkan oleh urus setia kejohanan<br>merdeka.samfirefc.com';
    $h .= '</div>';
    $h .= '<div class="ttdBlok">';
    $h .= $ttd !== '' ? '<img class="ttdImej" src="' . e($ttd) . '" alt="">' : '<div class="ttdKosong"></div>';
    $h .= '<div class="ttdGaris"></div>';
    $h .= '<div class="ttdNama">' . e($konteks['ttdNama']) . '</div>';
    $h .= '<div class="ttdJawatan">' . e($konteks['ttdJawatan']) . '</div>';
    $h .= '</div></div>';
    $h .= '</div></div>';
    return $h;
}

$tahunKej = count($tp) === 3 ? $tp[0] : date('Y');
$konteks = [
    'namaKej' => $namaKej, 'tarikhTeks' => $tarikhTeks, 'lokasi' => $lokasi,
    'ttdFail' => $ttdFail, 'ttdNama' => $ttdNama, 'ttdJawatan' => $ttdJawatan,
    'tahun'   => $tahunKej,
];
$siriAsas = 'MKB' . $tahunKej . '/' . $pasukan['kumpulan'] . $pasukan['slot'];

/* ------------------------------------------------------- mod cetak */
if ($cetak !== '') {
    $kad = '';
    $tajukHalaman = '';

    if ($cetak === 'pasukan') {
        $tajukHalaman = 'Sijil Penyertaan Pasukan — ' . $pasukan['nama'];
        $kad = kadSijil('Sijil Penyertaan Pasukan', $pasukan['nama'],
            'Telah menyertai kejohanan ini sebagai pasukan peserta',
            $pencapaian, $konteks,
            ['Kumpulan' => $pasukan['kumpulan'], 'Bilangan Pemain' => (string)count($pemain), 'Kategori' => 'Terbuka'],
            $siriAsas . '/P');

    } elseif ($cetak === 'pemain') {
        $idP = (int)inp('id', 0);
        $jumpa = null;
        foreach ($pemain as $p) if ((int)$p['id'] === $idP) $jumpa = $p;
        if (!$jumpa) { http_response_code(404); echo 'Pemain tidak dijumpai.'; exit; }
        $tajukHalaman = 'Sijil — ' . $jumpa['nama'];
        $kad = kadSijil('Sijil Penyertaan', $jumpa['nama'],
            'Pemain bagi pasukan <strong>' . e($pasukan['nama']) . '</strong>',
            $pencapaian, $konteks,
            ['Pasukan' => $pasukan['nama'], 'Kumpulan' => $pasukan['kumpulan'],
             'No. Jersi' => $jumpa['no_jersi'] !== '' ? $jumpa['no_jersi'] : '—'],
            $siriAsas . '/' . str_pad((string)$jumpa['id'], 3, '0', STR_PAD_LEFT));

    } else { // semua
        $tajukHalaman = 'Semua Sijil — ' . $pasukan['nama'];
        $kad = kadSijil('Sijil Penyertaan Pasukan', $pasukan['nama'],
            'Telah menyertai kejohanan ini sebagai pasukan peserta',
            $pencapaian, $konteks,
            ['Kumpulan' => $pasukan['kumpulan'], 'Bilangan Pemain' => (string)count($pemain), 'Kategori' => 'Terbuka'],
            $siriAsas . '/P');
        foreach ($pemain as $p) {
            $kad .= kadSijil('Sijil Penyertaan', $p['nama'],
                'Pemain bagi pasukan <strong>' . e($pasukan['nama']) . '</strong>',
                $pencapaian, $konteks,
                ['Pasukan' => $pasukan['nama'], 'Kumpulan' => $pasukan['kumpulan'],
                 'No. Jersi' => $p['no_jersi'] !== '' ? $p['no_jersi'] : '—'],
                $siriAsas . '/' . str_pad((string)$p['id'], 3, '0', STR_PAD_LEFT));
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
