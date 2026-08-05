<?php
/**
 * boot.php — dimuatkan oleh setiap endpoint.
 * Menyediakan: $CFG, $db (PDO), fungsi respons JSON, sesi, CSRF, audit.
 */

declare(strict_types=1);

// -------------------------------------------------------------------
// Jangan papar ralat mentah kepada pengguna — log sahaja.
// -------------------------------------------------------------------
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// config.local.php (jika ada) mengatasi config.php — digunakan semasa pembangunan
// sahaja. Fail tersebut TIDAK disertakan dalam pakej deployment.
$CFG = file_exists(__DIR__ . '/../config.local.php')
    ? require __DIR__ . '/../config.local.php'
    : require __DIR__ . '/../config.php';

date_default_timezone_set($CFG['timezone'] ?? 'Asia/Kuala_Lumpur');

// -------------------------------------------------------------------
// Header respons
// -------------------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!empty($CFG['dev_origin']) && ($_SERVER['HTTP_ORIGIN'] ?? '') === $CFG['dev_origin']) {
    header('Access-Control-Allow-Origin: ' . $CFG['dev_origin']);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// -------------------------------------------------------------------
// Respons JSON
// -------------------------------------------------------------------
function jsonOut(array $data, int $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ok(array $data = [])
{
    jsonOut(['ok' => true] + $data);
}

function fail(string $mesej, int $code = 400, array $extra = [])
{
    jsonOut(['ok' => false, 'mesej' => $mesej] + $extra, $code);
}

// -------------------------------------------------------------------
// Database
// -------------------------------------------------------------------
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    global $CFG;
    $d = $CFG['db'];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $d['host'], (int)$d['port'], $d['name']);
    try {
        $pdo = new PDO($dsn, $d['user'], $d['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    } catch (Throwable $e) {
        error_log('DB connect failed: ' . $e->getMessage());
        fail('Sistem tidak dapat sambung ke pangkalan data. Sila semak api/config.php.', 500);
    }
    return $pdo;
}

// -------------------------------------------------------------------
// Sesi admin
// -------------------------------------------------------------------
function startSesi(): void
{
    global $CFG;
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('MERDEKASESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (bool)($CFG['https_only'] ?? true) && !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $idle = (int)($CFG['session_idle_minit'] ?? 240) * 60;
    if (isset($_SESSION['aktif_pada']) && (time() - (int)$_SESSION['aktif_pada']) > $idle) {
        $_SESSION = [];
        session_destroy();
        session_start();
    }
    $_SESSION['aktif_pada'] = time();
}

function adminSemasa(): ?array
{
    startSesi();
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    return [
        'id'    => (int)$_SESSION['admin_id'],
        'nama'  => (string)($_SESSION['admin_nama'] ?? ''),
        'email' => (string)($_SESSION['admin_email'] ?? ''),
        'role'  => (string)($_SESSION['admin_role'] ?? 'admin'),
    ];
}

/** Wajib log masuk. Kembalikan maklumat admin. */
function wajibAdmin(): array
{
    $a = adminSemasa();
    if (!$a) {
        fail('Sesi tamat atau belum log masuk. Sila log masuk semula.', 401);
    }
    // Sahkan akaun masih aktif dalam DB (elak akaun dibuang tapi sesi hidup)
    $st = db()->prepare('SELECT aktif, role, nama FROM admins WHERE id = ?');
    $st->execute([$a['id']]);
    $row = $st->fetch();
    if (!$row || (int)$row['aktif'] !== 1) {
        startSesi();
        $_SESSION = [];
        session_destroy();
        fail('Akaun anda tidak lagi aktif.', 401);
    }
    $a['role'] = $row['role'];
    $a['nama'] = $row['nama'];
    return $a;
}

function wajibSuper(): array
{
    $a = wajibAdmin();
    if ($a['role'] !== 'super') {
        fail('Tindakan ini hanya untuk Super Admin.', 403);
    }
    return $a;
}

// -------------------------------------------------------------------
// CSRF
// -------------------------------------------------------------------
function csrfToken(): string
{
    startSesi();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function semakCsrf(): void
{
    startSesi();
    $hantar = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
    if (empty($_SESSION['csrf']) || !is_string($hantar) || !hash_equals($_SESSION['csrf'], $hantar)) {
        fail('Token keselamatan tidak sah. Sila muat semula halaman.', 419);
    }
}

// -------------------------------------------------------------------
// Input
// -------------------------------------------------------------------
function bodyJson(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $raw = file_get_contents('php://input') ?: '';
    $d = json_decode($raw, true);
    $cache = is_array($d) ? $d : [];
    return $cache;
}

function inp(string $key, $default = null)
{
    $b = bodyJson();
    if (array_key_exists($key, $b)) {
        return $b[$key];
    }
    if (array_key_exists($key, $_POST)) {
        return $_POST[$key];
    }
    if (array_key_exists($key, $_GET)) {
        return $_GET[$key];
    }
    return $default;
}

function wajibPost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        fail('Kaedah tidak dibenarkan.', 405);
    }
}

function ipKlien(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

// -------------------------------------------------------------------
// Audit log
// -------------------------------------------------------------------
function audit(?array $admin, string $tindakan, array $butiran = []): void
{
    try {
        $st = db()->prepare(
            'INSERT INTO audit_log (admin_id, admin_nama, tindakan, butiran_json, ip)
             VALUES (?, ?, ?, ?, ?)'
        );
        $st->execute([
            $admin['id'] ?? null,
            $admin['nama'] ?? '-',
            $tindakan,
            json_encode($butiran, JSON_UNESCAPED_UNICODE),
            ipKlien(),
        ]);
    } catch (Throwable $e) {
        error_log('audit gagal: ' . $e->getMessage());
    }
}

// -------------------------------------------------------------------
// Tetapan
// -------------------------------------------------------------------
function tetapanSemua(): array
{
    $out = [];
    foreach (db()->query('SELECT k, v FROM settings') as $r) {
        $out[$r['k']] = $r['v'];
    }
    return $out;
}

function tetapan(string $k, ?string $default = null): ?string
{
    $st = db()->prepare('SELECT v FROM settings WHERE k = ?');
    $st->execute([$k]);
    $v = $st->fetchColumn();
    return $v === false ? $default : (string)$v;
}

function setTetapan(string $k, string $v): void
{
    $st = db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
    $st->execute([$k, $v]);
}

function keputusanDikunci(): bool
{
    return tetapan('keputusan_dikunci', '0') === '1';
}

function tolakJikaDikunci(): void
{
    if (keputusanDikunci()) {
        fail('Keputusan kejohanan telah DIKUNCI oleh Super Admin. Tiada perubahan dibenarkan.', 423);
    }
}

// Tangkap ralat tidak dijangka -> respons JSON kemas (bukan halaman putih)
set_exception_handler(function (Throwable $e) {
    error_log('Ralat tidak dijangka: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    jsonOut(['ok' => false, 'mesej' => 'Ralat sistem. Sila cuba lagi.'], 500);
});
