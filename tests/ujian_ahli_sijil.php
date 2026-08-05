<?php
/**
 * ujian_ahli_sijil.php — ujian hujung-ke-hujung:
 *   A) Pendaftaran keahlian SAMFIRE FC (RM15)
 *   B) Sistem sijil penyertaan (pautan pasukan + cetak)
 */

declare(strict_types=1);

require __DIR__ . '/klien.php';

$BASE   = getenv('MERDEKA_BASE') ?: 'http://127.0.0.1:8080';
$DBNAME = getenv('MERDEKA_DB')   ?: 'merdeka';
$PASS   = 'ujian12345';

function resetDbAS(string $db, string $pass): void
{
    exec('sudo mariadb ' . escapeshellarg($db) . ' < ' . escapeshellarg(__DIR__ . '/../sql/schema.sql'), $o, $c);
    if ($c !== 0) throw new RuntimeException('Gagal import skema.');
    $h = password_hash($pass, PASSWORD_BCRYPT);
    exec('sudo mariadb ' . escapeshellarg($db) . ' -e ' . escapeshellarg(
        "INSERT INTO admins (nama,email,password_hash,role) VALUES ('Ujian Super','ujian@paksy.test','$h','super');"
    ));
    exec('sudo mariadb ' . escapeshellarg($db) . ' -e ' . escapeshellarg('DROP TABLE IF EXISTS ahli;'));
}

/** POST multipart. */
function postBorang(string $url, array $medan, array $fail = []): array
{
    foreach ($fail as $k => $p) $medan[$k] = new CURLFile($p, mime_content_type($p), basename($p));
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $medan,
        CURLOPT_TIMEOUT => 30,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    $d = json_decode((string)$r, true);
    return is_array($d) ? $d : ['_mentah' => (string)$r];
}

/** GET biasa (teks penuh, bukan JSON). */
function getTeks(string $url, ?string $cookieFile = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($cookieFile) curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $body = (string)curl_exec($ch);
    $kod  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$kod, $body];
}

/** Cipta imej ujian kecil. */
function imejUjian(string $path, int $w, int $h, array $rgb): string
{
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, ...$rgb));
    imagepng($im, $path);
    imagedestroy($im);
    return $path;
}

echo "Base: $BASE  DB: $DBNAME\n";
resetDbAS($DBNAME, $PASS);

$admin = new Klien($BASE, 'as_admin');
$r = $admin->login('ujian@paksy.test', $PASS);
if (empty($r['ok'])) { echo "Tidak dapat log masuk admin.\n"; exit(1); }

$gambar = imejUjian(sys_get_temp_dir() . '/uji_gambar.png', 400, 400, [30, 90, 160]);
$bukti  = imejUjian(sys_get_temp_dir() . '/uji_bukti.png', 600, 800, [240, 240, 240]);
$tanda  = imejUjian(sys_get_temp_dir() . '/uji_tandatangan.png', 500, 180, [255, 255, 255]);

/* ================================================================== */
tajukUjian('A1. Borang keahlian — maklumat awam');

$info = $admin->get('/api/ahli.php', ['action' => 'info']);
sahkan(!empty($info['ok']), 'GET ?action=info berjaya');
sahkan(($info['bayaran']['yuran'] ?? '') === 'RM15', 'Yuran keahlian RM15', 'dapat: ' . ($info['bayaran']['yuran'] ?? '-'));
sahkan(!empty($info['bayaran']['buka']), 'Pendaftaran keahlian dibuka secara lalai');

/* ================================================================== */
tajukUjian('A2. Hantar borang keahlian (dengan gambar + bukti bayaran)');

$hantar = postBorang("$BASE/api/ahli.php?action=hantar", [
    'nama'           => 'Muhammad Waffiy bin Rosli',
    'nama_panggilan' => 'Wafi',
    'no_kp'          => '900101075123',
    'tarikh_lahir'   => '1990-01-01',
    'jantina'        => 'lelaki',
    'telefon'        => '012-3456789',
    'emel'           => 'wafi@contoh.com',
    'alamat'         => 'No. 12, Jalan Bertam',
    'bandar'         => 'Kepala Batas',
    'poskod'         => '13200',
    'negeri'         => 'Pulau Pinang',
    'posisi'         => 'Penyerang',
    'no_jersi'       => '10',
    'pemain_idola'   => 'Messi, Ronaldo',
], ['gambar' => $gambar, 'bukti' => $bukti]);

sahkan(!empty($hantar['ok']), 'Borang keahlian diterima', json_encode($hantar));
sahkan(!empty($hantar['id']), 'ID ahli dipulangkan');
sahkan(preg_match('/^AHLI-\d{4}$/', (string)($hantar['rujukan'] ?? '')) === 1,
       'No. rujukan dijana (AHLI-0001)', 'dapat: ' . ($hantar['rujukan'] ?? '-'));
$idAhli = (int)($hantar['id'] ?? 0);

/* ================================================================== */
tajukUjian('A3. Pengesahan data (validasi)');

$k1 = postBorang("$BASE/api/ahli.php?action=hantar", ['nama' => 'Ab', 'no_kp' => '880202075555', 'telefon' => '0121112222']);
sahkan(empty($k1['ok']), 'Nama terlalu pendek ditolak', json_encode($k1));

$k2 = postBorang("$BASE/api/ahli.php?action=hantar", ['nama' => 'Ahmad Bakri', 'no_kp' => '123', 'telefon' => '0121112222']);
sahkan(empty($k2['ok']), 'No. KP tidak sah ditolak');

$k3 = postBorang("$BASE/api/ahli.php?action=hantar", ['nama' => 'Ahmad Bakri', 'no_kp' => '880202075555', 'telefon' => 'abcdef']);
sahkan(empty($k3['ok']), 'No. telefon tidak sah ditolak');

$k4 = postBorang("$BASE/api/ahli.php?action=hantar", [
    'nama' => 'Ahmad Bakri', 'no_kp' => '880202075555', 'telefon' => '0121112222', 'emel' => 'bukanemel',
]);
sahkan(empty($k4['ok']), 'Emel tidak sah ditolak');

$k5 = postBorang("$BASE/api/ahli.php?action=hantar", [
    'nama' => 'Penipu Bot', 'no_kp' => '880202075999', 'telefon' => '0121112222', 'website' => 'http://spam.test',
]);
sahkan(empty($k5['ok']), 'Honeypot menahan bot');

$k6 = postBorang("$BASE/api/ahli.php?action=hantar", [
    'nama' => 'Orang Lain Guna KP Sama', 'no_kp' => '900101075123', 'telefon' => '0129998888',
]);
sahkan(empty($k6['ok']), 'No. KP berulang ditolak', json_encode($k6));

/* ================================================================== */
tajukUjian('A4. Daftar beberapa ahli lagi');

$lagi = [
    ['Nurul Ain binti Hassan', '950505075111', 'perempuan', 'Penjaga Gol', 'Alisson'],
    ['Syafiq bin Ismail',      '010203075222', 'lelaki',    'Pertahanan', 'Van Dijk'],
];
foreach ($lagi as $i => [$nama, $kp, $jantina, $posisi, $idola]) {
    $x = postBorang("$BASE/api/ahli.php?action=hantar", [
        'nama' => $nama, 'no_kp' => $kp, 'jantina' => $jantina, 'telefon' => '011-2233' . (4455 + $i),
        'bandar' => 'Kepala Batas', 'negeri' => 'Pulau Pinang', 'poskod' => '13200',
        'posisi' => $posisi, 'pemain_idola' => $idola, 'no_jersi' => (string)(($i + 1) * 3),
    ]);
    sahkan(!empty($x['ok']), "Ahli '$nama' berjaya didaftar", json_encode($x));
}

/* ================================================================== */
tajukUjian('A5. Paparan admin — senarai & kiraan');

$urus = $admin->get('/api/ahli.php', ['action' => 'urus']);
sahkan(!empty($urus['ok']), 'Admin boleh baca senarai ahli');
sahkan(count($urus['ahli'] ?? []) === 3, 'Tiga rekod dipapar', 'dapat: ' . count($urus['ahli'] ?? []));
sahkan(($urus['kiraan']['baru'] ?? -1) === 3, 'Kiraan "menunggu" = 3');
$rekod = null;
foreach ($urus['ahli'] as $a) if ((int)$a['id'] === $idAhli) $rekod = $a;
sahkan($rekod !== null, 'Rekod ujian dijumpai');
sahkan(($rekod['pemain_idola'] ?? '') === 'Messi, Ronaldo', 'Pemain idola disimpan betul');
sahkan(($rekod['nama_panggilan'] ?? '') === 'Wafi', 'Nama panggilan disimpan');
sahkan(!empty($rekod['gambar_url']), 'Gambar diri tersimpan');
sahkan(!empty($rekod['bukti_url']), 'Bukti pembayaran tersimpan');

/* Awam TIDAK boleh baca senarai */
$tetamu = new Klien($BASE, 'as_tetamu');
$curi = $tetamu->get('/api/ahli.php', ['action' => 'urus']);
sahkan(empty($curi['ok']), 'Orang awam tidak boleh baca senarai ahli (privasi)');

/* ================================================================== */
tajukUjian('A6. Sahkan / tolak ahli');

$admin->segarkanCsrf();
$s = $admin->post('/api/ahli.php', ['action' => 'lulus'], ['id' => $idAhli]);
sahkan(!empty($s['ok']), 'Admin sahkan ahli', json_encode($s));

$urus2 = $admin->get('/api/ahli.php', ['action' => 'urus']);
sahkan(($urus2['kiraan']['lulus'] ?? -1) === 1, 'Kiraan "ahli sah" = 1');
sahkan(($urus2['kiraan']['baru'] ?? -1) === 2, 'Kiraan "menunggu" turun ke 2');

$idTolak = 0;
foreach ($urus2['ahli'] as $a) if ($a['status'] === 'baru') { $idTolak = (int)$a['id']; break; }
$t = $admin->post('/api/ahli.php', ['action' => 'tolak'], ['id' => $idTolak, 'catatan' => 'Bukti bayaran tiada']);
sahkan(!empty($t['ok']), 'Admin tolak permohonan');

$urus3 = $admin->get('/api/ahli.php', ['action' => 'urus']);
sahkan(($urus3['kiraan']['tolak'] ?? -1) === 1, 'Kiraan "ditolak" = 1');

/* ================================================================== */
tajukUjian('A7. Muat turun CSV');

$ck = sys_get_temp_dir() . '/merdeka_ck_as_admin_' . getmypid() . '.txt';
[$kod, $csv] = getTeks("$BASE/api/ahli.php?action=csv", $ck);
sahkan($kod === 200, 'CSV senarai ahli dimuat turun');
sahkan(str_contains($csv, 'Muhammad Waffiy'), 'CSV mengandungi nama ahli');
sahkan(str_contains($csv, 'Messi, Ronaldo') || str_contains($csv, '"Messi, Ronaldo"'), 'CSV mengandungi pemain idola');

[$kod2, $csv2] = getTeks("$BASE/api/ahli.php?action=csv&format=members", $ck);
sahkan($kod2 === 200, 'CSV format samfirefc.com dimuat turun');
sahkan(str_contains($csv2, 'member_no') && str_contains($csv2, 'full_name') && str_contains($csv2, 'ic_no'),
       'CSV format members ada lajur betul');
sahkan(str_contains($csv2, ',M,') || str_contains($csv2, '"M"'), 'Jantina ditukar ke format M/F');

/* ================================================================== */
tajukUjian('A8. Tutup / buka pendaftaran keahlian');

$admin->segarkanCsrf();
$tutup = $admin->post('/api/ahli.php', ['action' => 'tetapan'], ['ahli_buka' => false]);
sahkan(!empty($tutup['ok']), 'Admin boleh tutup pendaftaran');

$cubaTutup = postBorang("$BASE/api/ahli.php?action=hantar", [
    'nama' => 'Cuba Masa Tutup', 'no_kp' => '770707075777', 'telefon' => '0123334444',
]);
sahkan(empty($cubaTutup['ok']), 'Borang ditolak semasa pendaftaran ditutup');

$buka = $admin->post('/api/ahli.php', ['action' => 'tetapan'], ['ahli_buka' => true]);
sahkan(!empty($buka['ok']), 'Admin boleh buka semula pendaftaran');

$bayaran = $admin->post('/api/ahli.php', ['action' => 'tetapan'], [
    'yuran_ahli' => 'RM15', 'bayar_kepada' => 'SAMFIRE FOOTBALL CLUB',
    'bayar_bank' => 'Maybank', 'bayar_akaun' => '1122 3344 5566',
]);
sahkan(!empty($bayaran['ok']), 'Butiran bayaran boleh disimpan');
$info2 = $tetamu->get('/api/ahli.php', ['action' => 'info']);
sahkan(($info2['bayaran']['bayar_akaun'] ?? '') === '1122 3344 5566', 'Butiran bayaran dipapar kepada pemohon',
       'dapat: ' . ($info2['bayaran']['bayar_akaun'] ?? '-'));

/* ================================================================== */
/* ==========================  B. SIJIL  ============================ */
/* ================================================================== */
tajukUjian('B1. Sijil sebelum ada pasukan');

$admin->segarkanCsrf();
$p0 = $admin->get('/api/sijil.php', ['action' => 'pautan']);
sahkan(!empty($p0['ok']), 'Endpoint pautan sijil berfungsi');
sahkan(count($p0['pasukan'] ?? []) === 0, 'Belum ada pasukan → senarai kosong (betul)');

/* ---- Isi 24 pasukan melalui undian kumpulan --------------------- */
tajukUjian('B2. Sediakan 24 pasukan + pemain');

$namaPasukan = [];
for ($i = 1; $i <= 24; $i++) $namaPasukan[] = sprintf('Pasukan Ujian %02d', $i);
$admin->segarkanCsrf();
$senarai = [];
foreach ($namaPasukan as $n) $senarai[] = ['nama' => $n];
$undi = $admin->post('/api/undi_kumpulan.php', ['action' => 'jalan'], ['senarai' => $senarai]);
sahkan(!empty($undi['ok']), 'Undian 24 pasukan berjaya', json_encode(array_slice((array)$undi, 0, 3)));

$awam = $tetamu->get('/api/public.php', []);
$idPasukan = [];
foreach ($awam['pasukan'] ?? [] as $t) $idPasukan[] = (int)$t['id'];
sahkan(count($idPasukan) === 24, '24 pasukan wujud dalam database', 'dapat: ' . count($idPasukan));

/* Tambah pemain kepada pasukan pertama */
$admin->segarkanCsrf();
$pemain = [];
foreach (['Ahmad Faiz', 'Rizal Hakim', 'Amir Danial', 'Hafiz Nasir', 'Zulfahmi Aziz'] as $n => $nama) {
    $pemain[] = ['nama' => $nama, 'no_jersi' => (string)($n + 7)];
}
$sp = $admin->post('/api/teams.php', ['action' => 'pemain_simpan'], ['team_id' => $idPasukan[0], 'pemain' => $pemain]);
sahkan(!empty($sp['ok']), 'Pemain disimpan ke pasukan pertama', json_encode($sp));

/* ================================================================== */
tajukUjian('B3. Pautan sijil setiap pasukan');

$admin->segarkanCsrf();
$pl = $admin->get('/api/sijil.php', ['action' => 'pautan']);
sahkan(!empty($pl['ok']), 'Senarai pautan sijil dipulangkan');
sahkan(count($pl['pasukan'] ?? []) === 24, '24 pautan sijil dijana', 'dapat: ' . count($pl['pasukan'] ?? []));

$tokens = [];
foreach ($pl['pasukan'] as $p) {
    preg_match('/[?&]t=([a-f0-9]+)/', (string)$p['pautan'], $mm);
    $tokens[(int)$p['id']] = (string)($mm[1] ?? '');
}
sahkan(count(array_unique($tokens)) === 24, 'Setiap pasukan token UNIK (pasukan lain tak boleh intai)');
$satu = $pl['pasukan'][0];
sahkan(str_contains((string)$satu['pautan'], 'sijil.php?t='), 'Format pautan betul');
sahkan(strlen(reset($tokens)) === 16, 'Token 16 aksara', 'dapat: ' . strlen(reset($tokens)));

/* Orang awam tidak boleh dapat senarai pautan */
$curi2 = $tetamu->get('/api/sijil.php', ['action' => 'pautan']);
sahkan(empty($curi2['ok']), 'Orang awam tidak boleh dapat senarai semua pautan');

/* ================================================================== */
tajukUjian('B4. Muat naik tandatangan YB');

$admin->segarkanCsrf();
$meAdmin = $admin->get('/api/auth.php', ['action' => 'me']);
$csrfAdmin = (string)($meAdmin['csrf'] ?? '');
sahkan($csrfAdmin !== '', 'Token CSRF admin diperoleh');
$tt = postBorangSesi("$BASE/api/sijil.php?action=tandatangan", ['tandatangan' => $tanda], $ck, $csrfAdmin);
sahkan(!empty($tt['ok']), 'Tandatangan dimuat naik', json_encode($tt));

$set = $admin->post('/api/sijil.php', ['action' => 'tetapan'], [
    'nama_penandatangan'    => "YB Dato' Seri Reezal Merican",
    'jawatan_penandatangan' => 'Penaja Kejohanan',
]);
sahkan(!empty($set['ok']), 'Nama & jawatan penandatangan disimpan');

/* ================================================================== */
tajukUjian('B5. Halaman sijil pasukan (pautan awam)');

$idAda   = $idPasukan[0];
$tokAda  = $tokens[$idAda];
[$kodS, $htmlS] = getTeks("$BASE/api/sijil.php?t=$tokAda");
sahkan($kodS === 200, 'Pautan sijil dibuka tanpa log masuk (untuk pengurus pasukan)');
sahkan(str_contains($htmlS, 'Ahmad Faiz'), 'Nama pemain terpapar');
sahkan(str_contains($htmlS, 'Zulfahmi Aziz'), 'Semua 5 pemain terpapar');
sahkan(str_contains($htmlS, 'Cetak'), 'Butang Cetak / Simpan PDF ada');

$tokSalah = str_repeat('0', 16);
[$kodX, $htmlX] = getTeks("$BASE/api/sijil.php?t=$tokSalah");
sahkan($kodX !== 200 || !str_contains($htmlX, 'Ahmad Faiz'), 'Token salah ditolak');

$tokLain = $tokens[$idPasukan[1]];
[$kodY, $htmlY] = getTeks("$BASE/api/sijil.php?t=$tokLain");
sahkan(!str_contains($htmlY, 'Ahmad Faiz'), 'Pasukan lain TIDAK nampak pemain pasukan pertama');

/* ================================================================== */
tajukUjian('B6. Cetak semua sijil');

[$kodC, $htmlC] = getTeks("$BASE/api/sijil.php?t=$tokAda&cetak=semua");
sahkan($kodC === 200, 'Halaman cetak semua dibuka');
$bilSijil = count(explode('class="sijil"', $htmlC)) - 1;
sahkan($bilSijil === 6, '6 sijil dijana (5 pemain + 1 pasukan)', "dapat: $bilSijil");
sahkan(stripos($htmlC, 'Sijil Penyertaan') !== false, 'Tajuk "Sijil Penyertaan" ada');
sahkan(str_contains($htmlC, 'KEJOHANAN FUTSAL MERDEKA KEPALA BATAS 2026'), 'Nama kejohanan penuh ada');
sahkan(stripos($htmlC, 'Anjuran SAMFIRE FC') !== false, 'Kredit "Anjuran SAMFIRE FC" ada');
sahkan(str_contains($htmlC, 'MKB2026/'), 'No. siri sijil dijana');
sahkan(str_contains($htmlC, 'Reezal Merican'), 'Nama penandatangan terpapar');
sahkan(str_contains($htmlC, 'landscape'), 'Saiz cetak A4 landskap ditetapkan');
sahkan(substr_count($htmlC, 'jalurBawah') >= 1, 'Hiasan tema Merdeka (jalur) hadir');

/* ================================================================== */
tajukUjian('B7. Sijil individu seorang pemain');

preg_match('/cetak=pemain&(?:amp;)?id=(\d+)/', $htmlS, $m);
$idPemain = (int)($m[1] ?? 0);
sahkan($idPemain > 0, 'Pautan sijil individu dijumpai');

if ($idPemain > 0) {
    [$kodI, $htmlI] = getTeks("$BASE/api/sijil.php?t=$tokAda&cetak=pemain&id=$idPemain");
    sahkan($kodI === 200, 'Sijil individu dibuka');
    $bilI = count(explode('class="sijil"', $htmlI)) - 1;
    sahkan($bilI === 1, 'Satu sijil sahaja untuk pemain itu', "dapat: $bilI");
    sahkan(stripos($htmlI, 'No. Jersi') !== false, 'Jalur info (Pasukan/Kumpulan/No. Jersi) ada');
}

$namaA1 = '';
foreach ($pl['pasukan'] as $p) if ((int)$p['id'] === $idAda) $namaA1 = (string)$p['nama'];
[$kodP, $htmlP] = getTeks("$BASE/api/sijil.php?t=$tokAda&cetak=pasukan");
sahkan($kodP === 200, 'Sijil pasukan dibuka');
sahkan($namaA1 !== '' && str_contains($htmlP, $namaA1), 'Nama pasukan pada sijil pasukan', "cari: $namaA1");

/* ================================================================== */
tajukUjian('B8. Pasukan tanpa pemain');

$tokKosong = $tokens[$idPasukan[2]];
[$kodK, $htmlK] = getTeks("$BASE/api/sijil.php?t=$tokKosong");
sahkan($kodK === 200, 'Pasukan tanpa pemain masih boleh buka pautan (tiada ralat)');


/* ================================================================== */
tajukUjian('C. Admin sunting nama pasukan & pemain (selepas pengurus daftar)');

/* Pengurus hantar borang pendaftaran TANPA nama pemain */
$ch = curl_init("$BASE/api/daftar.php?action=hantar");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 30,
    CURLOPT_POSTFIELDS => ['nama' => 'SAMFIRE FC', 'pengurus' => 'MR ZAKI',
                           'telefon' => '+60 19-488 4084', 'pemain' => '[]'],
]);
$dRaw = curl_exec($ch); curl_close($ch);
$dDaftar = json_decode((string)$dRaw, true) ?: [];
sahkan(!empty($dDaftar['ok']), 'Pengurus hantar pendaftaran pasukan', json_encode($dDaftar));

$admin->segarkanCsrf();
$urusD = $admin->get('/api/daftar.php', ['action' => 'urus']);
$rek = null;
foreach ($urusD['senarai'] ?? [] as $x) if ($x['nama'] === 'SAMFIRE FC') $rek = $x;
sahkan($rek !== null, 'Pendaftaran dipapar kepada admin');
sahkan(count($rek['pemain'] ?? []) === 0, 'Mula dengan 0 pemain');

/* Admin TAMBAH pemain */
$idD = (int)$rek['id'];
$kemas = $admin->post('/api/daftar.php', ['action' => 'kemas'], [
    'id' => $idD, 'nama' => 'SAMFIRE FC', 'pengurus' => 'MR ZAKI', 'telefon' => '+60 19-488 4084',
    'pemain' => [
        ['nama' => 'Zaki Rahman', 'no_jersi' => '1'],
        ['nama' => 'Hakim Aziz',  'no_jersi' => '7'],
        ['nama' => 'Faiz Osman',  'no_jersi' => '9'],
    ],
]);
sahkan(!empty($kemas['ok']), 'Admin tambah 3 pemain', json_encode($kemas));
sahkan(count($kemas['pemain'] ?? []) === 3, 'Tiga pemain disimpan');

/* Admin BUANG seorang + UBAH nama */
$kemas2 = $admin->post('/api/daftar.php', ['action' => 'kemas'], [
    'id' => $idD, 'nama' => 'SAMFIRE FC A',
    'pemain' => [
        ['nama' => 'Zaki Rahman',   'no_jersi' => '1'],
        ['nama' => 'Hakim Abdul Aziz', 'no_jersi' => '77'],
    ],
]);
sahkan(!empty($kemas2['ok']), 'Admin buang seorang & betulkan nama');
sahkan(count($kemas2['pemain'] ?? []) === 2, 'Tinggal 2 pemain');
sahkan(($kemas2['pemain'][1]['nama'] ?? '') === 'Hakim Abdul Aziz', 'Nama pemain berjaya diubah');
sahkan(($kemas2['pemain'][1]['no_jersi'] ?? '') === '77', 'No. jersi berjaya diubah');

$urusD2 = $admin->get('/api/daftar.php', ['action' => 'urus']);
$rek2 = null;
foreach ($urusD2['senarai'] as $x) if ((int)$x['id'] === $idD) $rek2 = $x;
sahkan(($rek2['nama'] ?? '') === 'SAMFIRE FC A', 'Nama pasukan dikemas kini dalam senarai');

/* Nama kosong ditolak */
$kosong = $admin->post('/api/daftar.php', ['action' => 'kemas'], ['id' => $idD, 'nama' => 'A']);
sahkan(empty($kosong['ok']), 'Nama pasukan terlalu pendek ditolak');

/* Baris kosong diabaikan, had 20 pemain dikuatkuasakan */
$banyak = [];
for ($i = 1; $i <= 25; $i++) $banyak[] = ['nama' => "Pemain $i", 'no_jersi' => (string)$i];
$banyak[] = ['nama' => '   ', 'no_jersi' => '5'];
$kemas3 = $admin->post('/api/daftar.php', ['action' => 'kemas'], ['id' => $idD, 'pemain' => $banyak]);
sahkan(count($kemas3['pemain'] ?? []) === 20, 'Had maksimum 20 pemain dikuatkuasakan',
       'dapat: ' . count($kemas3['pemain'] ?? []));

/* Orang awam TIDAK boleh sunting */
$curi3 = $tetamu->post('/api/daftar.php', ['action' => 'kemas'], ['id' => $idD, 'nama' => 'DIRAMPAS FC']);
sahkan(empty($curi3['ok']), 'Orang awam tidak boleh sunting pendaftaran');

/* Selepas diluluskan + masuk slot, suntingan menular ke jadual pasukan & sijil */
$admin->segarkanCsrf();
$lulusD = $admin->post('/api/daftar.php', ['action' => 'lulus'], ['id' => $idD]);
sahkan(!empty($lulusD['ok']), 'Pendaftaran diluluskan');

exit(ringkasanUjian());

/** POST multipart menggunakan sesi admin sedia ada. */
function postBorangSesi(string $url, array $fail, string $cookieFile, string $csrf): array
{
    $medan = [];
    foreach ($fail as $nama => $p) $medan[$nama] = new CURLFile($p, mime_content_type($p), basename($p));
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $medan,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_COOKIEJAR  => $cookieFile,
        CURLOPT_HTTPHEADER => ['X-CSRF-Token: ' . $csrf],
        CURLOPT_TIMEOUT => 30,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    $d = json_decode((string)$r, true);
    return is_array($d) ? $d : ['_mentah' => substr((string)$r, 0, 200)];
}
