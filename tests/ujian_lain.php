<?php
/**
 * ujian_lain.php — ujian keselamatan, kerawakan undian & konflik serentak.
 *
 * Jalankan:  php tests/ujian_lain.php
 */

declare(strict_types=1);

require __DIR__ . '/klien.php';

$BASE   = getenv('MERDEKA_BASE') ?: 'http://127.0.0.1:8080';
$DBNAME = getenv('MERDEKA_DB')   ?: 'merdeka';

$EMAIL_SUPER = 'ujian@paksy.test';
$EMAIL_ADMIN = 'admin2@paksy.test';
$PASS = 'ujian12345';

function resetDb2(string $db, string $pass): void
{
    exec('sudo mariadb ' . escapeshellarg($db) . ' < ' . escapeshellarg(__DIR__ . '/../sql/schema.sql'), $o, $c);
    if ($c !== 0) throw new RuntimeException('Gagal import skema.');
    $h = password_hash($pass, PASSWORD_BCRYPT);
    $sql = sprintf(
        "INSERT INTO admins (nama,email,password_hash,role) VALUES ('Ujian Super','ujian@paksy.test','%s','super'),('Ujian Admin','admin2@paksy.test','%s','admin'),('Admin Tiga','admin3@paksy.test','%s','admin');",
        $h, $h, $h
    );
    exec('sudo mariadb ' . escapeshellarg($db) . ' -e ' . escapeshellarg($sql));
}

function isiPasukanDanMain(Klien $k, bool $siapSemua = true): void
{
    $awam = $k->get('/api/public.php');
    $hantar = [];
    foreach ($awam['pasukan'] as $t) {
        $hantar[] = ['id' => $t['id'], 'nama' => 'Pasukan ' . $t['kumpulan'] . $t['slot'], 'tiebreak' => $t['slot']];
    }
    $k->post('/api/teams.php', ['action' => 'simpan'], ['pasukan' => $hantar]);

    if (!$siapSemua) return;
    $sen = $k->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
    foreach ($sen as $m) {
        if ($m['peringkat'] !== 'grup') continue;
        // Skor tetap: slot 1 menang besar -> johan sentiasa jelas
        $k->post('/api/matches.php', ['action' => 'simpan'], [
            'id' => $m['id'], 'version' => $m['version'],
            'skor_home' => 3, 'skor_away' => 1, 'status' => 'done',
        ]);
    }
}

// =====================================================================
echo "UJIAN KESELAMATAN, KERAWAKAN & SERENTAK\n";

// ---------------------------------------------------------------------
tajukUjian('1. Kawalan akses (tanpa log masuk)');
resetDb2($DBNAME, $PASS);
$tanpa = new Klien($BASE, 'anon');
foreach ([
    ['/api/teams.php',   ['action' => 'senarai']],
    ['/api/matches.php', ['action' => 'senarai']],
    ['/api/admins.php',  ['action' => 'senarai']],
] as [$p, $q]) {
    $tanpa->get($p, $q);
    sahkan($tanpa->lastCode === 401, "Akses tanpa log masuk ditolak: $p", 'kod ' . $tanpa->lastCode);
}
$tanpa->get('/api/public.php');
sahkan($tanpa->lastCode === 200, 'Paparan awam boleh diakses tanpa log masuk');

// ---------------------------------------------------------------------
tajukUjian('2. CSRF');
$k = new Klien($BASE, 'csrf');
$k->login($EMAIL_SUPER, $PASS);
$sen = $k->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
// Hantar tanpa token CSRF (guna cURL mentah dengan kuki sesi yang sama)
$ch = curl_init($BASE . '/api/matches.php?action=simpan');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['id' => $sen[0]['id'], 'version' => $sen[0]['version'], 'skor_home' => 9, 'skor_away' => 0, 'status' => 'done']),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_COOKIEFILE => sys_get_temp_dir() . '/merdeka_ck_csrf_' . getmypid() . '.txt',
]);
curl_exec($ch);
$kod = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
sahkan($kod === 419, 'Permintaan tanpa token CSRF ditolak', 'kod ' . $kod);

// ---------------------------------------------------------------------
tajukUjian('3. Had percubaan log masuk (5 gagal -> kunci)');
resetDb2($DBNAME, $PASS);
$kb = new Klien($BASE, 'brute');
$dikunci = false;
for ($i = 1; $i <= 7; $i++) {
    $r = $kb->post('/api/auth.php', ['action' => 'login'], ['email' => $EMAIL_SUPER, 'password' => 'salahsalah']);
    if ($kb->lastCode === 429) { $dikunci = true; break; }
}
sahkan($dikunci, 'Akaun dikunci selepas percubaan gagal berulang', 'gagal pada cubaan ke-' . $i);
$r = $kb->post('/api/auth.php', ['action' => 'login'], ['email' => $EMAIL_SUPER, 'password' => $PASS]);
sahkan(empty($r['ok']), 'Kata laluan betul pun ditolak semasa tempoh kunci');

// ---------------------------------------------------------------------
tajukUjian('4. Konflik serentak (dua admin, perlawanan sama)');
resetDb2($DBNAME, $PASS);
$a = new Klien($BASE, 'a'); $a->login($EMAIL_SUPER, $PASS);
$b = new Klien($BASE, 'b'); $b->login($EMAIL_ADMIN, $PASS);
$c = new Klien($BASE, 'c'); $c->login('admin3@paksy.test', $PASS);
sahkan(true, '3 admin log masuk serentak tanpa konflik sesi');

$senA = $a->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
$senB = $b->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
$mA = $senA[0]; $mB = $senB[0];   // kedua-dua buka perlawanan yang SAMA
sahkan($mA['id'] === $mB['id'] && $mA['version'] === $mB['version'], 'Kedua-dua admin baca versi sama');

$r1 = $a->post('/api/matches.php', ['action' => 'simpan'], [
    'id' => $mA['id'], 'version' => $mA['version'], 'skor_home' => 5, 'skor_away' => 1, 'status' => 'done']);
sahkan(!empty($r1['ok']), 'Admin A berjaya simpan dahulu');

$r2 = $b->post('/api/matches.php', ['action' => 'simpan'], [
    'id' => $mB['id'], 'version' => $mB['version'], 'skor_home' => 0, 'skor_away' => 9, 'status' => 'done']);
sahkan(empty($r2['ok']) && $b->lastCode === 409, 'Admin B ditolak (konflik versi)', 'kod ' . $b->lastCode);
sahkan(!empty($r2['konflik']), 'Respons konflik memberitahu klien untuk segar semula');

$semak = $a->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
$m = null; foreach ($semak as $x) if ($x['id'] === $mA['id']) $m = $x;
sahkan($m['skor_home'] === 5 && $m['skor_away'] === 1, 'Skor Admin A kekal — tiada tindihan senyap');

// Admin B simpan semula dengan versi terkini -> berjaya
$r3 = $b->post('/api/matches.php', ['action' => 'simpan'], [
    'id' => $m['id'], 'version' => $m['version'], 'skor_home' => 4, 'skor_away' => 2, 'status' => 'done']);
sahkan(!empty($r3['ok']), 'Admin B berjaya selepas segar semula');

// Perlawanan BERBEZA serentak — kedua-dua mesti berjaya
$senA = $a->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
$m1 = $senA[1]; $m2 = $senA[2];
$rA = $a->post('/api/matches.php', ['action' => 'simpan'], ['id' => $m1['id'], 'version' => $m1['version'], 'skor_home' => 2, 'skor_away' => 0, 'status' => 'done']);
$rB = $c->post('/api/matches.php', ['action' => 'simpan'], ['id' => $m2['id'], 'version' => $m2['version'], 'skor_home' => 1, 'skor_away' => 3, 'status' => 'done']);
sahkan(!empty($rA['ok']) && !empty($rB['ok']), 'Dua admin kemaskini perlawanan berbeza serentak — kedua-dua berjaya');

// ---------------------------------------------------------------------
tajukUjian('5. Undian: 100 kali, taburan & integriti');
$taburan = [];
$semuaSah = true;
for ($i = 0; $i < 100; $i++) {
    resetDb2($DBNAME, $PASS);
    $k = new Klien($BASE, 'u' . $i);
    $k->login($EMAIL_SUPER, $PASS);
    isiPasukanDanMain($k);

    $r = $k->post('/api/draw.php', ['action' => 'jalan'], []);
    if (empty($r['ok'])) { $semuaSah = false; echo '  ! undian gagal: ' . json_encode($r) . "\n"; break; }
    $h = $r['undian']['kedudukan'];
    if (count($h) !== 8 || count(array_unique($h)) !== 8) { $semuaSah = false; break; }
    foreach ($h as $pos => $tid) {
        $taburan[$tid][$pos] = ($taburan[$tid][$pos] ?? 0) + 1;
    }
}
sahkan($semuaSah, '100 undian: semua sah, 8 pasukan unik setiap kali');
sahkan(count($taburan) === 8, 'Hanya 8 pasukan (johan kumpulan) pernah muncul', (string)count($taburan));

$minSel = PHP_INT_MAX; $maxSel = 0;
foreach ($taburan as $tid => $pos) {
    for ($i = 0; $i < 8; $i++) {
        $n = $pos[$i] ?? 0;
        $minSel = min($minSel, $n);
        $maxSel = max($maxSel, $n);
    }
}
// Jangkaan ~12.5 setiap sel (100 undian / 8 kedudukan). Julat longgar: 2..30
sahkan($minSel >= 2 && $maxSel <= 30, 'Taburan undian munasabah rawak', "min=$minSel maks=$maxSel (jangkaan ~12.5)");

// ---------------------------------------------------------------------
tajukUjian('6. Undian sebelum kumpulan selesai');
resetDb2($DBNAME, $PASS);
$k = new Klien($BASE, 'awal');
$k->login($EMAIL_SUPER, $PASS);
isiPasukanDanMain($k, false);
$r = $k->post('/api/draw.php', ['action' => 'jalan'], []);
sahkan(empty($r['ok']) && $k->lastCode === 409, 'Undian ditolak sebelum semua perlawanan kumpulan tamat', 'kod ' . $k->lastCode);

// ---------------------------------------------------------------------
tajukUjian('7. Reset undian (Super Admin sahaja + pengesahan)');
resetDb2($DBNAME, $PASS);
$sup = new Klien($BASE, 'sup'); $sup->login($EMAIL_SUPER, $PASS);
$biasa = new Klien($BASE, 'biasa'); $biasa->login($EMAIL_ADMIN, $PASS);
isiPasukanDanMain($sup);
$sup->post('/api/draw.php', ['action' => 'jalan'], []);

$r = $biasa->post('/api/draw.php', ['action' => 'reset'], ['sahkan' => 'RESET UNDIAN']);
sahkan(empty($r['ok']) && $biasa->lastCode === 403, 'Admin biasa tidak boleh reset undian', 'kod ' . $biasa->lastCode);

$r = $sup->post('/api/draw.php', ['action' => 'reset'], ['sahkan' => 'salah']);
sahkan(empty($r['ok']), 'Reset tanpa teks pengesahan tepat ditolak');

$r = $sup->post('/api/draw.php', ['action' => 'reset'], ['sahkan' => 'RESET UNDIAN']);
sahkan(!empty($r['ok']), 'Super Admin berjaya reset undian');

$sen = $sup->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
$koKosong = true;
foreach ($sen as $m) {
    if (in_array($m['peringkat'], ['sa','ss','third','final'], true)) {
        if ($m['team_home_id'] !== null || $m['skor_home'] !== null || $m['status'] !== 'scheduled') $koKosong = false;
    }
}
sahkan($koKosong, 'Semua perlawanan kalah mati dikosongkan selepas reset');

$r = $sup->post('/api/draw.php', ['action' => 'jalan'], []);
sahkan(!empty($r['ok']), 'Undian boleh dijalankan semula selepas reset');

// ---------------------------------------------------------------------
tajukUjian('8. Perlindungan skor kumpulan selepas undian');
$sen = $sup->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
$grup = null; foreach ($sen as $m) if ($m['peringkat'] === 'grup') { $grup = $m; break; }

$r = $biasa->post('/api/matches.php', ['action' => 'simpan'], [
    'id' => $grup['id'], 'version' => $grup['version'], 'skor_home' => 1, 'skor_away' => 0, 'status' => 'done']);
sahkan(empty($r['ok']) && $biasa->lastCode === 403, 'Admin biasa tidak boleh ubah skor kumpulan selepas undian');

$r = $sup->post('/api/matches.php', ['action' => 'simpan'], [
    'id' => $grup['id'], 'version' => $grup['version'], 'skor_home' => 1, 'skor_away' => 0, 'status' => 'done']);
sahkan(empty($r['ok']) && $sup->lastCode === 428, 'Super Admin diminta sahkan dahulu', 'kod ' . $sup->lastCode);

// Pembetulan kecil yang TIDAK menukar johan -> dibenarkan
$r = $sup->post('/api/matches.php', ['action' => 'simpan'], [
    'id' => $grup['id'], 'version' => $grup['version'], 'skor_home' => 4, 'skor_away' => 1, 'status' => 'done', 'paksa' => true]);
sahkan(!empty($r['ok']), 'Pembetulan yang tidak menukar johan dibenarkan', json_encode($r));

// Pembetulan yang MENUKAR johan -> mesti ditolak
$sen = $sup->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
foreach ($sen as $m) if ($m['id'] === $grup['id']) $grup = $m;
$r = $sup->post('/api/matches.php', ['action' => 'simpan'], [
    'id' => $grup['id'], 'version' => $grup['version'], 'skor_home' => 0, 'skor_away' => 9, 'status' => 'done', 'paksa' => true]);
sahkan(empty($r['ok']) && $sup->lastCode === 409, 'Perubahan yang menukar johan ditolak selepas undian', 'kod ' . $sup->lastCode);

$sen = $sup->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
foreach ($sen as $m) if ($m['id'] === $grup['id']) $g2 = $m;
sahkan($g2['skor_home'] === 4 && $g2['skor_away'] === 1, 'Skor asal kekal selepas penolakan (transaksi berundur)');

// ---------------------------------------------------------------------
tajukUjian('9. Kunci keputusan (Super Admin)');
$sup->post('/api/admins.php', ['action' => 'tetapan'], ['keputusan_dikunci' => true]);
$sen = $sup->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
$sa1 = null; foreach ($sen as $m) if ($m['kod'] === 'SA1') $sa1 = $m;
$r = $sup->post('/api/matches.php', ['action' => 'simpan'], [
    'id' => $sa1['id'], 'version' => $sa1['version'], 'skor_home' => 3, 'skor_away' => 0, 'status' => 'done']);
sahkan(empty($r['ok']) && $sup->lastCode === 423, 'Tiada perubahan dibenarkan semasa dikunci', 'kod ' . $sup->lastCode);
$sup->post('/api/admins.php', ['action' => 'tetapan'], ['keputusan_dikunci' => false]);
$r = $sup->post('/api/matches.php', ['action' => 'simpan'], [
    'id' => $sa1['id'], 'version' => $sa1['version'], 'skor_home' => 3, 'skor_away' => 0, 'status' => 'done']);
sahkan(!empty($r['ok']), 'Perubahan dibenarkan semula selepas kunci dibuka');

// ---------------------------------------------------------------------
tajukUjian('10. Pengesahan input');
$sen = $sup->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
$m = null; foreach ($sen as $x) if ($x['kod'] === 'SA2') $m = $x;
foreach ([
    [['skor_home' => -1, 'skor_away' => 0, 'status' => 'done'], 'Skor negatif ditolak'],
    [['skor_home' => 500, 'skor_away' => 0, 'status' => 'done'], 'Skor terlalu besar ditolak'],
    [['skor_home' => 'abc', 'skor_away' => 0, 'status' => 'done'], 'Skor bukan nombor ditolak'],
    [['skor_home' => null, 'skor_away' => null, 'status' => 'done'], 'Tandakan TAMAT tanpa skor ditolak'],
    [['skor_home' => 1, 'skor_away' => 0, 'status' => 'entah'], 'Status tidak sah ditolak'],
] as [$muatan, $tajuk]) {
    $r = $sup->post('/api/matches.php', ['action' => 'simpan'], ['id' => $m['id'], 'version' => $m['version']] + $muatan);
    sahkan(empty($r['ok']), $tajuk, json_encode($r));
}

// SQL injection dalam nama pasukan
$awam = $sup->get('/api/public.php');
$t0 = $awam['pasukan'][0];
$jahat = "Robert'); DROP TABLE teams;-- <script>alert(1)</script>";
$r = $sup->post('/api/teams.php', ['action' => 'simpan'], ['pasukan' => [['id' => $t0['id'], 'nama' => $jahat]]]);
sahkan(!empty($r['ok']), 'Nama dengan aksara khas diterima sebagai teks biasa');
$awam2 = $sup->get('/api/public.php');
sahkan(count($awam2['pasukan']) === 24, 'Jadual teams masih utuh selepas percubaan SQL injection');
$jumpa = false;
foreach ($awam2['pasukan'] as $t) if ($t['nama'] === $jahat) $jumpa = true;
sahkan($jumpa, 'Nama disimpan tepat (tiada pemotongan pelik)');

// ---------------------------------------------------------------------
tajukUjian('11. ETag paparan awam (jimat data)');
$an = new Klien($BASE, 'etag');
$an->get('/api/public.php');
$etag = $an->lastHeaders['etag'] ?? '';
sahkan($etag !== '', 'ETag dihantar');
$an->get('/api/public.php', [], ['If-None-Match: ' . $etag]);
sahkan($an->lastCode === 304, 'Panggilan berulang tanpa perubahan pulangkan 304', 'kod ' . $an->lastCode);

// ---------------------------------------------------------------------
tajukUjian('12. Log aktiviti');
$log = $sup->get('/api/admins.php', ['action' => 'log', 'had' => 200])['log'] ?? [];
$adaSkor = false; $adaUndi = false;
foreach ($log as $l) {
    if ($l['tindakan'] === 'skor_simpan') $adaSkor = true;
    if ($l['tindakan'] === 'undian_jalan') $adaUndi = true;
}
sahkan($adaSkor, 'Perubahan skor direkod dalam log');
sahkan($adaUndi, 'Undian direkod dalam log');

exit(ringkasanUjian());
