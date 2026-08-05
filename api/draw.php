<?php
/**
 * draw.php — undian suku akhir.
 *
 *  GET   ?action=status   -> layak/tidak, sebab, hasil sedia ada
 *  POST   action=jalan    -> jalankan undian (sekali sahaja)
 *  POST   action=reset    -> Super Admin sahaja + pengesahan
 *
 * Kerawakan menggunakan random_int() (CSPRNG sistem pengendalian),
 * bukan rand()/mt_rand(). Algoritma: Fisher-Yates tanpa bias.
 */

declare(strict_types=1);

require __DIR__ . '/lib/boot.php';
require __DIR__ . '/lib/kejohanan.php';

$action = (string)inp('action', 'status');

// ---------------------------------------------------------------------
if ($action === 'status') {
    $pasukan   = muatPasukan();
    $ked       = kiraKedudukan($pasukan, muatPerlawanan());
    $kelayakan = statusKelayakanUndian($ked);
    $undian    = bacaUndian();

    $sebab = [];
    if ($kelayakan['kumpulan_belum']) {
        $sebab[] = 'Kumpulan belum selesai: ' . implode(', ', $kelayakan['kumpulan_belum']);
    }
    if ($kelayakan['kumpulan_seri']) {
        $sebab[] = 'Kumpulan masih seri di tempat pertama (perlu cabutan undi kumpulan): '
                 . implode(', ', $kelayakan['kumpulan_seri']);
    }

    ok([
        'layak'   => $kelayakan['boleh'] && !$undian,
        'sudah'   => (bool)$undian,
        'sebab'   => $sebab,
        'johan'   => $kelayakan['johan'],
        'undian'  => $undian,
    ]);
}

// ---------------------------------------------------------------------
if ($action === 'jalan') {
    wajibPost();
    semakCsrf();
    $admin = wajibAdmin();
    tolakJikaDikunci();

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Kunci baris undian supaya dua admin tidak boleh undi serentak
        $pdo->query('SELECT id FROM draw FOR UPDATE');

        $adaLagi = (int)$pdo->query('SELECT COUNT(*) FROM draw')->fetchColumn();
        if ($adaLagi > 0) {
            $pdo->rollBack();
            fail('Undian suku akhir sudah dijalankan. Hanya Super Admin boleh reset.', 409);
        }

        $ked       = kiraKedudukan(muatPasukan(), muatPerlawanan());
        $kelayakan = statusKelayakanUndian($ked);
        if (!$kelayakan['boleh']) {
            $pdo->rollBack();
            $msg = 'Undian belum boleh dijalankan. ';
            if ($kelayakan['kumpulan_belum']) {
                $msg .= 'Kumpulan belum selesai: ' . implode(', ', $kelayakan['kumpulan_belum']) . '. ';
            }
            if ($kelayakan['kumpulan_seri']) {
                $msg .= 'Kumpulan seri di tempat pertama: ' . implode(', ', $kelayakan['kumpulan_seri'])
                      . ' — sila tetapkan susunan melalui cabutan undi kumpulan.';
            }
            fail(trim($msg), 409);
        }

        // ---- Fisher-Yates dengan random_int (CSPRNG) --------------------
        $johan = array_values($kelayakan['johan']);       // 8 team_id
        for ($i = count($johan) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$johan[$i], $johan[$j]] = [$johan[$j], $johan[$i]];
        }

        // Semakan integriti: mesti 8, unik, semua wujud
        if (count($johan) !== 8 || count(array_unique($johan)) !== 8) {
            $pdo->rollBack();
            fail('Ralat integriti undian. Undian dibatalkan — tiada perubahan dibuat.', 500);
        }

        $bukti = substr(hash('sha256', implode('-', $johan) . '|' . microtime(true)), 0, 32);

        $st = $pdo->prepare(
            'INSERT INTO draw (dijalankan_oleh, nama_pelaksana, hasil_json, seed_bukti) VALUES (?, ?, ?, ?)'
        );
        $st->execute([$admin['id'], $admin['nama'], json_encode($johan), $bukti]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    segarkanBracket($admin);
    audit($admin, 'undian_jalan', ['hasil' => $johan]);

    $pasukan = muatPasukan();
    $namaUrut = array_map(function ($id) use ($pasukan) { return isset($pasukan[$id]) ? $pasukan[$id]['nama_papar'] : ('#' . $id); }, $johan);

    ok([
        'undian'     => bacaUndian(),
        'nama'       => $namaUrut,
        'perlawanan' => array_values(muatPerlawanan()),
    ]);
}

// ---------------------------------------------------------------------
if ($action === 'reset') {
    wajibPost();
    semakCsrf();
    $admin = wajibSuper();
    tolakJikaDikunci();

    // Pengesahan dua peringkat
    if ((string)inp('sahkan', '') !== 'RESET UNDIAN') {
        fail('Pengesahan tidak lengkap. Taip tepat: RESET UNDIAN', 428);
    }

    $lama = bacaUndian();
    if (!$lama) fail('Tiada undian untuk direset.', 404);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM draw');
        // Kosongkan semua keputusan peringkat kalah mati
        $pdo->exec(
            'UPDATE matches
                SET team_home_id = NULL, team_away_id = NULL,
                    skor_home = NULL, skor_away = NULL,
                    penalti_home = NULL, penalti_away = NULL,
                    status = "scheduled", updated_at = NOW(), version = version + 1
              WHERE peringkat IN ("sa","ss","third","final")'
        );
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    segarkanBracket($admin);
    audit($admin, 'undian_reset', ['undian_lama' => $lama['kedudukan']]);

    ok(['perlawanan' => array_values(muatPerlawanan())]);
}

fail('Tindakan tidak dikenali.', 404);
