<?php
/**
 * pasang.php — PEMASANG SATU FAIL
 * Sistem Kejohanan Futsal Merdeka Kepala Batas 2026
 *
 * Letak fail ini dalam folder api/ dan buka dalam pelayar:
 *   https://merdeka.samfirefc.com/api/pasang.php
 *
 * Ia akan:
 *   1. Uji sambungan database
 *   2. Import kesemua jadual + 32 perlawanan (skema tertanam dalam fail ini)
 *   3. Tulis api/config.php dengan betul (tiada risiko salah taip)
 *   4. Cipta akaun Super Admin
 *   5. Padam sendiri fail pemasangan
 *
 * Ditulis dalam sintaks PHP lama supaya boleh jalan pada mana-mana versi PHP 7+.
 */

header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('SKEMA_B64', '__SKEMA_BASE64__');

$DIR = dirname(__FILE__);

/* ------------------------------------------------------------------ util */
function nilai($k, $d = '') {
    return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d;
}
function esc($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Pecahkan fail SQL kepada penyataan individu. */
function pecahSql($sql) {
    $baris = preg_split("/\r\n|\n|\r/", $sql);
    $keluar = array();
    $semasa = '';
    foreach ($baris as $b) {
        $t = trim($b);
        if ($t === '' || substr($t, 0, 2) === '--') { continue; }
        $semasa .= $b . "\n";
        if (substr($t, -1) === ';') {
            $keluar[] = $semasa;
            $semasa = '';
        }
    }
    if (trim($semasa) !== '') { $keluar[] = $semasa; }
    return $keluar;
}

/** Jana kandungan config.php dengan nilai di-escape secara selamat. */
function janaConfig($host, $nama, $user, $pass, $port, $appKey, $https) {
    $v = 'var_export';
    return "<?php\n"
        . "/**\n"
        . " * Konfigurasi sistem — dijana automatik oleh pasang.php\n"
        . " * Jangan edit melainkan perlu.\n"
        . " */\n\n"
        . "return [\n"
        . "    'db' => [\n"
        . "        'host' => " . $v($host, true) . ",\n"
        . "        'name' => " . $v($nama, true) . ",\n"
        . "        'user' => " . $v($user, true) . ",\n"
        . "        'pass' => " . $v($pass, true) . ",\n"
        . "        'port' => " . (int)$port . ",\n"
        . "    ],\n\n"
        . "    'app_key' => " . $v($appKey, true) . ",\n"
        . "    'https_only' => " . ($https ? 'true' : 'false') . ",\n\n"
        . "    'login_max_cuba'     => 5,\n"
        . "    'login_lock_minit'   => 15,\n"
        . "    'session_idle_minit' => 240,\n"
        . "    'timezone'           => 'Asia/Kuala_Lumpur',\n"
        . "    'dev_origin'         => '',\n"
        . "];\n";
}

function janaKunci() {
    if (function_exists('random_bytes')) { return bin2hex(random_bytes(24)); }
    return md5(uniqid('', true)) . md5(uniqid('', true));
}

/* --------------------------------------------------------------- proses */
$ralat   = array();
$langkah = array();
$siap    = false;
$configManual = '';

$f = array(
    'host'  => nilai('host', 'localhost'),
    'nama'  => nilai('nama'),
    'user'  => nilai('user'),
    'pass'  => isset($_POST['pass']) ? (string)$_POST['pass'] : '',
    'port'  => nilai('port', '3306'),
    'anama' => nilai('anama'),
    'aemel' => nilai('aemel'),
    'apass' => isset($_POST['apass']) ? (string)$_POST['apass'] : '',
    'apass2'=> isset($_POST['apass2']) ? (string)$_POST['apass2'] : '',
);

$hantar = ($_SERVER['REQUEST_METHOD'] === 'POST');

if ($hantar) {

    /* ---- 1. Semak versi PHP ---- */
    if (version_compare(PHP_VERSION, '7.4.0', '<')) {
        $ralat[] = 'Server ini guna PHP ' . PHP_VERSION . '. Sistem perlukan PHP 7.4 atau lebih baharu. '
                 . 'Tukar di cPanel &rarr; MultiPHP Manager &rarr; pilih domain ini &rarr; PHP 8.1.';
    }
    foreach (array('pdo_mysql', 'mbstring', 'json', 'session') as $ext) {
        if (!extension_loaded($ext)) {
            $ralat[] = 'Sambungan PHP <code>' . $ext . '</code> tidak aktif. Aktifkan di cPanel &rarr; Select PHP Version &rarr; Extensions.';
        }
    }

    /* ---- 2. Semak borang ---- */
    if ($f['nama'] === '')  { $ralat[] = 'Sila isi nama database.'; }
    if ($f['user'] === '')  { $ralat[] = 'Sila isi pengguna database.'; }
    if ($f['anama'] === '') { $ralat[] = 'Sila isi nama penuh admin.'; }
    if (!filter_var($f['aemel'], FILTER_VALIDATE_EMAIL)) { $ralat[] = 'Emel admin tidak sah.'; }
    if (strlen($f['apass']) < 8) { $ralat[] = 'Kata laluan admin mesti sekurang-kurangnya 8 aksara.'; }
    if ($f['apass'] !== $f['apass2']) { $ralat[] = 'Kata laluan admin tidak sepadan.'; }

    /* ---- 3. Sambung database ---- */
    $pdo = null;
    if (!$ralat) {
        try {
            $dsn = 'mysql:host=' . $f['host'] . ';port=' . (int)$f['port'] . ';dbname=' . $f['nama'] . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $f['user'], $f['pass'], array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ));
            $langkah[] = 'Berjaya sambung ke database <strong>' . esc($f['nama']) . '</strong>.';
        } catch (Exception $e) {
            $ralat[] = 'Tidak dapat sambung ke database: <code>' . esc($e->getMessage()) . '</code>';
        }
    }

    /* ---- 4. Import skema ---- */
    if (!$ralat && $pdo) {
        $sql = base64_decode(SKEMA_B64);
        if ($sql === false || strlen($sql) < 100) {
            $ralat[] = 'Skema database tidak dapat dibaca dari fail pemasang.';
        } else {
            try {
                $penyataan = pecahSql($sql);
                $bil = 0;
                foreach ($penyataan as $p) {
                    if (trim($p) === '') { continue; }
                    $pdo->exec($p);
                    $bil++;
                }
                $bilM = (int)$pdo->query('SELECT COUNT(*) FROM matches')->fetchColumn();
                $bilT = (int)$pdo->query('SELECT COUNT(*) FROM teams')->fetchColumn();
                if ($bilM !== 32 || $bilT !== 24) {
                    $ralat[] = 'Import selesai tetapi data tidak lengkap (' . $bilM . ' perlawanan, ' . $bilT . ' pasukan). Sepatutnya 32 dan 24.';
                } else {
                    $langkah[] = 'Import database selesai — ' . $bil . ' penyataan SQL, 8 jadual, 32 perlawanan, 24 slot pasukan.';
                }
            } catch (Exception $e) {
                $ralat[] = 'Ralat semasa import database: <code>' . esc($e->getMessage()) . '</code>';
            }
        }
    }

    /* ---- 5. Tulis config.php ---- */
    if (!$ralat) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $isi = janaConfig($f['host'], $f['nama'], $f['user'], $f['pass'], $f['port'], janaKunci(), $https);
        $laluan = $DIR . '/config.php';
        $tulis = @file_put_contents($laluan, $isi);
        if ($tulis === false) {
            $configManual = $isi;
            $ralat[] = 'Tidak dapat menulis <code>api/config.php</code> (kebenaran fail). '
                     . 'Salin kandungan di bawah dan tampal secara manual melalui File Manager.';
        } else {
            $semak = @include $laluan;
            if (!is_array($semak) || !isset($semak['db']['name'])) {
                $ralat[] = 'config.php ditulis tetapi tidak dapat dibaca semula. Sila hubungi pembangun.';
            } else {
                $langkah[] = 'Fail <code>api/config.php</code> ditulis dengan betul.';
            }
        }
    }

    /* ---- 6. Cipta Super Admin ---- */
    if (!$ralat && $pdo) {
        try {
            $adaAdmin = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
            if ($adaAdmin > 0) {
                $pdo->prepare('DELETE FROM admins')->execute();
            }
            $st = $pdo->prepare('INSERT INTO admins (nama, email, password_hash, role) VALUES (?, ?, ?, ?)');
            $st->execute(array($f['anama'], strtolower($f['aemel']), password_hash($f['apass'], PASSWORD_BCRYPT), 'super'));
            $langkah[] = 'Akaun Super Admin dicipta untuk <strong>' . esc($f['aemel']) . '</strong>.';
            $siap = true;
        } catch (Exception $e) {
            $ralat[] = 'Ralat mencipta akaun admin: <code>' . esc($e->getMessage()) . '</code>';
        }
    }

    /* ---- 7. Bersihkan fail pemasangan ---- */
    if ($siap) {
        $dipadam = array();
        foreach (array('setup.php', 'semak.php', 'pasang.php') as $fn) {
            $p = $DIR . '/' . $fn;
            if (file_exists($p) && @unlink($p)) { $dipadam[] = $fn; }
        }
        if ($dipadam) {
            $langkah[] = 'Fail pemasangan dipadam automatik: ' . implode(', ', $dipadam) . '.';
        } else {
            $langkah[] = '<strong>Penting:</strong> sila padam <code>api/pasang.php</code>, <code>api/setup.php</code> dan <code>api/semak.php</code> secara manual melalui File Manager.';
        }
    }
}

/* ---- maklumat server untuk paparan ---- */
$verOk = version_compare(PHP_VERSION, '7.4.0', '>=');
?><!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pemasang — Kejohanan Futsal Merdeka Kepala Batas 2026</title>
<style>
 *{box-sizing:border-box}
 body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f5f5f4;color:#1c1917;padding:24px 16px}
 .w{max-width:620px;margin:0 auto}
 .c{background:#fff;border:1px solid #e7e5e4;border-radius:14px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
 h1{font-size:19px;margin:0 0 4px;color:#7B1E2B}
 p.s{margin:0 0 18px;color:#78716c;font-size:13px}
 h2{font-size:13px;text-transform:uppercase;letter-spacing:.06em;color:#78716c;margin:22px 0 10px;padding-bottom:6px;border-bottom:1px solid #f5f5f4}
 label{display:block;font-size:12.5px;font-weight:600;margin:12px 0 5px}
 input{width:100%;padding:10px 12px;border:1px solid #d6d3d1;border-radius:9px;font-size:15px;background:#fff}
 input:focus{outline:2px solid #7B1E2B;outline-offset:1px;border-color:#7B1E2B}
 .hint{font-size:11.5px;color:#a8a29e;margin-top:4px}
 button{margin-top:22px;width:100%;padding:14px;background:#7B1E2B;color:#fff;border:0;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer}
 button:hover{background:#5f1721}
 .err,.ok,.warn{padding:13px 15px;border-radius:10px;font-size:13.5px;margin-bottom:14px;line-height:1.5}
 .err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
 .ok{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
 .warn{background:#fffbeb;border:1px solid #fde68a;color:#92400e}
 ul{margin:6px 0 0;padding-left:20px}
 li{margin:3px 0}
 code{background:rgba(0,0,0,.06);padding:1px 5px;border-radius:4px;font-size:12px;word-break:break-all}
 textarea{width:100%;height:220px;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px;padding:10px;border:1px solid #d6d3d1;border-radius:9px}
 a{color:#7B1E2B;font-weight:600}
 .row{display:flex;gap:10px}
 .row>div{flex:1}
 .pill{display:inline-block;font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;background:#f5f5f4;color:#57534e}
</style>
</head>
<body>
<div class="w">
  <div class="c">
    <h1>Pemasang Sistem Kejohanan</h1>
    <p class="s">
      Merdeka Kepala Batas 2026 &middot; PAKSY
      &nbsp;<span class="pill">PHP <?php echo esc(PHP_VERSION); ?></span>
    </p>

<?php if ($siap): ?>

    <div class="ok">
      <strong>Pemasangan selesai.</strong>
      <ul><?php foreach ($langkah as $l) { echo '<li>' . $l . '</li>'; } ?></ul>
    </div>
    <p style="font-size:14px;line-height:2">
      &rarr; <a href="../admin.html">Log masuk Panel Admin</a><br>
      &rarr; <a href="../">Buka paparan awam</a>
    </p>
    <p style="font-size:12.5px;color:#78716c;margin-top:16px">
      Log masuk guna emel <strong><?php echo esc($f['aemel']); ?></strong> dan kata laluan yang tuan taip tadi.
      Selepas log masuk, pergi tab <strong>Akaun</strong> untuk tambah admin urus setia yang lain.
    </p>

<?php else: ?>

  <?php if (!$verOk): ?>
    <div class="warn">
      Server ini guna <strong>PHP <?php echo esc(PHP_VERSION); ?></strong>. Sistem perlukan PHP 7.4 ke atas.<br>
      Tukar dahulu di cPanel &rarr; <strong>MultiPHP Manager</strong> &rarr; pilih domain ini &rarr; pilih <strong>PHP 8.1</strong> &rarr; Apply.
    </div>
  <?php endif; ?>

  <?php if ($ralat): ?>
    <div class="err">
      <strong>Belum berjaya:</strong>
      <ul><?php foreach ($ralat as $r) { echo '<li>' . $r . '</li>'; } ?></ul>
    </div>
  <?php endif; ?>

  <?php if ($langkah && $ralat): ?>
    <div class="ok"><ul><?php foreach ($langkah as $l) { echo '<li>' . $l . '</li>'; } ?></ul></div>
  <?php endif; ?>

  <?php if ($configManual !== ''): ?>
    <p style="font-size:13px">Salin semua ini, kemudian tampal ke dalam <code>api/config.php</code> melalui File Manager (padam kandungan lama dahulu):</p>
    <textarea onclick="this.select()"><?php echo esc($configManual); ?></textarea>
  <?php endif; ?>

    <form method="post" autocomplete="off">
      <h2>Butiran Database</h2>
      <p class="hint" style="margin-top:0">Dari cPanel &rarr; MySQL&reg; Databases. Salin tepat, termasuk awalan seperti <code>adampowe_</code>.</p>

      <label>Nama database</label>
      <input name="nama" required value="<?php echo esc($f['nama']); ?>" placeholder="adampowe_merdeka">

      <label>Pengguna database</label>
      <input name="user" required value="<?php echo esc($f['user']); ?>" placeholder="adampowe_merdekauser">

      <label>Kata laluan database</label>
      <input name="pass" type="text" required value="<?php echo esc($f['pass']); ?>" placeholder="kata laluan pengguna database">
      <p class="hint">Sengaja dipapar supaya tuan boleh semak tiada salah taip. Ia tidak disimpan di mana-mana selain <code>config.php</code>.</p>

      <div class="row">
        <div>
          <label>Host</label>
          <input name="host" value="<?php echo esc($f['host']); ?>" placeholder="localhost">
        </div>
        <div>
          <label>Port</label>
          <input name="port" value="<?php echo esc($f['port']); ?>" placeholder="3306">
        </div>
      </div>

      <h2>Akaun Super Admin</h2>
      <p class="hint" style="margin-top:0">Akaun utama tuan untuk masuk panel admin.</p>

      <label>Nama penuh</label>
      <input name="anama" required value="<?php echo esc($f['anama']); ?>" placeholder="Waffiy Rosli">

      <label>Emel (untuk log masuk)</label>
      <input name="aemel" type="email" required value="<?php echo esc($f['aemel']); ?>" placeholder="nama@contoh.com">

      <label>Kata laluan (minimum 8 aksara)</label>
      <input name="apass" type="password" required minlength="8">

      <label>Ulang kata laluan</label>
      <input name="apass2" type="password" required minlength="8">

      <button type="submit">Pasang Sistem Sekarang</button>
      <p class="hint" style="text-align:center;margin-top:12px">
        Pemasang akan import database, tulis <code>config.php</code>, cipta akaun admin, dan padam dirinya sendiri.
      </p>
    </form>

<?php endif; ?>
  </div>
</div>
</body>
</html>
