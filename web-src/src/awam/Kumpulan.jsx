import { Card, CardHeader, CardTitle, Badge } from '../ui'
import { petaPasukan, namaSisi, teksSkor, masaMy } from '../lib/util'

function Jadual({ g, peta }) {
  return (
    <div className="overflow-hidden">
      <table className="w-full text-sm">
        <thead>
          <tr className="border-b border-stone-200 text-[10px] uppercase tracking-wide text-stone-500 dark:border-stone-800">
            <th className="py-2 pl-4 pr-1 text-left font-semibold">Pasukan</th>
            <th className="w-8 px-1 text-center font-semibold" title="Main">M</th>
            <th className="w-8 px-1 text-center font-semibold" title="Menang">Mn</th>
            <th className="w-8 px-1 text-center font-semibold" title="Seri">S</th>
            <th className="w-8 px-1 text-center font-semibold" title="Kalah">K</th>
            <th className="w-10 px-1 text-center font-semibold" title="Beza gol">BG</th>
            <th className="w-10 pl-1 pr-4 text-center font-semibold" title="Mata">Mt</th>
          </tr>
        </thead>
        <tbody>
          {g.baris.map((b, i) => {
            const johan = i === 0 && g.siap && !g.perlu_undian
            return (
              <tr key={b.team_id} className={`border-b border-stone-100 last:border-0 dark:border-stone-900 ${johan ? 'bg-gold-500/10' : ''}`}>
                <td className="py-2 pl-4 pr-1">
                  <div className="flex items-center gap-2">
                    <span className={`tnum w-4 shrink-0 text-[11px] font-bold ${johan ? 'text-gold-600' : 'text-stone-400'}`}>{i + 1}</span>
                    <span className={`truncate ${johan ? 'font-bold' : ''}`}>{b.nama_papar}</span>
                    {johan && <Badge jenis="emas" className="shrink-0">Johan</Badge>}
                  </div>
                </td>
                <td className="tnum px-1 text-center text-stone-500">{b.main}</td>
                <td className="tnum px-1 text-center">{b.menang}</td>
                <td className="tnum px-1 text-center">{b.seri}</td>
                <td className="tnum px-1 text-center">{b.kalah}</td>
                <td className="tnum px-1 text-center">{b.beza > 0 ? `+${b.beza}` : b.beza}</td>
                <td className="tnum pl-1 pr-4 text-center font-bold">{b.mata}</td>
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}

export default function Kumpulan({ data }) {
  const peta = petaPasukan(data.pasukan)
  const huruf = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']

  return (
    <div className="space-y-4">
      <p className="px-1 text-xs text-stone-500">
        Kedudukan dikira automatik: <strong>Mata</strong> → <strong>Keputusan bersemuka (head-to-head)</strong> → <strong>Perbezaan gol</strong> → <strong>Jumlah gol</strong> → Undian.
        Johan setiap kumpulan sahaja layak ke Suku Akhir.
      </p>

      <div className="grid gap-4 lg:grid-cols-2">
        {huruf.map((h) => {
          const g = data.kedudukan[h]
          if (!g) return null
          const mGrup = data.perlawanan.filter((m) => m.peringkat === 'grup' && m.kumpulan === h)
          return (
            <Card key={h} className="overflow-hidden">
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <span className="grid size-6 place-items-center rounded-md bg-maroon-700 text-xs font-black text-white">{h}</span>
                  Kumpulan {h}
                </CardTitle>
                {g.siap
                  ? (g.perlu_undian
                      ? <Badge jenis="maroon">Perlu cabutan undi</Badge>
                      : <Badge jenis="hijau">Selesai</Badge>)
                  : <Badge>Belum selesai</Badge>}
              </CardHeader>

              <Jadual g={g} peta={peta} />

              <div className="border-t border-stone-200 bg-stone-50 px-4 py-2.5 dark:border-stone-800 dark:bg-stone-900/50">
                <p className="mb-1.5 text-[10px] font-bold uppercase tracking-wide text-stone-400">Perlawanan</p>
                <div className="space-y-1">
                  {mGrup.map((m) => (
                    <div key={m.kod} className="flex items-center gap-2 text-[12px]">
                      <span className="tnum w-11 shrink-0 text-stone-400">{m.masa}</span>
                      <span className="min-w-0 flex-1 truncate">
                        {namaSisi(peta, m.home_id, m.home_sumber)} <span className="text-stone-400">vs</span> {namaSisi(peta, m.away_id, m.away_sumber)}
                      </span>
                      <span className={`tnum shrink-0 font-semibold ${m.status === 'live' ? 'text-red-600' : ''}`}>
                        {m.status === 'scheduled' ? '—' : teksSkor(m)}
                      </span>
                    </div>
                  ))}
                </div>
              </div>

              {g.perlu_undian && (
                <div className="border-t border-amber-200 bg-amber-50 px-4 py-2 text-[11px] text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                  Dua pasukan sama sepenuhnya di tempat pertama — johan akan ditentukan melalui cabutan undi oleh urus setia.
                </div>
              )}
            </Card>
          )
        })}
      </div>
    </div>
  )
}
