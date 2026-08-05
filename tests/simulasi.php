<?php
/**
 * simulasi.php — ujian hujung-ke-hujung melalui API sebenar (HTTP).
 *
 * Jalankan:  php tests/simulasi.php [bilangan_pusingan]
 *
 * Setiap pusingan:
 *   1. Reset database ke keadaan kosong + seed admin
 *   2. Isi 24 nama pasukan
 *   3. Main 24 perlawanan kumpulan dengan skor rawak
 *   4. Sahkan kedudukan kumpulan (dikira semula secara bebas dalam ujian ini)
 *   5. Jalankan undian suku akhir, sahkan 8 johan unik masuk carta
 *   6. Main SA -> SS -> Tempat Ke-3 -> Akhir, sahkan pemenang naik dengan betul
 *   7. Sahkan kedudukan akhir 1-4
 */

declare(strict_types=1);

require __DIR__ . '/klien.php';

$BASE  = getenv('MERDEKA_BASE') ?: 'http://127.0.0.1:8080';
$DBNAME = getenv('MERDEKA_DB') ?: 'merdeka';
$PUSINGAN = (int)($argv[1] ?? 3);

$EMAIL = 'ujian@paksy.test';
$PASS  = 'ujian12345';

function resetDb(string $db, string $email, string $pass): void
{
    $skema = __DIR__ . '/../sql/schema.sql';
    exec('sudo mariadb ' . escapeshellarg($db) . ' < ' . escapeshellarg($skema), $o, $c);
    if ($c !== 0) {
        throw new RuntimeException('Gagal import skema.');
    }
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $sql = sprintf(
        "INSERT INTO admins (nama,email,password_hash,role) VALUES ('Ujian Super','%s','%s','super'),('Ujian Admin','admin2@paksy.test','%s','admin');",
        $email,
        str_replace("'", "''", $hash),
        str_replace("'", "''", $hash)
    );
    exec('sudo mariadb ' . escapeshellarg($db) . ' -e ' . escapeshellarg($sql), $o2, $c2);
    if ($c2 !== 0) throw new RuntimeException('Gagal seed admin.');
}

/** Kiraan kedudukan BEBAS (ditulis berasingan daripada kod pengeluaran). */
function kiraSendiri(array $perlawanan, array $pasukanIkutKumpulan): array
{
    $jadual = [];
    foreach ($pasukanIkutKumpulan as $kump => $ids) {
        $s = [];
        foreach ($ids as $id) {
            $s[$id] = ['id' => $id, 'mata' => 0, 'jgm' => 0, 'jgk' => 0, 'main' => 0];
        }
        foreach ($perlawanan as $m) {
            if ($m['peringkat'] !== 'grup' || $m['kumpulan'] !== $kump || $m['status'] !== 'done') continue;
            $h = $m['home_id']; $a = $m['away_id'];
            $x = (int)$m['skor_home']; $y = (int)$m['skor_away'];
            $s[$h]['main']++; $s[$a]['main']++;
            $s[$h]['jgm'] += $x; $s[$h]['jgk'] += $y;
            $s[$a]['jgm'] += $y; $s[$a]['jgk'] += $x;
            if ($x > $y) $s[$h]['mata'] += 3;
            elseif ($y > $x) $s[$a]['mata'] += 3;
            else { $s[$h]['mata']++; $s[$a]['mata']++; }
        }
        $jadual[$kump] = $s;
    }
    return $jadual;
}

// =====================================================================
echo "Sistem Kejohanan Futsal Merdeka Kepala Batas 2026 — UJIAN SIMULASI\n";
echo "Base: $BASE   Pusingan: $PUSINGAN\n";

for ($p = 1; $p <= $PUSINGAN; $p++) {
    tajukUjian("PUSINGAN $p / $PUSINGAN");
    resetDb($DBNAME, $EMAIL, $PASS);

    $k = new Klien($BASE, 'sim' . $p);
    $r = $k->login($EMAIL, $PASS);
    sahkan(!empty($r['ok']), 'Log masuk super admin');

    // ---- 1. Isi nama pasukan ------------------------------------------
    $awam = $k->get('/api/public.php');
    $slotPasukan = $awam['pasukan'];
    sahkan(count($slotPasukan) === 24, '24 slot pasukan wujud', 'dapat ' . count($slotPasukan));

    $hantar = [];
    foreach ($slotPasukan as $i => $t) {
        $hantar[] = ['id' => $t['id'], 'nama' => 'FC ' . $t['kumpulan'] . $t['slot'] . ' Pusingan' . $p, 'singkatan' => $t['kumpulan'] . $t['slot']];
    }
    $r = $k->post('/api/teams.php', ['action' => 'simpan'], ['pasukan' => $hantar]);
    sahkan(!empty($r['ok']), 'Simpan 24 nama pasukan', json_encode($r));

    // Nama berulang mesti ditolak
    $dup = $hantar;
    $dup[1]['nama'] = $dup[0]['nama'];
    $r = $k->post('/api/teams.php', ['action' => 'simpan'], ['pasukan' => $dup]);
    sahkan(empty($r['ok']), 'Nama pasukan berulang ditolak');

    // ---- 2. Main 24 perlawanan kumpulan --------------------------------
    $awam = $k->get('/api/public.php');
    $perlawanan = [];
    foreach ($awam['perlawanan'] as $m) $perlawanan[$m['kod']] = $m;

    $senaraiAdmin = $k->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
    $versi = [];
    foreach ($senaraiAdmin as $m) $versi[$m['kod']] = ['id' => $m['id'], 'version' => $m['version']];

    $skorDisimpan = [];
    foreach ($perlawanan as $kod => $m) {
        if ($m['peringkat'] !== 'grup') continue;
        $sh = random_int(0, 7);
        $sa = random_int(0, 7);
        $r = $k->post('/api/matches.php', ['action' => 'simpan'], [
            'id' => $versi[$kod]['id'], 'version' => $versi[$kod]['version'],
            'skor_home' => $sh, 'skor_away' => $sa, 'status' => 'done',
        ]);
        if (empty($r['ok'])) { sahkan(false, "Simpan skor $kod", json_encode($r)); continue; }
        $skorDisimpan[$kod] = [$sh, $sa];
    }
    sahkan(count($skorDisimpan) === 24, 'Semua 24 perlawanan kumpulan disimpan', 'dapat ' . count($skorDisimpan));

    // ---- 3. Sahkan kedudukan -------------------------------------------
    $awam = $k->get('/api/public.php');
    $ikutKumpulan = [];
    foreach ($awam['pasukan'] as $t) $ikutKumpulan[$t['kumpulan']][] = $t['id'];
    $sendiri = kiraSendiri($awam['perlawanan'], $ikutKumpulan);

    $johanSistem = [];
    foreach (['A','B','C','D','E','F','G','H'] as $kump) {
        $g = $awam['kedudukan'][$kump];
        sahkan($g['siap'] === true, "Kumpulan $kump ditanda siap");

        foreach ($g['baris'] as $b) {
            $s = $sendiri[$kump][$b['team_id']];
            sahkan($b['mata'] === $s['mata'], "Mata betul ($kump/{$b['team_id']})", "{$b['mata']} vs {$s['mata']}");
            sahkan($b['jgm'] === $s['jgm'] && $b['jgk'] === $s['jgk'], "Gol betul ($kump/{$b['team_id']})");
            sahkan($b['beza'] === $s['jgm'] - $s['jgk'], "Beza gol betul ($kump/{$b['team_id']})");
            sahkan($b['main'] === 2, "Setiap pasukan main 2 perlawanan ($kump)");
        }
        // susunan: mata mesti tidak menaik (h2h/beza/jgm memecah seri di belakang)
        for ($i = 1; $i < count($g['baris']); $i++) {
            $x = $g['baris'][$i - 1]; $y = $g['baris'][$i];
            sahkan($x['mata'] >= $y['mata'], "Susunan mata menurun ($kump)");
            $seMata = 0;
            foreach ($g['baris'] as $bb) if ($bb['mata'] === $x['mata']) $seMata++;
            if ($x['mata'] === $y['mata'] && $seMata === 2) {
                // TEPAT dua pasukan seri: pemenang pertemuan langsung mesti di atas
                // (3 pasukan seri boleh berkitar — jatuh ke beza gol, tidak diuji di sini)
                $h2h = 0; $gx = 0; $gy = 0;
                foreach ($awam['perlawanan'] as $m) {
                    if ($m['peringkat'] !== 'grup' || $m['kumpulan'] !== $kump || $m['status'] !== 'done') continue;
                    if (($m['home_id'] === $x['team_id'] && $m['away_id'] === $y['team_id'])) { $gx = (int)$m['skor_home']; $gy = (int)$m['skor_away']; $h2h = 1; }
                    if (($m['home_id'] === $y['team_id'] && $m['away_id'] === $x['team_id'])) { $gy = (int)$m['skor_home']; $gx = (int)$m['skor_away']; $h2h = 1; }
                }
                if ($h2h && $gx !== $gy) {
                    sahkan($gx > $gy, "Head-to-head dihormati bila mata sama ($kump)", "atas={$x['nama_papar']} $gx-$gy bawah={$y['nama_papar']}");
                }
            }
        }
        if ($g['johan_id']) $johanSistem[$kump] = $g['johan_id'];
    }

    // ---- 4. Undian ------------------------------------------------------
    $st = $k->get('/api/draw.php', ['action' => 'status']);
    $bolehUndi = $st['layak'];

    if (!$bolehUndi) {
        // Ada kumpulan seri sepenuhnya — selesaikan melalui medan tiebreak
        echo "  (nota: ada kumpulan seri — menetapkan pemecah seri manual)\n";
        $pasukanBaru = [];
        foreach ($awam['pasukan'] as $t) {
            $pasukanBaru[] = ['id' => $t['id'], 'nama' => 'FC ' . $t['kumpulan'] . $t['slot'] . ' Pusingan' . $p,
                              'singkatan' => $t['kumpulan'] . $t['slot'], 'tiebreak' => $t['slot']];
        }
        $k->post('/api/teams.php', ['action' => 'simpan'], ['pasukan' => $pasukanBaru]);
        $st = $k->get('/api/draw.php', ['action' => 'status']);
    }
    sahkan($st['layak'] === true, 'Undian layak dijalankan selepas 24 perlawanan', json_encode($st['sebab'] ?? []));

    $r = $k->post('/api/draw.php', ['action' => 'jalan'], []);
    sahkan(!empty($r['ok']), 'Undian berjaya dijalankan', json_encode($r));
    $hasilUndi = $r['undian']['kedudukan'] ?? [];
    sahkan(count($hasilUndi) === 8, 'Undian menghasilkan 8 kedudukan');
    sahkan(count(array_unique($hasilUndi)) === 8, 'Tiada pasukan berulang dalam undian');

    $awam = $k->get('/api/public.php');
    $johanSah = [];
    foreach (['A','B','C','D','E','F','G','H'] as $kump) {
        $johanSah[] = $awam['kedudukan'][$kump]['johan_id'];
    }
    sort($johanSah);
    $u = $hasilUndi; sort($u);
    sahkan($johanSah === $u, 'Peserta undian tepat 8 johan kumpulan');

    // Undian kedua mesti ditolak
    $r2 = $k->post('/api/draw.php', ['action' => 'jalan'], []);
    sahkan(empty($r2['ok']), 'Undian kedua ditolak');

    // ---- 5. Carta kalah mati -------------------------------------------
    $sen = $k->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
    $M = [];
    foreach ($sen as $m) $M[$m['kod']] = $m;

    sahkan($M['SA1']['team_home_id'] === $hasilUndi[0] && $M['SA1']['team_away_id'] === $hasilUndi[1], 'SA1 = undi 1 vs 2');
    sahkan($M['SA2']['team_home_id'] === $hasilUndi[2] && $M['SA2']['team_away_id'] === $hasilUndi[3], 'SA2 = undi 3 vs 4');
    sahkan($M['SA3']['team_home_id'] === $hasilUndi[4] && $M['SA3']['team_away_id'] === $hasilUndi[5], 'SA3 = undi 5 vs 6');
    sahkan($M['SA4']['team_home_id'] === $hasilUndi[6] && $M['SA4']['team_away_id'] === $hasilUndi[7], 'SA4 = undi 7 vs 8');
    sahkan($M['SS1']['team_home_id'] === null && $M['FINAL']['team_home_id'] === null, 'Peringkat seterusnya masih kosong');

    // Fungsi main satu perlawanan kalah mati; pulangkan [pemenang, kalah]
    $mainKO = function (string $kod, bool $paksaSeri = false) use ($k, &$M): array {
        $sen = $k->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
        foreach ($sen as $m) $M[$m['kod']] = $m;
        $m = $M[$kod];
        if ($paksaSeri) {
            $sh = $sa = 2; $ph = 4; $pa = 3;
        } else {
            $sh = random_int(0, 6);
            $sa = random_int(0, 6);
            if ($sh === $sa) { $sh++; }
            $ph = $pa = null;
        }
        $r = $k->post('/api/matches.php', ['action' => 'simpan'], [
            'id' => $m['id'], 'version' => $m['version'],
            'skor_home' => $sh, 'skor_away' => $sa,
            'penalti_home' => $ph, 'penalti_away' => $pa,
            'status' => 'done',
        ]);
        sahkan(!empty($r['ok']), "Simpan keputusan $kod", json_encode($r));
        $menang = $ph !== null
            ? ($ph > $pa ? $m['team_home_id'] : $m['team_away_id'])
            : ($sh > $sa ? $m['team_home_id'] : $m['team_away_id']);
        $kalah = $menang === $m['team_home_id'] ? $m['team_away_id'] : $m['team_home_id'];
        return [$menang, $kalah];
    };

    // Perlawanan seri tanpa penalti mesti ditolak
    $sen = $k->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
    foreach ($sen as $m) $M[$m['kod']] = $m;
    $r = $k->post('/api/matches.php', ['action' => 'simpan'], [
        'id' => $M['SA1']['id'], 'version' => $M['SA1']['version'],
        'skor_home' => 2, 'skor_away' => 2, 'status' => 'done',
    ]);
    sahkan(empty($r['ok']), 'Kalah mati seri tanpa penalti ditolak');

    [$w1] = $mainKO('SA1', true);          // uji laluan penalti
    [$w2] = $mainKO('SA2');
    [$w3] = $mainKO('SA3');
    [$w4] = $mainKO('SA4');

    $sen = $k->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
    foreach ($sen as $m) $M[$m['kod']] = $m;
    sahkan($M['SS1']['team_home_id'] === $w1 && $M['SS1']['team_away_id'] === $w2, 'SS1 diisi pemenang SA1 & SA2');
    sahkan($M['SS2']['team_home_id'] === $w3 && $M['SS2']['team_away_id'] === $w4, 'SS2 diisi pemenang SA3 & SA4');

    [$ws1, $ls1] = $mainKO('SS1');
    [$ws2, $ls2] = $mainKO('SS2');

    $sen = $k->get('/api/matches.php', ['action' => 'senarai'])['perlawanan'];
    foreach ($sen as $m) $M[$m['kod']] = $m;
    sahkan($M['FINAL']['team_home_id'] === $ws1 && $M['FINAL']['team_away_id'] === $ws2, 'Perlawanan Akhir diisi pemenang separuh akhir');
    sahkan($M['T3']['team_home_id'] === $ls1 && $M['T3']['team_away_id'] === $ls2, 'Tempat Ke-3 diisi yang kalah separuh akhir');

    [$juara3, $keempat] = $mainKO('T3');
    [$johan, $naib]     = $mainKO('FINAL');

    $awam = $k->get('/api/public.php');
    $ka = $awam['kedudukan_akhir'];
    sahkan($ka['johan'] === $johan,      'Johan betul');
    sahkan($ka['naib_johan'] === $naib,  'Naib johan betul');
    sahkan($ka['ketiga'] === $juara3,    'Tempat ketiga betul');
    sahkan($ka['keempat'] === $keempat,  'Tempat keempat betul');
    sahkan($awam['ringkasan']['tamat'] === 32, 'Kesemua 32 perlawanan tamat', (string)$awam['ringkasan']['tamat']);

    $empat = [$johan, $naib, $juara3, $keempat];
    sahkan(count(array_unique($empat)) === 4, '4 pasukan berbeza di kedudukan 1-4');
}

exit(ringkasanUjian());
