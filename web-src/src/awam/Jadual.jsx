import { useState, useMemo } from 'react'
import { Card, Badge, BadgeLive, Tabs } from '../ui'
import { petaPasukan, namaSisi, teksSkor, PERINGKAT_NAMA, kodPapar, pemenangId } from '../lib/util'

export default function Jadual({ data }) {
  const peta = petaPasukan(data.pasukan)
  const [tapis, setTapis] = useState('semua')

  const senarai = useMemo(() => {
    const s = [...data.perlawanan].sort((a, b) => a.urutan - b.urutan)
    if (tapis === 'semua') return s
    if (tapis === 'live') return s.filter((m) => m.status === 'live')
    if (tapis === 'tamat') return s.filter((m) => m.status === 'done')
    if (tapis === 'belum') return s.filter((m) => m.status === 'scheduled')
    return s.filter((m) => m.peringkat === tapis)
  }, [data.perlawanan, tapis])

  // Kumpulkan mengikut peringkat untuk tajuk seksyen
  const berkumpul = []
  let peringkatKini = null
  senarai.forEach((m) => {
    if (m.peringkat !== peringkatKini) {
      peringkatKini = m.peringkat
      berkumpul.push({ tajuk: PERINGKAT_NAMA[m.peringkat], item: [] })
    }
    berkumpul[berkumpul.length - 1].item.push(m)
  })

  return (
    <div className="space-y-4">
      <Tabs
        nilai={tapis}
        tetapkan={setTapis}
        item={[
          { nilai: 'semua', label: 'Semua' },
          { nilai: 'live',  label: 'Live' },
          { nilai: 'tamat', label: 'Tamat' },
          { nilai: 'belum', label: 'Belum' },
          { nilai: 'grup',  label: 'Kumpulan' },
        ]}
      />

      {berkumpul.length === 0 && (
        <Card className="p-8 text-center text-sm text-stone-500">Tiada perlawanan dalam tapisan ini.</Card>
      )}

      {berkumpul.map((seksyen) => (
        <div key={seksyen.tajuk}>
          <h3 className="mb-2 px-1 text-[11px] font-bold uppercase tracking-widest text-stone-400">{seksyen.tajuk}</h3>
          <Card className="divide-y divide-stone-100 overflow-hidden dark:divide-stone-900">
            {seksyen.item.map((m) => {
              const menang = pemenangId(m)
              return (
                <div key={m.kod} className="px-3 py-2.5 sm:px-4">
                  <div className="mb-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[10px] font-semibold uppercase tracking-wide text-stone-400">
                    <span className="tnum">{m.masa}</span>
                    <span>·</span>
                    <span>Gelanggang {m.gelanggang}</span>
                    <span>·</span>
                    <span>{kodPapar(m.kod)}</span>
                    {m.kumpulan && <><span>·</span><span>Kump {m.kumpulan}</span></>}
                    <span className="ml-auto">
                      {m.status === 'live' ? <BadgeLive />
                        : m.status === 'done' ? <Badge jenis="hijau">Tamat</Badge>
                        : <Badge>{m.tempoh} minit</Badge>}
                    </span>
                  </div>
                  <div className="flex items-center gap-3">
                    <div className="min-w-0 flex-1 space-y-0.5">
                      <p className={`truncate text-sm ${menang === m.home_id && menang ? 'font-bold' : ''} ${!m.home_id ? 'italic text-stone-400' : ''}`}>
                        {namaSisi(peta, m.home_id, m.home_sumber)}
                      </p>
                      <p className={`truncate text-sm ${menang === m.away_id && menang ? 'font-bold' : ''} ${!m.away_id ? 'italic text-stone-400' : ''}`}>
                        {namaSisi(peta, m.away_id, m.away_sumber)}
                      </p>
                    </div>
                    <div className={`tnum shrink-0 text-right text-base font-bold ${m.status === 'live' ? 'text-red-600' : ''}`}>
                      {m.skor_home === null ? <span className="text-stone-300">—</span> : (
                        <>
                          <div>{m.skor_home}</div>
                          <div>{m.skor_away}</div>
                        </>
                      )}
                    </div>
                  </div>
                  {m.penalti_home !== null && (
                    <p className="mt-1 text-[11px] text-stone-500">Sepakan penalti {m.penalti_home}–{m.penalti_away}</p>
                  )}
                  {m.catatan && <p className="mt-1 text-[11px] italic text-stone-500">{m.catatan}</p>}
                </div>
              )
            })}
          </Card>
        </div>
      ))}
    </div>
  )
}
