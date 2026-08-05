<?php
/**
 * matches.php — kemaskini skor & status perlawanan (admin sahaja).
 *
 *  GET   ?action=senarai
 *  POST   action=simpan  { id, version, skor_home, skor_away,
 *                          penalti_home?, penalti_away?, status, catatan?, paksa? }
 *
 * PERLINDUNGAN UTAMA
 *  - Optimistic locking: UPDATE ... WHERE id=? AND version=?  -> dua admin
 *    yang simpan serentak, hanya yang pertama berjaya; yang kedua diberitahu.
 *  - Selepas setiap simpanan, carta kalah mati dikira semula (segarkanBracket).
 *  - Jika undian sudah dijalankan, skor peringkat kumpulan hanya boleh diubah
 *    oleh Super Admin dan hanya jika ia TIDAK menukar mana-mana johan kumpulan.
 */

declare(strict_types=1);

require __DIR__ . '/lib/boot.php';
require __DIR__ . '/lib/kejohanan.php';

$action = (string)inp('action', 'senarai');

if ($action === 'senarai') {
    wajibAdmin();
    ok(['perlawanan' => array_values(muatPerlawanan())]);
}

if ($action !== 'simpan') {
    fail('Tindakan tidak dikenali.', 404);
}

// ---------------------------------------------------------------------
wajibPost();
semakCsrf();
$admin = wajibAdmin();
tolakJikaDikunci();

$id      = (int)inp('id', 0);
$version = (int)inp('version', -1);
$status  = (string)inp('status', 'scheduled');
$catatan = mb_substr(trim((string)inp('catatan', '')), 0, 200);
$paksa   = (bool)inp('paksa', false);

if (!in_array($status, ['scheduled', 'live', 'done'], true)) {
    fail('Status perlawanan tidak sah.');
}

$semua = muatPerlawanan();
$m = null;
foreach ($semua as $x) {
    if ($x['id'] === $id) { $m = $x; break; }
}
if (!$m) fail('Perlawanan tidak dijumpai.', 404);

if ($version !== $m['version']) {
    fail(
        'Perlawanan ini telah dikemaskini oleh admin lain sejak anda membukanya. Paparan telah disegarkan — sila semak semula sebelum simpan.',
        409,
        ['konflik' => true, 'perlawanan' => $m]
    );
}

// ---- skor -------------------------------------------------------------
$sh = inp('skor_home', null);
$sa = inp('skor_away', null);
$ph = inp('penalti_home', null);
$pa = inp('penalti_away', null);

$nomborAtauNull = function ($v, string $label) {
    if ($v === null || $v === '') return null;
    if (!is_numeric($v)) fail("$label mesti nombor.");
    $n = (int)$v;
    if ($n < 0 || $n > 99) fail("$label mesti antara 0 dan 99.");
    return $n;
};
$sh = $nomborAtauNull($sh, 'Skor tuan rumah');
$sa = $nomborAtauNull($sa, 'Skor lawan');
$ph = $nomborAtauNull($ph, 'Penalti tuan rumah');
$pa = $nomborAtauNull($pa, 'Penalti lawan');

if ($status === 'done') {
    if ($m['team_home_id'] === null || $m['team_away_id'] === null) {
        fail('Pasukan untuk perlawanan ini belum ditentukan. Tidak boleh ditandakan TAMAT.');
    }
    if ($sh === null || $sa === null) {
        fail('Sila isi kedua-dua skor sebelum tandakan perlawanan TAMAT.');
    }
    if ($m['peringkat'] !== 'grup' && $sh === $sa) {
        if ($ph === null || $pa === null) {
            fail('Perlawanan kalah mati tidak boleh seri. Sila isi keputusan sepakan penalti.');
        }
        if ($ph === $pa) {
            fail('Keputusan sepakan penalti tidak boleh sama. Sila isi pemenang penalti.');
        }
    }
    if ($m['peringkat'] === 'grup') {
        $ph = null; $pa = null;              // penalti tidak berkenaan di peringkat kumpulan
    }
    if ($sh !== $sa) {
        $ph = null; $pa = null;              // penalti hanya bila seri
    }
} else {
    // Belum tamat: kekalkan skor separa (untuk paparan LIVE) tetapi buang penalti
    $ph = null; $pa = null;
}

// ---- perlindungan: undian sudah dijalankan ---------------------------
$undian = bacaUndian();
if ($undian && $m['peringkat'] === 'grup') {
    if ($admin['role'] !== 'super') {
        fail('Undian suku akhir telah dijalankan. Hanya Super Admin boleh membetulkan skor peringkat kumpulan.', 403);
    }
    if (!$paksa) {
        fail(
            'Undian suku akhir telah dijalankan. Mengubah skor kumpulan boleh menjejaskan carta. Sahkan sekali lagi untuk teruskan.',
            428,
            ['perlu_pengesahan' => true]
        );
    }
}

// ---- simpan (dengan optimistic locking) -------------------------------
$pdo = db();
$pdo->beginTransaction();
try {
    $st = $pdo->prepare(
        'UPDATE matches
            SET skor_home = ?, skor_away = ?, penalti_home = ?, penalti_away = ?,
                status = ?, catatan = ?, updated_by = ?, updated_at = NOW(), version = version + 1
          WHERE id = ? AND version = ?'
    );
    $st->execute([$sh, $sa, $ph, $pa, $status, $catatan, $admin['id'], $id, $version]);

    if ($st->rowCount() === 0) {
        $pdo->rollBack();
        $segar = muatPerlawanan();
        $baru = null;
        foreach ($segar as $x) if ($x['id'] === $id) $baru = $x;
        fail(
            'Skor telah dikemaskini oleh admin lain sebentar tadi. Paparan disegarkan — sila semak semula.',
            409,
            ['konflik' => true, 'perlawanan' => $baru]
        );
    }

    // Jika ini perlawanan kumpulan & undian sudah ada — pastikan johan tidak berubah
    if ($undian && $m['peringkat'] === 'grup') {
        $ked = kiraKedudukan(muatPasukan(), muatPerlawanan());
        $kelayakan = statusKelayakanUndian($ked);
        $johanBaru = array_values($kelayakan['johan']);
        sort($johanBaru);
        $johanUndi = $undian['kedudukan'];
        sort($johanUndi);
        if (!$kelayakan['boleh'] || $johanBaru !== $johanUndi) {
            $pdo->rollBack();
            fail(
                'Perubahan ini menukar johan kumpulan, sedangkan undian suku akhir sudah dijalankan. '
                . 'Sila RESET undian dahulu (Super Admin), betulkan skor, kemudian jalankan undian semula.',
                409
            );
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

// ---- kemas kini carta kalah mati -------------------------------------
$perubahan = segarkanBracket($admin);

audit($admin, 'skor_simpan', [
    'kod'    => $m['kod'],
    'dari'   => ['skor_home' => $m['skor_home'], 'skor_away' => $m['skor_away'], 'status' => $m['status']],
    'ke'     => ['skor_home' => $sh, 'skor_away' => $sa, 'status' => $status],
    'penalti' => ($ph !== null ? "$ph-$pa" : null),
]);

$segar = muatPerlawanan();
$baru = null;
foreach ($segar as $x) if ($x['id'] === $id) $baru = $x;

ok(['perlawanan' => $baru, 'bracket_berubah' => $perubahan]);
