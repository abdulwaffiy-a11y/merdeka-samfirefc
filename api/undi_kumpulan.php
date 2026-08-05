<?php
/**
 * undi_kumpulan.php — CABUTAN UNDIAN KUMPULAN (admin sahaja).
 *
 * Masukkan senarai pasukan -> sistem undi secara rawak (CSPRNG, Fisher-Yates)
 * -> setiap pasukan ditentukan slotnya: A1, A2, A3, B1 ... H3.
 *
 *  GET   ?action=status  -> kolam pendaftaran lulus, slot kosong, boleh undi?
 *  POST   action=jalan   -> { senarai: [{nama, pendaftaran_id?}, ...] }
 *
 * Syarat:
 *  - Hanya sebelum kejohanan bermula (semua perlawanan kumpulan masih 'scheduled')
 *  - Mengisi slot KOSONG sahaja — pasukan yang sudah ada di slot tidak diusik
 *  - Nama tidak boleh berulang / berlanggar dengan pasukan sedia ada
 *  - Entri dari pendaftaran turut membawa pengurus, telefon, logo & pemain
 */

declare(strict_types=1);

require __DIR__ . '/lib/boot.php';
require __DIR__ . '/lib/kejohanan.php';

$action = (string)inp('action', 'status');

function kejohananBermula(): bool
{
    return (int)db()->query(
        "SELECT COUNT(*) FROM matches WHERE peringkat = 'grup' AND status <> 'scheduled'"
    )->fetchColumn() > 0;
}

function slotKosong(): array
{
    return db()->query("SELECT id, kumpulan, slot FROM teams WHERE nama = '' ORDER BY kumpulan, slot")->fetchAll();
}

/* ------------------------------------------------------------------ status */
if ($action === 'status') {
    wajibAdmin();

    $kolam = db()->query(
        "SELECT p.id, p.nama, p.logo FROM pendaftaran p
          WHERE p.status = 'lulus' AND p.team_id IS NULL ORDER BY p.id"
    )->fetchAll();

    ok([
        'boleh'        => !kejohananBermula(),
        'bermula'      => kejohananBermula(),
        'kolam'        => $kolam,                       // pendaftaran lulus, belum ada slot
        'slot_kosong'  => slotKosong(),
        'hasil_lepas'  => json_decode(tetapan('undian_kumpulan_json', '') ?: 'null', true),
    ]);
}

/* ------------------------------------------------------------------- jalan */
if ($action === 'jalan') {
    wajibPost();
    semakCsrf();
    $admin = wajibAdmin();
    tolakJikaDikunci();

    if (kejohananBermula()) {
        fail('Kejohanan sudah bermula — undian kumpulan tidak boleh dijalankan lagi.', 409);
    }

    $senarai = inp('senarai', []);
    if (!is_array($senarai)) $senarai = [];

    // Bersihkan entri
    $entri = [];
    $dilihat = [];
    foreach ($senarai as $e) {
        $nama = mb_substr(trim((string)($e['nama'] ?? '')), 0, 80);
        if ($nama === '') continue;
        $kunci = mb_strtolower($nama);
        if (isset($dilihat[$kunci])) fail('Nama berulang dalam senarai undian: "' . $nama . '".');
        $dilihat[$kunci] = true;
        $entri[] = [
            'nama' => $nama,
            'pendaftaran_id' => isset($e['pendaftaran_id']) ? (int)$e['pendaftaran_id'] : 0,
        ];
    }

    if (count($entri) < 2) fail('Masukkan sekurang-kurangnya 2 pasukan untuk diundi.');

    $kosong = slotKosong();
    if (count($entri) > count($kosong)) {
        fail('Senarai (' . count($entri) . ' pasukan) melebihi slot kosong (' . count($kosong) . ').');
    }

    // Nama tidak boleh berlanggar dengan pasukan sedia ada
    foreach (db()->query("SELECT nama FROM teams WHERE nama <> ''") as $r) {
        if (isset($dilihat[mb_strtolower($r['nama'])])) {
            fail('Pasukan "' . $r['nama'] . '" sudah ada dalam jadual kumpulan.');
        }
    }

    // ---- Undi: Fisher-Yates dengan random_int (CSPRNG) ------------------
    for ($i = count($entri) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$entri[$i], $entri[$j]] = [$entri[$j], $entri[$i]];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $hasil = [];
        $upd = $pdo->prepare('UPDATE teams SET nama = ?, pengurus = ?, telefon = ?, logo = ? WHERE id = ? AND nama = \'\'');
        $insP = $pdo->prepare('INSERT INTO players (team_id, nama, no_jersi) VALUES (?, ?, ?)');
        $updD = $pdo->prepare('UPDATE pendaftaran SET team_id = ? WHERE id = ?');
        $selD = $pdo->prepare("SELECT * FROM pendaftaran WHERE id = ? AND status = 'lulus'");

        foreach ($entri as $i => $e) {
            $slot = $kosong[$i];
            $pengurus = ''; $telefon = ''; $logo = ''; $pemain = [];

            if ($e['pendaftaran_id'] > 0) {
                $selD->execute([$e['pendaftaran_id']]);
                $d = $selD->fetch();
                if ($d) {
                    $pengurus = $d['pengurus']; $telefon = $d['telefon']; $logo = $d['logo'];
                    $pemain = json_decode((string)$d['pemain_json'], true) ?: [];
                }
            }

            $upd->execute([$e['nama'], $pengurus, $telefon, $logo, $slot['id']]);
            if ($upd->rowCount() === 0) {
                $pdo->rollBack();
                fail('Slot ' . $slot['kumpulan'] . $slot['slot'] . ' baru sahaja diisi oleh admin lain. Muat semula dan cuba lagi.', 409);
            }

            $pdo->prepare('DELETE FROM players WHERE team_id = ?')->execute([$slot['id']]);
            foreach ($pemain as $p) {
                if (empty($p['nama'])) continue;
                $insP->execute([$slot['id'], mb_substr((string)$p['nama'], 0, 80), mb_substr((string)($p['no_jersi'] ?? ''), 0, 4)]);
            }
            if ($e['pendaftaran_id'] > 0) $updD->execute([$slot['id'], $e['pendaftaran_id']]);

            $hasil[] = ['nama' => $e['nama'], 'slot' => $slot['kumpulan'] . $slot['slot'], 'kumpulan' => $slot['kumpulan'], 'team_id' => (int)$slot['id']];
        }

        $pdo->commit();
    } catch (Throwable $e2) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e2;
    }

    setTetapan('undian_kumpulan_json', json_encode([
        'oleh' => $admin['nama'],
        'pada' => date('Y-m-d H:i:s'),
        'hasil' => $hasil,
    ], JSON_UNESCAPED_UNICODE));

    audit($admin, 'undian_kumpulan', ['bilangan' => count($hasil), 'hasil' => $hasil]);

    ok(['hasil' => $hasil]);
}

fail('Tindakan tidak dikenali.', 404);
