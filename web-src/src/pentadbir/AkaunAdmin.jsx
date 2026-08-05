import { useEffect, useState } from 'react'
import { UserPlus, Trash2, KeyRound, ShieldCheck, Lock, Unlock, Megaphone } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardBody, Button, Input, Label, Select, Badge, Dialog, useToast } from '../ui'
import { api } from '../lib/api'

export default function AkaunAdmin({ admin, awam, muatSemula }) {
  const toast = useToast()
  const [senarai, setSenarai] = useState([])
  const [sibuk, setSibuk] = useState(false)
  const [baru, setBaru] = useState({ nama: '', email: '', password: '', role: 'admin' })
  const [resetUntuk, setResetUntuk] = useState(null)
  const [passBaru, setPassBaru] = useState('')
  const [pengumuman, setPengumuman] = useState(awam.tetapan.pengumuman || '')
  const [tukarPass, setTukarPass] = useState({ lama: '', baru: '' })

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

      {admin.role !== 'super' ? (
        <Card className="p-6 text-center text-sm text-stone-500">
          Pengurusan akaun dan tetapan kejohanan hanya boleh diakses oleh Super Admin.
        </Card>
      ) : (
        <>
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
