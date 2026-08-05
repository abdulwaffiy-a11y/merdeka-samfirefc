import { useEffect, useRef, createContext, useContext, useState, useCallback } from 'react'
import { X, CheckCircle2, AlertTriangle, Info } from 'lucide-react'

export const cx = (...a) => a.filter(Boolean).join(' ')

/* ------------------------------------------------------------------ Card */
export function Card({ className, children, ...p }) {
  return (
    <div
      className={cx(
        'rounded-xl border border-stone-200 bg-white shadow-sm',
        'dark:border-stone-800 dark:bg-stone-950',
        className,
      )}
      {...p}
    >
      {children}
    </div>
  )
}
export const CardHeader = ({ className, children }) => (
  <div className={cx('flex items-center justify-between gap-3 border-b border-stone-200 px-4 py-3 dark:border-stone-800', className)}>
    {children}
  </div>
)
export const CardTitle = ({ className, children }) => (
  <h3 className={cx('text-sm font-semibold tracking-tight', className)}>{children}</h3>
)
export const CardBody = ({ className, children }) => (
  <div className={cx('p-4', className)}>{children}</div>
)

/* ---------------------------------------------------------------- Button */
const varian = {
  utama:   'bg-maroon-700 text-white hover:bg-maroon-800 focus-visible:outline-maroon-700',
  navy:    'bg-navy-800 text-white hover:bg-navy-900 focus-visible:outline-navy-800',
  emas:    'bg-gold-500 text-navy-900 hover:bg-gold-600 focus-visible:outline-gold-500',
  garis:   'border border-stone-300 bg-white text-stone-800 hover:bg-stone-50 dark:border-stone-700 dark:bg-stone-900 dark:text-stone-100 dark:hover:bg-stone-800',
  senyap:  'text-stone-600 hover:bg-stone-100 dark:text-stone-300 dark:hover:bg-stone-800',
  bahaya:  'bg-red-600 text-white hover:bg-red-700 focus-visible:outline-red-600',
}
const saiz = {
  sm: 'h-8 px-3 text-xs',
  md: 'h-10 px-4 text-sm',
  lg: 'h-12 px-5 text-base',
  xl: 'h-14 px-6 text-lg',
}
export function Button({ jenis = 'utama', ukuran = 'md', className, disabled, children, ...p }) {
  return (
    <button
      disabled={disabled}
      className={cx(
        'inline-flex select-none items-center justify-center gap-2 rounded-lg font-semibold transition',
        'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
        'disabled:cursor-not-allowed disabled:opacity-50',
        varian[jenis], saiz[ukuran], className,
      )}
      {...p}
    >
      {children}
    </button>
  )
}

/* ----------------------------------------------------------------- Badge */
const badgeVar = {
  kelabu:  'bg-stone-100 text-stone-700 dark:bg-stone-800 dark:text-stone-300',
  maroon:  'bg-maroon-100 text-maroon-800 dark:bg-maroon-900/40 dark:text-maroon-200',
  navy:    'bg-navy-100 text-navy-800 dark:bg-navy-900/50 dark:text-navy-100',
  emas:    'bg-gold-500/15 text-gold-600 dark:text-gold-300',
  hijau:   'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
  live:    'bg-red-600 text-white',
}
export function Badge({ jenis = 'kelabu', className, children }) {
  return (
    <span className={cx('inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide', badgeVar[jenis], className)}>
      {children}
    </span>
  )
}
export const BadgeLive = () => (
  <Badge jenis="live" className="animate-denyut">
    <span className="size-1.5 rounded-full bg-white" /> Live
  </Badge>
)

/* ------------------------------------------------------------------ Tabs */
export function Tabs({ nilai, tetapkan, item, className }) {
  return (
    <div className={cx('skrol-x -mx-1 overflow-x-auto px-1', className)}>
      <div className="inline-flex min-w-full gap-1 rounded-xl bg-stone-100 p-1 dark:bg-stone-900">
        {item.map((t) => (
          <button
            key={t.nilai}
            onClick={() => tetapkan(t.nilai)}
            className={cx(
              'flex-1 whitespace-nowrap rounded-lg px-3 py-2 text-xs font-semibold transition sm:text-sm',
              nilai === t.nilai
                ? 'bg-white text-maroon-700 shadow-sm dark:bg-stone-800 dark:text-maroon-200'
                : 'text-stone-500 hover:text-stone-800 dark:text-stone-400 dark:hover:text-stone-200',
            )}
          >
            {t.label}
          </button>
        ))}
      </div>
    </div>
  )
}

/* ---------------------------------------------------------------- Dialog */
export function Dialog({ buka, tutup, tajuk, children, lebar = 'max-w-lg' }) {
  const ref = useRef(null)
  useEffect(() => {
    const esc = (e) => { if (e.key === 'Escape') tutup?.() }
    if (buka) {
      document.addEventListener('keydown', esc)
      document.body.style.overflow = 'hidden'
    }
    return () => {
      document.removeEventListener('keydown', esc)
      document.body.style.overflow = ''
    }
  }, [buka, tutup])

  if (!buka) return null
  return (
    <div
      className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4"
      onMouseDown={(e) => { if (e.target === ref.current) tutup?.() }}
      ref={ref}
    >
      <div className={cx('animate-masuk max-h-[92vh] w-full overflow-y-auto rounded-t-2xl bg-white shadow-xl sm:rounded-2xl dark:bg-stone-950', lebar)}>
        <div className="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-stone-200 bg-white px-4 py-3 dark:border-stone-800 dark:bg-stone-950">
          <h3 className="text-sm font-bold">{tajuk}</h3>
          <button onClick={tutup} className="rounded-lg p-1.5 text-stone-500 hover:bg-stone-100 dark:hover:bg-stone-800" aria-label="Tutup">
            <X className="size-4" />
          </button>
        </div>
        <div className="p-4">{children}</div>
      </div>
    </div>
  )
}

/* ---------------------------------------------------------------- Fields */
export function Label({ children, className }) {
  return <label className={cx('mb-1.5 block text-xs font-semibold text-stone-600 dark:text-stone-400', className)}>{children}</label>
}
export function Input({ className, ...p }) {
  return (
    <input
      className={cx(
        'h-10 w-full rounded-lg border border-stone-300 bg-white px-3 text-sm outline-none transition',
        'placeholder:text-stone-400 focus:border-maroon-700 focus:ring-2 focus:ring-maroon-700/20',
        'disabled:bg-stone-100 disabled:text-stone-500',
        'dark:border-stone-700 dark:bg-stone-900 dark:disabled:bg-stone-800',
        className,
      )}
      {...p}
    />
  )
}
export function Select({ className, children, ...p }) {
  return (
    <select
      className={cx('h-10 w-full rounded-lg border border-stone-300 bg-white px-3 text-sm outline-none focus:border-maroon-700 focus:ring-2 focus:ring-maroon-700/20 dark:border-stone-700 dark:bg-stone-900', className)}
      {...p}
    >
      {children}
    </select>
  )
}

/* ----------------------------------------------------------------- Toast */
const ToastCtx = createContext(() => {})
export const useToast = () => useContext(ToastCtx)

export function ToastProvider({ children }) {
  const [senarai, setSenarai] = useState([])

  const tunjuk = useCallback((mesej, jenis = 'info', tempoh = 4200) => {
    const id = Math.random().toString(36).slice(2)
    setSenarai((s) => [...s, { id, mesej, jenis }])
    setTimeout(() => setSenarai((s) => s.filter((t) => t.id !== id)), tempoh)
  }, [])

  const ikon = {
    ok:    <CheckCircle2 className="size-4 shrink-0 text-emerald-600" />,
    ralat: <AlertTriangle className="size-4 shrink-0 text-red-600" />,
    info:  <Info className="size-4 shrink-0 text-navy-500" />,
  }

  return (
    <ToastCtx.Provider value={tunjuk}>
      {children}
      <div className="pointer-events-none fixed inset-x-3 bottom-3 z-[60] flex flex-col items-center gap-2 sm:inset-x-auto sm:right-4 sm:items-end">
        {senarai.map((t) => (
          <div
            key={t.id}
            className={cx(
              'animate-masuk pointer-events-auto flex w-full max-w-sm items-start gap-2.5 rounded-xl border px-3.5 py-3 text-sm shadow-lg',
              t.jenis === 'ralat'
                ? 'border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100'
                : t.jenis === 'ok'
                  ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100'
                  : 'border-stone-200 bg-white text-stone-800 dark:border-stone-800 dark:bg-stone-900 dark:text-stone-100',
            )}
          >
            {ikon[t.jenis]}
            <span className="leading-snug">{t.mesej}</span>
          </div>
        ))}
      </div>
    </ToastCtx.Provider>
  )
}

/* --------------------------------------------------------------- Kosong */
export function Kosong({ ikon: Ikon, tajuk, nota }) {
  return (
    <div className="flex flex-col items-center gap-2 px-4 py-10 text-center">
      {Ikon && <Ikon className="size-7 text-stone-300 dark:text-stone-700" />}
      <p className="text-sm font-semibold text-stone-500">{tajuk}</p>
      {nota && <p className="max-w-xs text-xs text-stone-400">{nota}</p>}
    </div>
  )
}

/* -------------------------------------------------------------- Skeleton */
export const Rangka = ({ className }) => (
  <div className={cx('animate-pulse rounded-md bg-stone-200 dark:bg-stone-800', className)} />
)
