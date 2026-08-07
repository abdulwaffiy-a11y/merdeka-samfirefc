<?php
/**
 * ujian_pendaftaran.php — pemeriksaan menyeluruh DUA borang pendaftaran:
 *   D) Pendaftaran PASUKAN (tournament) + nama pemain
 *   E) Pendaftaran AHLI SAMFIRE FC
 * Fokus: kes tepi & bug yang boleh rosakkan data pada hari kejohanan.
 */

declare(strict_types=1);

require __DIR__ . '/klien.php';

$BASE   = getenv('MERDEKA_BASE') ?: 'http://127.0.0.1:8080';
$DBNAME = getenv('MERDEKA_DB')   ?: 'merdeka';
$PASS   = 'ujian12345';

function resetDbP(string $db, string $pass): void
{
    exec('sudo mariadb ' . escapeshellarg($db) . ' < ' . escapeshellarg(__DIR__ . '/../sql/schema.sql'), $o, $c);
    if ($c !== 0) throw new RuntimeException('Gagal import skema.');
    $h = password_hash($pass, PASSWORD_BCRYPT);
    exec('sudo mariadb ' . escapeshellarg($db) . ' -e ' . escapeshellarg(
        "INSERT INTO admins (nama,email,password_hash,role) VALUES ('Ujian Super','ujian@paksy.test','$h','super');"
    ));
    exec('sudo mariadb ' . escapeshellarg($db) . ' -e ' . escapeshellarg('DROP TABLE IF EXISTS ahli;'));
}

/** Padam had IP supaya ujian boleh hantar banyak borang. */
function kosongkanHadIp(string $db, string $jadual): void
{
    exec('sudo mariadb ' . escapeshellarg($db) . ' -e ' . escapeshellarg(
        "UPDATE $jadual SET created_at = created_at - INTERVAL 3 HOUR;"
    ));
}

function postBorangP(string $url, array $medan, array $fail = []): array
{
    foreach ($fail as $k => $p) $medan[$k] = new CURLFile($p, mime_content_type($p), basename($p));
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $medan, CURLOPT_TIMEOUT => 30,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    $d = json_decode((string)$r, true);
    return is_array($d) ? $d : ['_mentah' => substr((string)$r, 0, 300)];
}

function imejP(string $path, int $w, int $h): string
{
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, 200, 30, 60));
    imagepng($im, $path);
    imagedestroy($im);
    return $path;
}

/** Hantar borang pendaftaran pasukan. */
function daftarPasukan(string $base, string $nama, array $pemain = [], array $lain = [], ?string $logo = null): array
{
    return postBorangP("$base/api/daftar.php?action=hantar", array_merge([
        'nama'     => $nama,
        'pengurus' => 'Pengurus ' . $nama,
        'telefon'  => '012-3456789',
        'pemain'   => json_encode($pemain),
        'website'  => '',
    ], $lain), $logo ? ['logo' => $logo] : []);
}

echo "Base: $BASE  DB: $DBNAME\n";
resetDbP($DBNAME, $PASS);

$admin = new Klien($BASE, 'p_admin');
$r = $admin->login('ujian@paksy.test', $PASS);
if (empty($r['ok'])) { echo "Tidak dapat log masuk admin.\n"; exit(1); }
$tetamu = new Klien($BASE, 'p_tetamu');

$logoBesar = imejP(sys_get_temp_dir() . '/uji_logo.png', 900, 900);

/* ================================================================== */
tajukUjian('D1. Borang pasukan — medan wajib');

$x = daftarPasukan($BASE, 'AB');
sahkan(empty($x['ok']), 'Nama pasukan < 3 aksara ditolak');

$x = daftarPasukan($BASE, 'Pasukan Sah', [], ['pengurus' => 'Ab']);
sahkan(empty($x['ok']), 'Nama pengurus terlalu pendek ditolak');

$x = daftarPasukan($BASE, 'Pasukan Sah', [], ['telefon' => '123']);
sahkan(empty($x['ok']), 'Telefon terlalu pendek ditolak');

$x = daftarPasukan($BASE, 'Pasukan Sah', [], ['telefon' => 'abcdefghij']);
sahkan(empty($x['ok']), 'Telefon berhuruf ditolak');

$x = daftarPasukan($BASE, 'Pasukan Bot', [], ['website' => 'http://spam.test']);
sahkan(empty($x['ok']), 'Honeypot menahan bot');

/* ================================================================== */
tajukUjian('D2. Nama pemain — pembersihan data');

$pemainKotor = [
    ['nama' => '  Ahmad   Faiz  ', 'no_jersi' => '7'],       // ruang berganda
    ['nama' => '',                 'no_jersi' => '9'],        // kosong -> dibuang
    ['nama' => '   ',              'no_jersi' => '3'],        // ruang sahaja -> dibuang
    ['nama' => 'AHMAD FAIZ',       'no_jersi' => '11'],       // pendua (huruf besar)
    ['nama' => 'Rizal Hakim',      'no_jersi' => 'AB12'],     // jersi berhuruf
    ['nama' => 'Amir Danial',      'no_jersi' => '999'],      // jersi > 99
    ['nama' => 'Hafiz Nasir',      'no_jersi' => ''],         // tiada jersi
];
$x = daftarPasukan($BASE, 'Pasukan Bersih FC', $pemainKotor);
sahkan(!empty($x['ok']), 'Pendaftaran dengan data kotor diterima', json_encode($x));
sahkan(($x['pemain'] ?? -1) === 4, 'Baris kosong & nama pendua dibuang (4 pemain tinggal)',
       'dapat: ' . ($x['pemain'] ?? '-'));
sahkan(preg_match('/^PSK-\d{4}$/', (string)($x['rujukan'] ?? '')) === 1,
       'No. rujukan pasukan dijana (PSK-0001)', 'dapat: ' . ($x['rujukan'] ?? '-'));

$admin->segarkanCsrf();
$urus = $admin->get('/api/daftar.php', ['action' => 'urus']);
$rek = null;
foreach ($urus['senarai'] as $s) if ($s['nama'] === 'Pasukan Bersih FC') $rek = $s;
sahkan($rek !== null, 'Rekod dijumpai oleh admin');
$nm = array_column($rek['pemain'] ?? [], 'nama');
sahkan(($nm[0] ?? '') === 'Ahmad Faiz', 'Ruang berganda dimampatkan', 'dapat: ' . ($nm[0] ?? '-'));
sahkan(count(array_unique(array_map('mb_strtolower', $nm))) === count($nm),
       'Tiada nama pemain berulang (elak sijil pendua)');
$js = array_column($rek['pemain'] ?? [], 'no_jersi');
sahkan(($js[1] ?? 'x') === '12', 'Huruf dibuang dari no. jersi (AB12 -> 12)', 'dapat: ' . ($js[1] ?? '-'));
sahkan(($js[2] ?? 'x') === '', 'No. jersi > 99 ditolak', 'dapat: ' . ($js[2] ?? '-'));

/* Had 10 pemain */
$sebelas = [];
for ($i = 1; $i <= 15; $i++) $sebelas[] = ['nama' => "Pemain Ramai $i", 'no_jersi' => (string)$i];
$x = daftarPasukan($BASE, 'Pasukan Ramai FC', $sebelas);
sahkan(($x['pemain'] ?? -1) === 10, 'Had 10 pemain dikuatkuasakan', 'dapat: ' . ($x['pemain'] ?? '-'));

/* ================================================================== */
tajukUjian('D3. Nama pasukan pendua');

kosongkanHadIp($DBNAME, 'pendaftaran');
$x = daftarPasukan($BASE, 'Pasukan Bersih FC');
sahkan(empty($x['ok']), 'Nama pasukan sama persis ditolak');

$x = daftarPasukan($BASE, 'pasukan bersih fc');
sahkan(empty($x['ok']), 'Nama sama (huruf kecil) ditolak');

$x = daftarPasukan($BASE, 'Pasukan   Bersih   FC');
sahkan(empty($x['ok']), 'Nama sama dengan ruang berganda ditolak', json_encode($x));

/* ================================================================== */
tajukUjian('D4. Logo pasukan');

kosongkanHadIp($DBNAME, 'pendaftaran');
$x = daftarPasukan($BASE, 'Pasukan Berlogo FC', [['nama' => 'Zaki Rahman', 'no_jersi' => '1']], [], $logoBesar);
sahkan(!empty($x['ok']), 'Pendaftaran dengan logo diterima', json_encode($x));

$admin->segarkanCsrf();
$urus = $admin->get('/api/daftar.php', ['action' => 'urus']);
$rekLogo = null;
foreach ($urus['senarai'] as $s) if ($s['nama'] === 'Pasukan Berlogo FC') $rekLogo = $s;
sahkan(!empty($rekLogo['logo']), 'Logo tersimpan');
[$kodLogo, ] = (function (string $u) {
    $ch = curl_init($u);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
    $b = curl_exec($ch);
    $k = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$k, $b];
})("$BASE/api/uploads/" . $rekLogo['logo']);
sahkan($kodLogo === 200, 'Logo boleh dipapar di laman awam');

$palsu = sys_get_temp_dir() . '/bukan_imej.png';
file_put_contents($palsu, "<?php echo 'hack'; ?>");
$x = postBorangP("$BASE/api/daftar.php?action=hantar", [
    'nama' => 'Pasukan Palsu FC', 'pengurus' => 'Orang Jahat',
    'telefon' => '0123456789', 'pemain' => '[]', 'website' => '',
], ['logo' => $palsu]);
sahkan(empty($x['ok']), 'Fail PHP menyamar sebagai logo ditolak', json_encode($x));

/* ================================================================== */
tajukUjian('D5. Admin sunting — semakan nama pendua');

$admin->segarkanCsrf();
$idBersih = 0;
foreach ($urus['senarai'] as $s) if ($s['nama'] === 'Pasukan Bersih FC') $idBersih = (int)$s['id'];
$x = $admin->post('/api/daftar.php', ['action' => 'kemas'], ['id' => $idBersih, 'nama' => 'Pasukan Berlogo FC']);
sahkan(empty($x['ok']), 'Admin tidak boleh tukar nama jadi sama dengan pasukan lain', json_encode($x));

$x = $admin->post('/api/daftar.php', ['action' => 'kemas'], [
    'id' => $idBersih,
    'pemain' => [
        ['nama' => 'Sama Nama', 'no_jersi' => '1'],
        ['nama' => 'sama nama', 'no_jersi' => '2'],
        ['nama' => 'Lain Nama', 'no_jersi' => 'X9'],
    ],
]);
sahkan(!empty($x['ok']), 'Admin simpan senarai pemain');
sahkan(count($x['pemain'] ?? []) === 2, 'Pendua dibuang semasa admin sunting', 'dapat: ' . count($x['pemain'] ?? []));
sahkan(($x['pemain'][1]['no_jersi'] ?? '') === '9', 'Jersi ditapis semasa admin sunting');

/* Nama sendiri tidak dikira pendua */
$x = $admin->post('/api/daftar.php', ['action' => 'kemas'], ['id' => $idBersih, 'nama' => 'Pasukan Bersih FC']);
sahkan(!empty($x['ok']), 'Simpan semula nama sendiri dibenarkan (bukan pendua)', json_encode($x));

/* ================================================================== */
tajukUjian('D6. Luluskan & amaran kolam undian');

$admin->segarkanCsrf();
$x = $admin->post('/api/daftar.php', ['action' => 'lulus'], ['id' => $idBersih]);
sahkan(!empty($x['ok']), 'Pendaftaran diluluskan');
sahkan(($x['dalam_kolam'] ?? 0) === 1, 'Kiraan kolam undian betul');
sahkan(($x['slot_kosong'] ?? 0) === 24, '24 slot kosong dilaporkan');
sahkan(($x['amaran'] ?? 'x') === '', 'Tiada amaran ketika kolam masih kecil');

$x = $admin->post('/api/daftar.php', ['action' => 'lulus'], ['id' => $idBersih]);
sahkan(empty($x['ok']), 'Luluskan dua kali ditolak dengan mesej jelas', json_encode($x));

/* ================================================================== */
tajukUjian('D7. Pemain masuk sijil selepas undian');

$admin->segarkanCsrf();
$senarai = [];
foreach ($urus['senarai'] as $s) {
    if ($s['nama'] === 'Pasukan Bersih FC') $senarai[] = ['nama' => $s['nama'], 'pendaftaran_id' => (int)$s['id']];
}
$senarai[] = ['nama' => 'Pasukan Tambahan FC'];
$undi = $admin->post('/api/undi_kumpulan.php', ['action' => 'jalan'], ['senarai' => $senarai]);
sahkan(!empty($undi['ok']), 'Undian kumpulan berjaya', json_encode($undi));

$pl = $admin->get('/api/sijil.php', ['action' => 'pautan']);
$pBersih = null;
foreach ($pl['pasukan'] ?? [] as $p) if ($p['nama'] === 'Pasukan Bersih FC') $pBersih = $p;
sahkan($pBersih !== null, 'Pasukan muncul dalam senarai sijil');
sahkan(($pBersih['pemain'] ?? 0) === 2, 'Bilangan pemain dibawa ke sijil (2 orang)',
       'dapat: ' . ($pBersih['pemain'] ?? '-'));

/* ================================================================== */
tajukUjian('D8. Pendaftaran ditutup');

$admin->segarkanCsrf();
$admin->post('/api/daftar.php', ['action' => 'buka'], ['buka' => false]);
kosongkanHadIp($DBNAME, 'pendaftaran');
$x = daftarPasukan($BASE, 'Pasukan Lambat FC');
sahkan(empty($x['ok']), 'Borang ditolak semasa pendaftaran ditutup');
$admin->post('/api/daftar.php', ['action' => 'buka'], ['buka' => true]);

/* ================================================================== */
tajukUjian('D9. Had pendaftaran per IP');

kosongkanHadIp($DBNAME, 'pendaftaran');
$diterima = 0;
for ($i = 1; $i <= 5; $i++) {
    $x = daftarPasukan($BASE, "Pasukan Banjir $i FC");
    if (!empty($x['ok'])) $diterima++;
}
sahkan($diterima === 3, 'Had 3 pendaftaran/IP/jam dikuatkuasakan', "diterima: $diterima");

/* ================================================================== */
/* ========================  E. AHLI SAMFIRE  ======================= */
/* ================================================================== */
tajukUjian('E1. Ahli — sah/tolak berulang kali');

$a = postBorangP("$BASE/api/ahli.php?action=hantar", [
    'nama' => 'Zulkifli bin Hamid', 'no_kp' => '870707075111',
    'telefon' => '019-8887777', 'jantina' => 'lelaki',
]);
sahkan(!empty($a['ok']), 'Ahli didaftar', json_encode($a));
$idA = (int)($a['id'] ?? 0);

$admin->segarkanCsrf();
$s1 = $admin->post('/api/ahli.php', ['action' => 'lulus'], ['id' => $idA]);
sahkan(!empty($s1['ok']), 'Sahkan ahli kali pertama');

$s2 = $admin->post('/api/ahli.php', ['action' => 'lulus'], ['id' => $idA]);
sahkan(!empty($s2['ok']), 'Tekan "Sah" kali kedua TIDAK bagi ralat palsu', json_encode($s2));

$s3 = $admin->post('/api/ahli.php', ['action' => 'tolak'], ['id' => $idA, 'catatan' => 'Bukti tiada']);
sahkan(!empty($s3['ok']), 'Tukar ke tolak berjaya');

$s4 = $admin->post('/api/ahli.php', ['action' => 'lulus'], ['id' => $idA]);
sahkan(!empty($s4['ok']), 'Tukar semula ke lulus berjaya');
$urusA = $admin->get('/api/ahli.php', ['action' => 'urus']);
$rekA = null;
foreach ($urusA['ahli'] as $z) if ((int)$z['id'] === $idA) $rekA = $z;
sahkan(($rekA['catatan'] ?? '') === 'Bukti tiada', 'Catatan lama tidak dipadam bila sahkan semula',
       'dapat: ' . ($rekA['catatan'] ?? '-'));

$s5 = $admin->post('/api/ahli.php', ['action' => 'lulus'], ['id' => 999999]);
sahkan(empty($s5['ok']), 'ID tidak wujud tetap bagi ralat 404');

/* ================================================================== */
tajukUjian('E2. Ahli ditolak boleh mohon semula');

$admin->segarkanCsrf();
$b = postBorangP("$BASE/api/ahli.php?action=hantar", [
    'nama' => 'Farid bin Osman', 'no_kp' => '920909075222', 'telefon' => '017-5554444',
]);
sahkan(!empty($b['ok']), 'Ahli kedua didaftar');
$idB = (int)($b['id'] ?? 0);
$admin->post('/api/ahli.php', ['action' => 'tolak'], ['id' => $idB, 'catatan' => 'Bayaran tidak sah']);

kosongkanHadIp($DBNAME, 'ahli');
$b2 = postBorangP("$BASE/api/ahli.php?action=hantar", [
    'nama' => 'Farid bin Osman', 'no_kp' => '920909075222',
    'telefon' => '017-5554444', 'pemain_idola' => 'Messi, Ronaldo',
]);
sahkan(!empty($b2['ok']), 'Permohonan DITOLAK boleh dihantar semula', json_encode($b2));
sahkan((int)($b2['id'] ?? 0) === $idB, 'Guna rekod sama (tiada pendua KP)');

$urusB = $admin->get('/api/ahli.php', ['action' => 'urus']);
$rekB = null;
foreach ($urusB['ahli'] as $z) if ((int)$z['id'] === $idB) $rekB = $z;
sahkan(($rekB['status'] ?? '') === 'baru', 'Status kembali ke "baru" untuk semakan');
sahkan(($rekB['catatan'] ?? 'x') === '', 'Catatan penolakan lama dikosongkan');
sahkan(($rekB['pemain_idola'] ?? '') === 'Messi, Ronaldo', 'Maklumat baharu menggantikan yang lama');

/* Yang berstatus baru / lulus TIDAK boleh hantar semula */
kosongkanHadIp($DBNAME, 'ahli');
$b3 = postBorangP("$BASE/api/ahli.php?action=hantar", [
    'nama' => 'Farid Cuba Lagi', 'no_kp' => '920909075222', 'telefon' => '017-5554444',
]);
sahkan(empty($b3['ok']), 'KP yang sedang menunggu tidak boleh hantar semula');

/* ================================================================== */
tajukUjian('E3. Ahli — kes tepi medan');

kosongkanHadIp($DBNAME, 'ahli');
$c = postBorangP("$BASE/api/ahli.php?action=hantar", [
    'nama' => 'Siti Aminah', 'no_kp' => '99-0101-07-5333', 'telefon' => '013 222 1111',
    'jantina' => 'bukan_pilihan', 'tarikh_lahir' => 'bukan-tarikh', 'no_jersi' => '999',
    'poskod' => 'AB13200', 'emel' => '',
]);
sahkan(!empty($c['ok']), 'Borang dengan medan pelik masih diterima', json_encode($c));
$urusC = $admin->get('/api/ahli.php', ['action' => 'urus']);
$rekC = null;
foreach ($urusC['ahli'] as $z) if ($z['nama'] === 'Siti Aminah') $rekC = $z;
sahkan(($rekC['no_kp'] ?? '') === '990101075333', 'Sengkang dibuang dari no. KP', 'dapat: ' . ($rekC['no_kp'] ?? '-'));
sahkan(($rekC['jantina'] ?? '') === 'lelaki', 'Jantina tidak sah jatuh ke nilai lalai');
sahkan(($rekC['tarikh_lahir'] ?? null) === null, 'Tarikh lahir tidak sah disimpan sebagai kosong');
sahkan(($rekC['no_jersi'] ?? 'x') === '', 'No. jersi > 99 ditolak');
sahkan(($rekC['poskod'] ?? '') === '13200', 'Huruf dibuang dari poskod', 'dapat: ' . ($rekC['poskod'] ?? '-'));

exit(ringkasanUjian());
