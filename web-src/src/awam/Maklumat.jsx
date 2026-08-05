import { Trophy, Clock, Info, Globe, Shield } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardBody, Badge } from '../ui'
import { HADIAH } from '../lib/util'

const TENTATIF = [
  ['8.00 – 8.30 pagi',    'Pendaftaran pasukan & taklimat pengurus'],
  ['8.30 pagi',           'Bacaan doa & perlawanan pertama'],
  ['8.30 – 11.15 pagi',   'Peringkat Kumpulan — 24 perlawanan (2 gelanggang)'],
  ['11.20 – 11.35 pagi',  'Pengumuman 8 johan kumpulan & undian suku akhir'],
  ['11.35 pagi – 12.10 tgh', 'Suku Akhir — 4 perlawanan'],
  ['12.15 – 1.15 petang', 'Rehat & makan tengah hari'],
  ['1.22 petang',         'Solat Zohor berjemaah di surau'],
  ['1.45 – 2.15 petang',  'Tazkirah oleh ustaz jemputan'],
  ['2.30 – 2.50 petang',  'Separuh Akhir — 2 perlawanan serentak'],
  ['3.10 – 3.25 petang',  'Penentuan Tempat Ketiga'],
  ['3.40 – 4.00 petang',  'PERLAWANAN AKHIR'],
  ['4.05 – 4.25 petang',  'Ucapan, penyampaian hadiah & sesi bergambar'],
  ['4.28 petang',         'Solat Asar berjemaah & bersurai'],
]

const TEMPOH = [
  ['Peringkat Kumpulan', '5 min + 1 rehat + 5 min', '11 minit'],
  ['Suku Akhir',         '5 min + 1 rehat + 5 min', '11 minit'],
  ['Separuh Akhir',      '7 min + 1 rehat + 7 min', '15 minit'],
  ['Tempat Ketiga',      '7 min + 1 rehat + 7 min', '15 minit'],
  ['Perlawanan Akhir',   '7 min + 1 rehat + 7 min', '15 minit'],
]

export default function Maklumat({ data }) {
  return (
    <div className="space-y-4">
      <Card>
        <CardHeader><CardTitle className="flex items-center gap-2"><Trophy className="size-4 text-gold-500" />Struktur Hadiah</CardTitle></CardHeader>
        <div className="divide-y divide-stone-100 dark:divide-stone-900">
          {HADIAH.map((h) => (
            <div key={h.tempat} className="flex items-center gap-3 px-4 py-3">
              <div className="min-w-0 flex-1">
                <p className="text-sm font-semibold">{h.tempat}</p>
                <p className="text-[12px] text-stone-500">{h.lain}</p>
              </div>
              <Badge jenis={h.jenis}>{h.tunai}</Badge>
            </div>
          ))}
          <div className="flex items-center gap-3 px-4 py-3">
            <div className="min-w-0 flex-1">
              <p className="text-sm font-semibold">Semua Peserta</p>
              <p className="text-[12px] text-stone-500">Sijil Penyertaan · dimuat turun dalam talian</p>
            </div>
            <Badge jenis="navy">Sijil</Badge>
          </div>
        </div>
        <div className="border-t border-stone-200 px-4 py-2.5 text-[11px] text-stone-500 dark:border-stone-800">
          Jumlah wang tunai: <strong>RM2,500</strong> + medal. Tiada piala pusingan.
          Setiap pemain berdaftar menerima <strong>Sijil Penyertaan</strong> bertandatangan penaja kejohanan.
        </div>
      </Card>

      <Card>
        <CardHeader><CardTitle className="flex items-center gap-2"><Clock className="size-4" />Tentatif Hari Kejohanan</CardTitle></CardHeader>
        <div className="divide-y divide-stone-100 dark:divide-stone-900">
          {TENTATIF.map(([masa, aktiviti]) => {
            const penting = aktiviti.includes('AKHIR') || aktiviti.includes('Solat') || aktiviti.includes('Tazkirah')
            return (
              <div key={masa} className="flex gap-3 px-4 py-2.5">
                <span className="w-32 shrink-0 text-[12px] font-semibold text-stone-500">{masa}</span>
                <span className={`text-[13px] ${penting ? 'font-semibold text-maroon-700 dark:text-maroon-300' : ''}`}>{aktiviti}</span>
              </div>
            )
          })}
        </div>
      </Card>

      <Card>
        <CardHeader><CardTitle className="flex items-center gap-2"><Info className="size-4" />Format & Peraturan Ringkas</CardTitle></CardHeader>
        <CardBody className="space-y-3 text-[13px] text-stone-600 dark:text-stone-400">
          <ul className="list-disc space-y-1 pl-5">
            <li>Terbuka kepada penduduk yang menetap di <strong>Kepala Batas</strong>.</li>
            <li>Yuran penyertaan <strong>RM200</strong> setiap pasukan · maksimum <strong>10 pemain</strong>.</li>
            <li>Wajib menutup aurat sepanjang pertandingan.</li>
            <li>24 pasukan · 8 kumpulan (A–H) · 3 pasukan setiap kumpulan.</li>
            <li>Liga satu pusingan dalam kumpulan — 3 perlawanan setiap kumpulan.</li>
            <li>Mata: Menang 3 · Seri 1 · Kalah 0.</li>
            <li>Kedudukan jika mata sama: <strong>keputusan bersemuka (head-to-head)</strong> → beza gol → jumlah gol → undian.</li>
            <li><strong>Johan kumpulan sahaja</strong> layak ke Suku Akhir (8 pasukan).</li>
            <li>Pasangan Suku Akhir ditentukan melalui <strong>cabutan undi</strong> di hadapan wakil pasukan.</li>
            <li>Peringkat kalah mati tidak boleh seri — sepakan penalti menentukan pemenang.</li>
            <li>Jumlah 32 perlawanan: 24 kumpulan + 4 suku akhir + 2 separuh akhir + 1 tempat ke-3 + 1 akhir.</li>
            <li>
              <strong>Bantahan keputusan:</strong> hendaklah dibuat secara <strong>bertulis</strong> oleh pengurus pasukan
              sebelum perlawanan berikutnya bermula, disertakan deposit <strong>RM200</strong>.
              Deposit dikembalikan sekiranya bantahan berjaya. Keputusan Jawatankuasa Bantahan adalah <strong>muktamad</strong>.
            </li>
            <li>Setiap pemain berdaftar menerima <strong>Sijil Penyertaan</strong> — dimuat turun melalui pautan yang diberikan kepada pengurus pasukan.</li>
            <li>Utamakan solat · jaga adab, disiplin &amp; sportsmanship — <em>"Sukan dan Solat asas kejayaan dunia &amp; akhirat"</em>.</li>
          </ul>

          <div className="overflow-hidden rounded-lg border border-stone-200 dark:border-stone-800">
            <table className="w-full text-[12px]">
              <thead className="bg-stone-50 text-left text-[10px] uppercase tracking-wide text-stone-500 dark:bg-stone-900">
                <tr><th className="px-3 py-2">Peringkat</th><th className="px-3 py-2">Masa perlawanan</th><th className="px-3 py-2 text-right">Jumlah</th></tr>
              </thead>
              <tbody>
                {TEMPOH.map(([a, b, c]) => (
                  <tr key={a} className="border-t border-stone-100 dark:border-stone-900">
                    <td className="px-3 py-1.5">{a}</td><td className="px-3 py-1.5">{b}</td><td className="px-3 py-1.5 text-right font-semibold">{c}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </CardBody>
      </Card>

      <Card>
        <CardHeader><CardTitle>Penganjur & Penaja</CardTitle></CardHeader>
        <CardBody className="space-y-2 text-[13px] text-stone-600 dark:text-stone-400">
          <div>
            <p className="text-[10px] font-bold uppercase tracking-wide text-stone-400">Anjuran</p>
            <p className="font-semibold text-stone-800 dark:text-stone-200">SAMFIRE FC</p>
          </div>
          <div>
            <p className="text-[10px] font-bold uppercase tracking-wide text-stone-400">Dengan kerjasama</p>
            <p className="font-semibold text-stone-800 dark:text-stone-200">Pusat Kecemerlangan As-Syafiee (PAKSY)</p>
          </div>
          <div>
            <p className="text-[10px] font-bold uppercase tracking-wide text-stone-400">Tajaan</p>
            <p className="font-semibold text-stone-800 dark:text-stone-200">YB Dato&apos; Seri Reezal Merican</p>
          </div>
          <div className="border-t border-stone-100 pt-2 dark:border-stone-800">
            <p>{data.tetapan.lokasi}</p>
            {data.tetapan.telefon_urusetia && <p>Urus setia: {data.tetapan.telefon_urusetia}</p>}
          </div>

          <div className="grid gap-2 border-t border-stone-100 pt-3 sm:grid-cols-2 dark:border-stone-800">
            <a href={data.tetapan.url_website} target="_blank" rel="noreferrer"
               className="flex items-center justify-center gap-2 rounded-xl bg-navy-800 px-4 py-3 text-[13px] font-bold text-white transition hover:bg-navy-900">
              <Globe className="size-4" />Laman Web SAMFIRE FC
            </a>
            <a href={data.tetapan.url_daftar_ahli} target="_blank" rel="noreferrer"
               className="flex items-center justify-center gap-2 rounded-xl bg-gold-500 px-4 py-3 text-[13px] font-bold text-navy-900 transition hover:bg-gold-600">
              <Shield className="size-4" />Daftar Ahli SAMFIRE FC
            </a>
          </div>
          <p className="text-[11px] text-stone-400">
            Nota: &quot;Daftar Ahli&quot; adalah keahlian kelab SAMFIRE FC — berbeza daripada pendaftaran pasukan kejohanan di tab Daftar.
          </p>
        </CardBody>
      </Card>
    </div>
  )
}
