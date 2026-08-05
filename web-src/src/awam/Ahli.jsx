import { useEffect, useRef, useState } from 'react'
import { ShieldCheck, Upload, Trash2, CheckCircle2, Loader2, Wallet, Users } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardBody, Button, Input, Label, Select, Badge, useToast } from '../ui'

const asasApi = new URL('api/', document.baseURI).href.replace(/\/$/, '')

const NEGERI = ['Pulau Pinang','Kedah','Perak','Perlis','Selangor','Kuala Lumpur','Putrajaya','Negeri Sembilan',
  'Melaka','Johor','Pahang','Terengganu','Kelantan','Sabah','Sarawak','Labuan']

const POSISI = ['Penjaga Gol','Pertahanan','Tengah','Penyerang','Belum pasti']

export default function Ahli() {
  const toast = useToast()
  const [info, setInfo] = useState(null)
  const [f, setF] = useState({
    nama: '', nama_panggilan: '', no_kp: '', tarikh_lahir: '', jantina: 'lelaki',
    telefon: '', emel: '', alamat: '', bandar: '', poskod: '', negeri: 'Pulau Pinang',
    posisi: '', no_jersi: '', pemain_idola: '',
  })
  const [gambar, setGambar] = useState(null)
  const [gambarUrl, setGambarUrl] = useState('')
  const [bukti, setBukti] = useState(null)
  const [sibuk, setSibuk] = useState(false)
  const [berjaya, setBerjaya] = useState('')
  const refG = useRef(null)
  const refB = useRef(null)

  useEffect(() => {
    fetch(`${asasApi}/ahli.php?action=info`).then((r) => r.json())
      .then((d) => { if (d.ok) setInfo(d) }).catch(() => {})
  }, [])

  const set = (k) => (e) => setF({ ...f, [k]: e.target.value })

  const pilihImej = (e, jenis) => {
    const fail = e.target.files?.[0]
    if (!fail) return
    if (fail.size > 5242880) { toast('Imej melebihi 5MB.', 'ralat'); e.target.value = ''; return }
    if (!/^image\/(jpeg|png|webp)$/.test(fail.type)) { toast('Imej mesti JPG, PNG atau WEBP.', 'ralat'); e.target.value = ''; return }
    if (jenis === 'gambar') { setGambar(fail); setGambarUrl(URL.createObjectURL(fail)) }
    else setBukti(fail)
  }

  const hantar = async (e) => {
    e.preventDefault()
    setSibuk(true)
    try {
      const fd = new FormData()
      Object.entries(f).forEach(([k, v]) => fd.append(k, v))
      fd.append('website', '')
      if (gambar) fd.append('gambar', gambar)
      if (bukti) fd.append('bukti', bukti)

      const r = await fetch(`${asasApi}/ahli.php?action=hantar`, { method: 'POST', body: fd })
      const d = await r.json()
      if (!d.ok) throw new Error(d.mesej || 'Pendaftaran gagal.')

      setBerjaya(d.mesej)
      setF({ nama: '', nama_panggilan: '', no_kp: '', tarikh_lahir: '', jantina: 'lelaki',
        telefon: '', emel: '', alamat: '', bandar: '', poskod: '', negeri: 'Pulau Pinang',
        posisi: '', no_jersi: '', pemain_idola: '' })
      setGambar(null); setGambarUrl(''); setBukti(null)
      if (refG.current) refG.current.value = ''
      if (refB.current) refB.current.value = ''
    } catch (err) { toast(err.message, 'ralat') } finally { setSibuk(false) }
  }

  const b = info?.bayaran
  const buka = b ? b.buka : true

  return (
    <div className="space-y-4">
      {/* ---- Hero ---- */}
      <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-navy-900 via-navy-800 to-maroon-800 p-5 shadow-lg sm:p-6">
        <div className="pointer-events-none absolute -right-8 -top-10 size-36 rounded-full bg-gold-500/20 blur-2xl" />
        <div className="relative">
          <Badge jenis="emas" className="bg-white/15 text-gold-300">Keahlian Kelab</Badge>
          <h1 className="mt-2 text-xl font-black leading-tight text-white sm:text-2xl">Daftar Ahli SAMFIRE FC</h1>
          <p className="mt-1.5 max-w-lg text-[13px] leading-relaxed text-white/80">
            Sertai keluarga SAMFIRE FC. Ahli berdaftar mendapat keutamaan penyertaan aktiviti kelab,
            latihan berkala, dan maklumat kejohanan terkini.
          </p>
          <div className="mt-3 flex flex-wrap items-center gap-3">
            <span className="inline-flex items-center gap-1.5 rounded-lg bg-gold-500 px-3 py-1.5 text-[13px] font-black text-navy-900">
              <Wallet className="size-3.5" />Yuran {b?.yuran || 'RM15'}
            </span>
            {info && (
              <span className="inline-flex items-center gap-1.5 text-[12px] text-white/70">
                <Users className="size-3.5" />{info.jumlah_ahli} ahli berdaftar
              </span>
            )}
          </div>
        </div>
      </div>

      {/* ---- Cara bayar ---- */}
      {b && (
        <Card>
          <CardHeader><CardTitle className="flex items-center gap-2"><Wallet className="size-4" />Cara Pembayaran</CardTitle></CardHeader>
          <CardBody className="space-y-1.5 text-[13px] text-stone-600 dark:text-stone-400">
            <p>Yuran keahlian <strong className="text-maroon-700 dark:text-maroon-300">{b.yuran}</strong> — bayaran secara manual.</p>
            {b.bayar_bank && <p>Bank: <strong>{b.bayar_bank}</strong></p>}
            {b.bayar_akaun && <p>No. akaun: <strong>{b.bayar_akaun}</strong></p>}
            <p>Atas nama: <strong>{b.bayar_kepada}</strong></p>
            {b.bayar_nota && <p className="pt-1 text-[12px] text-stone-500">{b.bayar_nota}</p>}
          </CardBody>
        </Card>
      )}

      {/* ---- Borang ---- */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2"><ShieldCheck className="size-4" />Borang Keahlian</CardTitle>
          {buka ? <Badge jenis="hijau">Dibuka</Badge> : <Badge jenis="maroon">Ditutup</Badge>}
        </CardHeader>

        {berjaya ? (
          <CardBody className="text-center">
            <CheckCircle2 className="mx-auto size-10 text-emerald-600" />
            <p className="mt-2 text-sm font-bold">Permohonan diterima!</p>
            <p className="mx-auto mt-1 max-w-sm text-[13px] text-stone-600 dark:text-stone-400">{berjaya}</p>
            <Button jenis="garis" className="mt-4" onClick={() => setBerjaya('')}>Daftar orang lain</Button>
          </CardBody>
        ) : !buka ? (
          <CardBody className="text-center text-sm text-stone-500">
            Pendaftaran keahlian sedang ditutup. Sila hubungi urus setia SAMFIRE FC.
          </CardBody>
        ) : (
          <form onSubmit={hantar}>
            <CardBody className="space-y-5">

              <div>
                <p className="mb-2 text-[11px] font-bold uppercase tracking-wide text-stone-400">Maklumat Peribadi</p>
                <div className="grid gap-3 sm:grid-cols-2">
                  <div className="sm:col-span-2">
                    <Label>Nama penuh (seperti dalam KP) *</Label>
                    <Input required minLength={3} maxLength={120} value={f.nama} onChange={set('nama')} placeholder="Nama penuh" />
                  </div>
                  <div>
                    <Label>Nama panggilan</Label>
                    <Input maxLength={60} value={f.nama_panggilan} onChange={set('nama_panggilan')} placeholder="cth: Wafi" />
                  </div>
                  <div>
                    <Label>No. kad pengenalan *</Label>
                    <Input required inputMode="numeric" maxLength={14} value={f.no_kp} onChange={set('no_kp')} placeholder="000000000000" />
                  </div>
                  <div>
                    <Label>Tarikh lahir</Label>
                    <Input type="date" value={f.tarikh_lahir} onChange={set('tarikh_lahir')} />
                  </div>
                  <div>
                    <Label>Jantina *</Label>
                    <Select value={f.jantina} onChange={set('jantina')}>
                      <option value="lelaki">Lelaki</option>
                      <option value="perempuan">Perempuan</option>
                    </Select>
                  </div>
                </div>
              </div>

              <div>
                <p className="mb-2 text-[11px] font-bold uppercase tracking-wide text-stone-400">Maklumat Perhubungan</p>
                <div className="grid gap-3 sm:grid-cols-2">
                  <div>
                    <Label>No. telefon *</Label>
                    <Input required type="tel" pattern="[0-9 +\-]{9,20}" maxLength={20} value={f.telefon} onChange={set('telefon')} placeholder="012-3456789" />
                  </div>
                  <div>
                    <Label>Emel</Label>
                    <Input type="email" maxLength={190} value={f.emel} onChange={set('emel')} placeholder="nama@contoh.com" />
                  </div>
                  <div className="sm:col-span-2">
                    <Label>Alamat kediaman</Label>
                    <Input maxLength={200} value={f.alamat} onChange={set('alamat')} placeholder="No. rumah, jalan, taman" />
                  </div>
                  <div>
                    <Label>Bandar</Label>
                    <Input maxLength={80} value={f.bandar} onChange={set('bandar')} placeholder="Kepala Batas" />
                  </div>
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <Label>Poskod</Label>
                      <Input inputMode="numeric" maxLength={5} value={f.poskod} onChange={set('poskod')} placeholder="13200" />
                    </div>
                    <div>
                      <Label>Negeri</Label>
                      <Select value={f.negeri} onChange={set('negeri')}>
                        {NEGERI.map((n) => <option key={n} value={n}>{n}</option>)}
                      </Select>
                    </div>
                  </div>
                </div>
              </div>

              <div>
                <p className="mb-2 text-[11px] font-bold uppercase tracking-wide text-stone-400">Maklumat Pemain</p>
                <div className="grid gap-3 sm:grid-cols-3">
                  <div>
                    <Label>Posisi pilihan</Label>
                    <Select value={f.posisi} onChange={set('posisi')}>
                      <option value="">— Pilih —</option>
                      {POSISI.map((p) => <option key={p} value={p}>{p}</option>)}
                    </Select>
                  </div>
                  <div>
                    <Label>No. jersi pilihan</Label>
                    <Input inputMode="numeric" maxLength={2} value={f.no_jersi} onChange={set('no_jersi')} placeholder="10" />
                  </div>
                  <div>
                    <Label>Pemain idola</Label>
                    <Input maxLength={120} value={f.pemain_idola} onChange={set('pemain_idola')} placeholder="cth: Messi, Ronaldo" />
                  </div>
                </div>
              </div>

              <div>
                <p className="mb-2 text-[11px] font-bold uppercase tracking-wide text-stone-400">Gambar (pilihan)</p>
                <div className="grid gap-3 sm:grid-cols-2">
                  <div>
                    <Label>Gambar diri</Label>
                    <div className="flex items-center gap-3">
                      {gambarUrl
                        ? <img src={gambarUrl} alt="" className="size-14 rounded-lg border border-stone-200 object-cover dark:border-stone-700" />
                        : <div className="grid size-14 place-items-center rounded-lg border border-dashed border-stone-300 text-stone-300 dark:border-stone-700"><Upload className="size-5" /></div>}
                      <input ref={refG} type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => pilihImej(e, 'gambar')}
                             className="text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-maroon-700 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white" />
                      {gambar && (
                        <button type="button" onClick={() => { setGambar(null); setGambarUrl(''); if (refG.current) refG.current.value = '' }}
                                className="text-stone-400 hover:text-red-600"><Trash2 className="size-4" /></button>
                      )}
                    </div>
                  </div>
                  <div>
                    <Label>Bukti pembayaran {b?.yuran || 'RM15'}</Label>
                    <input ref={refB} type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => pilihImej(e, 'bukti')}
                           className="mt-2 w-full text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-navy-800 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white" />
                    {bukti && <p className="mt-1 text-[11px] text-emerald-600">{bukti.name} sedia dihantar</p>}
                  </div>
                </div>
              </div>

              <Button type="submit" ukuran="lg" className="w-full" disabled={sibuk}>
                {sibuk ? <Loader2 className="size-4 animate-spin" /> : <ShieldCheck className="size-4" />}
                {sibuk ? 'Menghantar…' : 'Hantar Permohonan Keahlian'}
              </Button>

              <p className="text-center text-[11px] text-stone-400">
                Maklumat anda hanya digunakan untuk urusan keahlian SAMFIRE FC.
              </p>
            </CardBody>
          </form>
        )}
      </Card>
    </div>
  )
}
