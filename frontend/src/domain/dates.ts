// Datumsdarstellung: Spiele tragen ein ISO-Datum (YYYY-MM-DD) und optional eine Uhrzeit (null = ganztägig)

const WEEKDAYS = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa']
const MONTHS = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez']

export const ymd = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
export const todayIso = () => ymd(new Date())
export const parseIso = (iso: string) => new Date(`${iso.slice(0, 10)}T00:00:00`)
export const isoToDe = (iso?: string | null) => (iso ? iso.slice(0, 10).split('-').toReversed().join('.') : '')
export const fmtDateTime = (iso: string) => new Date(iso).toLocaleString('de-DE')

/** „Sa, 26. Sep“ */
export function dayLabel (iso: string, withYear = false) {
  const d = parseIso(iso)
  return `${WEEKDAYS[d.getDay()]}, ${d.getDate()}. ${MONTHS[d.getMonth()]}${withYear ? ` ${d.getFullYear()}` : ''}`
}

export const timeLabel = (time: string | null) => time ?? 'ganztägig'

/** „Sa, 26. Sep · 15:00“ */
export const gameLabel = (g: { date: string, time: string | null }) => `${dayLabel(g.date)} · ${timeLabel(g.time)}`

/** Archiv: Spiele mit Datum vor heute (der Spieltag selbst gilt noch als aktuell). */
export const isPast = (g: { date: string }) => g.date < todayIso()

export function startMin (g: { time: string | null }) {
  const m = /(\d+):(\d+)/.exec(g.time ?? '')
  return m ? Number(m[1]) * 60 + Number(m[2]) : 0
}

export function byDate (a: { date: string, time: string | null }, b: { date: string, time: string | null }) {
  return a.date.localeCompare(b.date) || startMin(a) - startMin(b)
}

export function seasonLabel (now = new Date()) {
  const y = now.getMonth() >= 6 ? now.getFullYear() : now.getFullYear() - 1
  return `${y}/${String(y + 1).slice(2)}`
}

export type AbfrageState = 'offen' | 'vor' | 'beendet'

/** Status des Abfragezeitraums für die Rückmeldungen der Schiedsrichter. */
export function abfrageStatus (from: string | null, to: string | null): { state: AbfrageState, text: string } {
  const t = todayIso()
  if (!from || !to) {
    return { state: 'offen', text: 'Kein Zeitraum festgelegt — Rückmeldungen jederzeit möglich' }
  }
  if (t < from) {
    return { state: 'vor', text: `Die Abfrage startet am ${isoToDe(from)}` }
  }
  if (t > to) {
    return { state: 'beendet', text: `Die Abfrage ist seit dem ${isoToDe(to)} beendet` }
  }
  const days = Math.round((parseIso(to).getTime() - parseIso(t).getTime()) / 86_400_000)
  return { state: 'offen', text: `Abfragezeitraum ${isoToDe(from)} – ${isoToDe(to)} (${days === 0 ? 'letzter Tag' : `noch ${days} Tag${days === 1 ? '' : 'e'}`})` }
}

export const eur = (n: number) => n.toLocaleString('de-DE', { style: 'currency', currency: 'EUR' })
export const num = (n: number, digits = 1) => n.toFixed(digits).replace('.', ',')
