<?php
/**
 * teams.php — urus 24 pasukan & senarai pemain (admin sahaja).
 *
 *  GET   ?action=senarai
 *  POST   action=simpan        { pasukan: [{id, nama, singkatan, pengurus, telefon, tiebreak}] }
 *  POST   action=pemain_simpan { team_id, pemain: [{nama, no_jersi, no_kp}] }
 */

declare(strict_types=1);

require __DIR__ . '/lib/boot.php';
require __DIR__ . '/lib/kejohanan.php';

$action = (string)inp('action', 'senarai');

switch ($action) {

    // -----------------------------------------------------------------
    case 'senarai':
        wajibAdmin();
        $pasukan = array_values(muatPasukan());
        $pemain  = [];
        foreach (db()->query('SELECT id, team_id, nama, no_jersi, no_kp FROM players ORDER BY team_id, id') as $p) {
            $pemain[(int)$p['team_id']][] = [
                'id'       => (int)$p['id'],
                'nama'     => $p['nama'],
                'no_jersi' => $p['no_jersi'],
                'no_kp'    => $p['no_kp'],
            ];
        }
        ok(['pasukan' => $pasukan, 'pemain' => $pemain]);

    // -----------------------------------------------------------------
    case 'simpan':
        wajibPost();
        semakCsrf();
        $admin = wajibAdmin();
        tolakJikaDikunci();

        $senarai = inp('pasukan', []);
        if (!is_array($senarai) || !$senarai) {
            fail('Tiada data pasukan dihantar.');
        }
        if (count($senarai) > 24) {
            fail('Maksimum 24 pasukan.');
        }

        // Semak nama tidak berulang (antara yang dihantar + yang sedia ada)
        $sediaAda = muatPasukan();
        $namaAkhir = [];
        foreach ($sediaAda as $t) {
            $namaAkhir[$t['id']] = $t['nama'];
        }
        foreach ($senarai as $row) {
            $id = (int)($row['id'] ?? 0);
            if (!isset($sediaAda[$id])) {
                fail('Slot pasukan tidak sah (id ' . $id . ').');
            }
            $namaAkhir[$id] = trim((string)($row['nama'] ?? ''));
        }
        $dilihat = [];
        foreach ($namaAkhir as $n) {
            if ($n === '') continue;
            $kunci = mb_strtolower($n);
            if (isset($dilihat[$kunci])) {
                fail('Nama pasukan berulang: "' . $n . '". Setiap pasukan mesti ada nama berbeza.');
            }
            $dilihat[$kunci] = true;
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare(
                'UPDATE teams SET nama = ?, singkatan = ?, pengurus = ?, telefon = ?, tiebreak = ? WHERE id = ?'
            );
            $ubah = [];
            foreach ($senarai as $row) {
                $id   = (int)($row['id'] ?? 0);
                $nama = mb_substr(trim((string)($row['nama'] ?? '')), 0, 80);
                $sgk  = mb_substr(trim((string)($row['singkatan'] ?? '')), 0, 12);
                $png  = mb_substr(trim((string)($row['pengurus'] ?? '')), 0, 80);
                $tel  = mb_substr(trim((string)($row['telefon'] ?? '')), 0, 30);
                $tb   = max(0, min(99, (int)($row['tiebreak'] ?? 0)));
                $st->execute([$nama, $sgk, $png, $tel, $tb, $id]);
                if ($sediaAda[$id]['nama'] !== $nama) {
                    $ubah[] = ['id' => $id, 'dari' => $sediaAda[$id]['nama'], 'ke' => $nama];
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        audit($admin, 'pasukan_simpan', ['bilangan' => count($senarai), 'ubah_nama' => $ubah]);
        ok(['pasukan' => array_values(muatPasukan())]);

    // -----------------------------------------------------------------
    case 'pemain_simpan':
        wajibPost();
        semakCsrf();
        $admin = wajibAdmin();
        tolakJikaDikunci();

        $teamId = (int)inp('team_id', 0);
        $pemain = inp('pemain', []);
        if (!is_array($pemain)) $pemain = [];
        if (count($pemain) > 20) {
            fail('Maksimum 20 pemain setiap pasukan.');
        }

        $st = db()->prepare('SELECT id, nama FROM teams WHERE id = ?');
        $st->execute([$teamId]);
        $t = $st->fetch();
        if (!$t) fail('Pasukan tidak dijumpai.', 404);

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM players WHERE team_id = ?')->execute([$teamId]);
            $ins = $pdo->prepare('INSERT INTO players (team_id, nama, no_jersi, no_kp) VALUES (?, ?, ?, ?)');
            $bil = 0;
            foreach ($pemain as $p) {
                $nama = mb_substr(trim((string)($p['nama'] ?? '')), 0, 80);
                if ($nama === '') continue;
                $ins->execute([
                    $teamId,
                    $nama,
                    mb_substr(trim((string)($p['no_jersi'] ?? '')), 0, 4),
                    mb_substr(trim((string)($p['no_kp'] ?? '')), 0, 20),
                ]);
                $bil++;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        audit($admin, 'pemain_simpan', ['team_id' => $teamId, 'bilangan' => $bil]);
        ok(['bilangan' => $bil]);

    // -----------------------------------------------------------------
    default:
        fail('Tindakan tidak dikenali.', 404);
}
