<?php
/**
 * deploy.php — tarik kod terkini dari GitHub melalui HTTPS.
 *
 * Firewall hos menutup port FTP/SSH dari luar, jadi kaedah biasa
 * (GitHub Actions -> FTP) tidak boleh digunakan. Sebaliknya server
 * sendiri yang memuat turun kod dari GitHub — sambungan KELUAR,
 * sentiasa dibenarkan.
 *
 *  GET ?action=kunci   -> papar kunci deploy (perlu log masuk Super Admin)
 *  GET ?kunci=XXXXXX   -> jalankan deploy
 *
 * Fail yang TIDAK PERNAH disentuh:
 *   api/config.php   (tetapan database)
 *   api/uploads/     (logo pasukan)
 *   api/pasang.php   (pemasang)
 *   api/deploy.php   (fail ini sendiri)
 *
 * Database tidak pernah disentuh oleh deploy.
 */

declare(strict_types=1);

require __DIR__ . '/lib/boot.php';

const GH_OWNER  = 'abdulwaffiy-a11y';
const GH_REPO   = 'merdeka-samfirefc';
const GH_BRANCH = 'main';

/** Fail & folder yang disalin ke docroot (relatif kepada akar repo). */
const SALIN_FAIL = [
    'index.html',
    'admin.html',
    '.htaccess',
    'favicon.ico',
    'apple-touch-icon.png',
    'logo-samfire.png',
    'api/admins.php',
    'api/auth.php',
    'api/daftar.php',
    'api/draw.php',
    'api/matches.php',
    'api/public.php',
    'api/teams.php',
    'api/undi_kumpulan.php',
    'api/lib/boot.php',
    'api/lib/kejohanan.php',
    'api/lib/.htaccess',
    'sql/schema.sql',
];

/** Folder yang diganti sepenuhnya (fail lama dibuang dahulu). */
const SALIN_FOLDER = ['assets'];

// ---------------------------------------------------------------------
function kunciDeploy(): string
{
    global $CFG;
    return substr(hash_hmac('sha256', 'deploy-merdeka-2026', (string)($CFG['app_key'] ?? '')), 0, 40);
}

function docroot(): string
{
    return dirname(__DIR__);
}

function muatTurun(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_USERAGENT      => 'merdeka-deploy/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $data = curl_exec($ch);
        $kod  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($data !== false && $kod === 200) return (string)$data;
        error_log("deploy: curl gagal ($kod) $err");
        return null;
    }
    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(['http' => ['timeout' => 90, 'user_agent' => 'merdeka-deploy/1.0']]);
        $data = @file_get_contents($url, false, $ctx);
        return $data === false ? null : $data;
    }
    return null;
}

function buangFolder(string $dir): void
{
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($dir);
}

function salinFolder(string $dari, string $ke, array &$senarai): void
{
    @mkdir($ke, 0755, true);
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dari, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        if ($f->isDir()) continue;
        $rel = substr($f->getPathname(), strlen($dari) + 1);
        $sasaran = $ke . '/' . $rel;
        @mkdir(dirname($sasaran), 0755, true);
        if (@copy($f->getPathname(), $sasaran)) $senarai[] = basename($ke) . '/' . $rel;
    }
}

// ---------------------------------------------------------------------
$action = (string)inp('action', '');

if ($action === 'kunci') {
    $a = wajibSuper();
    ok([
        'kunci' => kunciDeploy(),
        'url'   => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https' : 'http') . '://'
                 . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/')
                 . '/deploy.php?kunci=' . kunciDeploy(),
        'nota'  => 'Simpan kunci ini sebagai GitHub secret DEPLOY_KEY. Jangan kongsi kepada sesiapa.',
    ]);
}

// ---- Jalankan deploy -------------------------------------------------
$kunci = (string)inp('kunci', '');
if ($kunci === '' || !hash_equals(kunciDeploy(), $kunci)) {
    audit(null, 'deploy_kunci_salah', []);
    fail('Kunci deploy tidak sah.', 403);
}

// Had kekerapan: 1 deploy setiap 20 saat
$penanda = sys_get_temp_dir() . '/merdeka_deploy_' . md5(docroot());
if (file_exists($penanda) && (time() - (int)filemtime($penanda)) < 20) {
    fail('Deploy baru sahaja dijalankan. Sila tunggu sebentar.', 429);
}
@touch($penanda);

$url = sprintf('https://codeload.github.com/%s/%s/zip/refs/heads/%s', GH_OWNER, GH_REPO, GH_BRANCH);
$zipData = muatTurun($url);
if ($zipData === null) {
    fail('Tidak dapat memuat turun kod dari GitHub. Semak sambungan keluar server atau nama repo.', 502);
}

if (!class_exists('ZipArchive')) {
    fail('Sambungan PHP "zip" tidak aktif di server. Aktifkan di cPanel > Select PHP Version > Extensions.', 500);
}

$tmpZip = tempnam(sys_get_temp_dir(), 'mdk') ?: null;
if (!$tmpZip || file_put_contents($tmpZip, $zipData) === false) {
    fail('Tidak dapat menulis fail sementara.', 500);
}
unset($zipData);

$tmpDir = $tmpZip . '_x';
@mkdir($tmpDir, 0755, true);

$zip = new ZipArchive();
if ($zip->open($tmpZip) !== true) {
    @unlink($tmpZip);
    fail('Fail zip dari GitHub rosak.', 502);
}
$zip->extractTo($tmpDir);
$zip->close();
@unlink($tmpZip);

// GitHub bungkus dalam folder "repo-branch/"
$akar = glob($tmpDir . '/*', GLOB_ONLYDIR);
$akar = $akar ? $akar[0] : null;
if (!$akar) {
    buangFolder($tmpDir);
    fail('Struktur zip tidak dijangka.', 502);
}

// ---- Salin -----------------------------------------------------------
$disalin = [];
$gagal   = [];

foreach (SALIN_FAIL as $rel) {
    $sumber  = $akar . '/' . $rel;
    $sasaran = docroot() . '/' . $rel;
    if (!is_file($sumber)) { $gagal[] = $rel . ' (tiada dalam repo)'; continue; }
    @mkdir(dirname($sasaran), 0755, true);
    if (@copy($sumber, $sasaran)) $disalin[] = $rel;
    else $gagal[] = $rel . ' (tidak dapat ditulis)';
}

foreach (SALIN_FOLDER as $folder) {
    $sumber = $akar . '/' . $folder;
    if (!is_dir($sumber)) { $gagal[] = $folder . '/ (tiada dalam repo)'; continue; }
    buangFolder(docroot() . '/' . $folder);
    salinFolder($sumber, docroot() . '/' . $folder, $disalin);
}

buangFolder($tmpDir);

// Baca commit terkini (maklumat sahaja)
$commit = '';
$info = muatTurun(sprintf('https://api.github.com/repos/%s/%s/commits/%s', GH_OWNER, GH_REPO, GH_BRANCH));
if ($info) {
    $j = json_decode($info, true);
    if (isset($j['sha'])) {
        $commit = substr((string)$j['sha'], 0, 7) . ' — ' . (string)($j['commit']['message'] ?? '');
    }
}

audit(null, 'deploy', ['fail' => count($disalin), 'gagal' => $gagal, 'commit' => $commit]);

ok([
    'mesej'   => count($disalin) . ' fail dikemas kini dari GitHub.',
    'commit'  => $commit,
    'disalin' => $disalin,
    'gagal'   => $gagal,
    'nota'    => 'config.php, uploads/, pasang.php dan database tidak disentuh.',
]);
