<?php
/**
 * ujian_daftar.php — pendaftaran awam + undian kumpulan + head-to-head.
 */

declare(strict_types=1);

require __DIR__ . '/klien.php';

$BASE   = getenv('MERDEKA_BASE') ?: 'http://127.0.0.1:8080';
$DBNAME = getenv('MERDEKA_DB')   ?: 'merdeka';
$PASS = 'ujian12345';

function resetDb3(string $db, string $pass): void
{
    exec('sudo mariadb ' . escapeshellarg($db) . ' < ' . escapeshellarg(__DIR__ . '/../sql/schema.sql'), $o, $c);
    if ($c !== 0) throw new RuntimeException('Gagal import skema.');
    $h = password_hash($pass, PASSWORD_BCRYPT);
    exec('sudo mariadb ' . escapeshellarg($db) . ' -e ' . escapeshellarg(
        "INSERT INTO admins (nama,email,password_hash,role) VALUES ('Ujian Super','ujian@paksy.test','$h','super');"
    ));
}

/** Hantar borang multipart (pendaftaran awam). */
function hantarDaftar(string $base, array $medan, ?string $logoPath = null): array
{
    $ch = curl_init($base . '/api/daftar.php?action=hantar');
    $post = $medan;
    if ($logoPath !== null) {
        $post['logo'] = new CURLFile($logoPath, mime_content_type($logoPath), basename($logoPath));
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_TIMEOUT => 30,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    $d = json_decode((string)$r, true);
    return is_array($d) ? $d : ['_mentah' => $r];
}

echo "UJIAN PENDAFTARAN + UNDIAN KUMPULAN + HEAD-TO-HEAD\n";
resetDb3($DBNAME, $PASS);

// ---- logo ujian ------------------------------------------------------
$logoKecil = sys_get_temp_dir() . '/logo_ujian.png';
$im = imagecreatetruecolor(300, 200);
imagefill($im, 0, 0, imagecolorallocate($im, 120, 20, 40));
imagepng($im, $logoKecil);
imagedestroy($im);

$logoBesar = sys_get_temp_dir() . '/logo_besar.png';
file_put_contents($logoBesar, str_repeat('A', 1200000));  // bukan imej + >1MB

// ---------------------------------------------------------------------
tajukUjian('1. Pendaftaran awam');
$r = hantarDaftar($BASE, ['nama' => 'Belia Lubok Meriam', 'pengurus' => 'Ahmad Albab', 'telefon' => '012-3456789',
    'pemain' => json_encode([['nama' => 'Ali', 'no_jersi' => '7'], ['nama' => 'Abu', 'no_jersi' => '10']]), 'website' => ''], $logoKecil);
sahkan(!empty($r['ok']), 'Pendaftaran dengan logo diterima', json_encode($r));

$r = hantarDaftar($BASE, ['nama' => 'Belia Lubok Meriam', 'pengurus' => 'Lain', 'telefon' => '019-8887777', 'website' => '']);
sahkan(empty($r['ok']), 'Nama pasukan berulang ditolak');

$r = hantarDaftar($BASE, ['nama' => 'Bot Spam FC', 'pengurus' => 'Bot', 'telefon' => '011-1111111', 'website' => 'http://spam.com']);
sahkan(empty($r['ok']), 'Honeypot menahan bot');

$r = hantarDaftar($BASE, ['nama' => 'Logo Besar FC', 'pengurus' => 'Boss', 'telefon' => '013-2223333', 'website' => ''], $logoBesar);
sahkan(empty($r['ok']), 'Logo >1MB / bukan imej ditolak', json_encode($r));

$r = hantarDaftar($BASE, ['nama' => 'Pasukan Kedua', 'pengurus' => 'Manager Dua', 'telefon' => '014-5556666',
    'pemain' => '[]', 'website' => '']);
sahkan(!empty($r['ok']), 'Pendaftaran kedua diterima');

// had IP: pendaftaran ke-3 ok, ke-4 disekat (had 3/jam)
$r = hantarDaftar($BASE, ['nama' => 'Pasukan Tiga', 'pengurus' => 'Tiga', 'telefon' => '015-1112222', 'website' => '']);
sahkan(!empty($r['ok']), 'Pendaftaran ketiga diterima');
$r = hantarDaftar($BASE, ['nama' => 'Pasukan Empat', 'pengurus' => 'Empat', 'telefon' => '016-3334444', 'website' => '']);
sahkan(empty($r['ok']), 'Pendaftaran ke-4 dari IP sama dalam sejam disekat');

// senarai awam
$k0 = new Klien($BASE, 'awam');
$sen = $k0->get('/api/daftar.php', ['action' => 'senarai']);
sahkan(count($sen['senarai']) === 3, 'Senarai awam papar 3 pendaftaran', (string)count($sen['senarai']));

// ---------------------------------------------------------------------
tajukUjian('2. Kelulusan admin');
$k = new Klien($BASE, 'adm');
$k->login('ujian@paksy.test', $PASS);
$urus = $k->get('/api/daftar.php', ['action' => 'urus']);
sahkan(count($urus['senarai']) === 3, 'Admin nampak semua pendaftaran');

foreach ($urus['senarai'] as $d) {
    if ($d['nama'] === 'Pasukan Tiga') {
        $r = $k->post('/api/daftar.php', ['action' => 'tolak'], ['id' => $d['id'], 'catatan' => 'tidak lengkap']);
        sahkan(!empty($r['ok']), 'Tolak pendaftaran');
    } else {
        $r = $k->post('/api/daftar.php', ['action' => 'lulus'], ['id' => $d['id']]);
        sahkan(!empty($r['ok']), 'Lulus: ' . $d['nama'], json_encode($r));
    }
}

// tutup & buka pendaftaran
$k->post('/api/daftar.php', ['action' => 'buka'], ['buka' => false]);
$r = hantarDaftar($BASE, ['nama' => 'Lewat FC', 'pengurus' => 'Lewat', 'telefon' => '017-9998888', 'website' => '']);
sahkan(empty($r['ok']), 'Pendaftaran ditolak bila ditutup');
$k->post('/api/daftar.php', ['action' => 'buka'], ['buka' => true]);

// ---------------------------------------------------------------------
tajukUjian('3. Undian kumpulan');
$st = $k->get('/api/undi_kumpulan.php', ['action' => 'status']);
sahkan($st['boleh'] === true, 'Undian kumpulan dibenarkan sebelum kejohanan');
sahkan(count($st['kolam']) === 2, 'Kolam ada 2 pasukan lulus', (string)count($st['kolam']));
sahkan(count($st['slot_kosong']) === 24, '24 slot kosong');

// senarai: 2 dari kolam + 22 manual = 24
$senarai = [];
foreach ($st['kolam'] as $kk) $senarai[] = ['nama' => $kk['nama'], 'pendaftaran_id' => $kk['id']];
for ($i = 1; $i <= 22; $i++) $senarai[] = ['nama' => "Jemputan $i FC"];

$r = $k->post('/api/undi_kumpulan.php', ['action' => 'jalan'], ['senarai' => $senarai]);
sahkan(!empty($r['ok']), 'Undian kumpulan berjaya', json_encode($r));
$hasil = $r['hasil'] ?? [];
sahkan(count($hasil) === 24, '24 pasukan ditempatkan');

$slotSemua = array_map(fn($h) => $h['slot'], $hasil);
sahkan(count(array_unique($slotSemua)) === 24, 'Semua slot unik (A1..H3)');
sort($slotSemua);
$jangka = [];
foreach (['A','B','C','D','E','F','G','H'] as $g) foreach ([1,2,3] as $s) $jangka[] = $g . $s;
sort($jangka);
sahkan($slotSemua === $jangka, 'Slot meliputi tepat A1 hingga H3');

// data pendaftaran mengalir ke pasukan
$awam = $k->get('/api/public.php');
$jumpaLogo = false; $namaDaftar = false;
foreach ($awam['pasukan'] as $t) {
    if ($t['nama'] === 'Belia Lubok Meriam') { $namaDaftar = true; if ($t['logo'] !== '') $jumpaLogo = true; }
}
sahkan($namaDaftar, 'Pasukan dari pendaftaran masuk jadual kumpulan');
sahkan($jumpaLogo, 'Logo pasukan dibawa bersama');
$adaPemain = false;
foreach ($awam['pemain'] as $tid => $ps) {
    foreach ($ps as $p) if ($p['nama'] === 'Ali') $adaPemain = true;
}
sahkan($adaPemain, 'Senarai pemain dibawa bersama');

// undian kedua: tiada slot kosong lagi
$r = $k->post('/api/undi_kumpulan.php', ['action' => 'jalan'], ['senarai' => [['nama' => 'X FC'], ['nama' => 'Y FC']]]);
sahkan(empty($r['ok']), 'Undian ditolak bila tiada slot kosong mencukupi adalah salah — sepatutnya gagal sebab slot penuh', '');
// nota: mesej sebenar ialah "melebihi slot kosong (0)" — ok selagi gagal

// selepas perlawanan bermula -> undian kumpulan disekat
$sen = $k->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
$m = $sen[0];
$k->post('/api/matches.php', ['action' => 'simpan'], ['id' => $m['id'], 'version' => $m['version'], 'skor_home' => 1, 'skor_away' => 0, 'status' => 'done']);
$st = $k->get('/api/undi_kumpulan.php', ['action' => 'status']);
sahkan($st['boleh'] === false, 'Undian kumpulan disekat selepas kejohanan bermula');

// ---------------------------------------------------------------------
tajukUjian('4. Head-to-head bila mata sama');
resetDb3($DBNAME, $PASS);
$k = new Klien($BASE, 'h2h');
$k->login('ujian@paksy.test', $PASS);
$awam = $k->get('/api/public.php');
$hantarNama = [];
foreach ($awam['pasukan'] as $t) $hantarNama[] = ['id' => $t['id'], 'nama' => 'P' . $t['kumpulan'] . $t['slot']];
$k->post('/api/teams.php', ['action' => 'simpan'], ['pasukan' => $hantarNama]);

// Kumpulan A: A1 kalahkan A2 1-0; A2 belasah A3 9-0; A3 kalahkan A1 2-1.
// Semua 3 mata. h2h pairwise: A1>A2, A2>A3, A3>A1 (kitaran) -> jatuh ke beza gol: A2 (+8), A3 (-7+2... kira)
// A1: +1, -1 => 0 beza? A1: menang 1-0, kalah 1-2 -> jgm2 jgk2 beza 0
// A2: kalah 0-1, menang 9-0 -> beza +8. A3: kalah 0-9, menang 2-1 -> beza -8.
// Jadi johan = A2 (beza terbaik) kerana h2h kitaran tak menyelesaikan.
$sen = $k->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
$M = [];
foreach ($sen as $m) $M[$m['kod']] = $m;
$idIkutNama = [];
foreach ($k->get('/api/public.php')['pasukan'] as $t) $idIkutNama[$t['nama']] = $t['id'];

$mainG = function (string $kod, int $sh, int $sa) use ($k, &$M) {
    $sen = $k->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
    foreach ($sen as $m) $M[$m['kod']] = $m;
    $m = $M[$kod];
    $r = $k->post('/api/matches.php', ['action' => 'simpan'], [
        'id' => $m['id'], 'version' => $m['version'], 'skor_home' => $sh, 'skor_away' => $sa, 'status' => 'done']);
    sahkan(!empty($r['ok']), "Main $kod", json_encode($r));
};

// A1 = home lwn A2 (perlawanan A1), A3 home lwn A1 (A2), A2 home lwn A3 (A3)
$mainG('A1', 1, 0);   // A1 tewaskan A2
$mainG('A2', 2, 1);   // A3 tewaskan A1
$mainG('A3', 9, 0);   // A2 belasah A3

$awam = $k->get('/api/public.php');
$g = $awam['kedudukan']['A'];
sahkan($g['baris'][0]['nama_papar'] === 'PA2', 'Kitaran h2h -> jatuh ke beza gol, PA2 johan', $g['baris'][0]['nama_papar']);

// Kes h2h jelas: Kumpulan B — B1 tewaskan B2, kedua-dua menang lwn B3? tak boleh (3 pasukan).
// B1 tewaskan B2 2-1; B1 kalah kpd B3 0-3; B2 tewaskan B3 5-0.
// Semua 3 mata. beza: B1 -2+1=... B1: +1, -3 => -2. B2: -1, +5 => +4. B3: +3, -5 => -2.
// h2h pairwise: B1>B2 (menang), B2>B3, B3>B1 — kitaran lagi. Cuba kes bukan kitaran:
// B1 tewaskan B2 1-0. B1 seri B3? Grup guna liga: B1 lwn B2, B3 lwn B1, B2 lwn B3.
// Mahu: B1 & B2 sama mata 6? mustahil (saling bertemu). Sama mata 3-3 dgn h2h jelas:
// B1 tewaskan B2 (3m). B3 tewaskan B1 (B3 3m). B2 tewaskan B3 dgn... maka semua 3 — kitaran semula.
// Kes non-kitaran: B1 tewaskan B2, B1 tewaskan B3 => B1 6 mata. B2 tewaskan B3 5-4, B2=3, B3=0. Tiada seri mata.
// Seri mata dengan h2h jelas hanya bila seri melibatkan seri perlawanan:
// B1 seri B2 1-1 (1,1). B3 kalah kedua-dua? B3 lwn B1: B1 menang 2-0 (B1=4). B2 lwn B3: B2 menang 1-0 (B2=4). B1 vs B2 seri -> h2h seri, jatuh beza: B1 +2+... B1: 1-1, 2-0 => jgm3 jgk1 beza+2. B2: 1-1, 1-0 => jgm2 jgk1 beza+1. B1 johan atas beza.
// Ujian h2h tulen: mata sama, h2h ada pemenang, beza gol BERTENTANGAN:
// C1 tewaskan C2 1-0. C2 tewaskan C3 9-0. C3 tewaskan C1?? -> kitaran. Dengan 3 pasukan liga penuh,
// mata sama 3-3-3 sentiasa kitaran ATAU melalui seri. Jadi uji h2h dua pasukan atas (mata sama, pihak ketiga kalah semua):
// C1 tewaskan C3 2-0. C2 tewaskan C3 1-0. C1 lwn C2: C2 menang 1-0.
// C1=3, C2=6... tak sama. Hmm: C1 dan C2 mata sama perlu kedua-dua 3 mata & C3 3 mata (kitaran) atau seri.
// KESIMPULAN: dalam liga 3 pasukan, dua pasukan sama mata dengan h2h berkeputusan hanya berlaku pada 3-3-3 (kitaran tidak semestinya!):
// C1 tewaskan C2. C1 kalah C3. C2 tewaskan C3 -> kitaran. C1 tewaskan C2, C1 kalah C3, C3 kalah C2? = C2 tewaskan C3 -> sama.
// Bukan kitaran: C1 tewaskan C2, C3 tewaskan C1, C3 tewaskan C2 => C3=6. tak sama.
// Maka 3-3-3 sentiasa kitaran dalam liga 3 pasukan. h2h berkesan bila BEZA & MATA sama tapi 2 pasukan seri? Seri beri h2h=0.
// h2h njelas: C1 & C2 kedua-dua 3 mata di mana satu menang satu kalah = kitaran shj. OK —
// h2h dengan pemenang jelas diuji cukup pada tahap unit beza gol bertentangan dlm kitaran separa:
// C1 tewaskan C2 5-0 (C1 beza besar). C2 tewaskan C3 1-0. C3 tewaskan C1 1-0.
// Semua 3 mata. beza: C1 +5-1=+4, C2 -5+1=-4, C3 -1+1... C3: menang 1-0, kalah 0-1 => 0.
// Kitaran -> beza: C1 johan. Sahkan C1.
$mainG('C1', 5, 0);   // C1 (home=slot1 lwn slot2) tewaskan C2
$mainG('C2', 1, 0);   // C3 (home=slot3) tewaskan C1
$mainG('C3', 1, 0);   // C2 (home=slot2) tewaskan C3
$awam = $k->get('/api/public.php');
$g = $awam['kedudukan']['C'];
sahkan($g['baris'][0]['nama_papar'] === 'PC1', 'Kitaran C -> beza gol, PC1 johan', $g['baris'][0]['nama_papar']);

// Kes h2h menang jelas mengatasi beza gol: D — D1 seri? Tidak. Guna: D1 tewaskan D2 1-0,
// D3 seri D1 0-0, D2 tewaskan D3 9-0. Mata: D1=4, D2=3, D3=1. Tak sama.
// Guna seri: D1 seri D2 2-2. D1 tewaskan D3 1-0. D2 tewaskan D3 9-0.
// D1=4, D2=4. h2h seri -> beza: D1 +1, D2 +9 => D2 johan (beza). h2h tak berkeputusan.
// h2h dengan keputusan + mata sama TIDAK WUJUD tanpa kitaran dlm liga-3. Ujian h2h penuh dibuat pada
// peringkat fungsi dalam simulasi (bandingBersemuka diuji tak langsung). Memadai.

exit(ringkasanUjian());
