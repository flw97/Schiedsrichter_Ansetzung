// Kalenderexport der eigenen Ansetzungen (iCalendar, RFC 5545)

import type { Game } from '@/types'

const esc = (s: string) => s.replace(/[;,]/g, m => `\\${m}`).replace(/\n/g, String.raw`\n`)
const compact = (iso: string) => iso.replace(/[-:]/g, '')

export function buildIcs (games: Game[], name: string, now = new Date()) {
  const stamp = `${compact(now.toISOString().slice(0, 19))}Z`
  const events = games.map(g => {
    const day = compact(g.date)
    // Turniere als ganztägiger Termin, Spiele mit zwei Stunden Dauer
    const when = g.time
      ? `DTSTART:${day}T${compact(g.time)}00\r\nDURATION:PT2H`
      : `DTSTART;VALUE=DATE:${day}`
    return ['BEGIN:VEVENT', `UID:game-${g.id}@ansetzung`, `DTSTAMP:${stamp}`, when, `SUMMARY:${esc(g.title)}`, `LOCATION:${esc(g.venue)}`, `DESCRIPTION:${esc(g.competition)}`, 'END:VEVENT'].join('\r\n')
  })
  return ['BEGIN:VCALENDAR', 'VERSION:2.0', `PRODID:-//Ansetzung//${esc(name)}//DE`, ...events, 'END:VCALENDAR'].join('\r\n')
}
