import { describe, expect, it } from 'vitest'
import { buildSeasonGames, planSeason, rowIssues, sameTrip } from '../planning'
import { game, referee } from './fixtures'

describe('season planning', () => {
  const none = () => null

  it('only plans upcoming league games that are imported or BW home games', () => {
    const games = [game(), game({ imported: false, home_state: 'BY' }), game({ type: 'tournament' }), game({ date: '2000-01-01' })]
    expect(buildSeasonGames(games)).toEqual([games[0]])
  })

  it('fills both slots, respects licences, declines and own clubs', () => {
    const g = game({ competition: '1. Bundesliga', home_team: 'BC Fils' })
    const low = referee({ license: 'Grundlizenz' })
    const declined = referee()
    const own = referee({ club: 'BC Fils' })
    const ok = [referee(), referee()]
    const plan = planSeason([g], [low, declined, own, ...ok], { cap: 5, minOne: false, answer: (_, r) => (r === declined.id ? 'no' : null) })
    expect(plan.missing).toBe(0)
    expect(plan.rows[0]!.refs.map(r => r.id).toSorted()).toEqual(ok.map(r => r.id).toSorted())
    expect(plan.rows[0]!.leader).not.toBeNull()
  })

  it('keeps confirmed games fixed and reports open slots', () => {
    const a = referee()
    const b = referee()
    const locked = game({ status: 'confirmed', confirmed_ids: [a.id, b.id] })
    const plan = planSeason([locked, game()], [a, b], { cap: 1, minOne: false, answer: none })
    expect(plan.rows[0]!.locked).toBe(true)
    expect(plan.rows[1]!.missing).toBe(2)
    expect(rowIssues(plan.rows[1]!, plan.rows, none, false).map(i => i.t)).toContain('Beide Plätze offen')
  })

  it('treats two games of one club at the same venue 2.5 h apart as one trip', () => {
    const a = game({ home_team: 'TSG Hohenstaufen', time: '12:30' })
    expect(sameTrip(a, game({ home_team: 'TSG Hohenstaufen II', time: '15:00' }))).toBe(true)
    expect(sameTrip(a, game({ home_team: 'TSG Hohenstaufen II', time: '14:00' }))).toBe(false)
  })
})
