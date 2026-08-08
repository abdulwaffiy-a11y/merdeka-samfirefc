import { useEffect, useState } from 'react'
import { DatabaseBackup, Download, ShieldCheck, RefreshCw, AlertTriangle, FileSpreadsheet } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardBody, Button, Badge, useToast } from '../ui'
import { jsonSelamat } from '../lib/api'

const asasApi = new URL('api/', document.baseURI).href.replace(/\/$/, '')

const saiz = (b) => (b > 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`)

export default function SandaranAdmin() {
  const toast = useToast()
  const [data, setData] = useState(null)
  const [sibuk, setSibuk] = useState(false)

  const ambil = async () => {
    try {
      const r = await fetch(`${asasApi}/backup.php?action=senarai`, { credentials: 'same-origin' })
      const d = await jsonSelamat(r)
      if (d.ok) setData(d)
      else toast(d.mesej, 'ralat')
    } catch (e) { toast('Tidak dapat membaca senarai sandaran.', 'ralat') }
  }
  useEffect(() => { ambil() }, [])

  const jana = async () => {
    setSibuk(true)
    try {
      const r = await fetch(`${asasApi}/backup.php?action=jana`, { credentials: 'same-origin' })
      const d = await jsonSelamat(r)
      if (!d.ok) throw new Error(d.mesej)
      toast(`Sandaran dibuat: ${d.fail} (${saiz(d.saiz)})`, 'ok')
      ambil()
    } catch (e) { toast(e.message, 'ralat') } finally { setSibuk(false) }
  }

  if (!data) return null

  const k = data.kiraan || {}

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2"><DatabaseBackup className="size-4" />Sandaran & Muat Turun Data</CardTitle>
        {data.backup_akhir
          ? <Badge jenis="hijau">Terakhir: {data.backup_akhir}</Badge>
          : <Badge jenis="maroon">Belum ada sandaran</Badge>}
      </CardHeader>
      <CardBody className="space-y-4">

        {/* ---- Kiraan data hidup ---- */}
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
          {[
            ['Pendaftaran', k.pendaftaran],
            ['Pasukan', k.pasukan_dinamakan],
            ['Pemain', k.pemain],
            ['Keputusan', k.perlawanan_tamat],
          ].map(([l, v]) => (
            <div key={l} className="rounded-lg bg-stone-50 px-3 py-2 text-center dark:bg-stone-900">
              <p className="tnum text-lg font-black text-maroon-700 dark:text-maroon-300">{v ?? 0}</p>
              <p className="text-[10px] font-semibold uppercase tracking-wide text-stone-500">{l}</p>
            </div>
          ))}
        </div>

        {!data.boleh_tulis && (
          <div className="flex gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-[12px] text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
            <AlertTriangle className="size-4 shrink-0" />
            Folder <code>api/backups</code> tidak boleh ditulis. Sandaran automatik takkan berfungsi —
            semak kebenaran folder melalui File Manager cPanel.
          </div>
        )}

        {/* ---- Muat turun segera ---- */}
        <div>
          <p className="mb-2 text-[12px] font-semibold text-stone-600 dark:text-stone-400">
            Muat turun salinan sekarang (buka terus dalam Excel)
          </p>
          <div className="flex flex-wrap gap-2">
            {[
              ['pendaftaran', 'Senarai Pendaftaran'],
              ['pasukan', 'Pasukan & Slot'],
              ['pemain', 'Senarai Pemain'],
            ].map(([j, label]) => (
              <a key={j} href={`${asasApi}/backup.php?action=csv&jenis=${j}`}
                 className="inline-flex items-center gap-2 rounded-lg border border-stone-300 px-3 py-2 text-xs font-semibold text-stone-700 transition hover:bg-stone-50 dark:border-stone-700 dark:text-stone-300 dark:hover:bg-stone-800">
                <FileSpreadsheet className="size-3.5" />{label}
              </a>
            ))}
          </div>
        </div>

        {/* ---- Sandaran penuh ---- */}
        <div>
          <div className="mb-2 flex flex-wrap items-center gap-2">
            <p className="text-[12px] font-semibold text-stone-600 dark:text-stone-400">
              Sandaran penuh database ({data.sandaran.length} disimpan)
            </p>
            <div className="ml-auto flex gap-2">
              <Button jenis="senyap" ukuran="sm" onClick={ambil}><RefreshCw className="size-3.5" /></Button>
              <Button ukuran="sm" onClick={jana} disabled={sibuk}>
                <ShieldCheck className="size-3.5" />{sibuk ? 'Menyimpan…' : 'Sandar Sekarang'}
              </Button>
            </div>
          </div>

          {data.sandaran.length === 0 ? (
            <p className="rounded-lg border border-dashed border-stone-300 py-5 text-center text-[12px] text-stone-400 dark:border-stone-700">
              Belum ada sandaran. Tekan “Sandar Sekarang”.
            </p>
          ) : (
            <div className="max-h-64 divide-y divide-stone-100 overflow-y-auto rounded-lg border border-stone-200 dark:divide-stone-900 dark:border-stone-800">
              {data.sandaran.map((s) => (
                <div key={s.fail} className="flex items-center gap-3 px-3 py-2">
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-[12px] font-medium">{s.masa}</p>
                    <p className="truncate text-[11px] text-stone-400">{s.fail} · {saiz(s.saiz)}</p>
                  </div>
                  <a href={`${asasApi}/backup.php?action=muat&fail=${encodeURIComponent(s.fail)}`}
                     className="shrink-0 rounded-lg p-2 text-stone-500 hover:bg-stone-100 dark:hover:bg-stone-800" title="Muat turun">
                    <Download className="size-4" />
                  </a>
                </div>
              ))}
            </div>
          )}
        </div>

        <p className="text-[11px] leading-relaxed text-stone-400">
          Sandaran automatik setiap jam melalui cron server; 48 salinan terkini disimpan.
          Fail sandaran dilindungi — hanya admin yang log masuk boleh muat turun.
          Simpan satu salinan di telefon atau Google Drive sebelum hari kejohanan.
        </p>
      </CardBody>
    </Card>
  )
}
