export const PERINGKAT_NAMA = {
  grup:  'Peringkat Kumpulan',
  sa:    'Suku Akhir',
  ss:    'Separuh Akhir',
  third: 'Tempat Ketiga',
  final: 'Perlawanan Akhir',
}

export const PERINGKAT_PENDEK = {
  grup: 'Kumpulan', sa: 'Suku Akhir', ss: 'Separuh Akhir', third: 'Tempat Ke-3', final: 'Akhir',
}

/** Peta id -> objek pasukan */
export function petaPasukan(senarai = []) {
  const m = new Map()
  senarai.forEach((t) => m.set(t.id, t))
  return m
}

/** Nama untuk dipaparkan bagi satu sisi perlawanan. */
export function namaSisi(peta, id, sumber) {
  if (id && peta.has(id)) return peta.get(id).nama
  return labelSumber(sumber)
}

export function labelSumber(sumber) {
  if (!sumber) return 'Belum ditentukan'
  const [jenis, nilai] = sumber.split(':')
  if (jenis === 'UNDI') return `Cabutan undi #${nilai}`
  if (jenis === 'W') return `Pemenang ${kodPapar(nilai)}`
  if (jenis === 'L') return `Kalah ${kodPapar(nilai)}`
  return 'Belum ditentukan'
}

export function kodPapar(kod) {
  if (kod === 'FINAL') return 'Akhir'
  if (kod === 'T3') return 'Tempat Ke-3'
  return kod
}

/** Adakah pasukan sudah diberi nama sebenar? */
export const adaNama = (t) => t && t.diisi

/** "08:30" -> "8:30 pagi" */
export function masaMy(hhmm) {
  if (!hhmm) return ''
  const [h, m] = hhmm.split(':').map(Number)
  const suffix = h < 12 ? 'pagi' : h < 15 ? 'tgh hari' : h < 19 ? 'petang' : 'malam'
  const jam = h % 12 === 0 ? 12 : h % 12
  return `${jam}.${String(m).padStart(2, '0')} ${suffix}`
}

export function tarikhMy(iso) {
  if (!iso) return ''
  const bulan = ['Januari','Februari','Mac','April','Mei','Jun','Julai','Ogos','September','Oktober','November','Disember']
  const d = new Date(iso + 'T00:00:00')
  if (Number.isNaN(d.getTime())) return iso
  return `${d.getDate()} ${bulan[d.getMonth()]} ${d.getFullYear()}`
}

export function masaPenuhMy(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  return d.toLocaleString('ms-MY', { dateStyle: 'medium', timeStyle: 'short' })
}

/** Keputusan satu perlawanan sebagai teks ringkas. */
export function teksSkor(m) {
  if (m.skor_home === null || m.skor_away === null) return '–'
  let s = `${m.skor_home} – ${m.skor_away}`
  if (m.penalti_home !== null && m.penalti_away !== null) s += ` (p ${m.penalti_home}–${m.penalti_away})`
  return s
}

export function pemenangId(m) {
  if (m.status !== 'done' || m.skor_home === null || m.skor_away === null) return null
  if (m.skor_home > m.skor_away) return m.home_id
  if (m.skor_home < m.skor_away) return m.away_id
  if (m.penalti_home !== null && m.penalti_away !== null) {
    if (m.penalti_home > m.penalti_away) return m.home_id
    if (m.penalti_home < m.penalti_away) return m.away_id
  }
  return null
}

/** Kira baki masa ke satu tarikh; pulangkan null jika sudah lepas. */
export function kiraUndur(tarikhIso, masaMula = '08:30') {
  if (!tarikhIso) return null
  const sasaran = new Date(`${tarikhIso}T${masaMula}:00`)
  const beza = sasaran.getTime() - Date.now()
  if (Number.isNaN(beza) || beza <= 0) return null
  const saat = Math.floor(beza / 1000)
  return {
    hari:  Math.floor(saat / 86400),
    jam:   Math.floor((saat % 86400) / 3600),
    minit: Math.floor((saat % 3600) / 60),
    saat:  saat % 60,
  }
}

/** Logo SAMFIRE FC — relatif kepada halaman, jadi berfungsi di root atau subfolder. */
export const LOGO = new URL('logo-samfire.png', document.baseURI).href

export const HADIAH = [
  { tempat: 'Juara',          tunai: 'RM1,000', lain: 'Medal + Piala', jenis: 'emas' },
  { tempat: 'Naib Juara',     tunai: 'RM700',   lain: 'Medal + Piala', jenis: 'kelabu' },
  { tempat: 'Tempat Ketiga',  tunai: 'RM500',   lain: 'Medal', jenis: 'maroon' },
  { tempat: 'Tempat Keempat', tunai: 'RM300',   lain: 'Medal', jenis: 'kelabu' },
]
