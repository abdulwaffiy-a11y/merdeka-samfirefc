import { useState, useEffect } from 'react'
import { Save, UserPlus, Trash2, Users } from 'lucide-react'
import { Card, CardHeader, CardTitle, Button, Input, Label, Dialog, Badge, useToast } from '../ui'
import { api } from '../lib/api'

function DialogPemain({ pasukan, pemainAsal, tutup, selepas }) {
  const toast = useToast()
  const [senarai, setSenarai] = useState(() =>
    (pemainAsal || []).map((p) => ({ nama: p.nama, no_jersi: p.no_jersi || '' })),
  )
  const [sibuk, setSibuk] = useState(false)

  const ubah = (i, k, v) => setSenarai((s) => s.map((p, j) => (j === i ? { ...p, [k]: v } : p)))

  const simpan = async () => {
    setSibuk(true)
    try {
      await api.pemainSimpan(pasukan.id, senarai.filter((p) => p.nama.trim() !== ''))
      toast('Senarai pemain disimpan.', 'ok')
      selepas()
      tutup()
    } catch (e) { toast(e.message, 'ralat') } finally { setSibuk(false) }
  }

  return (
    <Dialog buka tutup={tutup} tajuk={`Pemain — ${pasukan.nama || `Slot ${pasukan.kumpulan}${pasukan.slot}`}`}>
      <div className="space-y-2">
        {senarai.length === 0 && <p className="py-4 text-center text-sm text-stone-500">Belum ada pemain. Tekan “Tambah pemain”.</p>}
        {senarai.map((p, i) => (
          <div key={i} className="flex gap-2">
            <Input value={p.no_jersi} onChange={(e) => ubah(i, 'no_jersi', e.target.value.replace(/\D/g, '').slice(0, 2))} placeholder="No" className="w-16 text-center" />
            <Input value={p.nama} onChange={(e) => ubah(i, 'nama', e.target.value)} placeholder="Nama pemain" />
            <button onClick={() => setSenarai((s) => s.filter((_, j) => j !== i))} className="grid size-10 shrink-0 place-items-center rounded-lg text-stone-400 hover:bg-red-50 hover:text-red-600">
              <Trash2 className="size-4" />
            </button>
          </div>
        ))}
        {senarai.length < 10 && (
          <Button jenis="garis" className="w-full" onClick={() => setSenarai((s) => [...s, { nama: '', no_jersi: '' }])}>
            <UserPlus className="size-4" />Tambah pemain
          </Button>
        )}
        <div className="flex gap-2 pt-2">
          <Button jenis="garis" className="flex-1" onClick={tutup}>Batal</Button>
          <Button className="flex-1" disabled={sibuk} onClick={simpan}><Save className="size-4" />Simpan</Button>
        </div>
      </div>
    </Dialog>
  )
}

export default function PasukanAdmin({ muatSemula }) {
  const toast = useToast()
  const [pasukan, setPasukan] = useState([])
  const [pemain, setPemain] = useState({})
  const [sibuk, setSibuk] = useState(false)
  const [memuat, setMemuat] = useState(true)
  const [dialog, setDialog] = useState(null)

  const ambil = async () => {
    try {
      const r = await api.pasukanSenarai()
      setPasukan(r.pasukan)
      setPemain(r.pemain || {})
    } catch (e) { toast(e.message, 'ralat') } finally { setMemuat(false) }
  }
  useEffect(() => { ambil() }, [])

  const ubah = (id, k, v) => setPasukan((s) => s.map((t) => (t.id === id ? { ...t, [k]: v } : t)))

  const simpan = async () => {
    setSibuk(true)
    try {
      const r = await api.pasukanSimpan(pasukan.map((t) => ({
        id: t.id, nama: t.nama, singkatan: t.singkatan, pengurus: t.pengurus, telefon: t.telefon, tiebreak: t.tiebreak,
      })))
      setPasukan(r.pasukan)
      toast('Senarai pasukan disimpan.', 'ok')
      muatSemula()
    } catch (e) { toast(e.message, 'ralat') } finally { setSibuk(false) }
  }

  if (memuat) return <Card className="p-8 text-center text-sm text-stone-500">Memuatkan…</Card>

  const huruf = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']
  const diisi = pasukan.filter((t) => (t.nama || '').trim() !== '').length

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-3">
        <Badge jenis="maroon">{diisi} / 24 pasukan dinamakan</Badge>
        <p className="text-xs text-stone-500">Nama pasukan boleh diubah bila-bila masa — kedudukan &amp; carta tidak terjejas.</p>
        <Button className="ml-auto" onClick={simpan} disabled={sibuk}>
          <Save className="size-4" />{sibuk ? 'Menyimpan…' : 'Simpan Semua'}
        </Button>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        {huruf.map((h) => (
          <Card key={h}>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <span className="grid size-6 place-items-center rounded-md bg-maroon-700 text-xs font-black text-white">{h}</span>
                Kumpulan {h}
              </CardTitle>
            </CardHeader>
            <div className="divide-y divide-stone-100 dark:divide-stone-900">
              {pasukan.filter((t) => t.kumpulan === h).map((t) => (
                <div key={t.id} className="space-y-2 p-3">
                  <div className="flex gap-2">
                    <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-stone-100 text-xs font-bold text-stone-500 dark:bg-stone-800">{h}{t.slot}</span>
                    <Input value={t.nama || ''} onChange={(e) => ubah(t.id, 'nama', e.target.value)} placeholder={`Nama pasukan ${h}${t.slot}`} maxLength={80} />
                  </div>
                  <div className="flex gap-2 pl-12">
                    <Input value={t.pengurus || ''} onChange={(e) => ubah(t.id, 'pengurus', e.target.value)} placeholder="Pengurus" className="h-9 text-xs" />
                    <Input value={t.telefon || ''} onChange={(e) => ubah(t.id, 'telefon', e.target.value)} placeholder="Telefon" className="h-9 w-32 text-xs" />
                    <button
                      onClick={() => setDialog(t)}
                      className="flex h-9 shrink-0 items-center gap-1.5 rounded-lg border border-stone-300 px-2.5 text-xs font-semibold text-stone-600 hover:bg-stone-50 dark:border-stone-700 dark:text-stone-300"
                    >
                      <Users className="size-3.5" />{(pemain[t.id] || []).length}
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </Card>
        ))}
      </div>

      <Card>
        <CardHeader><CardTitle>Pemecah seri manual (undian kumpulan)</CardTitle></CardHeader>
        <div className="p-4">
          <p className="mb-3 text-xs text-stone-500">
            Guna ruangan ini <strong>hanya jika</strong> dua pasukan dalam kumpulan yang sama betul-betul seri
            (mata, beza gol, jumlah gol dan perlawanan bersemuka semuanya sama). Isikan nombor hasil cabutan undi —
            nombor lebih kecil bermakna kedudukan lebih tinggi. Biarkan 0 jika tidak berkenaan.
          </p>
          <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            {pasukan.map((t) => (
              <div key={t.id} className="flex items-center gap-2">
                <span className="w-8 shrink-0 text-[11px] font-bold text-stone-400">{t.kumpulan}{t.slot}</span>
                <span className="min-w-0 flex-1 truncate text-xs">{t.nama || '—'}</span>
                <Input
                  value={t.tiebreak ?? 0}
                  onChange={(e) => ubah(t.id, 'tiebreak', parseInt(e.target.value.replace(/\D/g, '') || '0', 10))}
                  className="h-8 w-14 text-center text-xs"
                  inputMode="numeric"
                />
              </div>
            ))}
          </div>
        </div>
      </Card>

      {dialog && (
        <DialogPemain
          pasukan={dialog}
          pemainAsal={pemain[dialog.id]}
          tutup={() => setDialog(null)}
          selepas={() => { ambil(); muatSemula() }}
        />
      )}
    </div>
  )
}
