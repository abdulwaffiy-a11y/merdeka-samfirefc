# PANDUAN PEMASANGAN
## Sistem Kejohanan Futsal Merdeka Kepala Batas 2026

Pemasangan sekarang **automatik**. Tuan tidak perlu edit mana-mana fail PHP dan tidak perlu import SQL secara manual.

---

# BAHAGIAN A — PASANG (3 langkah, ~10 minit)

## Langkah 1 — Cipta database dalam cPanel

cPanel → **MySQL® Databases**

1. **Create New Database** → nama: `merdeka` → jadi `xxxxx_merdeka`
2. **Add New User** → cipta pengguna + kata laluan → **simpan kata laluan ini**
3. **Add User To Database** → pilih pengguna + database → tanda **ALL PRIVILEGES** → Make Changes

Catat 3 perkara: **nama database**, **pengguna database**, **kata laluan**.

> Tidak perlu buka phpMyAdmin. Biarkan database kosong.

## Langkah 2 — Upload fail

cPanel → **File Manager** → masuk folder domain (cth `merdeka.samfirefc.com`)

1. **Upload** fail zip ini
2. Klik kanan pada zip → **Extract**
3. Pastikan struktur akhir begini (bukan folder dalam folder):

```
merdeka.samfirefc.com/
├── index.html
├── admin.html
├── assets/
└── api/
```

4. **Padam** fail zip dari folder tersebut selepas extract

> Jika semua masuk dalam satu subfolder selepas extract: masuk subfolder itu → Select All → **Move** ke atas satu tingkat.

## Langkah 3 — Buka pemasang

```
https://merdeka.samfirefc.com/api/pasang.php
```

Isi borang:

| Ruangan | Isi dengan |
|---|---|
| Nama database | dari Langkah 1 (cth `adampowe_merdeka`) |
| Pengguna database | dari Langkah 1 (cth `adampowe_merdekauser`) |
| Kata laluan database | dari Langkah 1 |
| Host / Port | biarkan `localhost` / `3306` |
| Nama penuh | nama tuan |
| Emel | emel untuk log masuk panel admin |
| Kata laluan | kata laluan admin tuan (min 8 aksara) |

Tekan **Pasang Sistem Sekarang**. Pemasang akan:

1. uji sambungan database
2. cipta 8 jadual + 32 perlawanan + 24 slot pasukan
3. tulis `api/config.php` sendiri
4. cipta akaun Super Admin tuan
5. **padam sendiri** fail pemasangan

Siap. Kalau ada masalah, pemasang akan beritahu tepat apa masalahnya di skrin yang sama.

| Halaman | Alamat |
|---|---|
| Paparan awam (penonton) | `https://merdeka.samfirefc.com/` |
| Panel admin (urus setia) | `https://merdeka.samfirefc.com/admin.html` |

---

## Kalau pemasang beritahu versi PHP terlalu rendah

cPanel → **MultiPHP Manager** → tanda domain `merdeka.samfirefc.com` → pilih **PHP 8.1** → **Apply**.
Kemudian buka semula `api/pasang.php`.

---

# BAHAGIAN B — SELEPAS PASANG

1. Log masuk `admin.html` guna emel & kata laluan yang tuan taip tadi
2. Tab **Akaun** → tambah 2 akaun admin untuk urus setia lain
   - **Admin** = boleh kemaskini skor & jalankan undian
   - **Super Admin** = kawalan penuh (reset undian, urus akaun, kunci keputusan)

## Pendaftaran pasukan (automatik)

- Orang awam boleh daftar pasukan sendiri di tab **Daftar** pada laman utama — nama pasukan, pengurus, telefon, senarai pemain (maks 10) dan **logo pasukan** (maks 1MB).
- Di tab Daftar juga ada butang **Laman Web SAMFIRE FC** dan **Daftar Ahli SAMFIRE FC**.
- Admin semak di tab **Daftar** panel admin → **Lulus** (masuk kolam undian) atau **Tolak**.
- Butang **Tutup pendaftaran** bila slot penuh.

## Undian kumpulan automatik (admin sahaja)

Tab **Undian** → bahagian **Undian Kumpulan**:

1. Tandakan pasukan dari kolam pendaftaran, dan/atau taip nama pasukan secara manual
2. Tekan **Jalankan Undian Kumpulan** — sistem undi secara rawak dan tentukan slot **A1, A2 … H3** secara automatik, dengan animasi
3. Nama, logo & pemain terus masuk ke jadual kumpulan

Undian kumpulan hanya boleh dijalankan **sebelum** perlawanan pertama bermula. Slot yang sudah berisi tidak diusik — boleh undi berperingkat (cth: 12 pasukan dulu, 12 lagi kemudian).

*Cara lama masih ada:* tab **Pasukan** → taip terus nama ke slot A1–H3 → **Simpan Semua**.

---

# BAHAGIAN C — CARA GUNA PADA HARI KEJOHANAN

**Sebelum mula**

- Setiap admin log masuk di telefon masing-masing melalui `/admin.html`
- Tab **Utama** → pastikan tiada amaran "Perlu Tindakan"

**Semasa perlawanan**

1. Tab **Skor** → tekan perlawanan berkenaan
2. Tekan **Sedang Main** bila perlawanan bermula (penonton nampak lencana LIVE)
3. Masukkan gol guna butang **+** / **−**
4. Bila tamat → tekan **Tamat** → **Simpan Keputusan**

Kedudukan kumpulan dan carta kalah mati dikira semula automatik. Paparan penonton kemas kini dalam 10 saat.

**Undian suku akhir (± 11.20 pagi)**

1. Pastikan kesemua 24 perlawanan kumpulan sudah ditanda **Tamat**
2. Tab **Undian** → sahkan 8 johan kumpulan betul
3. Tekan **Jalankan Undian Suku Akhir** — animasi cabutan dipaparkan (boleh sambung laptop ke TV/projektor)
4. Carta suku akhir terus terisi di paparan awam

> Undian hanya boleh dijalankan **sekali**. Hanya Super Admin boleh reset, dan reset akan mengosongkan semua keputusan kalah mati.

**Selepas majlis penyampaian hadiah**

Tab **Akaun** → **Kunci Sekarang**. Selepas ini tiada sesiapa boleh ubah keputusan — rekod jadi rasmi.

---

# SOAL JAWAB

**Dua admin masukkan skor perlawanan sama serentak?**
Yang pertama simpan berjaya. Yang kedua dapat mesej *"Skor telah dikemaskini oleh admin lain — sila semak semula"* dan paparan disegarkan. Tiada skor tertindih senyap.

**Perlawanan kalah mati seri?**
Sistem tidak benarkan tandakan Tamat sehingga keputusan sepakan penalti dimasukkan. Ruangan penalti muncul sendiri bila skor sama.

**Dua pasukan seri sepenuhnya dalam kumpulan?**
Sistem tandakan kumpulan itu "Perlu cabutan undi" dan undian suku akhir disekat. Buat cabutan undi depan wakil pasukan → tab **Pasukan** → bahagian *Pemecah seri manual* → isi `1` untuk yang menang cabutan, `2` untuk kedua → **Simpan Semua**.

**Salah masuk skor kumpulan selepas undian dijalankan?**
Hanya Super Admin boleh betulkan, dan sistem tolak sebarang pembetulan yang menukar johan kumpulan. Kalau johan memang berubah: reset undian → betulkan skor → undi semula.

**Internet gelanggang perlahan?**
Paparan awam muat turun ~85KB kali pertama, selepas itu beberapa bait sahaja setiap 10 saat kalau tiada perubahan. Kalau talian putus, paparan terakhir kekal di skrin penonton.

**Lupa kata laluan admin?**
Super Admin boleh tetapkan semula di tab **Akaun** → ikon kunci di sebelah nama admin.

**Log masuk terkunci?**
5 percubaan gagal → kunci 15 minit. Tunggu, atau Super Admin tetapkan kata laluan baharu.

**Nak pasang semula dari kosong?**
Upload semula `api/pasang.php` dari zip ini dan buka sekali lagi. Ia akan import semula database dan cipta akaun admin baharu. **Amaran:** ini memadam semua keputusan sedia ada.

---

## Backup pada hari kejohanan (disyorkan)

cPanel → **Cron Jobs** → tambah cron setiap jam pada 30 Ogos:

```
0 * * * * mysqldump -u PENGGUNA -pKATALALUAN NAMA_DATABASE > ~/backup_merdeka_$(date +\%H).sql
```

---

*Disediakan untuk Kejohanan Futsal Merdeka Kepala Batas 2026 — Pusat Kecemerlangan As-Syafiee (PAKSY).*
