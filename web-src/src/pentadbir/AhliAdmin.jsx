import { useEffect, useState } from 'react'
import { ShieldCheck, Check, X, Trash2, Phone, FileSpreadsheet, RefreshCw, Lock, Unlock, Wallet } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardBody, Button, Input, Label, Badge, useToast } from '../ui'
import { getCsrf } from '../lib/api'

const asasApi = new URL('api/', document.baseURI).href.replace(/\/$/, '')

export default function AhliAdmin({ admin }) {
  const toast = useToast()
  const [data, setData] = useState(null)
  const [sibukId, setSibukId] = useState(0)
  const [bayar, setBayar] = useState({ yuran_ahli: '', bayar_bank: '', bayar_akaun: '', bayar_kepada: '' })
  const [tapis, setTapis] = useState('baru')

  const ambil = async () => {
    try {
      const r = await fetch(`${asasApi}/ahli.php?action=urus`, { credentials: 'same-origin' })
      const d = await r.json()
      if (!d.ok) throw new Error(d.mesej)
      setData(d)
      setBayar({
        yuran_ahli: d.bayaran.yuran || '',
        bayar_bank: d.bayaran.bayar_bank || '',
        bayar_akaun: d.bayaran.bayar_akaun || '',
        bayar_kepada: d.bayaran.bayar_kepada || '',
      })
    } catch (e) { toast(e.message || 'Tidak dapat memuat senarai ahli.', 'ralat') }
  }
  useEffect(() => { ambil() }, [])

  const hantar = async (action, badan) => {
    const r = await fetch(`${asasApi}/ahli.php?action=${action}`, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrf() || '' },
      body: JSON.stringify(badan),
    })
    const d = await r.json()
    if (!d.ok) throw new Error(d.mesej)
    return d
  }

  const buat = async (id, action, badan, mesej) => {
    setSibukId(id)
    try { await hantar(action, badan); toast(mesej, 'ok'); ambil() }
    catch (e) { toast(e.message, 'ralat') } finally { setSibukId(0) }
  }

  if (!data) return null

  const k = data.kiraan
  const senarai = data.ahli.filter((a) => (tapis === 'semua' ? true : a.status === tapis))

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2"><ShieldCheck className="size-4" />Keahlian SAMFIRE FC</CardTitle>
        <Badge jenis={k.baru ? 'maroon' : 'kelabu'}>{k.baru} menunggu</Badge>
      </CardHeader>
      <CardBody className="space-y-4">

        {/* ---- Tetapan bayaran ---- */}
        <div className="rounded-xl border border-stone-200 p-3 dark:border-stone-800">
          <p className="mb-2 flex items-center gap-2 text-[12px] font-semibold text-stone-600 dark:text-stone-400">
            <Wallet className="size-3.5" />Butiran bayaran yang dipapar kepada pemohon
          </p>
          <div className="grid gap-2 sm:grid-cols-2">
            {[
              ['yuran_ahli', 'Yuran keahlian', 'RM15'],
              ['bayar_kepada', 'Atas nama', 'SAMFIRE FOOTBALL CLUB'],
              ['bayar_bank', 'Bank', 'Maybank / CIMB'],
              ['bayar_akaun', 'No. akaun', '1234 5678 9012'],
            ].map(([kk, label, ph]) => (
              <div key={kk}>
                <Label>{label}</Label>
                <Input value={bayar[kk]} placeholder={ph} onChange={(e) => setBayar({ ...bayar, [kk]: e.target.value })} />
              </div>
            ))}
          </div>
          <div className="mt-3 flex flex-wrap gap-2">
            <Button ukuran="sm" onClick={() => buat(-1, 'tetapan', bayar, 'Butiran bayaran disimpan.')}>Simpan butiran</Button>
            {data.bayaran.buka
              ? <Button jenis="garis" ukuran="sm" onClick={() => buat(-1, 'tetapan', { ahli_buka: false }, 'Pendaftaran ahli DITUTUP.')}><Lock className="size-3.5" />Tutup pendaftaran</Button>
              : <Button jenis="navy" ukuran="sm" onClick={() => buat(-1, 'tetapan', { ahli_buka: true }, 'Pendaftaran ahli DIBUKA.')}><Unlock className="size-3.5" />Buka pendaftaran</Button>}
          </div>
        </div>

        {/* ---- Muat turun ---- */}
        <div className="flex flex-wrap gap-2">
          <a href={`${asasApi}/ahli.php?action=csv`}
             className="inline-flex items-center gap-2 rounded-lg border border-stone-300 px-3 py-2 text-xs font-semibold text-stone-700 hover:bg-stone-50 dark:border-stone-700 dark:text-stone-300 dark:hover:bg-stone-800">
            <FileSpreadsheet className="size-3.5" />Senarai Ahli (CSV)
          </a>
          <a href={`${asasApi}/ahli.php?action=csv&format=members`}
             className="inline-flex items-center gap-2 rounded-lg border border-stone-300 px-3 py-2 text-xs font-semibold text-stone-700 hover:bg-stone-50 dark:border-stone-700 dark:text-stone-300 dark:hover:bg-stone-800">
            <FileSpreadsheet className="size-3.5" />Format import samfirefc.com
          </a>
          <Button jenis="senyap" ukuran="sm" className="ml-auto" onClick={ambil}><RefreshCw className="size-4" /></Button>
        </div>

        {/* ---- Tapisan ---- */}
        <div className="flex flex-wrap gap-1.5">
          {[['baru', `Menunggu (${k.baru})`], ['lulus', `Ahli sah (${k.lulus})`], ['tolak', `Ditolak (${k.tolak})`], ['semua', 'Semua']].map(([v, label]) => (
            <button key={v} onClick={() => setTapis(v)}
              className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                tapis === v ? 'bg-maroon-700 text-white' : 'bg-stone-100 text-stone-600 dark:bg-stone-800 dark:text-stone-300'}`}>
              {label}
            </button>
          ))}
        </div>

        {/* ---- Senarai ---- */}
        {senarai.length === 0 ? (
          <p className="rounded-lg border border-dashed border-stone-300 py-6 text-center text-[12px] text-stone-400 dark:border-stone-700">
            Tiada rekod dalam tapisan ini.
          </p>
        ) : (
          <div className="divide-y divide-stone-100 overflow-hidden rounded-lg border border-stone-200 dark:divide-stone-900 dark:border-stone-800">
            {senarai.map((a) => (
              <div key={a.id} className="px-3 py-3">
                <div className="flex items-center gap-3">
                  {a.gambar_url
                    ? <img src={a.gambar_url} alt="" className="size-11 shrink-0 rounded-full object-cover" />
                    : <div className="grid size-11 shrink-0 place-items-center rounded-full bg-stone-100 text-xs font-bold text-stone-400 dark:bg-stone-800">{a.nama.slice(0, 2).toUpperCase()}</div>}
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-bold">{a.nama}{a.nama_panggilan ? ` (${a.nama_panggilan})` : ''}</p>
                    <p className="truncate text-[12px] text-stone-500">
                      {a.no_kp} · <a href={`tel:${a.telefon}`} className="inline-flex items-center gap-0.5 text-maroon-700 dark:text-maroon-300"><Phone className="size-3" />{a.telefon}</a>
                    </p>
                    <p className="truncate text-[11px] text-stone-400">
                      {[a.bandar, a.negeri].filter(Boolean).join(', ')}
                      {a.posisi ? ` · ${a.posisi}` : ''}{a.no_jersi ? ` · #${a.no_jersi}` : ''}
                      {a.pemain_idola ? ` · idola ${a.pemain_idola}` : ''}
                    </p>
                  </div>
                  {a.status === 'baru' ? (
                    <div className="flex shrink-0 gap-1.5">
                      <Button ukuran="sm" disabled={sibukId === a.id}
                              onClick={() => buat(a.id, 'lulus', { id: a.id }, `${a.nama} disahkan sebagai ahli.`)}>
                        <Check className="size-3.5" />Sah
                      </Button>
                      <Button jenis="garis" ukuran="sm" disabled={sibukId === a.id}
                              onClick={() => buat(a.id, 'tolak', { id: a.id }, 'Permohonan ditolak.')}>
                        <X className="size-3.5" />
                      </Button>
                    </div>
                  ) : (
                    <div className="flex shrink-0 items-center gap-1.5">
                      <Badge jenis={a.status === 'lulus' ? 'hijau' : 'maroon'}>
                        {a.status === 'lulus' ? 'Ahli sah' : 'Ditolak'}
                      </Badge>
                      {admin.role === 'super' && (
                        <Button jenis="senyap" ukuran="sm" className="text-red-600" disabled={sibukId === a.id}
                                onClick={() => { if (confirm(`Padam rekod ${a.nama}?`)) buat(a.id, 'padam', { id: a.id }, 'Rekod dipadam.') }}>
                          <Trash2 className="size-3.5" />
                        </Button>
                      )}
                    </div>
                  )}
                </div>
                {a.bukti_url && (
                  <a href={a.bukti_url} target="_blank" rel="noreferrer"
                     className="mt-2 inline-block text-[11px] font-semibold text-maroon-700 hover:underline dark:text-maroon-300"
                     style={{ marginLeft: '3.5rem' }}>
                    Lihat bukti pembayaran
                  </a>
                )}
              </div>
            ))}
          </div>
        )}

        <p className="text-[11px] leading-relaxed text-stone-400">
          Medan borang ini diselaraskan dengan jadual <code>members</code> sistem keahlian SAMFIRE FC sedia ada.
          Guna butang <strong>Format import samfirefc.com</strong> untuk fail CSV yang lajurnya sama persis —
          boleh diimport terus ke database samfirefc.com melalui phpMyAdmin.
        </p>
      </CardBody>
    </Card>
  )
}
