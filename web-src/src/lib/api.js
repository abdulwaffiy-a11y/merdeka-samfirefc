// Klien API ringkas — semua panggilan pergi ke folder ./api relatif kepada halaman.

const asas = new URL('api/', document.baseURI).href.replace(/\/$/, '')

let csrf = null
export const setCsrf = (t) => { csrf = t }
export const getCsrf = () => csrf

class RalatApi extends Error {
  constructor(mesej, kod, data) {
    super(mesej)
    this.kod = kod
    this.data = data || {}
  }
}
export { RalatApi }

/**
 * Baca jawapan fetch sebagai JSON dengan selamat.
 *
 * Kalau pelayan pulangkan HTML (halaman ralat 404/500 Apache atau amaran PHP),
 * `res.json()` akan lempar mesej pelayar yang mengelirukan — contohnya Safari
 * iPhone menunjukkan "The string did not match the expected pattern."
 * Fungsi ini menggantikannya dengan mesej Bahasa Melayu yang berguna.
 */
export async function jsonSelamat(res) {
  const teks = await res.text()
  try {
    return JSON.parse(teks)
  } catch {
    const petunjuk = /^\s*</.test(teks) ? ' Pelayan memulangkan halaman HTML, bukan data.' : ''
    if (res.status === 404) {
      throw new RalatApi('Fail API tidak dijumpai di pelayan (404). Sila jalankan deploy semula.', 404)
    }
    if (res.status === 401 || res.status === 403) {
      throw new RalatApi('Sesi tamat atau tiada kebenaran. Sila log masuk semula.', res.status)
    }
    throw new RalatApi(
      `Pelayan memulangkan jawapan tidak sah (HTTP ${res.status}).${petunjuk} Sila cuba lagi.`,
      res.status,
    )
  }
}

async function panggil(fail, params = {}, badan = null, opsyen = {}) {
  const url = new URL(`${asas}/${fail}`)
  Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v))

  const init = {
    method: badan ? 'POST' : 'GET',
    credentials: 'same-origin',
    headers: {},
    ...opsyen,
  }
  if (badan) {
    init.headers['Content-Type'] = 'application/json'
    if (csrf) init.headers['X-CSRF-Token'] = csrf
    init.body = JSON.stringify(badan)
  }
  if (opsyen.etag) init.headers['If-None-Match'] = opsyen.etag

  let res
  try {
    res = await fetch(url, init)
  } catch {
    throw new RalatApi('Tiada sambungan internet. Sila cuba lagi.', 0)
  }

  if (res.status === 304) return { _tidakBerubah: true }

  let data = {}
  try { data = await res.json() } catch { /* badan kosong */ }

  if (!res.ok || data.ok === false) {
    throw new RalatApi(data.mesej || `Ralat ${res.status}`, res.status, data)
  }
  data._etag = res.headers.get('ETag')
  return data
}

export const api = {
  awam: (etag) => panggil('public.php', {}, null, etag ? { etag } : {}),

  saya:   () => panggil('auth.php', { action: 'me' }),
  login:  (email, password) => panggil('auth.php', { action: 'login' }, { email, password }),
  logout: () => panggil('auth.php', { action: 'logout' }, {}),
  tukarPassword: (lama, baru) => panggil('auth.php', { action: 'tukar_password' }, { lama, baru }),

  pasukanSenarai: () => panggil('teams.php', { action: 'senarai' }),
  pasukanSimpan:  (pasukan) => panggil('teams.php', { action: 'simpan' }, { pasukan }),
  pemainSimpan:   (team_id, pemain) => panggil('teams.php', { action: 'pemain_simpan' }, { team_id, pemain }),

  perlawananSenarai: () => panggil('matches.php', { action: 'senarai' }),
  perlawananSimpan:  (muatan) => panggil('matches.php', { action: 'simpan' }, muatan),

  undianStatus: () => panggil('draw.php', { action: 'status' }),
  undianJalan:  () => panggil('draw.php', { action: 'jalan' }, {}),
  undianReset:  (sahkan) => panggil('draw.php', { action: 'reset' }, { sahkan }),

  daftarUrus:  () => panggil('daftar.php', { action: 'urus' }),
  daftarLulus: (id) => panggil('daftar.php', { action: 'lulus' }, { id }),
  daftarTolak: (id, catatan) => panggil('daftar.php', { action: 'tolak' }, { id, catatan }),
  daftarPadam: (id) => panggil('daftar.php', { action: 'padam' }, { id }),
  daftarBuka:  (buka) => panggil('daftar.php', { action: 'buka' }, { buka }),
  daftarKemas: (badan) => panggil('daftar.php', { action: 'kemas' }, badan),

  undiKumpulanStatus: () => panggil('undi_kumpulan.php', { action: 'status' }),
  undiKumpulanJalan:  (senarai) => panggil('undi_kumpulan.php', { action: 'jalan' }, { senarai }),

  adminSenarai:   () => panggil('admins.php', { action: 'senarai' }),
  adminTambah:    (d) => panggil('admins.php', { action: 'tambah' }, d),
  adminAktif:     (id, aktif) => panggil('admins.php', { action: 'aktif' }, { id, aktif }),
  adminBuang:     (id) => panggil('admins.php', { action: 'buang' }, { id }),
  adminResetPass: (id, password) => panggil('admins.php', { action: 'reset_pass' }, { id, password }),
  log:            (had = 150) => panggil('admins.php', { action: 'log', had }),
  tetapanSimpan:  (d) => panggil('admins.php', { action: 'tetapan' }, d),
}
