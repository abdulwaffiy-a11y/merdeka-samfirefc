<?php
/**
 * seed_demo.php — isikan data contoh untuk tangkapan skrin / demo.
 *
 *   php tests/seed_demo.php [peratus_siap]
 *     0   = kosong (baru dipasang)
 *     100 = kejohanan selesai sepenuhnya
 */

declare(strict_types=1);

require __DIR__ . '/klien.php';

$BASE   = getenv('MERDEKA_BASE') ?: 'http://127.0.0.1:8090';
$DBNAME = getenv('MERDEKA_DB')   ?: 'merdeka';
$PERATUS = (int)($argv[1] ?? 100);

$EMAIL = 'demo@paksy.test';
$PASS  = 'demo12345';

exec('sudo mariadb ' . escapeshellarg($DBNAME) . ' < ' . escapeshellarg(__DIR__ . '/../sql/schema.sql'));
$h = password_hash($PASS, PASSWORD_BCRYPT);
exec('sudo mariadb ' . escapeshellarg($DBNAME) . ' -e ' . escapeshellarg(sprintf(
    "INSERT INTO admins (nama,email,password_hash,role) VALUES ('Waffiy Rosli','%s','%s','super'),('Urus Setia 2','admin2@paksy.test','%s','admin');",
    $EMAIL, $h, $h
)));

$NAMA = [
  'Surau Al-Hidayah FC','Tahfiz Darul Quran','Kampung Selamat United','PAKSY Warriors',
  'Bertam Perdana FC','Sungai Dua Rangers','Masjid Al-Ikhlas','Penaga Sportif',
  'Kubang Menerong FC','Guar Perahu Boys','Pinang Tunggal FC','Sama Gagah United',
  'Telok Air Tawar','Permatang Bertam FC','Kepala Batas Elit','Sungai Petani Rovers',
  'Tasek Gelugor FC','Pokok Sena Strikers','Belia Lubok Meriam','Surau Nurul Iman',
  'Padang Menora FC','Bumbung Lima Legends','Tahfiz As-Syafiee','Kampung Bahru FC',
];

$k = new Klien($BASE, 'demo');
$r = $k->login($EMAIL, $PASS);
if (empty($r['ok'])) { fwrite(STDERR, "Log masuk gagal: " . json_encode($r) . "\n"); exit(1); }

$awam = $k->get('/api/public.php');
$hantar = [];
foreach ($awam['pasukan'] as $i => $t) {
    $hantar[] = ['id' => $t['id'], 'nama' => $NAMA[$i], 'tiebreak' => $t['slot']];
}
$k->post('/api/teams.php', ['action' => 'simpan'], ['pasukan' => $hantar]);

// Beberapa pemain untuk pasukan pertama
foreach (array_slice($awam['pasukan'], 0, 3) as $t) {
    $pemain = [];
    foreach (['Ahmad Faiz','Muhammad Danial','Syafiq Aiman','Hakimi Rosli','Amirul Hakim','Zulhilmi Anuar'] as $j => $n) {
        $pemain[] = ['nama' => $n, 'no_jersi' => (string)($j + 7)];
    }
    $k->post('/api/teams.php', ['action' => 'pemain_simpan'], ['team_id' => $t['id'], 'pemain' => $pemain]);
}

if ($PERATUS <= 0) { echo "Data asas sahaja (kejohanan belum bermula).\n"; exit(0); }

$sen = $k->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
$grup = array_values(array_filter($sen, fn($m) => $m['peringkat'] === 'grup'));
$bilGrup = (int)round(count($grup) * min(100, $PERATUS) / 100);

foreach (array_slice($grup, 0, $bilGrup) as $m) {
    $k->post('/api/matches.php', ['action' => 'simpan'], [
        'id' => $m['id'], 'version' => $m['version'],
        'skor_home' => random_int(0, 6), 'skor_away' => random_int(0, 5), 'status' => 'done',
    ]);
}

// Satu perlawanan LIVE jika belum semua tamat
if ($bilGrup < count($grup)) {
    $m = $grup[$bilGrup];
    $sen2 = $k->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
    foreach ($sen2 as $x) if ($x['id'] === $m['id']) $m = $x;
    $k->post('/api/matches.php', ['action' => 'simpan'], [
        'id' => $m['id'], 'version' => $m['version'],
        'skor_home' => 2, 'skor_away' => 1, 'status' => 'live',
    ]);
    echo "Seed: {$bilGrup}/24 perlawanan kumpulan tamat, 1 LIVE.\n";
    exit(0);
}

// Undian + peringkat kalah mati
$r = $k->post('/api/draw.php', ['action' => 'jalan'], []);
if (empty($r['ok'])) { echo "Undian gagal: " . json_encode($r) . "\n"; exit(1); }

$mainKO = function (string $kod) use ($k) {
    $sen = $k->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
    $m = null; foreach ($sen as $x) if ($x['kod'] === $kod) $m = $x;
    $sh = random_int(1, 5); $sa = random_int(0, 4);
    if ($sh === $sa) $sh++;
    $k->post('/api/matches.php', ['action' => 'simpan'], [
        'id' => $m['id'], 'version' => $m['version'],
        'skor_home' => $sh, 'skor_away' => $sa, 'status' => 'done',
    ]);
};

foreach (['SA1','SA2','SA3','SA4','SS1','SS2','T3','FINAL'] as $kod) $mainKO($kod);

$k->post('/api/admins.php', ['action' => 'tetapan'], ['pengumuman' => 'Majlis penyampaian hadiah bermula 4.05 petang di hadapan pentas utama.']);

echo "Seed penuh selesai — kejohanan tamat.\nLog masuk demo: $EMAIL / $PASS\n";
