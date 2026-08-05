import { Radio, CheckCircle2, Clock, Shuffle, AlertTriangle, ExternalLink, Lock } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardBody, Badge, BadgeLive, Button } from '../ui'
import { petaPasukan, namaSisi, kodPapar, PERINGKAT_PENDEK } from '../lib/util'

export default function Dashboard({ awam, perlawanan, keTab }) {
  const peta = petaPasukan(awam.pasukan)
  const tamat = perlawanan.filter((m) => m.status === 'done').length
  const live = perlawanan.filter((m) => m.status === 'live')
  const seterusnya = perlawanan.filter((m) => m.status === 'scheduled').sort((a, b) => a.urutan - b.urutan).slice(0, 5)

  const grupTamat = perlawanan.filter((m) => m.peringkat === 'grup' && m.status === 'done').length
  const namaKosong = awam.pasukan.filter((t) => !t.diisi).length
  const adaUndian = awam.undian?.ada
  const kumpSeri = ['A','B','C','D','E','F','G','H'].filter((h) => awam.kedudukan[h]?.perlu_undian)

  const Stat = ({ label, nilai, nota, warna = 'text-maroon-700 dark:text-maroon-300' }) => (
    <Card className="p-3">
      <p className={`tnum text-2xl font-black ${warna}`}>{nilai}</p>
      <p className="text-[11px] font-semibold uppercase tracking-wide text-stone-500">{label}</p>
      {nota && <p className="mt-0.5 text-[11px] text-stone-400">{nota}</p>}
    </Card>
  )

  return (
    <div className="space-y-4">
      {awam.tetapan.dikunci && (
        <div className="flex items-center gap-2 rounded-xl border border-navy-500/30 bg-navy-50 px-4 py-3 text-sm font-semibold text-navy-800 dark:bg-navy-900/40 dark:text-navy-100">
          <Lock className="size-4" />Keputusan kejohanan telah DIKUNCI. Tiada perubahan dibenarkan.
        </div>
      )}

      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <Stat label="Perlawanan Tamat" nilai={`${tamat}/32`} />
        <Stat label="Sedang Main" nilai={live.length} warna={live.length ? 'text-red-600' : 'text-stone-400'} />
        <Stat label="Kumpulan" nilai={`${grupTamat}/24`} nota="perlawanan kumpulan" />
        <Stat label="Pasukan Belum Dinamakan" nilai={namaKosong} warna={namaKosong ? 'text-amber-600' : 'text-emerald-600'} />
      </div>

      {/* ---- Perkara perlu tindakan ---- */}
      {(namaKosong > 0 || kumpSeri.length > 0 || (grupTamat === 24 && !adaUndian)) && (
        <Card>
          <CardHeader><CardTitle className="flex items-center gap-2"><AlertTriangle className="size-4 text-amber-600" />Perlu Tindakan</CardTitle></CardHeader>
          <div className="divide-y divide-stone-100 dark:divide-stone-900">
            {namaKosong > 0 && (
              <div className="flex items-center gap-3 px-4 py-3">
                <p className="min-w-0 flex-1 text-[13px]">{namaKosong} slot pasukan masih belum dinamakan.</p>
                <Button ukuran="sm" jenis="garis" onClick={() => keTab('pasukan')}>Isi nama</Button>
              </div>
            )}
            {kumpSeri.length > 0 && (
              <div className="flex items-center gap-3 px-4 py-3">
                <p className="min-w-0 flex-1 text-[13px]">
                  Kumpulan <strong>{kumpSeri.join(', ')}</strong> seri sepenuhnya di tempat pertama — perlu cabutan undi kumpulan.
                </p>
                <Button ukuran="sm" jenis="garis" onClick={() => keTab('pasukan')}>Tetapkan</Button>
              </div>
            )}
            {grupTamat === 24 && !adaUndian && (
              <div className="flex items-center gap-3 px-4 py-3">
                <p className="min-w-0 flex-1 text-[13px] font-semibold">Semua perlawanan kumpulan tamat — undian suku akhir boleh dijalankan.</p>
                <Button ukuran="sm" onClick={() => keTab('undian')}><Shuffle className="size-3.5" />Undian</Button>
              </div>
            )}
          </div>
        </Card>
      )}

      {/* ---- Live ---- */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2"><Radio className="size-4 text-red-600" />Sedang Berlangsung</CardTitle>
          {live.length > 0 && <BadgeLive />}
        </CardHeader>
        {live.length === 0
          ? <CardBody className="text-center text-sm text-stone-500">Tiada perlawanan ditanda LIVE.</CardBody>
          : (
            <div className="divide-y divide-stone-100 dark:divide-stone-900">
              {live.map((m) => (
                <button key={m.kod} onClick={() => keTab('perlawanan')} className="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-stone-50 dark:hover:bg-stone-900">
                  <span className="w-14 shrink-0 text-[11px] font-bold text-stone-500">{kodPapar(m.kod)}</span>
                  <span className="min-w-0 flex-1 truncate text-sm">
                    {namaSisi(peta, m.team_home_id, m.home_sumber)} vs {namaSisi(peta, m.team_away_id, m.away_sumber)}
                  </span>
                  <span className="tnum shrink-0 font-bold">{m.skor_home ?? 0}–{m.skor_away ?? 0}</span>
                </button>
              ))}
            </div>
          )}
      </Card>

      {/* ---- Seterusnya ---- */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2"><Clock className="size-4" />Perlawanan Seterusnya</CardTitle>
          <Button jenis="senyap" ukuran="sm" onClick={() => keTab('perlawanan')}>Semua</Button>
        </CardHeader>
        {seterusnya.length === 0
          ? <CardBody className="text-center text-sm text-stone-500"><CheckCircle2 className="mx-auto mb-1 size-5 text-emerald-600" />Semua perlawanan telah tamat.</CardBody>
          : (
            <div className="divide-y divide-stone-100 dark:divide-stone-900">
              {seterusnya.map((m) => (
                <button key={m.kod} onClick={() => keTab('perlawanan')} className="flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-stone-50 dark:hover:bg-stone-900">
                  <span className="tnum w-12 shrink-0 text-[11px] font-semibold text-stone-400">{String(m.masa_jadual).slice(0, 5)}</span>
                  <span className="min-w-0 flex-1 truncate text-[13px]">
                    {namaSisi(peta, m.team_home_id, m.home_sumber)} <span className="text-stone-400">vs</span> {namaSisi(peta, m.team_away_id, m.away_sumber)}
                  </span>
                  <Badge>{m.peringkat === 'grup' ? `Kump ${m.kumpulan}` : PERINGKAT_PENDEK[m.peringkat]}</Badge>
                  <Badge>G{m.gelanggang}</Badge>
                </button>
              ))}
            </div>
          )}
      </Card>

      <a href="./index.html" target="_blank" rel="noreferrer"
         className="flex items-center justify-center gap-2 rounded-xl border border-stone-200 bg-white px-4 py-3 text-sm font-semibold text-stone-600 hover:bg-stone-50 dark:border-stone-800 dark:bg-stone-950 dark:text-stone-300">
        <ExternalLink className="size-4" />Buka paparan awam (untuk penonton)
      </a>
    </div>
  )
}
