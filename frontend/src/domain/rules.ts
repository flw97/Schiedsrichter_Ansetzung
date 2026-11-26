// Fachregeln der Ansetzung (Spiegel von backend/app/Support/Licenses.php)

import type { AvailabilityResponse, Game, Referee } from '@/types'
import { parseIso } from './dates'

export const LICENSE_LEVELS = ['Grundlizenz', 'National', 'International', 'BEC-Certified', 'BEC-Accredited', 'BWF-Certified', 'BWF-Accredited']
export const COMPETITIONS = ['Regionalliga Südost', '2. Bundesliga', '1. Bundesliga', 'BEC-Veranstaltung', 'BWF-Veranstaltung']
export const LEAGUES = ['Regionalliga Südost', '2. Bundesliga', '1. Bundesliga']

export const ROLE_LABEL = { master: 'Master-Admin', admin: 'Admin', schiri: 'Schiedsrichter', lesend: 'Lesend' } as const
export const ROLES = Object.keys(ROLE_LABEL) as (keyof typeof ROLE_LABEL)[]

export const licenseRank = (level?: string | null) => LICENSE_LEVELS.indexOf(level ?? '')

export function requiredLevel (competition: string) {
  if (competition === '2. Bundesliga' || competition === '1. Bundesliga') {
    return 1
  }
  if (competition === 'BEC-Veranstaltung' || competition === 'BWF-Veranstaltung') {
    return 2
  }
  return 0
}
export const requiredLevelLabel = (rank: number) => `${LICENSE_LEVELS[rank]} oder höher`
export const eligible = (r: Referee, g: Game) => licenseRank(r.license) >= requiredLevel(g.competition)

export const isLeague = (g: Pick<Game, 'type' | 'competition'>) => g.type === 'match' && LEAGUES.includes(g.competition)

/** Fokus Baden-Württemberg: regulär übernommen werden nur Heimspiele in BW. */
export function classify (g: Game) {
  if (g.home_state === 'BW') {
    return { standard: true, label: 'Heimspiel in BW' }
  }
  return { standard: false, label: g.away_state === 'BW' ? 'Auswärtsspiel BW-Team' : 'Ohne BW-Beteiligung' }
}

export function teamsOf (g: Pick<Game, 'home_team' | 'away_team' | 'title'>): string[] {
  if (g.home_team || g.away_team) {
    return [g.home_team, g.away_team].filter((t): t is string => !!t)
  }
  const t = g.title.split('·').pop()!.trim()
  return t.includes(' vs. ') ? t.split(' vs. ').map(s => s.trim()) : []
}

/** Schiedsrichter dürfen keine Mannschaften des eigenen Vereins leiten (außer als Notlösung). */
export function clubConflict (r: Referee, g: Game) {
  const club = r.club
  return !!club && teamsOf(g).some(t => t === club || t.startsWith(`${club} `))
}

/** Ranghöher = höhere Lizenzstufe, bei Gleichstand mehr Einsätze in der Saison. */
export function pickLeader<T extends Referee> (refs: T[]): T | null {
  return refs.toSorted((a, b) => licenseRank(b.license) - licenseRank(a.license) || (b.season_count ?? 0) - (a.season_count ?? 0))[0] ?? null
}

export function isBlocked (r: Referee, g: Pick<Game, 'date'>) {
  return (r.blackouts ?? []).find(s => s.from <= g.date && g.date <= (s.to || s.from)) ?? null
}

/** Erinnerung 1 Jahr vor Lizenzende. */
export function reminderDate (r: Referee) {
  if (!r.license_valid_until) {
    return null
  }
  const d = parseIso(r.license_valid_until)
  d.setFullYear(d.getFullYear() - 1)
  return d
}

export const initials = (name: string) => name.split(' ').map(p => p[0]).join('')

/** Alle angesetzten Personen eines Spiels (Schiedsrichter und Referee). */
export const assignedIds = (g: Game) => [...g.confirmed_ids, ...(g.head_referee_id ? [g.head_referee_id] : [])]

// Rückmeldungen der Abfrage: schneller Zugriff über Spiel und Schiedsrichter
export type ResponseIndex = Map<string, AvailabilityResponse>
export const indexResponses = (list: AvailabilityResponse[]): ResponseIndex => new Map(list.map(r => [`${r.game_id}:${r.referee_id}`, r]))
export const responseOf = (idx: ResponseIndex, gameId: number, refId: number) => idx.get(`${gameId}:${refId}`) ?? null

// Filter: Ligaspiele / Turniere und einzelne Wettbewerbe
export interface GameFilterValue { typ: 'alle' | 'liga' | 'turnier', liga: string }
export const GF0: GameFilterValue = { typ: 'alle', liga: 'alle' }
export function gameMatches (g: Game, f: GameFilterValue) {
  return (f.typ === 'alle' || (f.typ === 'liga' ? g.type === 'match' : g.type === 'tournament')) && (f.liga === 'alle' || g.competition === f.liga)
}

export interface Weights { distance: number, avail: number, fair: number }

/** Score 0–100 für den Ansetzungsvorschlag: Entfernung, Verfügbarkeit und faire Verteilung. */
export function weightedScore (r: Referee, km: number, w: Weights) {
  const dist = Math.max(0, 1 - km / 40)
  return Math.round((dist * w.distance + (r.available ? 1 : 0) * w.avail + (r.fairness ?? 0.5) * w.fair) * 100)
}

export const CARD_COLORS: Record<string, string> = {
  gelb: '#E8C23A',
  rot: '#C0392B',
  schwarz: 'rgb(var(--v-theme-card-schwarz, 27, 36, 31))',
}
export const CARD_LABEL: Record<string, string> = { gelb: 'Gelb', rot: 'Rot', schwarz: 'Schwarz' }
