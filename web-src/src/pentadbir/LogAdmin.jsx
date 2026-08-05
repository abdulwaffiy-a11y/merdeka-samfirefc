import { useEffect, useState } from 'react'
import { ScrollText, RefreshCw } from 'lucide-react'
import { Card, CardHeader, CardTitle, Button, Badge, useToast } from '../ui'
import { api } from '../lib/api'

const LABEL = {
  login: 'Log masuk', login_gagal: 'Log masuk GAGAL', logout: 'Log keluar',
  skor_simpan: 'Kemaskini skor', pasukan_simpan: 'Kemaskini pasukan', pemain_simpan: 'Kemaskini pemain',
  undian_jalan: 'Undian dijalankan', undian_reset: 'Undian direset', bracket_segar: 'Carta dikemas kini',
  admin_tambah: 'Admin ditambah', admin_buang: 'Admin dibuang', admin_aktif: 'Status admin diubah',
  admin_reset_pass: 'Kata laluan admin ditetapkan', tukar_password: 'Kata laluan ditukar',
  tetapan_ubah: 'Tetapan diubah', setup_super_admin: 'Pemasangan awal',
}
const PENTING = ['undian_jalan', 'undian_reset', 'admin_buang', 'login_gagal', 'tetapan_ubah']

export default function LogAdmin() {
  const toast = useToast()
  const [log, setLog] = useState([])
  const [memuat, setMemuat] = useState(true)

  const ambil = async () => {
    setMemuat(true)
    try { setLog((await api.log(250)).log) } catch (e) { toast(e.message, 'ralat') } finally { setMemuat(false) }
  }
  useEffect(() => { ambil() }, [])

  const ringkas = (l) => {
    try {
      const d = JSON.parse(l.butiran_json || '{}')
      if (l.tindakan === 'skor_simpan') {
        return `${d.kod}: ${d.dari?.skor_home ?? '–'}-${d.dari?.skor_away ?? '–'} → ${d.ke?.skor_home ?? '–'}-${d.ke?.skor_away ?? '–'} (${d.ke?.status})`
      }
      if (l.tindakan === 'pasukan_simpan') return `${d.bilangan} pasukan disimpan`
      if (l.tindakan === 'login_gagal') return d.email || ''
      if (l.tindakan === 'undian_jalan') return `${(d.hasil || []).length} pasukan diundi`
      if (l.tindakan === 'bracket_segar') return (d.perubahan || []).map((p) => p.kod).join(', ')
      return Object.keys(d).length ? JSON.stringify(d).slice(0, 90) : ''
    } catch { return '' }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2"><ScrollText className="size-4" />Log Aktiviti</CardTitle>
        <Button jenis="senyap" ukuran="sm" onClick={ambil}><RefreshCw className={`size-4 ${memuat ? 'animate-spin' : ''}`} /></Button>
      </CardHeader>
      <div className="max-h-[70vh] divide-y divide-stone-100 overflow-y-auto dark:divide-stone-900">
        {log.length === 0 && <p className="p-8 text-center text-sm text-stone-500">Tiada rekod.</p>}
        {log.map((l) => (
          <div key={l.id} className="flex gap-3 px-4 py-2.5">
            <div className="tnum w-28 shrink-0 text-[11px] text-stone-400">{l.created_at}</div>
            <div className="min-w-0 flex-1">
              <div className="flex flex-wrap items-center gap-2">
                <span className="text-[13px] font-semibold">{LABEL[l.tindakan] || l.tindakan}</span>
                {PENTING.includes(l.tindakan) && <Badge jenis="maroon">Penting</Badge>}
              </div>
              <p className="truncate text-[12px] text-stone-500">{l.admin_nama} · {ringkas(l)}</p>
            </div>
          </div>
        ))}
      </div>
    </Card>
  )
}
