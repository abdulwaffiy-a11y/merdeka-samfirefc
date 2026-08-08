import { useEffect, useRef, useState } from 'react'
import { Images, Trash2, Upload, Loader2, Check } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardBody, Button, Input, Badge, useToast } from '../ui'
import { getCsrf, jsonSelamat } from '../lib/api'

const asasApi = new URL('api/', document.baseURI).href.replace(/\/$/, '')

async function hantar(action, badan, jsonMode = false) {
  const opsyen = {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'X-CSRF-Token': getCsrf() || '' },
  }
  if (jsonMode) {
    opsyen.headers['Content-Type'] = 'application/json'
    opsyen.body = JSON.stringify(badan)
  } else {
    opsyen.body = badan
  }
  const r = await fetch(`${asasApi}/galeri.php?action=${action}`, opsyen)
  const d = await jsonSelamat(r)
  if (!d.ok) throw new Error(d.mesej || 'Ralat.')
  return d
}

export default function GaleriAdmin() {
  const toast = useToast()
  const [senarai, setSenarai] = useState([])
  const [sibuk, setSibuk] = useState(false)
  const [kemajuan, setKemajuan] = useState('')
  const [kapsyen, setKapsyen] = useState({})
  const failRef = useRef(null)

  const ambil = async () => {
    try {
      const r = await fetch(`${asasApi}/galeri.php?action=senarai`)
      const d = await jsonSelamat(r)
      if (d.ok) setSenarai(d.galeri || [])
    } catch { /* biarkan */ }
  }
  useEffect(() => { ambil() }, [])

  const naik = async (fail) => {
    const senaraiFail = Array.from(fail || [])
    if (senaraiFail.length === 0) return
    setSibuk(true)
    let jumlahOk = 0
    const semuaGagal = []
    try {
      // hantar 5 gambar setiap kumpulan — elak had saiz POST server
      for (let i = 0; i < senaraiFail.length; i += 5) {
        const kumpulan = senaraiFail.slice(i, i + 5)
        setKemajuan(`Memuat naik ${i + 1}–${Math.min(i + 5, senaraiFail.length)} daripada ${senaraiFail.length}…`)
        const fd = new FormData()
        kumpulan.forEach((f) => fd.append('gambar[]', f))
        const d = await hantar('naik', fd)
        jumlahOk += d.berjaya || 0
        if (d.gagal?.length) semuaGagal.push(...d.gagal)
      }
      toast(`${jumlahOk} gambar dimuat naik.` + (semuaGagal.length ? ` ${semuaGagal.length} gagal.` : ''), semuaGagal.length ? 'info' : 'ok')
      if (semuaGagal.length) toast(semuaGagal.slice(0, 3).join(' · '), 'ralat')
      ambil()
    } catch (e) {
      toast(e.message, 'ralat')
    } finally {
      setSibuk(false); setKemajuan('')
      if (failRef.current) failRef.current.value = ''
    }
  }

  const buang = async (g) => {
    if (!confirm('Buang gambar ini dari galeri?')) return
    try { await hantar('buang', { id: g.id }, true); toast('Gambar dibuang.', 'ok'); ambil() }
    catch (e) { toast(e.message, 'ralat') }
  }

  const simpanKapsyen = async (g) => {
    try {
      await hantar('kapsyen', { id: g.id, kapsyen: kapsyen[g.id] ?? g.kapsyen }, true)
      toast('Kapsyen disimpan.', 'ok'); ambil()
    } catch (e) { toast(e.message, 'ralat') }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2"><Images className="size-4" />Galeri Gambar</CardTitle>
        <Badge>{senarai.length} / 200 gambar</Badge>
      </CardHeader>
      <CardBody className="space-y-4">
        <p className="text-[12px] text-stone-500">
          Ambil terus dari telefon atau pilih gambar WhatsApp — boleh pilih banyak sekali gus (maksimum 20).
          Sistem betulkan orientasi, mampatkan kepada lebar 1600px, dan buat thumbnail kecil supaya
          halaman awam tetap ringan. Gambar disimpan sebagai fail di server, bukan dalam database.
        </p>

        <div className="flex flex-wrap items-center gap-3">
          <input
            ref={failRef}
            type="file"
            accept="image/jpeg,image/png,image/webp"
            multiple
            onChange={(e) => naik(e.target.files)}
            disabled={sibuk}
            className="w-full max-w-full min-w-0 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-maroon-700 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white disabled:opacity-50"
          />
          {sibuk && (
            <span className="flex items-center gap-2 text-[12px] text-stone-500">
              <Loader2 className="size-3.5 animate-spin" />{kemajuan}
            </span>
          )}
        </div>

        {senarai.length === 0 ? (
          <div className="flex flex-col items-center gap-1.5 rounded-xl border border-dashed border-stone-300 py-8 text-center dark:border-stone-700">
            <Upload className="size-5 text-stone-300" />
            <p className="text-sm font-semibold text-stone-500">Galeri masih kosong</p>
            <p className="text-xs text-stone-400">Gambar yang dimuat naik akan muncul di halaman Utama.</p>
          </div>
        ) : (
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            {senarai.map((g) => (
              <div key={g.id} className="overflow-hidden rounded-xl border border-stone-200 dark:border-stone-800">
                <img src={g.thumb} alt="" className="aspect-square w-full object-cover" loading="lazy" />
                <div className="space-y-1.5 p-2">
                  <div className="flex gap-1">
                    <Input
                      className="h-8 text-xs"
                      placeholder="Kapsyen (pilihan)"
                      value={kapsyen[g.id] ?? g.kapsyen}
                      maxLength={160}
                      onChange={(e) => setKapsyen({ ...kapsyen, [g.id]: e.target.value })}
                    />
                    <button
                      onClick={() => simpanKapsyen(g)}
                      className="grid size-8 shrink-0 place-items-center rounded-lg bg-stone-100 text-stone-600 hover:bg-stone-200 dark:bg-stone-800 dark:text-stone-300"
                      title="Simpan kapsyen"
                    >
                      <Check className="size-3.5" />
                    </button>
                  </div>
                  <Button jenis="senyap" ukuran="sm" className="w-full text-red-600" onClick={() => buang(g)}>
                    <Trash2 className="size-3.5" />Buang
                  </Button>
                </div>
              </div>
            ))}
          </div>
        )}
      </CardBody>
    </Card>
  )
}
