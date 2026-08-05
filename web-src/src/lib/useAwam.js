import { useEffect, useRef, useState, useCallback } from 'react'
import { api } from './api'

const KUNCI_CACHE = 'merdeka_awam_cache_v1'
const SELANG = 10000   // 10 saat

/**
 * Hook data awam dengan auto-refresh 10 saat.
 *
 * Ciri tahan-lasak untuk hari kejohanan:
 *  - ETag: jika tiada perubahan, server pulangkan 304 (beberapa bait sahaja)
 *  - Cache tempatan: jika server/talian putus, paparan terakhir masih kekal
 *  - Berhenti polling bila tab tidak dilihat (jimat bateri & data)
 */
export function useAwam() {
  const [data, setData] = useState(() => {
    try {
      const c = sessionStorage.getItem(KUNCI_CACHE)
      return c ? JSON.parse(c) : null
    } catch { return null }
  })
  const [memuat, setMemuat] = useState(true)
  const [menyegar, setMenyegar] = useState(false)
  const [ralat, setRalat] = useState(null)
  const [segarPada, setSegarPada] = useState(null)

  const etag = useRef(null)
  const jalan = useRef(true)

  const ambil = useCallback(async (manual = false) => {
    if (manual) setMenyegar(true)
    try {
      const r = await api.awam(etag.current)
      if (!jalan.current) return
      if (!r._tidakBerubah) {
        etag.current = r._etag || null
        setData(r)
        try { sessionStorage.setItem(KUNCI_CACHE, JSON.stringify(r)) } catch { /* kuota penuh */ }
      }
      setRalat(null)
      setSegarPada(new Date())
    } catch (e) {
      if (!jalan.current) return
      setRalat(e.message || 'Tidak dapat sambung ke pelayan.')
    } finally {
      if (jalan.current) setMemuat(false)
      if (manual) setTimeout(() => { if (jalan.current) setMenyegar(false) }, 400)
    }
  }, [])

  useEffect(() => {
    jalan.current = true
    ambil()

    let timer = setInterval(() => {
      if (document.visibilityState === 'visible') ambil()
    }, SELANG)

    const bilaNampak = () => { if (document.visibilityState === 'visible') ambil() }
    document.addEventListener('visibilitychange', bilaNampak)
    window.addEventListener('online', bilaNampak)

    return () => {
      jalan.current = false
      clearInterval(timer)
      document.removeEventListener('visibilitychange', bilaNampak)
      window.removeEventListener('online', bilaNampak)
    }
  }, [ambil])

  return { data, memuat, menyegar, ralat, segarPada, segarSekarang: () => ambil(true) }
}
