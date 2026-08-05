<?php
/**
 * kejohanan.php — enjin logik kejohanan.
 *
 *  - Kiraan kedudukan kumpulan (Mata > Beza gol > Jumlah gol > Bersemuka > Undian)
 *  - Penentuan 8 johan kumpulan
 *  - Penyelesaian carta kalah mati (UNDI:n, W:KOD, L:KOD)
 *
 * Semua fungsi di sini TIDAK menganggap apa-apa keadaan sebelumnya —
 * setiap kali dipanggil ia mengira semula dari data mentah. Ini
 * menghapuskan kelas pepijat "data tidak segerak".
 */

declare(strict_types=1);

const KUMPULAN_SENARAI = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

/** Semua pasukan, diindeks oleh id. */
function muatPasukan(): array
{
    $out = [];
    $sql = 'SELECT id, nama, singkatan, pengurus, telefon, logo, kumpulan, slot, tiebreak
            FROM teams ORDER BY kumpulan, slot';
    foreach (db()->query($sql) as $r) {
        $r['id']       = (int)$r['id'];
        $r['slot']     = (int)$r['slot'];
        $r['tiebreak'] = (int)$r['tiebreak'];
        $r['nama_papar'] = $r['nama'] !== '' ? $r['nama'] : ('Pasukan ' . $r['kumpulan'] . $r['slot']);
        $out[$r['id']] = $r;
    }
    return $out;
}

/** Semua perlawanan, diindeks oleh kod. */
function muatPerlawanan(): array
{
    $out = [];
    $sql = 'SELECT id, kod, peringkat, kumpulan, urutan, gelanggang, masa_jadual, tempoh_minit,
                   team_home_id, team_away_id, home_sumber, away_sumber,
                   skor_home, skor_away, penalti_home, penalti_away,
                   status, catatan, updated_by, updated_at, version
            FROM matches ORDER BY urutan';
    foreach (db()->query($sql) as $r) {
        foreach (['id', 'urutan', 'gelanggang', 'tempoh_minit', 'version'] as $k) {
            $r[$k] = (int)$r[$k];
        }
        foreach (['team_home_id', 'team_away_id', 'skor_home', 'skor_away', 'penalti_home', 'penalti_away', 'updated_by'] as $k) {
            $r[$k] = $r[$k] === null ? null : (int)$r[$k];
        }
        $out[$r['kod']] = $r;
    }
    return $out;
}

/** Pemenang satu perlawanan kalah mati (atau null jika belum diputuskan). */
function pemenangPerlawanan(array $m): ?int
{
    if ($m['status'] !== 'done' || $m['skor_home'] === null || $m['skor_away'] === null) {
        return null;
    }
    if ($m['skor_home'] > $m['skor_away']) return $m['team_home_id'];
    if ($m['skor_home'] < $m['skor_away']) return $m['team_away_id'];
    // Seri -> sepakan penalti
    if ($m['penalti_home'] !== null && $m['penalti_away'] !== null) {
        if ($m['penalti_home'] > $m['penalti_away']) return $m['team_home_id'];
        if ($m['penalti_home'] < $m['penalti_away']) return $m['team_away_id'];
    }
    return null;
}

function kalahPerlawanan(array $m): ?int
{
    $w = pemenangPerlawanan($m);
    if ($w === null) return null;
    return $w === $m['team_home_id'] ? $m['team_away_id'] : $m['team_home_id'];
}

/**
 * Kira kedudukan setiap kumpulan.
 *
 * Kembali: ['A' => ['baris' => [...], 'siap' => bool, 'perlu_undian' => bool], ...]
 * Setiap baris: team_id, nama_papar, main, menang, seri, kalah, jgm, jgk, beza, mata, kedudukan
 */
function kiraKedudukan(array $pasukan, array $perlawanan): array
{
    $hasil = [];

    foreach (KUMPULAN_SENARAI as $kump) {
        // --- kumpul pasukan dalam kumpulan ini ---
        $ahli = [];
        foreach ($pasukan as $t) {
            if ($t['kumpulan'] === $kump) {
                $ahli[$t['id']] = [
                    'team_id'    => $t['id'],
                    'slot'       => $t['slot'],
                    'nama_papar' => $t['nama_papar'],
                    'tiebreak'   => $t['tiebreak'],
                    'main' => 0, 'menang' => 0, 'seri' => 0, 'kalah' => 0,
                    'jgm' => 0, 'jgk' => 0, 'beza' => 0, 'mata' => 0,
                ];
            }
        }

        // --- perlawanan kumpulan ini ---
        $mGrup = [];
        foreach ($perlawanan as $m) {
            if ($m['peringkat'] === 'grup' && $m['kumpulan'] === $kump) {
                $mGrup[] = $m;
            }
        }

        $siap = true;
        foreach ($mGrup as $m) {
            if ($m['status'] !== 'done' || $m['skor_home'] === null || $m['skor_away'] === null) {
                $siap = false;
                continue;
            }
            $h = $m['team_home_id'];
            $a = $m['team_away_id'];
            if ($h === null || $a === null || !isset($ahli[$h], $ahli[$a])) {
                continue;
            }
            $sh = (int)$m['skor_home'];
            $sa = (int)$m['skor_away'];

            $ahli[$h]['main']++; $ahli[$a]['main']++;
            $ahli[$h]['jgm'] += $sh; $ahli[$h]['jgk'] += $sa;
            $ahli[$a]['jgm'] += $sa; $ahli[$a]['jgk'] += $sh;

            if ($sh > $sa)      { $ahli[$h]['menang']++; $ahli[$a]['kalah']++;  $ahli[$h]['mata'] += 3; }
            elseif ($sh < $sa)  { $ahli[$a]['menang']++; $ahli[$h]['kalah']++;  $ahli[$a]['mata'] += 3; }
            else                { $ahli[$h]['seri']++;   $ahli[$a]['seri']++;   $ahli[$h]['mata']++; $ahli[$a]['mata']++; }
        }

        foreach ($ahli as $id => $_) {
            $ahli[$id]['beza'] = $ahli[$id]['jgm'] - $ahli[$id]['jgk'];
            $ahli[$id]['h2h_mata'] = 0;
            $ahli[$id]['h2h_beza'] = 0;
            $ahli[$id]['h2h_jgm']  = 0;
        }

        // --- HEAD-TO-HEAD: liga-mini antara pasukan yang SAMA MATA -------
        // (kaedah rasmi: keputusan pertemuan sesama pasukan seri diguna
        //  dahulu sebelum beza gol keseluruhan. Untuk 2 pasukan = pemenang
        //  pertemuan; untuk 3 pasukan berkitar ia jatuh ke beza gol.)
        $ikutMata = [];
        foreach ($ahli as $id => $s) {
            $ikutMata[$s['mata']][] = $id;
        }
        foreach ($ikutMata as $ids) {
            if (count($ids) < 2) continue;
            $set = array_flip($ids);
            foreach ($mGrup as $m) {
                if ($m['status'] !== 'done' || $m['skor_home'] === null || $m['skor_away'] === null) continue;
                $h = $m['team_home_id']; $a = $m['team_away_id'];
                if ($h === null || $a === null || !isset($set[$h], $set[$a])) continue;
                $sh = (int)$m['skor_home']; $sa = (int)$m['skor_away'];
                $ahli[$h]['h2h_jgm'] += $sh; $ahli[$h]['h2h_beza'] += $sh - $sa;
                $ahli[$a]['h2h_jgm'] += $sa; $ahli[$a]['h2h_beza'] += $sa - $sh;
                if ($sh > $sa)     $ahli[$h]['h2h_mata'] += 3;
                elseif ($sh < $sa) $ahli[$a]['h2h_mata'] += 3;
                else { $ahli[$h]['h2h_mata']++; $ahli[$a]['h2h_mata']++; }
            }
        }

        $baris = array_values($ahli);

        usort($baris, function (array $x, array $y): int {
            // 1. Mata
            if ($x['mata'] !== $y['mata']) return $y['mata'] <=> $x['mata'];
            // 2-4. Head-to-head (liga-mini sesama pasukan seri)
            if ($x['h2h_mata'] !== $y['h2h_mata']) return $y['h2h_mata'] <=> $x['h2h_mata'];
            if ($x['h2h_beza'] !== $y['h2h_beza']) return $y['h2h_beza'] <=> $x['h2h_beza'];
            if ($x['h2h_jgm']  !== $y['h2h_jgm'])  return $y['h2h_jgm']  <=> $x['h2h_jgm'];
            // 5. Perbezaan gol keseluruhan
            if ($x['beza'] !== $y['beza']) return $y['beza'] <=> $x['beza'];
            // 6. Jumlah gol keseluruhan
            if ($x['jgm'] !== $y['jgm']) return $y['jgm'] <=> $x['jgm'];
            // 7. Undian manual (nilai lebih kecil = lebih tinggi; 0 = belum ditetapkan)
            $tx = $x['tiebreak'] > 0 ? $x['tiebreak'] : PHP_INT_MAX;
            $ty = $y['tiebreak'] > 0 ? $y['tiebreak'] : PHP_INT_MAX;
            if ($tx !== $ty) return $tx <=> $ty;
            return $x['slot'] <=> $y['slot'];   // susunan stabil sementara
        });

        foreach ($baris as $i => $_) {
            $baris[$i]['kedudukan'] = $i + 1;
        }

        // "perlu_undian" hanya bermakna jika ia menjejaskan tempat pertama
        $undianJejasJohan = false;
        if (count($baris) >= 2) {
            $a = $baris[0];
            $b = $baris[1];
            if ($a['mata'] === $b['mata']
                && $a['h2h_mata'] === $b['h2h_mata'] && $a['h2h_beza'] === $b['h2h_beza'] && $a['h2h_jgm'] === $b['h2h_jgm']
                && $a['beza'] === $b['beza'] && $a['jgm'] === $b['jgm']
                && !($a['tiebreak'] > 0 || $b['tiebreak'] > 0)) {
                $undianJejasJohan = true;
            }
        }

        $hasil[$kump] = [
            'kumpulan'     => $kump,
            'baris'        => $baris,
            'siap'         => $siap,
            'perlu_undian' => $undianJejasJohan,
            'johan_id'     => ($siap && !$undianJejasJohan && $baris) ? $baris[0]['team_id'] : null,
        ];
    }

    return $hasil;
}

/** Bandingkan dua pasukan berdasarkan perlawanan bersemuka sahaja. */
function bandingBersemuka(int $a, int $b, array $mGrup): int
{
    $ma = 0; $mb = 0; $ga = 0; $gb = 0;
    foreach ($mGrup as $m) {
        if ($m['status'] !== 'done' || $m['skor_home'] === null || $m['skor_away'] === null) continue;
        $h = $m['team_home_id']; $w = $m['team_away_id'];
        if (($h === $a && $w === $b) || ($h === $b && $w === $a)) {
            $sh = (int)$m['skor_home']; $sa = (int)$m['skor_away'];
            if ($h === $a) { $ga += $sh; $gb += $sa; if ($sh > $sa) $ma += 3; elseif ($sh < $sa) $mb += 3; else { $ma++; $mb++; } }
            else           { $ga += $sa; $gb += $sh; if ($sa > $sh) $ma += 3; elseif ($sa < $sh) $mb += 3; else { $ma++; $mb++; } }
        }
    }
    if ($ma !== $mb) return $mb <=> $ma;
    if ($ga !== $gb) return $gb <=> $ga;
    return 0;
}

/** Adakah semua 24 perlawanan kumpulan selesai dan 8 johan sah? */
function statusKelayakanUndian(array $kedudukan): array
{
    $johan = [];
    $belumSiap = [];
    $seri = [];
    foreach (KUMPULAN_SENARAI as $k) {
        $g = $kedudukan[$k];
        if (!$g['siap']) { $belumSiap[] = $k; continue; }
        if ($g['perlu_undian']) { $seri[] = $k; continue; }
        $johan[$k] = $g['johan_id'];
    }
    return [
        'boleh'           => count($johan) === 8,
        'johan'           => $johan,
        'kumpulan_belum'  => $belumSiap,
        'kumpulan_seri'   => $seri,
    ];
}

/** Baca hasil undian (senarai 8 team_id mengikut kedudukan carta) atau null. */
function bacaUndian(): ?array
{
    $row = db()->query('SELECT id, nama_pelaksana, hasil_json, created_at FROM draw ORDER BY id DESC LIMIT 1')->fetch();
    if (!$row) return null;
    $ids = json_decode((string)$row['hasil_json'], true);
    if (!is_array($ids) || count($ids) !== 8) return null;
    return [
        'id'             => (int)$row['id'],
        'nama_pelaksana' => $row['nama_pelaksana'],
        'created_at'     => $row['created_at'],
        'kedudukan'      => array_map('intval', array_values($ids)),
    ];
}

/**
 * Segarkan carta kalah mati.
 *
 * Mengira semula team_home_id / team_away_id bagi SEMUA perlawanan kalah mati
 * berdasarkan hasil undian + keputusan terkini. Jika sesuatu slot berubah
 * pasukan sedangkan skor sudah dimasukkan, skor tersebut dikosongkan semula
 * (dan direkod dalam log) supaya tiada keputusan "hantu".
 *
 * Selamat dipanggil berulang kali.
 */
function segarkanBracket(?array $admin = null): array
{
    $undian = bacaUndian();
    $perubahan = [];

    // Diproses mengikut urutan: SA -> SS -> T3/FINAL
    $kodTertib = ['SA1', 'SA2', 'SA3', 'SA4', 'SS1', 'SS2', 'T3', 'FINAL'];

    foreach ($kodTertib as $kod) {
        $semua = muatPerlawanan();            // baca segar setiap kali
        if (!isset($semua[$kod])) continue;
        $m = $semua[$kod];

        $home = selesaiSumber($m['home_sumber'], $undian, $semua);
        $away = selesaiSumber($m['away_sumber'], $undian, $semua);

        if ($home === $m['team_home_id'] && $away === $m['team_away_id']) {
            continue;                          // tiada perubahan
        }

        $adaSkor = $m['skor_home'] !== null || $m['skor_away'] !== null || $m['status'] !== 'scheduled';
        $resetSkor = $adaSkor && ($home !== $m['team_home_id'] || $away !== $m['team_away_id']);

        if ($resetSkor) {
            $st = db()->prepare(
                'UPDATE matches SET team_home_id=?, team_away_id=?, skor_home=NULL, skor_away=NULL,
                        penalti_home=NULL, penalti_away=NULL, status="scheduled",
                        updated_at=NOW(), version=version+1 WHERE id=?'
            );
            $st->execute([$home, $away, $m['id']]);
        } else {
            $st = db()->prepare(
                'UPDATE matches SET team_home_id=?, team_away_id=?, updated_at=NOW(), version=version+1 WHERE id=?'
            );
            $st->execute([$home, $away, $m['id']]);
        }

        $perubahan[] = ['kod' => $kod, 'home' => $home, 'away' => $away, 'skor_direset' => $resetSkor];
    }

    if ($perubahan && $admin !== null) {
        audit($admin, 'bracket_segar', ['perubahan' => $perubahan]);
    }
    return $perubahan;
}

/** Selesaikan rujukan 'UNDI:n' / 'W:KOD' / 'L:KOD' kepada team_id. */
function selesaiSumber(string $sumber, ?array $undian, array $perlawanan): ?int
{
    if ($sumber === '') return null;
    [$jenis, $nilai] = array_pad(explode(':', $sumber, 2), 2, '');

    if ($jenis === 'UNDI') {
        $n = (int)$nilai;
        if (!$undian || $n < 1 || $n > 8) return null;
        return $undian['kedudukan'][$n - 1] ?? null;
    }
    if (!isset($perlawanan[$nilai])) return null;
    $m = $perlawanan[$nilai];
    return $jenis === 'W' ? pemenangPerlawanan($m) : ($jenis === 'L' ? kalahPerlawanan($m) : null);
}

/**
 * Kedudukan akhir kejohanan (1-4) jika perlawanan berkenaan sudah tamat.
 */
function kedudukanAkhir(array $perlawanan): array
{
    $out = ['johan' => null, 'naib_johan' => null, 'ketiga' => null, 'keempat' => null];
    if (isset($perlawanan['FINAL'])) {
        $out['johan']      = pemenangPerlawanan($perlawanan['FINAL']);
        $out['naib_johan'] = kalahPerlawanan($perlawanan['FINAL']);
    }
    if (isset($perlawanan['T3'])) {
        $out['ketiga']  = pemenangPerlawanan($perlawanan['T3']);
        $out['keempat'] = kalahPerlawanan($perlawanan['T3']);
    }
    return $out;
}
