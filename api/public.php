<?php
/**
 * public.php — data untuk paparan awam (tiada log masuk diperlukan).
 * Dipanggil setiap 10 saat oleh frontend. Ringan (~10-20KB) + sokongan ETag
 * supaya panggilan berulang hanya pulangkan 304 (beberapa bait sahaja).
 */

declare(strict_types=1);

require __DIR__ . '/lib/boot.php';
require __DIR__ . '/lib/kejohanan.php';

$pasukan    = muatPasukan();
$perlawanan = muatPerlawanan();
$kedudukan  = kiraKedudukan($pasukan, $perlawanan);
$undian     = bacaUndian();
$tetapan    = tetapanSemua();
$akhir      = kedudukanAkhir($perlawanan);

// ---- Ringkasan status -------------------------------------------------
$jumlah = count($perlawanan);
$tamat  = 0;
$live   = [];
foreach ($perlawanan as $m) {
    if ($m['status'] === 'done') $tamat++;
    if ($m['status'] === 'live') $live[] = $m['kod'];
}

// ---- Pasukan (ringkas) -------------------------------------------------
$pasukanOut = [];
foreach ($pasukan as $t) {
    $pasukanOut[] = [
        'id'        => $t['id'],
        'nama'      => $t['nama_papar'],
        'diisi'     => $t['nama'] !== '',
        'singkatan' => $t['singkatan'],
        'logo'      => $t['logo'] !== '' ? 'api/uploads/' . $t['logo'] : '',
        'kumpulan'  => $t['kumpulan'],
        'slot'      => $t['slot'],
    ];
}

// ---- Pemain (dikumpul ikut pasukan) ------------------------------------
$pemain = [];
foreach (db()->query('SELECT team_id, nama, no_jersi FROM players ORDER BY team_id, id') as $p) {
    $pemain[(int)$p['team_id']][] = ['nama' => $p['nama'], 'no_jersi' => $p['no_jersi']];
}

// ---- Perlawanan --------------------------------------------------------
$perlawananOut = [];
foreach ($perlawanan as $m) {
    $perlawananOut[] = [
        'kod'          => $m['kod'],
        'peringkat'    => $m['peringkat'],
        'kumpulan'     => $m['kumpulan'],
        'urutan'       => $m['urutan'],
        'gelanggang'   => $m['gelanggang'],
        'masa'         => substr((string)$m['masa_jadual'], 0, 5),
        'tempoh'       => $m['tempoh_minit'],
        'home_id'      => $m['team_home_id'],
        'away_id'      => $m['team_away_id'],
        'home_sumber'  => $m['home_sumber'],
        'away_sumber'  => $m['away_sumber'],
        'skor_home'    => $m['skor_home'],
        'skor_away'    => $m['skor_away'],
        'penalti_home' => $m['penalti_home'],
        'penalti_away' => $m['penalti_away'],
        'status'       => $m['status'],
        'catatan'      => $m['catatan'],
    ];
}

$data = [
    'ok'         => true,
    'masa'       => date('c'),
    'tetapan'    => [
        'nama_kejohanan'   => $tetapan['nama_kejohanan']   ?? '',
        'nama_penganjur'   => $tetapan['nama_penganjur']   ?? '',
        'tarikh_kejohanan' => $tetapan['tarikh_kejohanan'] ?? '',
        'masa_mula'        => $tetapan['masa_mula']        ?? '08:30',
        'lokasi'           => $tetapan['lokasi']           ?? '',
        'pengumuman'       => $tetapan['pengumuman']       ?? '',
        'dikunci'          => ($tetapan['keputusan_dikunci'] ?? '0') === '1',
        'pendaftaran_buka' => ($tetapan['pendaftaran_buka'] ?? '0') === '1',
        'yuran'            => $tetapan['yuran']            ?? 'RM200',
        'telefon_urusetia' => $tetapan['telefon_urusetia'] ?? '',
        'url_website'      => $tetapan['url_website']      ?? 'https://samfirefc.com',
        'url_daftar_ahli'  => $tetapan['url_daftar_ahli']  ?? 'https://samfirefc.com',
        'poster'           => !empty($tetapan['poster']) ? 'api/uploads/' . $tetapan['poster'] : '',
    ],
    'ringkasan'  => [
        'jumlah_perlawanan' => $jumlah,
        'tamat'             => $tamat,
        'live'              => $live,
    ],
    'pasukan'    => $pasukanOut,
    'pemain'     => $pemain,
    'perlawanan' => $perlawananOut,
    'kedudukan'  => $kedudukan,
    'undian'     => $undian ? [
        'ada'            => true,
        'nama_pelaksana' => $undian['nama_pelaksana'],
        'created_at'     => $undian['created_at'],
        'kedudukan'      => $undian['kedudukan'],
    ] : ['ada' => false],
    'kedudukan_akhir' => $akhir,
];

// ---- ETag: elak hantar data sama berulang kali ------------------------
$badan = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$kunci = $data;
unset($kunci['masa']);                       // masa berubah setiap saat — jangan kira
$etag  = '"' . md5(json_encode($kunci)) . '"';

header('ETag: ' . $etag);
if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}

http_response_code(200);
echo $badan;
