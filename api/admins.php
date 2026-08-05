<?php
/**
 * admins.php — urus akaun admin (Super Admin sahaja) + tetapan kejohanan + log.
 *
 *  GET   ?action=senarai
 *  POST   action=tambah      { nama, email, password, role }
 *  POST   action=aktif       { id, aktif }
 *  POST   action=buang       { id }
 *  POST   action=reset_pass  { id, password }
 *  GET   ?action=log&had=100
 *  POST   action=tetapan     { pengumuman?, keputusan_dikunci? }
 */

declare(strict_types=1);

require __DIR__ . '/lib/boot.php';
require __DIR__ . '/lib/kejohanan.php';

$action = (string)inp('action', 'senarai');

switch ($action) {

    case 'senarai':
        wajibSuper();
        $rows = db()->query(
            'SELECT id, nama, email, role, aktif, last_login_at, created_at FROM admins ORDER BY id'
        )->fetchAll();
        ok(['admins' => $rows]);

    // -----------------------------------------------------------------
    case 'tambah':
        wajibPost();
        semakCsrf();
        $me = wajibSuper();

        $nama  = mb_substr(trim((string)inp('nama', '')), 0, 100);
        $email = strtolower(trim((string)inp('email', '')));
        $pass  = (string)inp('password', '');
        $role  = (string)inp('role', 'admin');

        if ($nama === '') fail('Sila isi nama.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Format emel tidak sah.');
        if (strlen($pass) < 8) fail('Kata laluan mesti sekurang-kurangnya 8 aksara.');
        if (!in_array($role, ['admin', 'super'], true)) fail('Peranan tidak sah.');

        $st = db()->prepare('SELECT COUNT(*) FROM admins WHERE email = ?');
        $st->execute([$email]);
        if ((int)$st->fetchColumn() > 0) fail('Emel ini sudah digunakan.');

        if ((int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() >= 10) {
            fail('Had 10 akaun admin telah dicapai.');
        }

        db()->prepare('INSERT INTO admins (nama, email, password_hash, role) VALUES (?, ?, ?, ?)')
            ->execute([$nama, $email, password_hash($pass, PASSWORD_BCRYPT), $role]);

        audit($me, 'admin_tambah', ['email' => $email, 'role' => $role]);
        ok(['id' => (int)db()->lastInsertId()]);

    // -----------------------------------------------------------------
    case 'aktif':
        wajibPost();
        semakCsrf();
        $me = wajibSuper();
        $id = (int)inp('id', 0);
        $aktif = (int)((bool)inp('aktif', true));
        if ($id === $me['id'] && $aktif === 0) fail('Anda tidak boleh nyahaktifkan akaun sendiri.');
        db()->prepare('UPDATE admins SET aktif = ? WHERE id = ?')->execute([$aktif, $id]);
        audit($me, 'admin_aktif', ['id' => $id, 'aktif' => $aktif]);
        ok();

    // -----------------------------------------------------------------
    case 'buang':
        wajibPost();
        semakCsrf();
        $me = wajibSuper();
        $id = (int)inp('id', 0);
        if ($id === $me['id']) fail('Anda tidak boleh membuang akaun sendiri.');

        $st = db()->prepare('SELECT email, role FROM admins WHERE id = ?');
        $st->execute([$id]);
        $u = $st->fetch();
        if (!$u) fail('Akaun tidak dijumpai.', 404);

        if ($u['role'] === 'super') {
            $bil = (int)db()->query('SELECT COUNT(*) FROM admins WHERE role = "super" AND aktif = 1')->fetchColumn();
            if ($bil <= 1) fail('Mesti ada sekurang-kurangnya satu Super Admin aktif.');
        }

        db()->prepare('DELETE FROM admins WHERE id = ?')->execute([$id]);
        audit($me, 'admin_buang', ['id' => $id, 'email' => $u['email']]);
        ok();

    // -----------------------------------------------------------------
    case 'reset_pass':
        wajibPost();
        semakCsrf();
        $me = wajibSuper();
        $id   = (int)inp('id', 0);
        $pass = (string)inp('password', '');
        if (strlen($pass) < 8) fail('Kata laluan mesti sekurang-kurangnya 8 aksara.');
        db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($pass, PASSWORD_BCRYPT), $id]);
        audit($me, 'admin_reset_pass', ['id' => $id]);
        ok();

    // -----------------------------------------------------------------
    case 'log':
        wajibAdmin();
        $had = max(10, min(500, (int)inp('had', 150)));
        $st = db()->prepare(
            'SELECT id, admin_nama, tindakan, butiran_json, ip, created_at
               FROM audit_log ORDER BY id DESC LIMIT ' . $had
        );
        $st->execute();
        ok(['log' => $st->fetchAll()]);

    // -----------------------------------------------------------------
    case 'tetapan':
        wajibPost();
        semakCsrf();
        $me = wajibSuper();

        $peng = inp('pengumuman', null);
        if ($peng !== null) {
            setTetapan('pengumuman', mb_substr((string)$peng, 0, 300));
        }
        $kunci = inp('keputusan_dikunci', null);
        if ($kunci !== null) {
            setTetapan('keputusan_dikunci', ((bool)$kunci) ? '1' : '0');
        }
        foreach (['nama_kejohanan', 'nama_penganjur', 'tarikh_kejohanan', 'lokasi',
                  'yuran', 'telefon_urusetia', 'url_website', 'url_daftar_ahli'] as $k) {
            $v = inp($k, null);
            if ($v !== null) setTetapan($k, mb_substr((string)$v, 0, 200));
        }

        audit($me, 'tetapan_ubah', ['pengumuman' => $peng, 'dikunci' => $kunci]);
        ok(['tetapan' => tetapanSemua()]);

    // -----------------------------------------------------------------
    default:
        fail('Tindakan tidak dikenali.', 404);
}
