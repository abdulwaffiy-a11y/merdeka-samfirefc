import { useMemo, useState } from 'react'
import { Minus, Plus, Save, Radio, CheckCircle2, RotateCcw, AlertTriangle } from 'lucide-react'
import { Card, Badge, BadgeLive, Button, Dialog, Input, Label, Tabs, useToast } from '../ui'
import { petaPasukan, namaSisi, teksSkor, PERINGKAT_NAMA, kodPapar } from '../lib/util'
import { api } from '../lib/api'

/* ---------------------------------------------------- Pemilih skor besar */
function PilihSkor({ label, nilai, tetapkan }) {
  const ubah = (d) => tetapkan(Math.max(0, Math.min(99, (nilai ?? 0) + d)))
  return (
    <div className="min-w-0 rounded-xl border border-stone-200 p-2 sm:p-3 dark:border-stone-800">
      <p className="mb-2 truncate text-center text-[12px] font-semibold sm:text-[13px]">{label}</p>
      <div className="flex items-center justify-center gap-1.5 sm:gap-3">
        <button
          onClick={() => ubah(-1)}
          className="grid size-10 shrink-0 place-items-center rounded-xl bg-stone-100 text-stone-700 active:scale-95 sm:size-12 dark:bg-stone-800 dark:text-stone-200"
          aria-label="Kurang"
        >
          <Minus className="size-5" />
        </button>
        <input
          inputMode="numeric"
          value={nilai ?? ''}
          onChange={(e) => {
            const v = e.target.value.replace(/\D/g, '')
            tetapkan(v === '' ? null : Math.min(99, parseInt(v, 10)))
          }}
          className="tnum h-12 w-full min-w-0 max-w-20 rounded-xl border border-stone-300 text-center text-2xl font-black outline-none focus:border-maroon-700 focus:ring-2 focus:ring-maroon-700/20 sm:h-14 sm:text-3xl dark:border-stone-700 dark:bg-stone-900"
          placeholder="–"
        />
        <button
          onClick={() => ubah(1)}
          className="grid size-10 shrink-0 place-items-center rounded-xl bg-maroon-700 text-white active:scale-95 sm:size-12"
          aria-label="Tambah"
        >
          <Plus className="size-5" />
        </button>
      </div>
    </div>
  )
}

/* -------------------------------------------------------- Dialog kemaskini */
function DialogSkor({ m, peta, tutup, selepasSimpan }) {
  const toast = useToast()
  const [sh, setSh] = useState(m.skor_home)
  const [sa, setSa] = useState(m.skor_away)
  const [ph, setPh] = useState(m.penalti_home)
  const [pa, setPa] = useState(m.penalti_away)
  const [status, setStatus] = useState(m.status)
  const [catatan, setCatatan] = useState(m.catatan || '')
  const [sibuk, setSibuk] = useState(false)
  const [perluPaksa, setPerluPaksa] = useState(false)

  const koSeri = m.peringkat !== 'grup' && status === 'done' && sh !== null && sh === sa
  const belumAdaPasukan = !m.team_home_id || !m.team_away_id

  const simpan = async (paksa = false) => {
    setSibuk(true)
    try {
      const r = await api.perlawananSimpan({
        id: m.id, version: m.version,
        skor_home: sh, skor_away: sa,
        penalti_home: koSeri ? ph : null,
        penalti_away: koSeri ? pa : null,
        status, catatan, paksa,
      })
      toast('Keputusan disimpan.', 'ok')
      selepasSimpan(r)
      tutup()
    } catch (e) {
      if (e.kod === 428) { setPerluPaksa(true); toast(e.message, 'ralat') }
      else if (e.kod === 409) { toast(e.message, 'ralat'); selepasSimpan(null); tutup() }
      else toast(e.message, 'ralat')
    } finally {
      setSibuk(false)
    }
  }

  const namaH = namaSisi(peta, m.team_home_id, m.home_sumber)
  const namaA = namaSisi(peta, m.team_away_id, m.away_sumber)

  return (
    <Dialog buka tutup={tutup} tajuk={`${kodPapar(m.kod)} · ${m.peringkat === 'grup' ? `Kumpulan ${m.kumpulan}` : PERINGKAT_NAMA[m.peringkat]}`}>
      {belumAdaPasukan ? (
        <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
          Pasukan untuk perlawanan ini belum ditentukan ({namaH} vs {namaA}). Selesaikan peringkat sebelumnya dahulu.
        </div>
      ) : (
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <PilihSkor label={namaH} nilai={sh} tetapkan={setSh} />
            <PilihSkor label={namaA} nilai={sa} tetapkan={setSa} />
          </div>

          {koSeri && (
            <div className="rounded-xl border border-maroon-200 bg-maroon-50 p-3 dark:border-maroon-900 dark:bg-maroon-900/20">
              <p className="mb-2 text-center text-[12px] font-semibold text-maroon-800 dark:text-maroon-200">
                Perlawanan kalah mati tidak boleh seri — isi keputusan sepakan penalti
              </p>
              <div className="grid grid-cols-2 gap-3">
                <PilihSkor label={`Penalti · ${namaH}`} nilai={ph} tetapkan={setPh} />
                <PilihSkor label={`Penalti · ${namaA}`} nilai={pa} tetapkan={setPa} />
              </div>
            </div>
          )}

          <div>
            <Label>Status perlawanan</Label>
            <div className="grid grid-cols-3 gap-2">
              {[
                ['scheduled', 'Belum Mula', RotateCcw],
                ['live', 'Sedang Main', Radio],
                ['done', 'Tamat', CheckCircle2],
              ].map(([nilai, label, Ikon]) => (
                <button
                  key={nilai}
                  onClick={() => setStatus(nilai)}
                  className={`flex h-14 flex-col items-center justify-center gap-1 rounded-xl border text-[11px] font-bold transition ${
                    status === nilai
                      ? nilai === 'live'
                        ? 'border-red-600 bg-red-600 text-white'
                        : nilai === 'done'
                          ? 'border-emerald-600 bg-emerald-600 text-white'
                          : 'border-stone-500 bg-stone-600 text-white'
                      : 'border-stone-300 text-stone-600 dark:border-stone-700 dark:text-stone-400'
                  }`}
                >
                  <Ikon className="size-4" />{label}
                </button>
              ))}
            </div>
          </div>

          <div>
            <Label>Catatan (pilihan)</Label>
            <Input value={catatan} onChange={(e) => setCatatan(e.target.value)} placeholder="cth: pasukan lawan tidak hadir" maxLength={200} />
          </div>

          {perluPaksa && (
            <div className="flex gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-[12px] text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
              <AlertTriangle className="size-4 shrink-0" />
              <span>Undian suku akhir sudah dijalankan. Sahkan sekali lagi untuk membetulkan skor kumpulan ini. Perubahan yang menukar johan kumpulan akan tetap ditolak.</span>
            </div>
          )}

          <div className="flex gap-2 pt-1">
            <Button jenis="garis" ukuran="lg" className="flex-1" onClick={tutup}>Batal</Button>
            <Button
              ukuran="lg"
              className="flex-[2]"
              disabled={sibuk}
              jenis={perluPaksa ? 'bahaya' : 'utama'}
              onClick={() => simpan(perluPaksa)}
            >
              <Save className="size-4" />{sibuk ? 'Menyimpan…' : perluPaksa ? 'Sahkan & Simpan' : 'Simpan Keputusan'}
            </Button>
          </div>
        </div>
      )}
    </Dialog>
  )
}

/* ------------------------------------------------------------------ Senarai */
export default function PerlawananAdmin({ awam, perlawanan, muatSemula }) {
  const peta = petaPasukan(awam.pasukan)
  const [tapis, setTapis] = useState('semua')
  const [pilih, setPilih] = useState(null)

  const senarai = useMemo(() => {
    const s = [...perlawanan].sort((a, b) => a.urutan - b.urutan)
    if (tapis === 'semua') return s
    if (tapis === 'belum') return s.filter((m) => m.status !== 'done')
    if (tapis === 'live')  return s.filter((m) => m.status === 'live')
    if (tapis === 'ko')    return s.filter((m) => m.peringkat !== 'grup')
    return s.filter((m) => m.peringkat === 'grup')
  }, [perlawanan, tapis])

  return (
    <div className="space-y-4">
      <Tabs
        nilai={tapis} tetapkan={setTapis}
        item={[
          { nilai: 'semua', label: 'Semua' },
          { nilai: 'belum', label: 'Belum Tamat' },
          { nilai: 'live',  label: 'Live' },
          { nilai: 'grup',  label: 'Kumpulan' },
          { nilai: 'ko',    label: 'Kalah Mati' },
        ]}
      />

      <Card className="divide-y divide-stone-100 overflow-hidden dark:divide-stone-900">
        {senarai.length === 0 && <p className="p-8 text-center text-sm text-stone-500">Tiada perlawanan.</p>}
        {senarai.map((m) => (
          <button
            key={m.kod}
            onClick={() => setPilih(m)}
            className="flex w-full items-center gap-3 px-3 py-3 text-left transition hover:bg-stone-50 active:bg-stone-100 sm:px-4 dark:hover:bg-stone-900"
          >
            <div className="w-16 shrink-0">
              <p className="text-[11px] font-bold text-stone-500">{kodPapar(m.kod)}</p>
              <p className="tnum text-[10px] text-stone-400">{String(m.masa_jadual).slice(0, 5)}</p>
            </div>
            <div className="min-w-0 flex-1">
              <p className={`truncate text-[13px] ${!m.team_home_id ? 'italic text-stone-400' : ''}`}>{namaSisi(peta, m.team_home_id, m.home_sumber)}</p>
              <p className={`truncate text-[13px] ${!m.team_away_id ? 'italic text-stone-400' : ''}`}>{namaSisi(peta, m.team_away_id, m.away_sumber)}</p>
            </div>
            <div className="shrink-0 text-right">
              <p className="tnum text-base font-bold">
                {m.skor_home === null ? <span className="text-stone-300">–</span> : `${m.skor_home}–${m.skor_away}`}
              </p>
              {m.status === 'live' ? <BadgeLive /> : m.status === 'done' ? <Badge jenis="hijau">Tamat</Badge> : <Badge>Belum</Badge>}
            </div>
          </button>
        ))}
      </Card>

      {pilih && (
        <DialogSkor
          key={pilih.kod + pilih.version}
          m={pilih}
          peta={peta}
          tutup={() => setPilih(null)}
          selepasSimpan={() => muatSemula()}
        />
      )}
    </div>
  )
}
