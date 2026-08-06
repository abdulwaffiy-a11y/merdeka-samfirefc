import { StrictMode, useEffect, useState, useCallback } from 'react'
import { createRoot } from 'react-dom/client'
import {
  LayoutDashboard, Users, ListChecks, Shuffle, ShieldCheck, ScrollText,
  LogOut, RefreshCw, LogIn, Loader2, ClipboardList,
} from 'lucide-react'
import './styles.css'

import { api, setCsrf } from './lib/api'
import { LOGO } from './lib/util'
import { Card, CardBody, Button, Input, Label, Badge, ToastProvider, useToast, Rangka } from './ui'
import Dashboard from './pentadbir/Dashboard'
import PasukanAdmin from './pentadbir/PasukanAdmin'
import PerlawananAdmin from './pentadbir/PerlawananAdmin'
import UndianAdmin from './pentadbir/UndianAdmin'
import AkaunAdmin from './pentadbir/AkaunAdmin'
import LogAdmin from './pentadbir/LogAdmin'
import DaftarAdmin from './pentadbir/DaftarAdmin'

const TAB = [
  { id: 'dashboard',  label: 'Dashboard',  pendek: 'Utama',  ikon: LayoutDashboard },
  { id: 'perlawanan', label: 'Perlawanan', pendek: 'Skor',   ikon: ListChecks },
  { id: 'daftar',     label: 'Daftar',     pendek: 'Daftar', ikon: ClipboardList },
  { id: 'pasukan',    label: 'Pasukan',    pendek: 'Pasukan',ikon: Users },
  { id: 'undian',     label: 'Undian',     pendek: 'Undian', ikon: Shuffle },
  { id: 'akaun',      label: 'Akaun',      pendek: 'Akaun',  ikon: ShieldCheck },
  { id: 'log',        label: 'Log',        pendek: 'Log',    ikon: ScrollText },
]

/* ------------------------------------------------------------------ Login */
function Login({ selepasLogin }) {
  const toast = useToast()
  const [email, setEmail] = useState('')
  const [pass, setPass] = useState('')
  const [sibuk, setSibuk] = useState(false)

  const hantar = async (e) => {
    e.preventDefault()
    setSibuk(true)
    try {
      const r = await api.login(email.trim(), pass)
      setCsrf(r.csrf)
      selepasLogin(r.admin)
    } catch (err) {
      toast(err.message, 'ralat')
    } finally { setSibuk(false) }
  }

  return (
    <div className="flex min-h-dvh items-center justify-center bg-gradient-to-br from-navy-900 via-navy-800 to-maroon-900 p-4">
      <Card className="w-full max-w-sm border-0 shadow-2xl">
        <CardBody className="p-6">
          <div className="mb-5 text-center">
            <img src={LOGO} alt="SAMFIRE FC" className="mx-auto size-16 object-contain" />
            <h1 className="mt-3 text-base font-bold">Panel Admin</h1>
            <p className="mt-0.5 text-[12px] text-stone-500">Kejohanan Futsal Merdeka Kepala Batas 2026</p>
          </div>
          <form onSubmit={hantar} className="space-y-3">
            <div>
              <Label>Emel</Label>
              <Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoComplete="username" autoFocus />
            </div>
            <div>
              <Label>Kata laluan</Label>
              <Input type="password" value={pass} onChange={(e) => setPass(e.target.value)} required autoComplete="current-password" />
            </div>
            <Button ukuran="lg" className="w-full" disabled={sibuk} type="submit">
              {sibuk ? <Loader2 className="size-4 animate-spin" /> : <LogIn className="size-4" />}
              {sibuk ? 'Menyemak…' : 'Log Masuk'}
            </Button>
          </form>
          <p className="mt-4 text-center text-[11px] text-stone-400">
            5 percubaan gagal akan mengunci log masuk selama 15 minit.
          </p>
        </CardBody>
      </Card>
    </div>
  )
}

/* -------------------------------------------------------------------- App */
function Panel({ admin, keluar }) {
  const toast = useToast()
  const [tab, setTab] = useState('dashboard')
  const [awam, setAwam] = useState(null)
  const [perlawanan, setPerlawanan] = useState([])
  const [memuat, setMemuat] = useState(true)
  const [menyegar, setMenyegar] = useState(false)
  const [segarPada, setSegarPada] = useState(null)

  const muat = useCallback(async (manual = false) => {
    if (manual) setMenyegar(true)
    try {
      const [a, m] = await Promise.all([api.awam(), api.perlawananSenarai()])
      setAwam(a)
      setPerlawanan(m.perlawanan)
      setSegarPada(new Date())
      if (manual) toast('Data disegarkan.', 'ok')
    } catch (e) {
      if (e.kod === 401) { keluar(); return }
      toast(e.message, 'ralat')
    } finally {
      setMemuat(false)
      if (manual) setTimeout(() => setMenyegar(false), 400)
    }
  }, [keluar, toast])

  useEffect(() => {
    muat()
    const i = setInterval(() => { if (document.visibilityState === 'visible') muat() }, 15000)
    return () => clearInterval(i)
  }, [muat])

  if (memuat || !awam) {
    return <div className="mx-auto max-w-4xl space-y-3 p-4"><Rangka className="h-24" /><Rangka className="h-40" /></div>
  }

  const Kandungan = {
    dashboard:  <Dashboard awam={awam} perlawanan={perlawanan} keTab={setTab} />,
    perlawanan: <PerlawananAdmin awam={awam} perlawanan={perlawanan} muatSemula={muat} />,
    daftar:     <DaftarAdmin admin={admin} muatSemula={muat} />,
    pasukan:    <PasukanAdmin muatSemula={muat} />,
    undian:     <UndianAdmin admin={admin} awam={awam} muatSemula={muat} />,
    akaun:      <AkaunAdmin admin={admin} awam={awam} muatSemula={muat} />,
    log:        <LogAdmin />,
  }[tab]

  return (
    <div className="min-h-dvh pb-20 md:pb-6">
      <header className="sticky top-0 z-40 border-b border-stone-200 bg-navy-800 text-white">
        <div className="mx-auto flex max-w-5xl items-center gap-3 px-4 py-2.5">
          <img src={LOGO} alt="SAMFIRE FC" className="size-9 shrink-0 object-contain" />
          <div className="min-w-0 flex-1">
            <p className="truncate text-[13px] font-bold leading-tight">Panel Admin</p>
            <p className="truncate text-[10px] text-white/60">{admin.nama} · {admin.role === 'super' ? 'Super Admin' : 'Admin'}</p>
          </div>
          <button
            onClick={() => muat(true)}
            disabled={menyegar}
            className="rounded-lg p-2 text-white/70 transition hover:bg-white/10 active:scale-90 disabled:opacity-60"
            title={segarPada ? `Dikemas kini ${segarPada.toLocaleTimeString('ms-MY')}` : 'Segarkan'}
          >
            <RefreshCw className={`size-4 ${menyegar ? 'animate-spin' : ''}`} />
          </button>
          <button onClick={keluar} className="rounded-lg p-2 text-white/70 hover:bg-white/10" title="Log keluar">
            <LogOut className="size-4" />
          </button>
        </div>
        <nav className="mx-auto hidden max-w-5xl gap-1 px-3 pb-2 md:flex">
          {TAB.map((t) => (
            <button
              key={t.id}
              onClick={() => setTab(t.id)}
              className={`flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold transition ${
                tab === t.id ? 'bg-white text-navy-800' : 'text-white/70 hover:bg-white/10'
              }`}
            >
              <t.ikon className="size-4" />{t.label}
            </button>
          ))}
        </nav>
      </header>

      <main className="mx-auto max-w-5xl p-4">{Kandungan}</main>

      <footer className="mx-auto max-w-5xl px-4 pb-4 text-center text-[11px] text-stone-400">
        &copy; {new Date().getFullYear()} SAMFIRE FC · Sistem dibangunkan oleh{' '}
        <a href="https://waffiymarketingexpert.com/" target="_blank" rel="noopener noreferrer"
           className="font-bold text-maroon-700 underline-offset-2 hover:underline dark:text-maroon-300">
          Waffiy Marketing Expert
        </a>
      </footer>

      <nav className="fixed inset-x-0 bottom-0 z-40 border-t border-stone-200 bg-white/95 backdrop-blur-md md:hidden dark:border-stone-800 dark:bg-stone-950/95"
           style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}>
        <div className="grid grid-cols-7">
          {TAB.map((t) => (
            <button
              key={t.id}
              onClick={() => setTab(t.id)}
              className={`flex min-w-0 flex-col items-center gap-0.5 px-0.5 py-2 text-[10px] font-semibold ${tab === t.id ? 'text-maroon-700 dark:text-maroon-300' : 'text-stone-400'}`}
            >
              <t.ikon className="size-[18px] shrink-0" />
              <span className="w-full truncate text-center leading-tight">{t.pendek}</span>
            </button>
          ))}
        </div>
      </nav>
    </div>
  )
}

function App() {
  const [admin, setAdmin] = useState(undefined)   // undefined = belum semak

  useEffect(() => {
    api.saya()
      .then((r) => { setCsrf(r.csrf); setAdmin(r.admin) })
      .catch(() => setAdmin(null))
  }, [])

  const keluar = async () => {
    try { await api.logout() } catch { /* abaikan */ }
    setCsrf(null)
    setAdmin(null)
  }

  if (admin === undefined) {
    return <div className="grid min-h-dvh place-items-center"><Loader2 className="size-6 animate-spin text-stone-400" /></div>
  }
  if (!admin) return <Login selepasLogin={setAdmin} />
  return <Panel admin={admin} keluar={keluar} />
}

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <ToastProvider><App /></ToastProvider>
  </StrictMode>,
)
