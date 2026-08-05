import { useState } from 'react'
import { Users, Search } from 'lucide-react'
import { Card, CardHeader, CardTitle, Input, Badge, Kosong } from '../ui'

export default function Pasukan({ data }) {
  const [cari, setCari] = useState('')
  const q = cari.trim().toLowerCase()

  const huruf = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']
  const diisi = data.pasukan.filter((t) => t.diisi).length

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-3">
        <div className="relative min-w-0 flex-1">
          <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
          <Input value={cari} onChange={(e) => setCari(e.target.value)} placeholder="Cari nama pasukan atau pemain…" className="pl-9" />
        </div>
        <Badge jenis="maroon">{diisi} / {data.pasukan.length} pasukan didaftar</Badge>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {huruf.map((h) => {
          const ahli = data.pasukan.filter((t) => t.kumpulan === h)
          const padan = ahli.filter((t) => {
            if (!q) return true
            if (t.nama.toLowerCase().includes(q)) return true
            return (data.pemain?.[t.id] || []).some((p) => p.nama.toLowerCase().includes(q))
          })
          if (q && padan.length === 0) return null

          return (
            <Card key={h}>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <span className="grid size-6 place-items-center rounded-md bg-navy-800 text-xs font-black text-white">{h}</span>
                  Kumpulan {h}
                </CardTitle>
              </CardHeader>
              <div className="divide-y divide-stone-100 dark:divide-stone-900">
                {padan.map((t) => {
                  const pemain = data.pemain?.[t.id] || []
                  return (
                    <div key={t.id} className="px-4 py-2.5">
                      <div className="flex items-center gap-2.5">
                        {t.logo && <img src={t.logo} alt="" className="size-7 shrink-0 rounded-md object-contain" loading="lazy" />}
                        <p className={`text-sm font-semibold ${t.diisi ? '' : 'italic text-stone-400'}`}>{t.nama}</p>
                      </div>
                      {pemain.length > 0 && (
                        <ul className="mt-1.5 space-y-0.5">
                          {pemain.map((p, i) => (
                            <li key={i} className="flex gap-2 text-[12px] text-stone-600 dark:text-stone-400">
                              {p.no_jersi && <span className="tnum w-5 shrink-0 text-right font-semibold text-stone-400">{p.no_jersi}</span>}
                              <span className="truncate">{p.nama}</span>
                            </li>
                          ))}
                        </ul>
                      )}
                    </div>
                  )
                })}
              </div>
            </Card>
          )
        })}
      </div>

      {diisi === 0 && (
        <Kosong ikon={Users} tajuk="Nama pasukan belum dimasukkan" nota="Urus setia akan memasukkan 24 nama pasukan selepas cabutan undi kumpulan." />
      )}
    </div>
  )
}
