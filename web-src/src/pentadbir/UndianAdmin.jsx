import { useState, useEffect, useRef } from 'react'
import { Shuffle, Lock, RotateCcw, PlayCircle, CheckCircle2, AlertTriangle, Dices, Plus, Trash2 } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardBody, Button, Badge, Dialog, Input, Label, useToast } from '../ui'
import { petaPasukan } from '../lib/util'
import { api } from '../lib/api'

const PASANGAN = [
  ['SA1', 0, 1], ['SA2', 2, 3], ['SA3', 4, 5], ['SA4', 6, 7],
]

/* =================================================================
   UNDIAN KUMPULAN — masukkan nama, sistem undi slot A1..H3 automatik
   ================================================================= */
function UndianKumpulan({ muatSemula }) {
  const toast = useToast()
  const [st, setSt] = useState(null)
  const [manual, setManual] = useState([])          // nama tambahan ditaip admin
  const [pilih, setPilih] = useState({})            // pendaftaran_id -> true
  const [sibuk, setSibuk] = useState(false)
  const [hasil, setHasil] = useState(null)
  const [dedah, setDedah] = useState(0)
  const pemasa = useRef([])

  const ambil = async () => {
    try {
      const r = await api.undiKumpulanStatus()
      setSt(r)
      const semua = {}
      r.kolam.forEach((k) => { semua[k.id] = true })
      setPilih(semua)
      if (r.hasil_lepas) setHasil(r.hasil_lepas.hasil ? r.hasil_lepas : null)
    } catch (e) { toast(e.message, 'ralat') }
  }
  useEffect(() => { ambil() }, [])
  useEffect(() => () => pemasa.current.forEach(clearTimeout), [])

  if (!st) return null
  if (st.bermula && !hasil) return null              // kejohanan dah mula & tiada rekod — sembunyi

  const senaraiUndi = [
    ...st.kolam.filter((k) => pilih[k.id]).map((k) => ({ nama: k.nama, pendaftaran_id: k.id })),
    ...manual.filter((n) => n.trim() !== '').map((n) => ({ nama: n.trim() })),
  ]

  const jalan = async () => {
    setSibuk(true)
    try {
      const r = await api.undiKumpulanJalan(senaraiUndi)
      const h = { oleh: '', pada: '', hasil: r.hasil }
      setHasil(h)
      // animasi dedah satu-satu
      pemasa.current.forEach(clearTimeout); pemasa.current = []
      setDedah(0)
      r.hasil.forEach((_, i) => pemasa.current.push(setTimeout(() => setDedah(i + 1), 500 * (i + 1))))
      setManual([])
      toast(`Undian kumpulan selesai — ${r.hasil.length} pasukan ditempatkan.`, 'ok')
      await ambil()
      muatSemula()
    } catch (e) { toast(e.message, 'ralat') } finally { setSibuk(false) }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2"><Dices className="size-4" />Undian Kumpulan (slot A1 – H3)</CardTitle>
        {st.boleh ? <Badge jenis="emas">{st.slot_kosong.length} slot kosong</Badge> : <Badge>Kejohanan bermula</Badge>}
      </CardHeader>
      <CardBody className="space-y-4">

        {st.boleh && (
          <>
            <p className="text-[13px] text-stone-600 dark:text-stone-400">
              Pilih pasukan dari kolam pendaftaran dan/atau taip nama sendiri. Sistem akan <strong>mengundi secara rawak</strong> dan
              menentukan slot kumpulan (A1, A2, C3, …) secara automatik. Hanya slot kosong diisi.
            </p>

            {st.kolam.length > 0 && (
              <div>
                <Label>Dari pendaftaran yang diluluskan ({st.kolam.length})</Label>
                <div className="grid gap-1.5 sm:grid-cols-2">
                  {st.kolam.map((k) => (
                    <label key={k.id} className="flex cursor-pointer items-center gap-2 rounded-lg border border-stone-200 px-3 py-2 text-sm dark:border-stone-700">
                      <input type="checkbox" checked={!!pilih[k.id]}
                             onChange={(e) => setPilih({ ...pilih, [k.id]: e.target.checked })}
                             className="size-4 accent-maroon-700" />
                      <span className="truncate">{k.nama}</span>
                    </label>
                  ))}
                </div>
              </div>
            )}

            <div>
              <Label>Tambah pasukan secara manual</Label>
              <div className="space-y-2">
                {manual.map((n, i) => (
                  <div key={i} className="flex gap-2">
                    <Input value={n} maxLength={80} placeholder={`Nama pasukan`}
                           onChange={(e) => setManual((s) => s.map((x, j) => j === i ? e.target.value : x))} />
                    <button onClick={() => setManual((s) => s.filter((_, j) => j !== i))}
                            className="grid size-10 shrink-0 place-items-center rounded-lg text-stone-400 hover:bg-red-50 hover:text-red-600">
                      <Trash2 className="size-4" />
                    </button>
                  </div>
                ))}
              </div>
              <Button jenis="garis" ukuran="sm" className="mt-2" onClick={() => setManual((s) => [...s, ''])}>
                <Plus className="size-3.5" />Tambah nama
              </Button>
            </div>

            <Button ukuran="lg" className="w-full" disabled={sibuk || senaraiUndi.length < 2 || senaraiUndi.length > st.slot_kosong.length}
                    onClick={jalan}>
              <Dices className="size-5" />
              {sibuk ? 'Mengundi…' : `Jalankan Undian Kumpulan (${senaraiUndi.length} pasukan)`}
            </Button>
            {senaraiUndi.length > st.slot_kosong.length && (
              <p className="text-center text-[12px] text-red-600">Senarai melebihi bilangan slot kosong ({st.slot_kosong.length}).</p>
            )}
          </>
        )}

        {/* ---- hasil ---- */}
        {hasil?.hasil?.length > 0 && (
          <div>
            <div className="mb-2 flex items-center justify-between">
              <Label className="mb-0">Keputusan undian kumpulan</Label>
              <button onClick={() => {
                pemasa.current.forEach(clearTimeout); pemasa.current = []
                setDedah(0)
                hasil.hasil.forEach((_, i) => pemasa.current.push(setTimeout(() => setDedah(i + 1), 500 * (i + 1))))
              }} className="text-xs font-semibold text-maroon-700 hover:underline dark:text-maroon-300">Papar semula animasi</button>
            </div>
            <div className="grid gap-2 sm:grid-cols-2">
              {hasil.hasil.map((h, i) => (
                <div key={i}
                     className={`flex items-center gap-3 rounded-xl border px-3 py-2.5 transition-all duration-500 ${
                       i < dedah || dedah === 0 && !sibuk ? 'opacity-100' : 'opacity-15'
                     } border-stone-200 dark:border-stone-700`}>
                  <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-maroon-700 text-sm font-black text-white">{h.slot}</span>
                  <span className="truncate text-sm font-semibold">{h.nama}</span>
                </div>
              ))}
            </div>
            {hasil.oleh && <p className="mt-2 text-[11px] text-stone-400">Dijalankan oleh {hasil.oleh} pada {hasil.pada}</p>}
          </div>
        )}
      </CardBody>
    </Card>
  )
}

export default function UndianAdmin({ admin, awam, muatSemula }) {
  const toast = useToast()
  const peta = petaPasukan(awam.pasukan)

  const [status, setStatus] = useState(null)
  const [sibuk, setSibuk] = useState(false)
  const [dedah, setDedah] = useState(0)          // berapa slot sudah didedahkan
  const [animasi, setAnimasi] = useState(false)
  const [dialogReset, setDialogReset] = useState(false)
  const [teksReset, setTeksReset] = useState('')
  const pemasa = useRef([])

  const ambil = async () => {
    try { setStatus(await api.undianStatus()) } catch (e) { toast(e.message, 'ralat') }
  }
  useEffect(() => { ambil() }, [])
  useEffect(() => () => pemasa.current.forEach(clearTimeout), [])

  const hasil = status?.undian?.kedudukan || []

  const mainAnimasi = (senarai) => {
    pemasa.current.forEach(clearTimeout)
    pemasa.current = []
    setAnimasi(true)
    setDedah(0)
    senarai.forEach((_, i) => {
      pemasa.current.push(setTimeout(() => setDedah(i + 1), 700 * (i + 1)))
    })
    pemasa.current.push(setTimeout(() => setAnimasi(false), 700 * senarai.length + 500))
  }

  const jalan = async () => {
    setSibuk(true)
    try {
      const r = await api.undianJalan()
      await ambil()
      mainAnimasi(r.undian.kedudukan)
      toast('Undian selesai. Carta suku akhir telah dikemas kini.', 'ok')
      muatSemula()
    } catch (e) { toast(e.message, 'ralat') } finally { setSibuk(false) }
  }

  const reset = async () => {
    setSibuk(true)
    try {
      await api.undianReset(teksReset)
      setDialogReset(false); setTeksReset(''); setDedah(0)
      await ambil()
      toast('Undian direset. Semua keputusan kalah mati dikosongkan.', 'ok')
      muatSemula()
    } catch (e) { toast(e.message, 'ralat') } finally { setSibuk(false) }
  }

  if (!status) return <Card className="p-8 text-center text-sm text-stone-500">Memuatkan status undian…</Card>

  const sudah = status.sudah
  const bolehPapar = sudah && hasil.length === 8
  const paparSlot = (i) => (animasi ? (i < dedah) : true)

  return (
    <div className="space-y-4">
      {/* ---- Undian kumpulan (sebelum kejohanan) ---- */}
      <UndianKumpulan muatSemula={muatSemula} />

      {/* ---- Status ---- */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2"><Shuffle className="size-4" />Undian Suku Akhir</CardTitle>
          {sudah ? <Badge jenis="hijau"><CheckCircle2 className="size-3" />Selesai</Badge>
                 : status.layak ? <Badge jenis="emas">Sedia untuk diundi</Badge>
                 : <Badge>Belum layak</Badge>}
        </CardHeader>
        <CardBody className="space-y-3">
          {!sudah && !status.layak && (
            <div className="space-y-1.5 rounded-lg border border-amber-200 bg-amber-50 p-3 text-[13px] text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
              <p className="flex items-center gap-2 font-semibold"><AlertTriangle className="size-4" />Undian belum boleh dijalankan</p>
              <ul className="list-disc pl-5">
                {(status.sebab || []).map((s, i) => <li key={i}>{s}</li>)}
                {(status.sebab || []).length === 0 && <li>Semua 24 perlawanan kumpulan mesti tamat dahulu.</li>}
              </ul>
            </div>
          )}

          {!sudah && status.layak && (
            <>
              <p className="text-[13px] text-stone-600 dark:text-stone-400">
                Kesemua 24 perlawanan kumpulan telah tamat dan 8 johan kumpulan sudah disahkan.
                Tekan butang di bawah untuk menjalankan cabutan undi. <strong>Undian hanya boleh dijalankan sekali.</strong>
              </p>
              <Button ukuran="xl" className="w-full" onClick={jalan} disabled={sibuk}>
                <Shuffle className="size-5" />{sibuk ? 'Mengundi…' : 'Jalankan Undian Suku Akhir'}
              </Button>
            </>
          )}

          {sudah && (
            <div className="flex flex-wrap items-center gap-2">
              <p className="min-w-0 flex-1 text-[12px] text-stone-500">
                Dijalankan oleh <strong>{status.undian?.nama_pelaksana}</strong> pada {status.undian?.created_at}
              </p>
              <Button jenis="garis" ukuran="sm" onClick={() => mainAnimasi(hasil)}>
                <PlayCircle className="size-4" />Papar semula animasi
              </Button>
              {admin.role === 'super' && (
                <Button jenis="bahaya" ukuran="sm" onClick={() => setDialogReset(true)}>
                  <RotateCcw className="size-4" />Reset undian
                </Button>
              )}
            </div>
          )}
        </CardBody>
      </Card>

      {/* ---- Johan kumpulan ---- */}
      {!sudah && (
        <Card>
          <CardHeader><CardTitle>Johan Kumpulan Setakat Ini</CardTitle></CardHeader>
          <div className="grid grid-cols-2 gap-px bg-stone-200 sm:grid-cols-4 dark:bg-stone-800">
            {['A','B','C','D','E','F','G','H'].map((h) => {
              const id = status.johan?.[h]
              return (
                <div key={h} className="bg-white p-3 dark:bg-stone-950">
                  <p className="text-[10px] font-bold uppercase tracking-wide text-stone-400">Kumpulan {h}</p>
                  <p className={`mt-0.5 truncate text-sm font-semibold ${id ? '' : 'italic text-stone-400'}`}>
                    {id ? peta.get(id)?.nama : 'Belum disahkan'}
                  </p>
                </div>
              )
            })}
          </div>
        </Card>
      )}

      {/* ---- Hasil undian / papar besar ---- */}
      {bolehPapar && (
        <Card className="overflow-hidden">
          <CardHeader><CardTitle>Pasangan Suku Akhir</CardTitle></CardHeader>
          <div className="grid gap-3 p-4 sm:grid-cols-2">
            {PASANGAN.map(([kod, i, j]) => (
              <div key={kod} className="overflow-hidden rounded-xl border border-stone-200 dark:border-stone-800">
                <div className="bg-navy-800 px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-white">{kod}</div>
                {[i, j].map((idx) => (
                  <div key={idx} className={`border-t border-stone-100 px-3 py-3 text-center transition-all duration-500 dark:border-stone-900 ${paparSlot(idx) ? 'opacity-100' : 'opacity-0'}`}>
                    <p className="truncate text-base font-bold sm:text-lg">
                      {paparSlot(idx) ? (peta.get(hasil[idx])?.nama ?? '—') : '…'}
                    </p>
                  </div>
                ))}
              </div>
            ))}
          </div>
          {animasi && (
            <div className="border-t border-stone-200 bg-gold-500/10 px-4 py-2 text-center text-[12px] font-semibold text-gold-600 dark:border-stone-800">
              Cabutan undi sedang dipaparkan… {dedah}/8
            </div>
          )}
        </Card>
      )}

      <Dialog buka={dialogReset} tutup={() => setDialogReset(false)} tajuk="Reset Undian Suku Akhir">
        <div className="space-y-3">
          <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-[13px] text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100">
            <p className="font-semibold">Tindakan ini akan:</p>
            <ul className="mt-1 list-disc pl-5">
              <li>memadam hasil undian sedia ada;</li>
              <li>mengosongkan <strong>semua</strong> keputusan Suku Akhir, Separuh Akhir, Tempat Ke-3 dan Perlawanan Akhir.</li>
            </ul>
            <p className="mt-2">Tindakan ini direkod dalam log aktiviti.</p>
          </div>
          <div>
            <Label>Taip <code className="rounded bg-stone-100 px-1 dark:bg-stone-800">RESET UNDIAN</code> untuk mengesahkan</Label>
            <Input value={teksReset} onChange={(e) => setTeksReset(e.target.value)} placeholder="RESET UNDIAN" />
          </div>
          <div className="flex gap-2">
            <Button jenis="garis" className="flex-1" onClick={() => setDialogReset(false)}>Batal</Button>
            <Button jenis="bahaya" className="flex-1" disabled={sibuk || teksReset !== 'RESET UNDIAN'} onClick={reset}>
              <RotateCcw className="size-4" />Reset
            </Button>
          </div>
        </div>
      </Dialog>
    </div>
  )
}
