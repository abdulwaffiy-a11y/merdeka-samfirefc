import { StrictMode, useState } from 'react'
import { createRoot } from 'react-dom/client'
import { Home, LayoutGrid, CalendarDays, GitBranch, Users, Info, RefreshCw, WifiOff, Lock, UserPlus, ShieldCheck } from 'lucide-react'
import './styles.css'

import { useAwam } from './lib/useAwam'
import { LOGO } from './lib/util'
import { Rangka, Badge, ToastProvider } from './ui'
import Beranda from './awam/Beranda'
import Kumpulan from './awam/Kumpulan'
import Jadual from './awam/Jadual'
import Carta from './awam/Carta'
import Pasukan from './awam/Pasukan'
import Maklumat from './awam/Maklumat'
import Daftar from './awam/Daftar'
import Ahli from './awam/Ahli'

const TAB = [
  { id: 'beranda',  label: 'Utama',    ikon: Home },
  { id: 'kumpulan', label: 'Kumpulan', ikon: LayoutGrid },
  { id: 'jadual',   label: 'Jadual',   ikon: CalendarDays },
  { id: 'carta',    label: 'Carta',    ikon: GitBranch },
  { id: 'pasukan',  label: 'Pasukan',  ikon: Users },
  { id: 'daftar',   label: 'Daftar',   ikon: UserPlus },
  { id: 'info',     label: 'Info',     ikon: Info },
]

function Memuat() {
  return (
    <div className="mx-auto max-w-3xl space-y-4 p-4">
      <Rangka className="h-44 rounded-2xl" />
      <Rangka className="h-28" />
      <Rangka className="h-40" />
    </div>
  )
}

function App() {
  const { data, memuat, menyegar, ralat, segarPada, segarSekarang } = useAwam()
  const [tab, setTab] = useState('beranda')

  if (memuat && !data) return <Memuat />

  if (!data) {
    return (
      <div className="mx-auto flex min-h-dvh max-w-md flex-col items-center justify-center gap-3 p-6 text-center">
        <WifiOff className="size-8 text-stone-400" />
        <p className="text-sm font-semibold">Tidak dapat memuatkan data kejohanan</p>
        <p className="text-xs text-stone-500">{ralat}</p>
        <button onClick={segarSekarang} className="mt-2 rounded-lg bg-maroon-700 px-4 py-2 text-sm font-semibold text-white">Cuba lagi</button>
      </div>
    )
  }

  const Kandungan = {
    beranda:  <Beranda data={data} keTab={setTab} />,
    kumpulan: <Kumpulan data={data} />,
    jadual:   <Jadual data={data} />,
    carta:    <Carta data={data} />,
    pasukan:  <Pasukan data={data} />,
    daftar:   <Daftar data={data} />,
    ahli:     <Ahli />,
    info:     <Maklumat data={data} />,
  }[tab]

  return (
    <div className="min-h-dvh pb-20 md:pb-6">
      {/* ---- Bar atas ---- */}
      <header className="sticky top-0 z-40 border-b border-stone-200 bg-white/85 backdrop-blur-md dark:border-stone-800 dark:bg-stone-950/85">
        <div className="mx-auto flex max-w-5xl items-center gap-3 px-4 py-2.5">
          <img src={LOGO} alt="SAMFIRE FC" className="size-9 shrink-0 object-contain" />
          <div className="min-w-0 flex-1">
            <p className="truncate text-[13px] font-bold leading-tight">Merdeka Kepala Batas 2026</p>
            <p className="truncate text-[10px] text-stone-500">Anjuran SAMFIRE FC · 30 Ogos 2026</p>
          </div>
          {data.tetapan.dikunci && <Badge jenis="navy" className="hidden lg:inline-flex"><Lock className="size-3" />Keputusan Rasmi</Badge>}
          <button
            onClick={() => setTab('ahli')}
            className={`inline-flex shrink-0 items-center gap-1.5 rounded-lg px-2.5 py-2 text-[12px] font-bold transition active:scale-95 sm:px-3.5 sm:text-[13px] ${
              tab === 'ahli'
                ? 'bg-navy-800 text-white'
                : 'bg-gold-500 text-navy-900 hover:bg-gold-400'
            }`}
            title="Daftar sebagai ahli SAMFIRE FC"
          >
            <ShieldCheck className="size-4 shrink-0" />
            <span className="hidden xs:inline">Jadi Ahli</span>
            <span className="xs:hidden">Ahli</span>
          </button>
          <button
            onClick={segarSekarang}
            title={segarPada ? `Dikemas kini ${segarPada.toLocaleTimeString('ms-MY')}` : 'Segarkan'}
            disabled={menyegar}
            className="rounded-lg p-2 text-stone-500 transition hover:bg-stone-100 active:scale-90 disabled:opacity-60 dark:hover:bg-stone-800"
          >
            <RefreshCw className={`size-4 ${menyegar || memuat ? 'animate-spin' : ''}`} />
          </button>
        </div>

        {/* Tab mendatar untuk skrin besar */}
        <nav className="mx-auto hidden max-w-5xl gap-1 px-3 pb-2 md:flex">
          {TAB.map((t) => (
            <button
              key={t.id}
              onClick={() => setTab(t.id)}
              className={`flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold transition ${
                tab === t.id
                  ? 'bg-maroon-700 text-white'
                  : 'text-stone-600 hover:bg-stone-100 dark:text-stone-400 dark:hover:bg-stone-800'
              }`}
            >
              <t.ikon className="size-4" />{t.label}
            </button>
          ))}
        </nav>
      </header>

      {ralat && (
        <div className="mx-auto max-w-5xl px-4 pt-3">
          <div className="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[12px] text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
            <WifiOff className="size-3.5 shrink-0" />
            Sambungan terputus — memaparkan data terakhir yang diterima.
          </div>
        </div>
      )}

      <main className="mx-auto max-w-5xl p-4">{Kandungan}</main>

      <footer className="mx-auto max-w-5xl px-4 pb-4 text-center text-[11px] text-stone-400">
        Keputusan dikemas kini automatik setiap 10 saat
        {segarPada && <> · terakhir {segarPada.toLocaleTimeString('ms-MY')}</>}
      </footer>

      {/* ---- Navigasi bawah (telefon) ---- */}
      <nav className="fixed inset-x-0 bottom-0 z-40 border-t border-stone-200 bg-white/95 backdrop-blur-md md:hidden dark:border-stone-800 dark:bg-stone-950/95"
           style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}>
        <div className="grid grid-cols-7">
          {TAB.map((t) => (
            <button
              key={t.id}
              onClick={() => setTab(t.id)}
              className={`flex min-w-0 flex-col items-center gap-0.5 px-0.5 py-2 text-[10px] font-semibold transition ${
                tab === t.id ? 'text-maroon-700 dark:text-maroon-300' : 'text-stone-400'
              }`}
            >
              <t.ikon className="size-[18px] shrink-0" />
              <span className="w-full truncate text-center leading-tight">{t.label}</span>
            </button>
          ))}
        </div>
      </nav>
    </div>
  )
}

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <ToastProvider><App /></ToastProvider>
  </StrictMode>,
)
