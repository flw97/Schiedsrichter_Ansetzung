import type { Game, Referee } from '@/types'

let id = 0
export function referee (p: Partial<Referee> = {}): Referee {
  id++
  return { id, name: `SR ${id}`, license: 'National', club: null, available: true, address: 'Stuttgart', season_count: 0, blackouts: [], ...p }
}

export function game (p: Partial<Game> = {}): Game {
  id++
  return {
    id, nuliga_id: null, title: `Regionalliga Südost · A vs. B ${id}`, type: 'match', date: '2099-01-10', time: '15:00', venue: 'Halle Nordstadt',
    competition: 'Regionalliga Südost', needed_count: 2, status: 'open', home_team: 'A', away_team: 'B', home_state: 'BW', away_state: 'BW',
    imported: true, nuliga_change: null, pending_date: null, pending_time: null, pending_venue: null, leader_id: null, requested_by: null,
    reopened_note: null, schedule_change: null, report: null, confirmed_ids: [], planned_ids: [], head_referee_id: null,
    planned_head_referee_id: null, reconfirmed_ids: [], ...p,
  }
}
