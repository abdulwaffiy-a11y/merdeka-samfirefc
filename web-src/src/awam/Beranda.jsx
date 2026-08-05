import { useEffect, useState } from 'react'
import { Trophy, MapPin, CalendarDays, Radio, Clock } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardBody, Badge, BadgeLive, Kosong } from '../ui'
import Galeri from './Galeri'
import { petaPasukan, namaSisi, teksSkor, masaMy, tarikhMy, kiraUndur, PERINGKAT_PENDEK, kodPapar, pemenangId, LOGO } from '../lib/util'

function KotakUndur({ tarikh, masaMula }) {
  const [t, setT] = useState(() => kiraUndur(tarikh, masaMula))
  useEffect(() => {
    const i = setInterval(() => setT(kiraUndur(tarikh, masaMula)), 1000)
    return () => clearInterval(i)
  }, [tarikh, masaMula])

  if (!t) return null
  const sel = [
    ['Hari', t.hari], ['Jam', t.jam], ['Minit', t.minit], ['Saat', t.saat],
  ]
  return (
    <div className="grid grid-cols-4 gap-2">
      {sel.map(([label, nilai]) => (
        <div key={label} className="rounded-lg bg-white/10 px-2 py-2 text-center backdrop-blur-sm">
          <div className="tnum text-xl font-bold leading-none text-white sm:text-2xl">{String(nilai).padStart(2, '0')}</div>
          <div className="mt-1 text-[10px] font-medium uppercase tracking-wider text-white/70">{label}</div>
        </div>
      ))}
    </div>
  )
}

export function BarisPerlawanan({ m, peta, tebal }) {
  const menang = pemenangId(m)
  const sisi = (id, sumber, skor, adalahHome) => (
    <div className={`flex items-center justify-between gap-2 ${menang && menang === id ? 'font-bold' : ''}`}>
      <span className={`truncate text-sm ${!id ? 'italic text-stone-400' : ''}`}>{namaSisi(peta, id, sumber)}</span>
      <span className="tnum shrink-0 text-sm font-semibold">{skor === null ? '' : skor}</span>
    </div>
  )
  return (
    <div className={`space-y-1 ${tebal ? 'py-1' : ''}`}>
      {sisi(m.home_id, m.home_sumber, m.skor_home, true)}
      {sisi(m.away_id, m.away_sumber, m.skor_away, false)}
      {m.penalti_home !== null && (
        <p className="text-[11px] text-stone-500">Sepakan penalti {m.penalti_home}–{m.penalti_away}</p>
      )}
    </div>
  )
}

export default function Beranda({ data, keTab }) {
  const peta = petaPasukan(data.pasukan)
  const t = data.tetapan
  const perlawanan = data.perlawanan

  const live = perlawanan.filter((m) => m.status === 'live')
  const tamat = perlawanan.filter((m) => m.status === 'done')
  const terkini = [...tamat].sort((a, b) => b.urutan - a.urutan).slice(0, 6)
  const seterusnya = perlawanan.filter((m) => m.status === 'scheduled').sort((a, b) => a.urutan - b.urutan).slice(0, 4)
  const ka = data.kedudukan_akhir
  const adaJuara = !!ka.johan

  return (
    <div className="space-y-4">
      {/* ---- Hero ---- */}
      <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-maroon-800 via-maroon-700 to-navy-800 p-5 shadow-lg sm:p-7">
        <div className="pointer-events-none absolute -right-10 -top-10 size-40 rounded-full bg-gold-500/20 blur-2xl" />
        <img src={LOGO} alt="SAMFIRE FC" className="absolute right-0 top-0 size-20 object-contain opacity-90 sm:size-24" />
        <div className="relative">
          <Badge jenis="emas" className="bg-white/15 text-gold-300">Merdeka 2026</Badge>
          <h1 className="mt-2.5 max-w-[calc(100%-4.5rem)] text-2xl font-black leading-tight tracking-tight text-white sm:max-w-[calc(100%-6rem)] sm:text-3xl">
            {t.nama_kejohanan}
          </h1>
          <p className="mt-1.5 text-[13px] font-bold uppercase tracking-wide text-gold-300">
            Anjuran SAMFIRE FC
          </p>
          <p className="mt-1 text-[12px] leading-snug text-white/75">
            Dengan kerjasama PAKSY · Tajaan <span className="font-semibold text-white/90">YB Dato' Seri Reezal Merican</span>
          </p>
          <div className="mt-3 space-y-1.5 text-[13px] text-white/80">
            <p className="flex items-center gap-2"><CalendarDays className="size-3.5 shrink-0" />{tarikhMy(t.tarikh_kejohanan)} · bermula {masaMy(t.masa_mula)}</p>
            <p className="flex items-start gap-2"><MapPin className="mt-0.5 size-3.5 shrink-0" /><span>{t.lokasi}</span></p>
          </div>
          <div className="mt-4"><KotakUndur tarikh={t.tarikh_kejohanan} masaMula={t.masa_mula} /></div>
        </div>
      </div>

      {t.pengumuman && (
        <div className="rounded-xl border border-gold-500/40 bg-gold-500/10 px-4 py-3 text-sm font-medium text-gold-600 dark:text-gold-300">
          📣 {t.pengumuman}
        </div>
      )}

      {/* ---- Poster kejohanan ---- */}
      {t.poster && (
        <a
          href={t.poster}
          target="_blank"
          rel="noreferrer"
          title="Tekan untuk lihat saiz penuh"
          className="block overflow-hidden rounded-2xl border border-stone-200 bg-stone-100 shadow-sm transition hover:shadow-md dark:border-stone-800 dark:bg-stone-900"
        >
          <img
            src={t.poster}
            alt="Poster Kejohanan Futsal Merdeka Kepala Batas 2026"
            className="mx-auto block h-auto w-full max-w-md object-contain"
            loading="lazy"
          />
        </a>
      )}

      {/* ---- Juara ---- */}
      {adaJuara && (
        <Card className="overflow-hidden border-gold-500/50">
          <div className="animate-kilau bg-gradient-to-r from-gold-500/10 to-transparent p-5 text-center">
            <Trophy className="mx-auto size-8 text-gold-500" />
            <p className="mt-2 text-[11px] font-bold uppercase tracking-widest text-gold-600">Johan Kejohanan</p>
            <p className="mt-1 text-xl font-black">{peta.get(ka.johan)?.nama ?? '—'}</p>
            <div className="mt-3 grid grid-cols-3 gap-2 text-xs">
              {[['Naib Johan', ka.naib_johan], ['Tempat Ke-3', ka.ketiga], ['Tempat Ke-4', ka.keempat]].map(([l, id]) => (
                <div key={l} className="rounded-lg bg-stone-100 px-2 py-2 dark:bg-stone-900">
                  <p className="text-[10px] font-semibold uppercase tracking-wide text-stone-500">{l}</p>
                  <p className="mt-0.5 truncate font-semibold">{id ? peta.get(id)?.nama : '—'}</p>
                </div>
              ))}
            </div>
          </div>
        </Card>
      )}

      {/* ---- Sedang berlangsung ---- */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2"><Radio className="size-4 text-red-600" />Sedang Berlangsung</CardTitle>
          {live.length > 0 && <BadgeLive />}
        </CardHeader>
        {live.length === 0 ? (
          <Kosong ikon={Radio} tajuk="Tiada perlawanan sedang berlangsung" nota="Halaman ini kemas kini sendiri setiap 10 saat." />
        ) : (
          <div className="divide-y divide-stone-200 dark:divide-stone-800">
            {live.map((m) => (
              <div key={m.kod} className="px-4 py-3">
                <div className="mb-1.5 flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-stone-500">
                  <span>{kodPapar(m.kod)}</span>
                  <span>·</span>
                  <span>{m.peringkat === 'grup' ? `Kumpulan ${m.kumpulan}` : PERINGKAT_PENDEK[m.peringkat]}</span>
                  <span>·</span>
                  <span>Gelanggang {m.gelanggang}</span>
                </div>
                <BarisPerlawanan m={m} peta={peta} tebal />
              </div>
            ))}
          </div>
        )}
      </Card>

      {/* ---- Keputusan terkini ---- */}
      <Card>
        <CardHeader>
          <CardTitle>Keputusan Terkini</CardTitle>
          <button onClick={() => keTab('jadual')} className="text-xs font-semibold text-maroon-700 hover:underline dark:text-maroon-300">Lihat semua</button>
        </CardHeader>
        {terkini.length === 0 ? (
          <Kosong ikon={Clock} tajuk="Belum ada keputusan" nota="Keputusan akan dipaparkan sebaik sahaja perlawanan tamat." />
        ) : (
          <div className="divide-y divide-stone-200 dark:divide-stone-800">
            {terkini.map((m) => (
              <div key={m.kod} className="flex items-center gap-3 px-4 py-2.5">
                <div className="w-14 shrink-0 text-[11px] font-semibold text-stone-400">{kodPapar(m.kod)}</div>
                <div className="min-w-0 flex-1 truncate text-sm">
                  {namaSisi(peta, m.home_id, m.home_sumber)} <span className="text-stone-400">vs</span> {namaSisi(peta, m.away_id, m.away_sumber)}
                </div>
                <div className="tnum shrink-0 text-sm font-bold">{teksSkor(m)}</div>
              </div>
            ))}
          </div>
        )}
      </Card>

      {/* ---- Seterusnya ---- */}
      {seterusnya.length > 0 && (
        <Card>
          <CardHeader><CardTitle>Perlawanan Seterusnya</CardTitle></CardHeader>
          <div className="divide-y divide-stone-200 dark:divide-stone-800">
            {seterusnya.map((m) => (
              <div key={m.kod} className="flex items-center gap-3 px-4 py-2.5">
                <div className="tnum w-14 shrink-0 text-[11px] font-semibold text-stone-400">{m.masa}</div>
                <div className="min-w-0 flex-1 truncate text-sm">
                  <span className={m.home_id ? '' : 'italic text-stone-400'}>{namaSisi(peta, m.home_id, m.home_sumber)}</span>
                  <span className="text-stone-400"> vs </span>
                  <span className={m.away_id ? '' : 'italic text-stone-400'}>{namaSisi(peta, m.away_id, m.away_sumber)}</span>
                </div>
                <Badge>G{m.gelanggang}</Badge>
              </div>
            ))}
          </div>
        </Card>
      )}

      {/* ---- Ringkasan ---- */}
      <div className="grid grid-cols-3 gap-3">
        {[
          ['Pasukan', data.pasukan.length],
          ['Perlawanan', data.ringkasan.jumlah_perlawanan],
          ['Selesai', data.ringkasan.tamat],
        ].map(([l, v]) => (
          <Card key={l} className="p-3 text-center">
            <p className="tnum text-2xl font-black text-maroon-700 dark:text-maroon-300">{v}</p>
            <p className="mt-0.5 text-[11px] font-semibold uppercase tracking-wide text-stone-500">{l}</p>
          </Card>
        ))}
      </div>

      {/* ---- Peraturan ringkas ---- */}
      <Card>
        <CardHeader>
          <CardTitle>Peraturan Kejohanan</CardTitle>
          <button onClick={() => keTab('info')} className="text-xs font-semibold text-maroon-700 hover:underline dark:text-maroon-300">Penuh</button>
        </CardHeader>
        <div className="grid gap-x-4 gap-y-2 p-4 text-[12.5px] text-stone-600 sm:grid-cols-2 dark:text-stone-400">
          {[
            'Terbuka kepada penduduk yang menetap di Kepala Batas',
            'Yuran RM200 setiap pasukan · maksimum 10 pemain',
            'Format: liga berkumpulan, kemudian kalah mati',
            'Masa perlawanan: 5 minit · rehat 1 minit · 5 minit',
            'Mata sama? Keputusan bersemuka (head-to-head) diguna dahulu',
            'Johan kumpulan sahaja mara ke Suku Akhir',
            'Kalah mati seri → sepakan penalti',
            'Wajib menutup aurat sepanjang pertandingan',
            'Utamakan solat · jaga adab, disiplin & sportsmanship',
          ].map((p) => (
            <div key={p} className="flex gap-2">
              <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-maroon-700/60" />
              <span>{p}</span>
            </div>
          ))}
        </div>
      </Card>

      {/* ---- Galeri gambar ---- */}
      <Galeri />
    </div>
  )
}
