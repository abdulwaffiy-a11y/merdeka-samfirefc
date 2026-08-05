<?php
/**
 * ujian_undian24.php — ujian menyeluruh UNDIAN KUMPULAN dengan 24 pasukan.
 *
 * Meniru aliran sebenar:
 *   1. 24 pasukan daftar melalui borang awam (dengan logo & pemain)
 *   2. Admin luluskan semua 24
 *   3. Undian kumpulan dijalankan -> slot A1 hingga H3
 *   4. Sahkan tiada pasukan hilang / berulang, semua slot terisi
 *   5. Sahkan jadual perlawanan terus betul (A1 lwn A2, dll.)
 *   6. Ulang undian 150 kali -> semak taburan benar-benar rawak
 *   7. Uji undian berperingkat (12 dahulu, 12 kemudian)
 *   8. Uji undian disekat selepas kejohanan bermula
 */

declare(strict_types=1);

require __DIR__ . '/klien.php';

$BASE   = getenv('MERDEKA_BASE') ?: 'http://127.0.0.1:8080';
$DBNAME = getenv('MERDEKA_DB')   ?: 'merdeka';
$PASS   = 'ujian12345';
$EMAIL  = 'ujian@paksy.test';

$NAMA_PASUKAN = [
  'Surau Al-Hidayah FC','Tahfiz Darul Quran','Kampung Selamat United','PAKSY Warriors',
  'Bertam Perdana FC','Sungai Dua Rangers','Masjid Al-Ikhlas','Penaga Sportif',
  'Kubang Menerong FC','Guar Perahu Boys','Pinang Tunggal FC','Sama Gagah United',
  'Telok Air Tawar','Permatang Bertam FC','Kepala Batas Elit','Sungai Petani Rovers',
  'Tasek Gelugor FC','Pokok Sena Strikers','Belia Lubok Meriam','Surau Nurul Iman',
  'Padang Menora FC','Bumbung Lima Legends','Tahfiz As-Syafiee','Kampung Bahru FC',
];

function sql(string $db, string $q): void
{
    exec('sudo mariadb ' . escapeshellarg($db) . ' -e ' . escapeshellarg($q), $o, $c);
    if ($c !== 0) throw new RuntimeException("SQL gagal: $q");
}

function resetPenuh(string $db, string $pass): void
{
    exec('sudo mariadb ' . escapeshellarg($db) . ' < ' . escapeshellarg(__DIR__ . '/../sql/schema.sql'), $o, $c);
    if ($c !== 0) throw new RuntimeException('Gagal import skema.');
    $h = password_hash($pass, PASSWORD_BCRYPT);
    sql($db, "INSERT INTO admins (nama,email,password_hash,role) VALUES ('Ujian Super','ujian@paksy.test','$h','super');");
}

/** Kosongkan slot pasukan supaya undian boleh diulang (untuk ujian taburan). */
function kosongkanSlot(string $db): void
{
    sql($db, "DELETE FROM players;");
    sql($db, "UPDATE teams SET nama='', singkatan='', pengurus='', telefon='', logo='', tiebreak=0;");
    sql($db, "UPDATE pendaftaran SET team_id=NULL;");
    sql($db, "DELETE FROM settings WHERE k='undian_kumpulan_json';");
}

function hantarDaftar(string $base, array $medan, ?string $logo = null): array
{
    $ch = curl_init($base . '/api/daftar.php?action=hantar');
    $post = $medan;
    if ($logo) $post['logo'] = new CURLFile($logo, 'image/png', 'logo.png');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post, CURLOPT_TIMEOUT => 30,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    $d = json_decode((string)$r, true);
    return is_array($d) ? $d : ['_mentah' => $r];
}

// =====================================================================
echo "UJIAN UNDIAN KUMPULAN — 24 PASUKAN BERDAFTAR\n";
resetPenuh($DBNAME, $PASS);

$logo = sys_get_temp_dir() . '/logo_uk.png';
$im = imagecreatetruecolor(200, 200);
imagefill($im, 0, 0, imagecolorallocate($im, 20, 60, 120));
imagepng($im, $logo);
imagedestroy($im);

// ---------------------------------------------------------------------
tajukUjian('1. 24 pasukan daftar melalui borang awam');

$didaftar = 0;
foreach ($NAMA_PASUKAN as $i => $nama) {
    // Setiap pasukan daftar dari telefon berbeza — kosongkan jejak IP
    // supaya had 3-per-IP tidak menghalang ujian.
    sql($DBNAME, "UPDATE pendaftaran SET ip = CONCAT('10.0.', FLOOR(RAND()*250), '.', FLOOR(RAND()*250));");

    $pemain = [];
    for ($j = 1; $j <= 10; $j++) $pemain[] = ['nama' => "Pemain $j " . ($i + 1), 'no_jersi' => (string)$j];

    $r = hantarDaftar($BASE, [
        'nama'     => $nama,
        'pengurus' => 'Pengurus ' . ($i + 1),
        'telefon'  => '01' . (($i % 9) + 1) . '-' . str_pad((string)(1000000 + $i), 7, '0', STR_PAD_LEFT),
        'pemain'   => json_encode($pemain),
        'website'  => '',
    ], $i % 3 === 0 ? $logo : null);   // sepertiga hantar logo

    if (!empty($r['ok'])) $didaftar++;
    else echo "  ! gagal daftar $nama: " . ($r['mesej'] ?? '?') . "\n";
}
sahkan($didaftar === 24, '24 pasukan berjaya daftar', "berjaya: $didaftar");

$anon = new Klien($BASE, 'anon24');
$awamDaftar = $anon->get('/api/daftar.php', ['action' => 'senarai']);
sahkan($awamDaftar['jumlah'] === 24, 'Senarai awam papar 24 pendaftaran', (string)$awamDaftar['jumlah']);

// ---------------------------------------------------------------------
tajukUjian('2. Admin luluskan kesemua 24');

$k = new Klien($BASE, 'adm24');
$k->login($EMAIL, $PASS);
$urus = $k->get('/api/daftar.php', ['action' => 'urus']);
sahkan(count($urus['senarai']) === 24, 'Admin nampak 24 pendaftaran');

$lulus = 0;
foreach ($urus['senarai'] as $d) {
    $r = $k->post('/api/daftar.php', ['action' => 'lulus'], ['id' => $d['id']]);
    if (!empty($r['ok'])) $lulus++;
}
sahkan($lulus === 24, 'Kesemua 24 diluluskan', (string)$lulus);

$st = $k->get('/api/undi_kumpulan.php', ['action' => 'status']);
sahkan(count($st['kolam']) === 24, 'Kolam undian ada 24 pasukan', (string)count($st['kolam']));
sahkan(count($st['slot_kosong']) === 24, '24 slot kosong sedia');
sahkan($st['boleh'] === true, 'Undian kumpulan dibenarkan');

// ---------------------------------------------------------------------
tajukUjian('3. Jalankan undian — semak penempatan');

$senarai = [];
foreach ($st['kolam'] as $kk) $senarai[] = ['nama' => $kk['nama'], 'pendaftaran_id' => $kk['id']];

$r = $k->post('/api/undi_kumpulan.php', ['action' => 'jalan'], ['senarai' => $senarai]);
sahkan(!empty($r['ok']), 'Undian berjaya dijalankan', json_encode($r['mesej'] ?? ''));

$hasil = $r['hasil'] ?? [];
sahkan(count($hasil) === 24, '24 pasukan ditempatkan', (string)count($hasil));

$slots  = array_map(fn($h) => $h['slot'], $hasil);
$namaH  = array_map(fn($h) => $h['nama'], $hasil);
sahkan(count(array_unique($slots)) === 24, 'Tiada slot berulang');
sahkan(count(array_unique($namaH)) === 24, 'Tiada pasukan berulang');

$jangka = [];
foreach (['A','B','C','D','E','F','G','H'] as $g) foreach ([1,2,3] as $s) $jangka[] = $g . $s;
sort($jangka); $ss = $slots; sort($ss);
sahkan($ss === $jangka, 'Slot meliputi tepat A1 hingga H3');

$asal = $NAMA_PASUKAN; sort($asal);
$dapat = $namaH; sort($dapat);
sahkan($asal === $dapat, 'Semua 24 nama pasukan asal hadir — tiada yang hilang');

// setiap kumpulan tepat 3 pasukan
foreach (['A','B','C','D','E','F','G','H'] as $g) {
    $bil = count(array_filter($slots, fn($s) => $s[0] === $g));
    sahkan($bil === 3, "Kumpulan $g ada tepat 3 pasukan", (string)$bil);
}

// ---------------------------------------------------------------------
tajukUjian('4. Data pendaftaran mengalir ke jadual');

$awam = $k->get('/api/public.php');
$diisi = 0; $adaLogo = 0;
foreach ($awam['pasukan'] as $t) {
    if ($t['diisi']) $diisi++;
    if ($t['logo'] !== '') $adaLogo++;
}
sahkan($diisi === 24, '24 slot pasukan terisi di paparan awam', (string)$diisi);
sahkan($adaLogo === 8, 'Logo pasukan dibawa masuk (8 daripada 24 hantar logo)', (string)$adaLogo);

$jumPemain = 0;
foreach ($awam['pemain'] as $tid => $ps) $jumPemain += count($ps);
sahkan($jumPemain === 240, 'Kesemua 240 pemain (24 x 10) dibawa masuk', (string)$jumPemain);

// jadual perlawanan guna pasukan yang betul
$peta = [];
foreach ($awam['pasukan'] as $t) $peta[$t['kumpulan'] . $t['slot']] = $t['id'];
$mGrup = array_filter($awam['perlawanan'], fn($m) => $m['peringkat'] === 'grup');
sahkan(count($mGrup) === 24, '24 perlawanan kumpulan wujud');

$semuaAdaPasukan = true;
foreach ($mGrup as $m) {
    if ($m['home_id'] === null || $m['away_id'] === null) $semuaAdaPasukan = false;
}
sahkan($semuaAdaPasukan, 'Setiap perlawanan kumpulan ada dua pasukan sebenar');

// A1 (perlawanan pertama kumpulan A) = slot A1 lwn A2
$m = null; foreach ($awam['perlawanan'] as $x) if ($x['kod'] === 'A1') $m = $x;
sahkan($m['home_id'] === $peta['A1'] && $m['away_id'] === $peta['A2'], 'Perlawanan A1 = slot A1 lwn A2');
$m = null; foreach ($awam['perlawanan'] as $x) if ($x['kod'] === 'H3') $m = $x;
sahkan($m['home_id'] === $peta['H2'] && $m['away_id'] === $peta['H3'], 'Perlawanan H3 = slot H2 lwn H3');

// setiap pasukan main tepat 2 perlawanan
$kiraMain = [];
foreach ($mGrup as $m) {
    $kiraMain[$m['home_id']] = ($kiraMain[$m['home_id']] ?? 0) + 1;
    $kiraMain[$m['away_id']] = ($kiraMain[$m['away_id']] ?? 0) + 1;
}
$semuaDua = count($kiraMain) === 24;
foreach ($kiraMain as $bil) if ($bil !== 2) $semuaDua = false;
sahkan($semuaDua, 'Setiap pasukan dijadualkan tepat 2 perlawanan kumpulan');

// ---------------------------------------------------------------------
tajukUjian('5. Kerawakan — 150 undian berulang');

$PUSINGAN = 150;
$taburan = [];      // nama => [kumpulan => bilangan]
$sahSemua = true;

for ($p = 0; $p < $PUSINGAN; $p++) {
    kosongkanSlot($DBNAME);
    $k2 = new Klien($BASE, 'rnd');
    $k2->login($EMAIL, $PASS);
    $st2 = $k2->get('/api/undi_kumpulan.php', ['action' => 'status']);
    $sen2 = [];
    foreach ($st2['kolam'] as $kk) $sen2[] = ['nama' => $kk['nama'], 'pendaftaran_id' => $kk['id']];

    $r2 = $k2->post('/api/undi_kumpulan.php', ['action' => 'jalan'], ['senarai' => $sen2]);
    if (empty($r2['ok']) || count($r2['hasil']) !== 24) {
        $sahSemua = false;
        echo "  ! pusingan $p gagal: " . json_encode($r2['mesej'] ?? '') . "\n";
        break;
    }
    $sl = array_map(fn($h) => $h['slot'], $r2['hasil']);
    if (count(array_unique($sl)) !== 24) { $sahSemua = false; break; }

    foreach ($r2['hasil'] as $h) {
        $taburan[$h['nama']][$h['kumpulan']] = ($taburan[$h['nama']][$h['kumpulan']] ?? 0) + 1;
    }
}
sahkan($sahSemua, "$PUSINGAN undian: semua sah, 24 pasukan unik setiap kali");
sahkan(count($taburan) === 24, 'Kesemua 24 pasukan muncul dalam taburan', (string)count($taburan));

// Setiap pasukan sepatutnya mendarat dalam setiap kumpulan lebih kurang
// PUSINGAN/8 kali (~18.75). Semak julat longgar 4..45 dan pastikan setiap
// pasukan pernah masuk sekurang-kurangnya 6 kumpulan berbeza.
$min = PHP_INT_MAX; $max = 0; $minKumpBerbeza = 8;
foreach ($taburan as $nama => $ikutKump) {
    $minKumpBerbeza = min($minKumpBerbeza, count($ikutKump));
    foreach (['A','B','C','D','E','F','G','H'] as $g) {
        $n = $ikutKump[$g] ?? 0;
        $min = min($min, $n); $max = max($max, $n);
    }
}
$jangkaan = $PUSINGAN / 8;
sahkan($min >= 4 && $max <= 45, 'Taburan kumpulan munasabah rawak',
       "min=$min maks=$max (jangkaan ~" . round($jangkaan, 1) . ")");
sahkan($minKumpBerbeza >= 6, 'Setiap pasukan pernah masuk >= 6 kumpulan berbeza', (string)$minKumpBerbeza);

// Semak tiada pasukan "melekat" pada slot yang sama setiap kali
$melekat = 0;
foreach ($taburan as $nama => $ikutKump) {
    foreach ($ikutKump as $g => $n) if ($n > $PUSINGAN * 0.5) $melekat++;
}
sahkan($melekat === 0, 'Tiada pasukan melekat pada kumpulan yang sama (>50% masa)', (string)$melekat);

// ---------------------------------------------------------------------
tajukUjian('6. Undian berperingkat — 12 dahulu, 12 kemudian');

kosongkanSlot($DBNAME);
$k3 = new Klien($BASE, 'peringkat');
$k3->login($EMAIL, $PASS);
$st3 = $k3->get('/api/undi_kumpulan.php', ['action' => 'status']);
$kolam = $st3['kolam'];

$batch1 = [];
foreach (array_slice($kolam, 0, 12) as $kk) $batch1[] = ['nama' => $kk['nama'], 'pendaftaran_id' => $kk['id']];
$r3 = $k3->post('/api/undi_kumpulan.php', ['action' => 'jalan'], ['senarai' => $batch1]);
sahkan(!empty($r3['ok']) && count($r3['hasil']) === 12, 'Undian pertama tempatkan 12 pasukan');
$slotBatch1 = array_map(fn($h) => $h['slot'], $r3['hasil']);

$st3 = $k3->get('/api/undi_kumpulan.php', ['action' => 'status']);
sahkan(count($st3['slot_kosong']) === 12, '12 slot kosong tinggal', (string)count($st3['slot_kosong']));
sahkan(count($st3['kolam']) === 12, '12 pasukan masih dalam kolam', (string)count($st3['kolam']));

$batch2 = [];
foreach ($st3['kolam'] as $kk) $batch2[] = ['nama' => $kk['nama'], 'pendaftaran_id' => $kk['id']];
$r4 = $k3->post('/api/undi_kumpulan.php', ['action' => 'jalan'], ['senarai' => $batch2]);
sahkan(!empty($r4['ok']) && count($r4['hasil']) === 12, 'Undian kedua tempatkan 12 lagi');

$slotBatch2 = array_map(fn($h) => $h['slot'], $r4['hasil']);
sahkan(count(array_intersect($slotBatch1, $slotBatch2)) === 0, 'Undian kedua tidak mengganggu slot undian pertama');

$awam3 = $k3->get('/api/public.php');
$diisi3 = 0; foreach ($awam3['pasukan'] as $t) if ($t['diisi']) $diisi3++;
sahkan($diisi3 === 24, 'Kesemua 24 slot terisi selepas dua peringkat', (string)$diisi3);

// undian ketiga tanpa slot kosong -> ditolak
$r5 = $k3->post('/api/undi_kumpulan.php', ['action' => 'jalan'], ['senarai' => [['nama' => 'Pasukan Lewat FC'], ['nama' => 'Pasukan Lewat 2 FC']]]);
sahkan(empty($r5['ok']), 'Undian ditolak bila tiada slot kosong');

// nama berulang dengan pasukan sedia ada -> ditolak
kosongkanSlot($DBNAME);
$k3->post('/api/undi_kumpulan.php', ['action' => 'jalan'], ['senarai' => [['nama' => 'Alpha FC'], ['nama' => 'Beta FC']]]);
$r6 = $k3->post('/api/undi_kumpulan.php', ['action' => 'jalan'], ['senarai' => [['nama' => 'Alpha FC'], ['nama' => 'Gamma FC']]]);
sahkan(empty($r6['ok']), 'Nama yang sudah ada dalam jadual ditolak');

$r7 = $k3->post('/api/undi_kumpulan.php', ['action' => 'jalan'], ['senarai' => [['nama' => 'Sama FC'], ['nama' => 'Sama FC']]]);
sahkan(empty($r7['ok']), 'Nama berulang dalam senarai yang sama ditolak');

// ---------------------------------------------------------------------
tajukUjian('7. Undian disekat selepas kejohanan bermula');

kosongkanSlot($DBNAME);
$st4 = $k3->get('/api/undi_kumpulan.php', ['action' => 'status']);
$sen4 = [];
foreach ($st4['kolam'] as $kk) $sen4[] = ['nama' => $kk['nama'], 'pendaftaran_id' => $kk['id']];
$k3->post('/api/undi_kumpulan.php', ['action' => 'jalan'], ['senarai' => $sen4]);

$sen = $k3->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
$m = null; foreach ($sen as $x) if ($x['kod'] === 'A1') $m = $x;
$k3->post('/api/matches.php', ['action' => 'simpan'], [
    'id' => $m['id'], 'version' => $m['version'], 'skor_home' => 3, 'skor_away' => 1, 'status' => 'done']);

$st5 = $k3->get('/api/undi_kumpulan.php', ['action' => 'status']);
sahkan($st5['boleh'] === false, 'Status undian kumpulan: disekat selepas perlawanan pertama');

kosongkanSlot($DBNAME);   // cuba kosongkan slot sekalipun — masih disekat
$r8 = $k3->post('/api/undi_kumpulan.php', ['action' => 'jalan'], ['senarai' => [['nama' => 'Cuba FC'], ['nama' => 'Cuba 2 FC']]]);
sahkan(empty($r8['ok']), 'Undian ditolak walaupun slot dikosongkan, kerana kejohanan sudah bermula');

exit(ringkasanUjian());
