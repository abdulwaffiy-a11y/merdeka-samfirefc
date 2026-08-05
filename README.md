# Sistem Kejohanan Futsal Merdeka Kepala Batas 2026

**Anjuran SAMFIRE FC** dengan kerjasama PAKSY · 30 Ogos 2026 · merdeka.samfirefc.com

## Kandungan

- Root = fail siap deploy (frontend sudah di-build)
- `api/` — backend PHP (serasi PHP 7.4+)
- `assets/` — CSS & JS yang telah di-build
- `sql/schema.sql` — struktur database
- `web-src/` — kod sumber React (Vite + Tailwind). Build: `cd web-src && npm i && npx vite build`
- `tests/` — ujian automatik (perlukan PHP CLI + MariaDB)

## Pemasangan kali pertama

Upload fail ke domain, buka `api/pasang.php`, isi borang database + akaun Super Admin. Fail pemasang padam sendiri selepas siap.

## Auto-deploy

cPanel → **Git™ Version Control** → Clone repo ini → tekan **Deploy HEAD Commit**.
`.cpanel.yml` menguruskan penyalinan fail. Database dan `api/config.php` tidak disentuh.

## Ciri

- Paparan awam: live score, kedudukan kumpulan, carta kalah mati, pendaftaran pasukan (logo ≤1MB)
- Panel admin: kemaskini skor mesra telefon, kelulusan pendaftaran, log aktiviti
- Undian kumpulan automatik (slot A1–H3) & undian suku akhir — CSPRNG Fisher-Yates
- Kedudukan: Mata → head-to-head (liga-mini) → beza gol → jumlah gol → undian
- Multi-admin serentak dengan optimistic locking (tiada skor tertindih)
