import { Trophy, Medal } from 'lucide-react'
import { Card, Badge, BadgeLive } from '../ui'
import { petaPasukan, namaSisi, masaMy, kodPapar, pemenangId } from '../lib/util'

function Kotak({ m, peta, sorotEmas }) {
  if (!m) return null
  const menang = pemenangId(m)

  const sisi = (id, sumber, skor, penalti) => {
    const isMenang = menang && menang === id
    return (
      <div className={`flex items-center gap-2 px-3 py-2 ${isMenang ? 'bg-gold-500/10' : ''}`}>
        <span className={`min-w-0 flex-1 truncate text-[13px] ${isMenang ? 'font-bold' : ''} ${!id ? 'italic text-stone-400' : ''}`}>
          {namaSisi(peta, id, sumber)}
        </span>
        {penalti !== null && penalti !== undefined && (
          <span className="tnum shrink-0 rounded bg-stone-200 px-1 text-[10px] font-semibold text-stone-600 dark:bg-stone-800 dark:text-stone-300">p{penalti}</span>
        )}
        <span className="tnum w-5 shrink-0 text-right text-sm font-bold">{skor === null ? '–' : skor}</span>
      </div>
    )
  }

  return (
    <Card className={`overflow-hidden ${sorotEmas ? 'border-gold-500/60 shadow-md' : ''}`}>
      <div className="flex items-center justify-between gap-2 border-b border-stone-200 bg-stone-50 px-3 py-1.5 dark:border-stone-800 dark:bg-stone-900/60">
        <span className="text-[10px] font-bold uppercase tracking-wide text-stone-500">
          {kodPapar(m.kod)} · <span className="tnum">{m.masa}</span> · G{m.gelanggang}
        </span>
        {m.status === 'live' ? <BadgeLive /> : m.status === 'done' ? <Badge jenis="hijau">Tamat</Badge> : null}
      </div>
      <div className="divide-y divide-stone-100 dark:divide-stone-900">
        {sisi(m.home_id, m.home_sumber, m.skor_home, m.penalti_home)}
        {sisi(m.away_id, m.away_sumber, m.skor_away, m.penalti_away)}
      </div>
    </Card>
  )
}

const Lajur = ({ tajuk, children }) => (
  <div className="space-y-3">
    <h3 className="px-1 text-[11px] font-bold uppercase tracking-widest text-stone-400">{tajuk}</h3>
    <div className="flex flex-col justify-around gap-3 md:h-full">{children}</div>
  </div>
)

export default function Carta({ data }) {
  const peta = petaPasukan(data.pasukan)
  const cari = (kod) => data.perlawanan.find((m) => m.kod === kod)
  const ka = data.kedudukan_akhir
  const adaUndian = data.undian?.ada

  return (
    <div className="space-y-5">
      {!adaUndian && (
        <div className="rounded-xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm text-stone-600 dark:border-stone-800 dark:bg-stone-900 dark:text-stone-400">
          Carta akan terisi selepas kesemua 24 perlawanan kumpulan tamat dan <strong>undian suku akhir</strong> dijalankan.
        </div>
      )}

      <div className="grid gap-5 md:grid-cols-3 md:items-stretch">
        <Lajur tajuk="Suku Akhir">
          {['SA1', 'SA2', 'SA3', 'SA4'].map((k) => <Kotak key={k} m={cari(k)} peta={peta} />)}
        </Lajur>
        <Lajur tajuk="Separuh Akhir">
          {['SS1', 'SS2'].map((k) => <Kotak key={k} m={cari(k)} peta={peta} />)}
        </Lajur>
        <Lajur tajuk="Perlawanan Akhir">
          <Kotak m={cari('FINAL')} peta={peta} sorotEmas />
          <div>
            <h4 className="mb-2 px-1 text-[11px] font-bold uppercase tracking-widest text-stone-400">Penentuan Tempat Ke-3</h4>
            <Kotak m={cari('T3')} peta={peta} />
          </div>
        </Lajur>
      </div>

      {(ka.johan || ka.ketiga) && (
        <Card className="overflow-hidden">
          <div className="grid grid-cols-2 divide-x divide-stone-200 sm:grid-cols-4 dark:divide-stone-800">
            {[
              ['Johan', ka.johan, Trophy, 'text-gold-500'],
              ['Naib Johan', ka.naib_johan, Medal, 'text-stone-400'],
              ['Tempat Ke-3', ka.ketiga, Medal, 'text-amber-700'],
              ['Tempat Ke-4', ka.keempat, null, ''],
            ].map(([label, id, Ikon, warna]) => (
              <div key={label} className="p-3 text-center">
                {Ikon && <Ikon className={`mx-auto size-5 ${warna}`} />}
                <p className="mt-1 text-[10px] font-bold uppercase tracking-wide text-stone-500">{label}</p>
                <p className="mt-0.5 truncate text-sm font-semibold">{id ? peta.get(id)?.nama : '—'}</p>
              </div>
            ))}
          </div>
        </Card>
      )}
    </div>
  )
}
