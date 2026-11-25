// Datenstrukturen der API (GET /api/state), Feldnamen wie im Laravel-Backend

export type Role = 'master' | 'admin' | 'schiri' | 'lesend'
export type GameType = 'match' | 'tournament'
export type GameStatus = 'open' | 'proposed' | 'confirmed'
export type CardColor = 'gelb' | 'rot' | 'schwarz'

export interface Blackout { from: string, to: string, reason: string | null }

export interface Referee {
  id: number
  name: string
  license: string
  club: string | null
  // Nur für Admins bzw. im eigenen Profil vorhanden
  nuliga_id?: string | null
  email?: string | null
  phone?: string | null
  address?: string | null
  lat?: number | null
  lon?: number | null
  license_valid_until?: string | null
  license_synced_at?: string | null
  license_reminded_for?: string | null
  persp_national?: boolean
  persp_international?: boolean
  available?: boolean
  season_count?: number
  monthly_load?: number
  fairness?: number
  last_game?: string | null
  trend?: string | null
  blackouts?: Blackout[]
  proofs?: { date: string, text: string }[]
  user_id?: number | null
}

export interface Card { color: CardColor, team: string, person?: string | null, reason?: string | null, given_by?: string | null }
export interface Report { note: string, cards: Card[], author: string, at: string }

export interface Game {
  id: number
  nuliga_id: string | null
  title: string
  type: GameType
  date: string
  time: string | null
  venue: string
  competition: string
  needed_count: number
  status: GameStatus
  home_team: string | null
  away_team: string | null
  home_state: string | null
  away_state: string | null
  imported: boolean
  nuliga_change: string | null
  pending_date: string | null
  pending_time: string | null
  pending_venue: string | null
  leader_id: number | null
  requested_by: string | null
  reopened_note: string | null
  schedule_change: { old: string, new: string } | null
  report: Report | null
  confirmed_ids: number[]
  planned_ids: number[]
  head_referee_id: number | null
  planned_head_referee_id: number | null
  reconfirmed_ids: number[]
}

export interface AvailabilityResponse { game_id: number, referee_id: number, answer: 'yes' | 'no', comment: string | null, updated_at: string }

export interface Rulebook {
  id: number
  title: string
  category: string
  version: string
  valid_from: string | null
  mandatory: boolean
  filename: string
  size: number
  updated_by: string
  updated_at: string
  versions: { id: number, version: string, valid_from: string | null, filename: string, replaced_at: string }[]
}

export interface Accommodation {
  id: number
  name: string
  street: string | null
  zip: string | null
  city: string
  venue: string
  lat: number | null
  lon: number | null
  added_by: string
  reviews: { id: number, user_id: number, author: string, stars: number, text: string | null, updated_at: string }[]
}

export interface Observation {
  id: number
  referee_id: number
  date: string
  game: string
  scores: Record<string, number>
  strengths: string | null
  development: string | null
  recommendation: string
  observer: string
}

export interface User { id: number, name: string, username: string, email: string | null, role: Role, active: boolean, referee_id: number | null }

export interface AppState {
  me: { id: number, name: string, username: string, role: Role, referee_id: number | null, permissions: string[] }
  referees: Referee[]
  games: Game[]
  responses: AvailabilityResponse[]
  settings: { abfrage_from: string | null, abfrage_to: string | null, nuliga_last_sync: string | null }
  pushes: { id: number, text: string, created_at: string }[]
  rulebooks: Rulebook[]
  rulebook_reads: { rulebook_id: number, user_id: number, version: string }[]
  accommodations: Accommodation[]
  nuliga_licenses?: Record<number, { license: string, valid_until: string } | null>
  observations?: Observation[]
  users?: User[]
  audit?: { id: number, kind: 'access' | 'change', text: string, created_at: string }[]
  permission_matrix?: { key: string, label: string, roles: Role[] }[]
}
