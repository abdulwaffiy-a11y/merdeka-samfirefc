import { useEffect, useState } from 'react'
import { Award, Copy, ExternalLink, Trash2, PenLine, Check, RefreshCw } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardBody, Button, Input, Label, Badge, useToast } from '../ui'
import { getCsrf } from '../lib/api'

const asasApi = new URL('api/', document.baseURI).href.replace(/\/$/, '')

export default function SijilAdmin() {
  const toast = useToast()
  const [data, setData] = useState(null)
  const [sibuk, setSibuk] = useState(false)
  const [ttd, setTtd] = useState({ nama_penandatangan: '', jawatan_penandatangan: '' })
  const [disalin, setDisalin] = useState(0)

  const ambil = async () => {
    try {
      const r = await fetch(`${asasApi}/sijil.php?action=pautan`, { credentials: 'same-origin' })
      const d = await r.json()
      if (!d.ok) throw new Error(d.mesej)
      setData(d)
      setTtd({
        nama_penandatangan: d.nama_penandatangan || '',
        jawatan_penandatangan: d.jawatan_penandatangan || '',
      })
    } catch (e) { toast(e.message || 'Tidak dapat memuat data sijil.', 'ralat') }
  }
  useEffect(() => { ambil() }, [])

  const naikTtd = async (fail) => {
    if (!fail) return
    if (fail.size > 2097152) { toast('Imej tandatangan melebihi 2MB.', 'ralat'); return }
    setSibuk(true)
    try {
      const fd = new FormData()
      fd.append('tandatangan', fail)
      const r = await fetch(`${asasApi}/sijil.php?action=tandatangan`, {
        method: 'POST', body: fd, credentials: 'same-origin',
        headers: { 'X-CSRF-Token': getCsrf() || '' },
      })
      const d = await r.json()
      if (!d.ok) throw new Error(d.mesej)
      toast('Tandatangan dimuat naik.', 'ok')
      ambil()
    } catch (e) { toast(e.message, 'ralat') } finally { setSibuk(false) }
  }

  const hantarJson = async (action, badan) => {
    const r = await fetch(`${asasApi}/sijil.php?action=${action}`, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrf() || '' },
      body: JSON.stringify(badan),
    })
    const d = await r.json()
    if (!d.ok) throw new Error(d.mesej)
    return d
  }

  const simpanTtd = async () => {
    try { await hantarJson('tetapan', ttd); toast('Butiran penandatangan disimpan.', 'ok'); ambil() }
    catch (e) { toast(e.message, 'ralat') }
  }

  const buangTtd = async () => {
    if (!confirm('Buang imej tandatangan?')) return
    try { await hantarJson('buang_tandatangan', {}); toast('Tandatangan dibuang.', 'ok'); ambil() }
    catch (e) { toast(e.message, 'ralat') }
  }

  const salin = async (teks, id) => {
    try {
      await navigator.clipboard.writeText(teks)
      setDisalin(id)
      setTimeout(() => setDisalin(0), 1800)
    } catch {
      window.prompt('Salin pautan ini:', teks)
    }
  }

  const salinSemua = async () => {
    const teks = data.pasukan
      .map((p) => `${p.slot} — ${p.nama}\n${p.pautan}`)
      .join('\n\n')
    try { await navigator.clipboard.writeText(teks); toast('Semua pautan disalin.', 'ok') }
    catch { window.prompt('Salin semua pautan:', teks) }
  }

  if (!data) return null

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2"><Award className="size-4 text-gold-500" />Sijil Penyertaan</CardTitle>
        <Badge>{data.pasukan.length} pasukan</Badge>
      </CardHeader>
      <CardBody className="space-y-5">

        {/* ---- Tandatangan ---- */}
        <div>
          <p className="mb-2 text-[12px] font-semibold text-stone-600 dark:text-stone-400">
            Tandatangan pada sijil
          </p>
          <div className="flex flex-wrap items-start gap-4">
            <div className="grid h-20 w-40 shrink-0 place-items-center rounded-lg border border-stone-200 bg-white p-2 dark:border-stone-700 dark:bg-stone-900">
              {data.tandatangan
                ? <img src={data.tandatangan} alt="Tandatangan" className="max-h-full max-w-full object-contain" />
                : <span className="text-[11px] text-stone-400">Belum ada</span>}
            </div>
            <div className="flex min-w-52 flex-1 flex-col gap-2">
              <input
                type="file" accept="image/jpeg,image/png,image/webp"
                onChange={(e) => naikTtd(e.target.files?.[0])} disabled={sibuk}
                className="text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-maroon-700 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white"
              />
              <p className="text-[11px] text-stone-400">
                PNG latar telus paling kemas. Maksimum 2MB. Imbas atau ambil gambar tandatangan di atas kertas putih.
              </p>
              {data.tandatangan && (
                <Button jenis="garis" ukuran="sm" className="self-start" onClick={buangTtd}>
                  <Trash2 className="size-3.5" />Buang tandatangan
                </Button>
              )}
            </div>
          </div>

          <div className="mt-3 grid gap-3 sm:grid-cols-2">
            <div>
              <Label>Nama penandatangan</Label>
              <Input value={ttd.nama_penandatangan} maxLength={120}
                     placeholder="YB Dato' Seri Reezal Merican"
                     onChange={(e) => setTtd({ ...ttd, nama_penandatangan: e.target.value })} />
            </div>
            <div>
              <Label>Jawatan</Label>
              <div className="flex gap-2">
                <Input value={ttd.jawatan_penandatangan} maxLength={120}
                       placeholder="Penaja Kejohanan"
                       onChange={(e) => setTtd({ ...ttd, jawatan_penandatangan: e.target.value })} />
                <Button ukuran="sm" onClick={simpanTtd}><PenLine className="size-3.5" />Simpan</Button>
              </div>
            </div>
          </div>
        </div>

        {/* ---- Pautan pasukan ---- */}
        <div>
          <div className="mb-2 flex flex-wrap items-center gap-2">
            <p className="text-[12px] font-semibold text-stone-600 dark:text-stone-400">
              Pautan sijil untuk setiap pasukan
            </p>
            <div className="ml-auto flex gap-2">
              <Button jenis="senyap" ukuran="sm" onClick={ambil}><RefreshCw className="size-3.5" /></Button>
              {data.pasukan.length > 0 && (
                <Button jenis="garis" ukuran="sm" onClick={salinSemua}>
                  <Copy className="size-3.5" />Salin semua
                </Button>
              )}
            </div>
          </div>

          {data.pasukan.length === 0 ? (
            <p className="rounded-lg border border-dashed border-stone-300 py-6 text-center text-[12px] text-stone-400 dark:border-stone-700">
              Belum ada pasukan dinamakan. Isi nama pasukan atau jalankan undian kumpulan dahulu.
            </p>
          ) : (
            <div className="divide-y divide-stone-100 overflow-hidden rounded-lg border border-stone-200 dark:divide-stone-900 dark:border-stone-800">
              {data.pasukan.map((p) => (
                <div key={p.id} className="flex flex-wrap items-center gap-2 px-3 py-2.5">
                  <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-maroon-700 text-[11px] font-black text-white">{p.slot}</span>
                  <div className="min-w-32 flex-1">
                    <p className="truncate text-[13px] font-semibold">{p.nama}</p>
                    <p className="truncate text-[11px] text-stone-400">
                      {p.pemain} pemain{p.pengurus ? ` · ${p.pengurus}` : ''}
                    </p>
                  </div>
                  <button
                    onClick={() => salin(p.pautan, p.id)}
                    className="inline-flex items-center gap-1.5 rounded-lg border border-stone-300 px-2.5 py-1.5 text-[11px] font-semibold text-stone-600 hover:bg-stone-50 dark:border-stone-700 dark:text-stone-300 dark:hover:bg-stone-800"
                  >
                    {disalin === p.id ? <><Check className="size-3.5 text-emerald-600" />Disalin</> : <><Copy className="size-3.5" />Salin pautan</>}
                  </button>
                  <a
                    href={p.pautan} target="_blank" rel="noreferrer"
                    className="inline-flex items-center gap-1.5 rounded-lg bg-navy-800 px-2.5 py-1.5 text-[11px] font-semibold text-white hover:bg-navy-900"
                  >
                    <ExternalLink className="size-3.5" />Buka
                  </a>
                </div>
              ))}
            </div>
          )}
        </div>

        <p className="text-[11px] leading-relaxed text-stone-400">
          Hantar pautan kepada pengurus pasukan melalui WhatsApp. Mereka boleh buka sendiri,
          lihat senarai pemain, dan cetak semua sijil sekali gus (satu muka surat setiap orang) —
          atau simpan sebagai PDF. Setiap pautan hanya buka pasukan itu sahaja.
          Jika kejohanan sudah tamat, kedudukan Johan / Naib Johan / Tempat Ketiga akan terpapar
          pada sijil secara automatik.
        </p>
      </CardBody>
    </Card>
  )
}
