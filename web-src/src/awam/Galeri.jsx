import { useEffect, useState } from 'react'
import { Images, X, ChevronLeft, ChevronRight } from 'lucide-react'
import { Card, CardHeader, CardTitle } from '../ui'
import { jsonSelamat } from '../lib/api'

const asasApi = new URL('api/', document.baseURI).href.replace(/\/$/, '')

export default function Galeri() {
  const [senarai, setSenarai] = useState([])
  const [buka, setBuka] = useState(-1)      // indeks gambar dibuka penuh
  const [semua, setSemua] = useState(false)

  useEffect(() => {
    let hidup = true
    fetch(`${asasApi}/galeri.php?action=senarai`)
      .then((r) => jsonSelamat(r))
      .then((d) => { if (hidup && d.ok) setSenarai(d.galeri || []) })
      .catch(() => {})
    return () => { hidup = false }
  }, [])

  useEffect(() => {
    if (buka < 0) return
    const key = (e) => {
      if (e.key === 'Escape') setBuka(-1)
      if (e.key === 'ArrowRight') setBuka((i) => (i + 1) % senarai.length)
      if (e.key === 'ArrowLeft') setBuka((i) => (i - 1 + senarai.length) % senarai.length)
    }
    document.addEventListener('keydown', key)
    document.body.style.overflow = 'hidden'
    return () => { document.removeEventListener('keydown', key); document.body.style.overflow = '' }
  }, [buka, senarai.length])

  if (senarai.length === 0) return null

  const papar = semua ? senarai : senarai.slice(0, 9)

  return (
    <>
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2"><Images className="size-4" />Galeri Gambar</CardTitle>
          {senarai.length > 9 && (
            <button onClick={() => setSemua(!semua)} className="text-xs font-semibold text-maroon-700 hover:underline dark:text-maroon-300">
              {semua ? 'Tunjuk kurang' : `Lihat semua (${senarai.length})`}
            </button>
          )}
        </CardHeader>
        <div className="grid grid-cols-3 gap-1.5 p-3 sm:grid-cols-4 md:grid-cols-6">
          {papar.map((g, i) => (
            <button
              key={g.id}
              onClick={() => setBuka(i)}
              className="group relative aspect-square overflow-hidden rounded-lg bg-stone-100 dark:bg-stone-800"
            >
              <img
                src={g.thumb}
                alt={g.kapsyen || 'Gambar kejohanan'}
                loading="lazy"
                decoding="async"
                className="size-full object-cover transition duration-300 group-hover:scale-105"
              />
              {g.kapsyen && (
                <span className="absolute inset-x-0 bottom-0 truncate bg-gradient-to-t from-black/70 to-transparent px-1.5 pb-1 pt-4 text-left text-[10px] font-medium text-white">
                  {g.kapsyen}
                </span>
              )}
            </button>
          ))}
        </div>
      </Card>

      {/* ---- Paparan penuh ---- */}
      {buka >= 0 && senarai[buka] && (
        <div
          className="fixed inset-0 z-[70] flex items-center justify-center bg-black/90 p-3"
          onClick={() => setBuka(-1)}
        >
          <button
            onClick={(e) => { e.stopPropagation(); setBuka(-1) }}
            className="absolute right-3 top-3 rounded-full bg-white/10 p-2 text-white hover:bg-white/20"
            aria-label="Tutup"
          >
            <X className="size-5" />
          </button>

          {senarai.length > 1 && (
            <>
              <button
                onClick={(e) => { e.stopPropagation(); setBuka((i) => (i - 1 + senarai.length) % senarai.length) }}
                className="absolute left-2 rounded-full bg-white/10 p-2 text-white hover:bg-white/20"
                aria-label="Sebelum"
              >
                <ChevronLeft className="size-6" />
              </button>
              <button
                onClick={(e) => { e.stopPropagation(); setBuka((i) => (i + 1) % senarai.length) }}
                className="absolute right-2 rounded-full bg-white/10 p-2 text-white hover:bg-white/20"
                aria-label="Seterusnya"
              >
                <ChevronRight className="size-6" />
              </button>
            </>
          )}

          <figure className="max-h-full max-w-4xl" onClick={(e) => e.stopPropagation()}>
            <img
              src={senarai[buka].url}
              alt={senarai[buka].kapsyen || ''}
              className="mx-auto max-h-[82vh] w-auto rounded-lg object-contain"
            />
            <figcaption className="mt-2 text-center text-[12px] text-white/70">
              {senarai[buka].kapsyen && <span className="block text-white">{senarai[buka].kapsyen}</span>}
              {buka + 1} / {senarai.length}
            </figcaption>
          </figure>
        </div>
      )}
    </>
  )
}
