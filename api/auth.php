<?php
/**
 * auth.php — log masuk / log keluar / semak sesi.
 *
 *  GET  ?action=me       -> maklumat admin semasa + token CSRF
 *  POST  action=login    -> { email, password }
 *  POST  action=logout
 *  POST  action=tukar_password -> { lama, baru }
 */

declare(strict_types=1);

require __DIR__ . '/lib/boot.php';

$action = (string)inp('action', 'me');

switch ($action) {

    // -----------------------------------------------------------------
    case 'me':
        $a = adminSemasa();
        ok([
            'admin' => $a,
            'csrf'  => csrfToken(),
        ]);
        // no break — ok() keluar

    // -----------------------------------------------------------------
    case 'login':
        wajibPost();
        $email = strtolower(trim((string)inp('email', '')));
        $pass  = (string)inp('password', '');
        $ip    = ipKlien();

        if ($email === '' || $pass === '') {
            fail('Sila isi emel dan kata laluan.');
        }

        // ---- rate limit -------------------------------------------------
        $maxCuba = (int)$CFG['login_max_cuba'];
        $lock    = (int)$CFG['login_lock_minit'];

        $st = db()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE berjaya = 0 AND created_at > (NOW() - INTERVAL ? MINUTE)
               AND (email = ? OR ip = ?)'
        );
        $st->execute([$lock, $email, $ip]);
        if ((int)$st->fetchColumn() >= $maxCuba) {
            fail("Terlalu banyak percubaan gagal. Sila cuba semula dalam {$lock} minit.", 429);
        }

        // ---- semak akaun -------------------------------------------------
        $st = db()->prepare('SELECT id, nama, email, password_hash, role, aktif FROM admins WHERE email = ?');
        $st->execute([$email]);
        $u = $st->fetch();

        $sah = $u && (int)$u['aktif'] === 1 && password_verify($pass, $u['password_hash']);

        $ins = db()->prepare('INSERT INTO login_attempts (email, ip, berjaya) VALUES (?, ?, ?)');
        $ins->execute([$email, $ip, $sah ? 1 : 0]);

        if (!$sah) {
            audit(null, 'login_gagal', ['email' => $email]);
            fail('Emel atau kata laluan tidak betul.', 401);
        }

        // ---- log masuk berjaya ------------------------------------------
        startSesi();
        session_regenerate_id(true);              // elak session fixation
        $_SESSION['admin_id']    = (int)$u['id'];
        $_SESSION['admin_nama']  = $u['nama'];
        $_SESSION['admin_email'] = $u['email'];
        $_SESSION['admin_role']  = $u['role'];
        $_SESSION['aktif_pada']  = time();
        unset($_SESSION['csrf']);

        db()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?')->execute([(int)$u['id']]);

        $admin = adminSemasa();
        audit($admin, 'login', []);

        ok(['admin' => $admin, 'csrf' => csrfToken()]);

    // -----------------------------------------------------------------
    case 'logout':
        wajibPost();
        $a = adminSemasa();
        if ($a) audit($a, 'logout', []);
        startSesi();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
        }
        session_destroy();
        ok();

    // -----------------------------------------------------------------
    case 'tukar_password':
        wajibPost();
        semakCsrf();
        $a    = wajibAdmin();
        $lama = (string)inp('lama', '');
        $baru = (string)inp('baru', '');

        if (strlen($baru) < 8) {
            fail('Kata laluan baharu mesti sekurang-kurangnya 8 aksara.');
        }
        $st = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
        $st->execute([$a['id']]);
        $hash = (string)$st->fetchColumn();
        if (!password_verify($lama, $hash)) {
            fail('Kata laluan semasa tidak betul.', 401);
        }
        db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($baru, PASSWORD_BCRYPT), $a['id']]);
        audit($a, 'tukar_password', []);
        ok();

    // -----------------------------------------------------------------
    default:
        fail('Tindakan tidak dikenali.', 404);
}
