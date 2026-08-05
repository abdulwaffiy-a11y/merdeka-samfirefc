import { useEffect, useState } from 'react'
import { ClipboardList, Check, X, Trash2, Phone, RefreshCw, Lock, Unlock } from 'lucide-react'
import { Card, CardHeader, CardTitle, Button, Badge, useToast } from '../ui'
import { api } from '../lib/api'

const asasApi = new URL('api/', document.baseURI).href.replace(/\/$/, '')

export default function DaftarAdmin({ admin, muatSemula }) {
  const toast = useToast()
  const [data, setData] = useState(null)
  const [sibukId, setSibukId] = useState(0)

  const ambil = async () => {
    try { setData(await api.daftarUrus()) } catch (e) { toast(e.message, 'ralat') }
  }
  useEffect(() => { ambil() }, [])

  const buat = async (id, fn, mesej) => {
    setSibukId(id)
    try {
      await fn()
      toast(mesej, 'ok')
      await ambil()
      muatSemula()
    } catch (e) { toast(e.message, 'ralat') } finally { setSibukId(0) }
  }

  if (!data) return <Card className="p-8 text-center text-sm text-stone-500">Memuatkan…</Card>

  const baru  = data.senarai.filter((s) => s.status === 'baru')
  const lulus = data.senarai.filter((s) => s.status === 'lulus')
  const tolak = data.senarai.filter((s) => s.status === 'tolak')

  const Baris = ({ s }) => (
    <div className="px-4 py-3">
      <div className="flex items-center gap-3">
        {s.logo
          ? <img src={`${asasApi}/uploads/${s.logo}`} alt="" className="size-10 shrink-0 rounded-lg border border-stone-200 object-contain dark:border-stone-700" />
          : <div className="grid size-10 shrink-0 place-items-center rounded-lg bg-stone-100 text-xs font-bold text-stone-400 dark:bg-stone-800">{s.nama.slice(0, 2).toUpperCase()}</div>}
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-bold">{s.nama}</p>
          <p className="truncate text-[12px] text-stone-500">
            {s.pengurus} · <a href={`tel:${s.telefon}`} className="inline-flex items-center gap-0.5 text-maroon-700 dark:text-maroon-300"><Phone className="size-3" />{s.telefon}</a>
            {' '}· {s.pemain.length} pemain
          </p>
        </div>
        {s.status === 'baru' && (
          <>
            <Button ukuran="sm" disabled={sibukId === s.id}
                    onClick={() => buat(s.id, () => api.daftarLulus(s.id), `${s.nama} diluluskan — sedia untuk undian kumpulan.`)}>
              <Check className="size-3.5" />Lulus
            </Button>
            <Button ukuran="sm" jenis="garis" disabled={sibukId === s.id}
                    onClick={() => buat(s.id, () => api.daftarTolak(s.id, ''), 'Pendaftaran ditolak.')}>
              <X className="size-3.5" />
            </Button>
          </>
        )}
        {s.status === 'lulus' && (s.team_id
          ? <Badge jenis="hijau">Slot ditetapkan</Badge>
          : <Badge jenis="emas">Menunggu undian kumpulan</Badge>)}
        {s.status === 'tolak' && (
          <>
            <Badge jenis="maroon">Ditolak</Badge>
            {admin.role === 'super' && (
              <Button ukuran="sm" jenis="senyap" className="text-red-600" disabled={sibukId === s.id}
                      onClick={() => buat(s.id, () => api.daftarPadam(s.id), 'Rekod dipadam.')}>
                <Trash2 className="size-3.5" />
              </Button>
            )}
          </>
        )}
      </div>
      {s.pemain.length > 0 && (
        <p className="mt-1.5 truncate pl-13 text-[11px] text-stone-400" style={{ paddingLeft: '3.25rem' }}>
          {s.pemain.map((p) => p.nama).join(' · ')}
        </p>
      )}
    </div>
  )

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-2">
        <Badge jenis="maroon">{baru.length} menunggu semakan</Badge>
        <Badge jenis="hijau">{lulus.length} lulus</Badge>
        <Badge>{data.slot_kosong.length} slot kosong</Badge>
        <div className="ml-auto flex gap-2">
          <Button jenis="senyap" ukuran="sm" onClick={ambil}><RefreshCw className="size-4" /></Button>
          {data.buka
            ? <Button jenis="garis" ukuran="sm" onClick={() => buat(-1, () => api.daftarBuka(false), 'Pendaftaran DITUTUP.')}><Lock className="size-3.5" />Tutup pendaftaran</Button>
            : <Button jenis="navy" ukuran="sm" onClick={() => buat(-1, () => api.daftarBuka(true), 'Pendaftaran DIBUKA.')}><Unlock className="size-3.5" />Buka pendaftaran</Button>}
        </div>
      </div>

      {[
        ['Menunggu Semakan', baru, 'Belum ada pendaftaran baharu.'],
        ['Diluluskan', lulus, 'Belum ada pasukan diluluskan.'],
        ['Ditolak', tolak, null],
      ].map(([tajuk, senarai, kosong]) => (
        (senarai.length > 0 || kosong) && (
          <Card key={tajuk}>
            <CardHeader><CardTitle className="flex items-center gap-2"><ClipboardList className="size-4" />{tajuk} ({senarai.length})</CardTitle></CardHeader>
            {senarai.length === 0
              ? <p className="p-6 text-center text-sm text-stone-500">{kosong}</p>
              : <div className="divide-y divide-stone-100 dark:divide-stone-900">{senarai.map((s) => <Baris key={s.id} s={s} />)}</div>}
          </Card>
        )
      ))}

      <p className="px-1 text-[12px] text-stone-500">
        Selepas diluluskan, pasukan masuk ke <strong>kolam undian</strong>. Pergi ke tab <strong>Undian</strong> →
        "Undian Kumpulan" untuk sistem menentukan slot (A1, B2, …) secara rawak automatik.
      </p>
    </div>
  )
}
