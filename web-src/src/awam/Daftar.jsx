import { useEffect, useRef, useState } from 'react'
import { UserPlus, Users, Trash2, Upload, CheckCircle2, Loader2 } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardBody, Button, Input, Label, Badge, useToast } from '../ui'

const asasApi = new URL('api/', document.baseURI).href.replace(/\/$/, '')

export default function Daftar({ data }) {
  const toast = useToast()
  const t = data.tetapan

  const [info, setInfo] = useState(null)
  const [borang, setBorang] = useState({ nama: '', pengurus: '', telefon: '' })
  const [pemain, setPemain] = useState([{ nama: '', no_jersi: '' }])
  const [logo, setLogo] = useState(null)          // File
  const [logoUrl, setLogoUrl] = useState('')
  const [sibuk, setSibuk] = useState(false)
  const [berjaya, setBerjaya] = useState('')
  const failRef = useRef(null)

  const muatSenarai = async () => {
    try {
      const r = await fetch(`${asasApi}/daftar.php?action=senarai`)
      const d = await r.json()
      if (d.ok) setInfo(d)
    } catch { /* biarkan */ }
  }
  useEffect(() => { muatSenarai() }, [])

  const pilihLogo = (e) => {
    const f = e.target.files?.[0]
    if (!f) return
    if (f.size > 1048576) {
      toast('Logo melebihi 1MB. Sila pilih fail lebih kecil.', 'ralat')
      e.target.value = ''
      return
    }
    if (!/^image\/(jpeg|png|webp)$/.test(f.type)) {
      toast('Logo mesti JPG, PNG atau WEBP.', 'ralat')
      e.target.value = ''
      return
    }
    setLogo(f)
    setLogoUrl(URL.createObjectURL(f))
  }

  const hantar = async (e) => {
    e.preventDefault()
    setSibuk(true)
    try {
      const fd = new FormData()
      fd.append('nama', borang.nama)
      fd.append('pengurus', borang.pengurus)
      fd.append('telefon', borang.telefon)
      fd.append('pemain', JSON.stringify(pemain.filter((p) => p.nama.trim() !== '')))
      fd.append('website', '')                     // honeypot
      if (logo) fd.append('logo', logo)

      const r = await fetch(`${asasApi}/daftar.php?action=hantar`, { method: 'POST', body: fd })
      const d = await r.json()
      if (!d.ok) throw new Error(d.mesej || 'Pendaftaran gagal.')

      setBerjaya(d.mesej)
      setBorang({ nama: '', pengurus: '', telefon: '' })
      setPemain([{ nama: '', no_jersi: '' }])
      setLogo(null); setLogoUrl('')
      if (failRef.current) failRef.current.value = ''
      muatSenarai()
    } catch (err) {
      toast(err.message, 'ralat')
    } finally { setSibuk(false) }
  }

  const buka = info ? info.buka : t.pendaftaran_buka

  return (
    <div className="space-y-4">
      {/* ---- Status ---- */}
      {info && (
        <div className="grid grid-cols-3 gap-3">
          {[
            ['Berdaftar', info.jumlah],
            ['Disahkan', info.lulus],
            ['Slot Baki', info.baki],
          ].map(([l, v]) => (
            <Card key={l} className="p-3 text-center">
              <p className="tnum text-2xl font-black text-maroon-700 dark:text-maroon-300">{v}</p>
              <p className="mt-0.5 text-[11px] font-semibold uppercase tracking-wide text-stone-500">{l}</p>
            </Card>
          ))}
        </div>
      )}

      {/* ---- Borang ---- */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2"><UserPlus className="size-4" />Pendaftaran Pasukan</CardTitle>
          {buka ? <Badge jenis="hijau">Dibuka</Badge> : <Badge jenis="maroon">Ditutup</Badge>}
        </CardHeader>

        {berjaya ? (
          <CardBody className="text-center">
            <CheckCircle2 className="mx-auto size-10 text-emerald-600" />
            <p className="mt-2 text-sm font-bold">Pendaftaran diterima!</p>
            <p className="mx-auto mt-1 max-w-sm text-[13px] text-stone-600 dark:text-stone-400">{berjaya}</p>
            <Button jenis="garis" className="mt-4" onClick={() => setBerjaya('')}>Daftar pasukan lain</Button>
          </CardBody>
        ) : !buka ? (
          <CardBody className="text-center text-sm text-stone-500">
            Pendaftaran telah ditutup. Untuk pertanyaan, hubungi urus setia{t.telefon_urusetia ? ` di ${t.telefon_urusetia}` : ''}.
          </CardBody>
        ) : (
          <form onSubmit={hantar}>
            <CardBody className="space-y-4">
              <div className="rounded-lg bg-stone-50 px-3 py-2.5 text-[12px] text-stone-600 dark:bg-stone-900 dark:text-stone-400">
                Yuran penyertaan <strong>{t.yuran}</strong> setiap pasukan · maksimum <strong>10 pemain</strong> ·
                terbuka kepada penduduk yang menetap di Kepala Batas. Urus setia akan hubungi pengurus untuk pengesahan bayaran.
              </div>

              <div className="grid gap-3 sm:grid-cols-2">
                <div className="sm:col-span-2">
                  <Label>Nama pasukan *</Label>
                  <Input required minLength={3} maxLength={80} value={borang.nama}
                         onChange={(e) => setBorang({ ...borang, nama: e.target.value })} placeholder="cth: Belia Lubok Meriam FC" />
                </div>
                <div>
                  <Label>Nama pengurus *</Label>
                  <Input required minLength={3} maxLength={80} value={borang.pengurus}
                         onChange={(e) => setBorang({ ...borang, pengurus: e.target.value })} placeholder="Nama penuh" />
                </div>
                <div>
                  <Label>No. telefon pengurus *</Label>
                  <Input required type="tel" pattern="[0-9 +\-]{9,20}" maxLength={20} value={borang.telefon}
                         onChange={(e) => setBorang({ ...borang, telefon: e.target.value })} placeholder="012-3456789" />
                </div>
              </div>

              {/* Logo */}
              <div>
                <Label>Logo pasukan (pilihan · JPG/PNG/WEBP · maksimum 1MB)</Label>
                <div className="flex items-center gap-3">
                  {logoUrl
                    ? <img src={logoUrl} alt="" className="size-14 rounded-lg border border-stone-200 object-contain dark:border-stone-700" />
                    : <div className="grid size-14 place-items-center rounded-lg border border-dashed border-stone-300 text-stone-300 dark:border-stone-700"><Upload className="size-5" /></div>}
                  <input ref={failRef} type="file" accept="image/jpeg,image/png,image/webp" onChange={pilihLogo}
                         className="text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-maroon-700 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white" />
                  {logo && (
                    <button type="button" onClick={() => { setLogo(null); setLogoUrl(''); if (failRef.current) failRef.current.value = '' }}
                            className="text-stone-400 hover:text-red-600"><Trash2 className="size-4" /></button>
                  )}
                </div>
              </div>

              {/* Pemain */}
              <div>
                <Label>Senarai pemain (maksimum 10 — boleh lengkapkan kemudian)</Label>
                <div className="space-y-2">
                  {pemain.map((p, i) => (
                    <div key={i} className="flex gap-2">
                      <Input value={p.no_jersi} maxLength={4} placeholder="No"
                             onChange={(e) => setPemain((s) => s.map((x, j) => j === i ? { ...x, no_jersi: e.target.value } : x))}
                             className="w-16 text-center" />
                      <Input value={p.nama} maxLength={80} placeholder={`Pemain ${i + 1}`}
                             onChange={(e) => setPemain((s) => s.map((x, j) => j === i ? { ...x, nama: e.target.value } : x))} />
                      <button type="button" onClick={() => setPemain((s) => s.filter((_, j) => j !== i))}
                              className="grid size-10 shrink-0 place-items-center rounded-lg text-stone-400 hover:bg-red-50 hover:text-red-600">
                        <Trash2 className="size-4" />
                      </button>
                    </div>
                  ))}
                </div>
                {pemain.length < 10 && (
                  <Button type="button" jenis="garis" ukuran="sm" className="mt-2"
                          onClick={() => setPemain((s) => [...s, { nama: '', no_jersi: '' }])}>
                    <Users className="size-3.5" />Tambah pemain
                  </Button>
                )}
              </div>

              <Button type="submit" ukuran="lg" className="w-full" disabled={sibuk}>
                {sibuk ? <Loader2 className="size-4 animate-spin" /> : <UserPlus className="size-4" />}
                {sibuk ? 'Menghantar…' : 'Hantar Pendaftaran'}
              </Button>
            </CardBody>
          </form>
        )}
      </Card>

      {/* ---- Senarai berdaftar ---- */}
      {info && info.senarai.length > 0 && (
        <Card>
          <CardHeader><CardTitle>Pasukan Berdaftar ({info.jumlah})</CardTitle></CardHeader>
          <div className="divide-y divide-stone-100 dark:divide-stone-900">
            {info.senarai.map((s, i) => (
              <div key={i} className="flex items-center gap-3 px-4 py-2.5">
                {s.logo
                  ? <img src={`${asasApi}/uploads/${s.logo}`} alt="" className="size-8 shrink-0 rounded-md object-contain" loading="lazy" />
                  : <div className="grid size-8 shrink-0 place-items-center rounded-md bg-stone-100 text-[10px] font-bold text-stone-400 dark:bg-stone-800">{s.nama.slice(0, 2).toUpperCase()}</div>}
                <span className="min-w-0 flex-1 truncate text-sm font-medium">{s.nama}</span>
                {s.status === 'lulus'
                  ? <Badge jenis="hijau">Disahkan</Badge>
                  : <Badge>Menunggu pengesahan</Badge>}
              </div>
            ))}
          </div>
        </Card>
      )}
    </div>
  )
}
