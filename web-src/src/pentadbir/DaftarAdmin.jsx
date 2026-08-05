import { useEffect, useState } from 'react'
import { ClipboardList, Check, X, Trash2, Phone, RefreshCw, Lock, Unlock, Pencil, Shuffle, UserPlus, Save, Users } from 'lucide-react'
import { Card, CardHeader, CardTitle, Button, Badge, Input, Label, Dialog, useToast } from '../ui'
import { api } from '../lib/api'

const asasApi = new URL('api/', document.baseURI).href.replace(/\/$/, '')

/* ---- Dialog: sunting butiran pasukan + senarai pemain (admin sahaja) ---- */
function DialogSunting({ rekod, tutup, selepas }) {
  const toast = useToast()
  const [f, setF] = useState({
    nama: rekod.nama || '', pengurus: rekod.pengurus || '', telefon: rekod.telefon || '',
  })
  const [pemain, setPemain] = useState(() =>
    (rekod.pemain || []).map((p) => ({ nama: p.nama || '', no_jersi: p.no_jersi || '' })),
  )
  const [sibuk, setSibuk] = useState(false)

  const ubah = (i, k, v) => setPemain((s) => s.map((p, j) => (j === i ? { ...p, [k]: v } : p)))

  const simpan = async () => {
    setSibuk(true)
    try {
      await api.daftarKemas({
        id: rekod.id, ...f,
        pemain: pemain.filter((p) => p.nama.trim() !== ''),
      })
      toast('Butiran pasukan dikemas kini.', 'ok')
      selepas()
      tutup()
    } catch (e) { toast(e.message, 'ralat') } finally { setSibuk(false) }
  }

  return (
    <Dialog buka tutup={tutup} tajuk={`Sunting — ${rekod.nama}`}>
      <div className="space-y-3">
        <div>
          <Label>Nama pasukan</Label>
          <Input value={f.nama} maxLength={80} onChange={(e) => setF({ ...f, nama: e.target.value })} />
        </div>
        <div className="grid gap-2 sm:grid-cols-2">
          <div>
            <Label>Pengurus</Label>
            <Input value={f.pengurus} maxLength={80} onChange={(e) => setF({ ...f, pengurus: e.target.value })} />
          </div>
          <div>
            <Label>Telefon</Label>
            <Input value={f.telefon} maxLength={30} onChange={(e) => setF({ ...f, telefon: e.target.value })} />
          </div>
        </div>

        <div className="border-t border-stone-100 pt-3 dark:border-stone-800">
          <p className="mb-2 flex items-center gap-2 text-[12px] font-semibold text-stone-600 dark:text-stone-400">
            <Users className="size-3.5" />Senarai pemain ({pemain.length}/20)
          </p>
          <div className="max-h-72 space-y-2 overflow-y-auto pr-1">
            {pemain.length === 0 && (
              <p className="rounded-lg border border-dashed border-stone-300 py-4 text-center text-[12px] text-stone-400 dark:border-stone-700">
                Pengurus belum isi nama pemain. Tekan “Tambah pemain”.
              </p>
            )}
            {pemain.map((p, i) => (
              <div key={i} className="flex gap-2">
                <Input value={p.no_jersi} placeholder="No" className="w-16 text-center"
                       onChange={(e) => ubah(i, 'no_jersi', e.target.value.replace(/\D/g, '').slice(0, 4))} />
                <Input value={p.nama} placeholder="Nama pemain" maxLength={80}
                       onChange={(e) => ubah(i, 'nama', e.target.value)} />
                <button
                  onClick={() => setPemain((s) => s.filter((_, j) => j !== i))}
                  className="grid size-10 shrink-0 place-items-center rounded-lg text-stone-400 hover:bg-red-50 hover:text-red-600"
                  title="Buang pemain"
                >
                  <Trash2 className="size-4" />
                </button>
              </div>
            ))}
          </div>
          {pemain.length < 20 && (
            <Button jenis="garis" className="mt-2 w-full" onClick={() => setPemain((s) => [...s, { nama: '', no_jersi: '' }])}>
              <UserPlus className="size-4" />Tambah pemain
            </Button>
          )}
        </div>

        {rekod.team_id > 0 && (
          <p className="rounded-lg bg-gold-500/10 px-3 py-2 text-[11px] text-stone-600 dark:text-stone-400">
            Pasukan ini sudah masuk slot kumpulan — perubahan di sini turut mengemas kini
            senarai pasukan, sijil dan paparan awam.
          </p>
        )}

        <div className="flex gap-2 pt-1">
          <Button jenis="garis" className="flex-1" onClick={tutup}>Batal</Button>
          <Button className="flex-1" disabled={sibuk} onClick={simpan}>
            <Save className="size-4" />{sibuk ? 'Menyimpan…' : 'Simpan'}
          </Button>
        </div>
      </div>
    </Dialog>
  )
}

export default function DaftarAdmin({ admin, muatSemula }) {
  const toast = useToast()
  const [data, setData] = useState(null)
  const [sibukId, setSibukId] = useState(0)
  const [sunting, setSunting] = useState(null)

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
  const menungguUndian = lulus.filter((s) => !s.team_id).length

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
            {' '}· <span className={s.pemain.length === 0 ? 'font-semibold text-amber-600' : ''}>{s.pemain.length} pemain</span>
          </p>
        </div>
        {s.status !== 'tolak' && (
          <button
            onClick={() => setSunting(s)}
            className="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-stone-300 px-2.5 py-1.5 text-[11px] font-semibold text-stone-600 hover:bg-stone-50 dark:border-stone-700 dark:text-stone-300 dark:hover:bg-stone-800"
            title="Sunting nama pasukan & senarai pemain"
          >
            <Pencil className="size-3.5" />Sunting
          </button>
        )}
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
        <Badge jenis={data.slot_kosong.length === 0 ? 'hijau' : 'kelabu'}>
          {24 - data.slot_kosong.length}/24 slot kumpulan diisi
        </Badge>
        <div className="ml-auto flex gap-2">
          <Button jenis="senyap" ukuran="sm" onClick={ambil}><RefreshCw className="size-4" /></Button>
          {data.buka
            ? <Button jenis="garis" ukuran="sm" onClick={() => buat(-1, () => api.daftarBuka(false), 'Pendaftaran DITUTUP.')}><Lock className="size-3.5" />Tutup pendaftaran</Button>
            : <Button jenis="navy" ukuran="sm" onClick={() => buat(-1, () => api.daftarBuka(true), 'Pendaftaran DIBUKA.')}><Unlock className="size-3.5" />Buka pendaftaran</Button>}
        </div>
      </div>

      {menungguUndian > 0 && (
        <div className="flex items-start gap-2.5 rounded-xl border border-gold-500/40 bg-gold-500/10 px-4 py-3">
          <Shuffle className="mt-0.5 size-4 shrink-0 text-gold-600" />
          <p className="text-[12.5px] leading-relaxed text-stone-700 dark:text-stone-300">
            <strong>{menungguUndian} pasukan</strong> sudah diluluskan dan berada dalam <strong>kolam undian</strong>,
            tetapi belum ada slot kumpulan. Slot A1–H3 hanya diisi selepas anda jalankan
            <strong> Undian Kumpulan</strong> di tab <strong>Undian</strong> — sebab itu kiraan slot masih 0/24.
          </p>
        </div>
      )}

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
        Butang <strong>Sunting</strong> membolehkan admin membetulkan nama pasukan dan
        menambah / membuang nama pemain — pengurus pasukan tidak boleh ubah selepas menghantar borang.
      </p>

      {sunting && (
        <DialogSunting
          rekod={sunting}
          tutup={() => setSunting(null)}
          selepas={() => { ambil(); muatSemula() }}
        />
      )}
    </div>
  )
}
