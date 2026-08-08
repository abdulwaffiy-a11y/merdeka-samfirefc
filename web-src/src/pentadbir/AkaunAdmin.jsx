import { useEffect, useState } from 'react'
import { UserPlus, Trash2, KeyRound, ShieldCheck, Lock, Unlock, Megaphone, Image as ImageIcon } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardBody, Button, Input, Label, Select, Badge, Dialog, useToast } from '../ui'
import { api, getCsrf, jsonSelamat } from '../lib/api'
import GaleriAdmin from './GaleriAdmin'
import SandaranAdmin from './SandaranAdmin'
import SijilAdmin from './SijilAdmin'
import AhliAdmin from './AhliAdmin'

const asasApi = new URL('api/', document.baseURI).href.replace(/\/$/, '')

export default function AkaunAdmin({ admin, awam, muatSemula }) {
  const toast = useToast()
  const [senarai, setSenarai] = useState([])
  const [sibuk, setSibuk] = useState(false)
  const [baru, setBaru] = useState({ nama: '', email: '', password: '', role: 'admin' })
  const [resetUntuk, setResetUntuk] = useState(null)
  const [passBaru, setPassBaru] = useState('')
  const [pengumuman, setPengumuman] = useState(awam.tetapan.pengumuman || '')
  const [tukarPass, setTukarPass] = useState({ lama: '', baru: '' })
  const [naikSibuk, setNaikSibuk] = useState(false)
  const [butiran, setButiran] = useState({
    yuran: awam.tetapan.yuran || '',
    telefon_urusetia: awam.tetapan.telefon_urusetia || '',
    url_website: awam.tetapan.url_website || '',
    url_daftar_ahli: awam.tetapan.url_daftar_ahli || '',
  })

  const naikPoster = async (fail) => {
    if (!fail) return
    if (fail.size > 4194304) { toast('Poster melebihi 4MB.', 'ralat'); return }
    setNaikSibuk(true)
    try {
      const fd = new FormData()
      fd.append('poster', fail)
      const r = await fetch(`${asasApi}/poster.php?action=naik`, {
        method: 'POST', body: fd, credentials: 'same-origin',
        headers: { 'X-CSRF-Token': getCsrf() || '' },
      })
      const d = await jsonSelamat(r)
      if (!d.ok) throw new Error(d.mesej || 'Muat naik gagal.')
      toast(`Poster dimuat naik (${d.dimensi}).`, 'ok')
      muatSemula()
    } catch (e) { toast(e.message, 'ralat') } finally { setNaikSibuk(false) }
  }

  const buangPoster = async () => {
    if (!confirm('Buang poster dari halaman utama?')) return
    try {
      const r = await fetch(`${asasApi}/poster.php?action=buang`, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrf() || '' },
        body: '{}',
      })
      const d = await jsonSelamat(r)
      if (!d.ok) throw new Error(d.mesej)
      toast('Poster dibuang.', 'ok')
      muatSemula()
    } catch (e) { toast(e.message, 'ralat') }
  }

  const ambil = async () => {
    try { setSenarai((await api.adminSenarai()).admins) } catch (e) { toast(e.message, 'ralat') }
  }
  useEffect(() => { if (admin.role === 'super') ambil() }, [])

  const tambah = async () => {
    setSibuk(true)
    try {
      await api.adminTambah(baru)
      setBaru({ nama: '', email: '', password: '', role: 'admin' })
      toast('Akaun admin ditambah.', 'ok')
      ambil()
    } catch (e) { toast(e.message, 'ralat') } finally { setSibuk(false) }
  }

  const buang = async (u) => {
    if (!confirm(`Buang akaun ${u.email}?`)) return
    try { await api.adminBuang(u.id); toast('Akaun dibuang.', 'ok'); ambil() }
    catch (e) { toast(e.message, 'ralat') }
  }

  const simpanTetapan = async (d) => {
    try { await api.tetapanSimpan(d); toast('Tetapan disimpan.', 'ok'); muatSemula() }
    catch (e) { toast(e.message, 'ralat') }
  }

  const tukarPasswordSaya = async () => {
    try {
      await api.tukarPassword(tukarPass.lama, tukarPass.baru)
      setTukarPass({ lama: '', baru: '' })
      toast('Kata laluan anda telah ditukar.', 'ok')
    } catch (e) { toast(e.message, 'ralat') }
  }

  return (
    <div className="space-y-4">
      {/* ---- Kata laluan sendiri ---- */}
      <Card>
        <CardHeader><CardTitle className="flex items-center gap-2"><KeyRound className="size-4" />Tukar Kata Laluan Saya</CardTitle></CardHeader>
        <CardBody className="flex flex-wrap items-end gap-3">
          <div className="min-w-40 flex-1">
            <Label>Kata laluan semasa</Label>
            <Input type="password" value={tukarPass.lama} onChange={(e) => setTukarPass({ ...tukarPass, lama: e.target.value })} />
          </div>
          <div className="min-w-40 flex-1">
            <Label>Kata laluan baharu</Label>
            <Input type="password" value={tukarPass.baru} onChange={(e) => setTukarPass({ ...tukarPass, baru: e.target.value })} />
          </div>
          <Button onClick={tukarPasswordSaya} disabled={tukarPass.baru.length < 8}>Tukar</Button>
        </CardBody>
      </Card>

      <AhliAdmin admin={admin} />

      <SijilAdmin />

      <SandaranAdmin />

      <GaleriAdmin />

      {admin.role !== 'super' ? (
        <Card className="p-6 text-center text-sm text-stone-500">
          Pengurusan akaun dan tetapan kejohanan hanya boleh diakses oleh Super Admin.
        </Card>
      ) : (
        <>
          {/* ---- Poster ---- */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2"><ImageIcon className="size-4" />Poster Kejohanan</CardTitle>
              {awam.tetapan.poster && <Badge jenis="hijau">Dipaparkan</Badge>}
            </CardHeader>
            <CardBody className="space-y-3">
              <p className="text-[12px] text-stone-500">
                Dipaparkan di halaman Utama, di bawah kotak merah. Saiz disyorkan <strong>1080 × 1350 px</strong> (nisbah 4:5) —
                muat penuh lebar telefon tanpa terlalu tinggi. Apa-apa saiz diterima; sistem kecilkan sendiri kepada lebar 1080px.
                Maksimum 4MB, format JPG / PNG / WEBP.
              </p>
              <div className="flex flex-wrap items-start gap-4">
                {awam.tetapan.poster && (
                  <img src={awam.tetapan.poster} alt="" className="w-28 rounded-lg border border-stone-200 dark:border-stone-700" />
                )}
                <div className="flex min-w-48 flex-1 flex-col gap-2">
                  <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    onChange={(e) => naikPoster(e.target.files?.[0])}
                    className="w-full max-w-full min-w-0 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-maroon-700 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white"
                  />
                  {naikSibuk && <p className="text-[12px] text-stone-500">Memuat naik…</p>}
                  {awam.tetapan.poster && (
                    <Button jenis="garis" ukuran="sm" className="self-start" onClick={buangPoster}>
                      <Trash2 className="size-3.5" />Buang poster
                    </Button>
                  )}
                </div>
              </div>
            </CardBody>
          </Card>

          {/* ---- Butiran kejohanan ---- */}
          <Card>
            <CardHeader><CardTitle>Butiran Kejohanan</CardTitle></CardHeader>
            <CardBody className="grid gap-3 sm:grid-cols-2">
              {[
                ['yuran', 'Yuran penyertaan', 'RM200'],
                ['telefon_urusetia', 'Telefon urus setia', '019-123 4567'],
                ['url_website', 'URL laman web SAMFIRE FC', 'https://samfirefc.com'],
                ['url_daftar_ahli', 'URL daftar ahli SAMFIRE FC', 'https://samfirefc.com/daftar'],
              ].map(([k, label, ph]) => (
                <div key={k}>
                  <Label>{label}</Label>
                  <div className="flex gap-2">
                    <Input value={butiran[k] ?? ''} placeholder={ph}
                           onChange={(e) => setButiran({ ...butiran, [k]: e.target.value })} />
                    <Button ukuran="sm" onClick={() => simpanTetapan({ [k]: butiran[k] ?? '' })}>Simpan</Button>
                  </div>
                </div>
              ))}
            </CardBody>
          </Card>

          {/* ---- Pengumuman & kunci ---- */}
          <Card>
            <CardHeader><CardTitle className="flex items-center gap-2"><Megaphone className="size-4" />Pengumuman & Kunci Keputusan</CardTitle></CardHeader>
            <CardBody className="space-y-4">
              <div>
                <Label>Pengumuman di paparan awam (kosongkan untuk buang)</Label>
                <div className="flex gap-2">
                  <Input value={pengumuman} onChange={(e) => setPengumuman(e.target.value)} placeholder="cth: Perlawanan ditangguh 15 minit" maxLength={300} />
                  <Button onClick={() => simpanTetapan({ pengumuman })}>Simpan</Button>
                </div>
              </div>
              <div className="flex flex-wrap items-center gap-3 rounded-lg border border-stone-200 p-3 dark:border-stone-800">
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-semibold">Kunci keputusan kejohanan</p>
                  <p className="text-[12px] text-stone-500">
                    Bila dikunci, tiada sesiapa boleh mengubah skor, pasukan atau undian. Guna selepas majlis penyampaian hadiah.
                  </p>
                </div>
                {awam.tetapan.dikunci
                  ? <Button jenis="garis" onClick={() => simpanTetapan({ keputusan_dikunci: false })}><Unlock className="size-4" />Buka Kunci</Button>
                  : <Button jenis="navy" onClick={() => simpanTetapan({ keputusan_dikunci: true })}><Lock className="size-4" />Kunci Sekarang</Button>}
              </div>
            </CardBody>
          </Card>

          {/* ---- Senarai admin ---- */}
          <Card>
            <CardHeader><CardTitle className="flex items-center gap-2"><ShieldCheck className="size-4" />Akaun Admin</CardTitle></CardHeader>
            <div className="divide-y divide-stone-100 dark:divide-stone-900">
              {senarai.map((u) => (
                <div key={u.id} className="flex flex-wrap items-center gap-2 px-4 py-3">
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold">{u.nama} {u.id === admin.id && <span className="text-[11px] font-normal text-stone-400">(anda)</span>}</p>
                    <p className="truncate text-[12px] text-stone-500">{u.email}</p>
                  </div>
                  <Badge jenis={u.role === 'super' ? 'maroon' : 'kelabu'}>{u.role === 'super' ? 'Super Admin' : 'Admin'}</Badge>
                  {!u.aktif && <Badge jenis="kelabu">Tidak aktif</Badge>}
                  <Button jenis="senyap" ukuran="sm" onClick={() => { setResetUntuk(u); setPassBaru('') }}><KeyRound className="size-3.5" /></Button>
                  {u.id !== admin.id && (
                    <Button jenis="senyap" ukuran="sm" onClick={() => buang(u)} className="text-red-600"><Trash2 className="size-3.5" /></Button>
                  )}
                </div>
              ))}
            </div>
            <CardBody className="border-t border-stone-200 dark:border-stone-800">
              <p className="mb-2 text-xs font-semibold text-stone-500">Tambah admin baharu</p>
              <div className="grid gap-2 sm:grid-cols-2">
                <Input placeholder="Nama" value={baru.nama} onChange={(e) => setBaru({ ...baru, nama: e.target.value })} />
                <Input placeholder="Emel" type="email" value={baru.email} onChange={(e) => setBaru({ ...baru, email: e.target.value })} />
                <Input placeholder="Kata laluan (min 8 aksara)" type="text" value={baru.password} onChange={(e) => setBaru({ ...baru, password: e.target.value })} />
                <Select value={baru.role} onChange={(e) => setBaru({ ...baru, role: e.target.value })}>
                  <option value="admin">Admin — kemaskini skor & undian</option>
                  <option value="super">Super Admin — kawalan penuh</option>
                </Select>
              </div>
              <Button className="mt-3" onClick={tambah} disabled={sibuk}><UserPlus className="size-4" />Tambah Akaun</Button>
            </CardBody>
          </Card>
        </>
      )}

      <Dialog buka={!!resetUntuk} tutup={() => setResetUntuk(null)} tajuk={`Tetapkan kata laluan — ${resetUntuk?.nama || ''}`}>
        <div className="space-y-3">
          <div>
            <Label>Kata laluan baharu (min 8 aksara)</Label>
            <Input value={passBaru} onChange={(e) => setPassBaru(e.target.value)} />
          </div>
          <div className="flex gap-2">
            <Button jenis="garis" className="flex-1" onClick={() => setResetUntuk(null)}>Batal</Button>
            <Button
              className="flex-1"
              disabled={passBaru.length < 8}
              onClick={async () => {
                try {
                  await api.adminResetPass(resetUntuk.id, passBaru)
                  toast('Kata laluan ditetapkan.', 'ok')
                  setResetUntuk(null)
                } catch (e) { toast(e.message, 'ralat') }
              }}
            >Tetapkan</Button>
          </div>
        </div>
      </Dialog>
    </div>
  )
}
