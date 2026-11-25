import { describe, expect, it } from 'vitest'
import { abfrageStatus, gameLabel, ymd } from '../dates'
import { buildIcs } from '../ics'
import { classify, clubConflict, isBlocked, pickLeader, requiredLevel, teamsOf } from '../rules'
import { game, referee } from './fixtures'

describe('rules', () => {
  it('derives the required licence from the competition', () => {
    expect(requiredLevel('Regionalliga Südost')).toBe(0)
    expect(requiredLevel('1. Bundesliga')).toBe(1)
    expect(requiredLevel('BWF-Veranstaltung')).toBe(2)
  })

  it('detects own-club conflicts including second teams', () => {
    const g = game({ home_team: 'TSG Hohenstaufen II', away_team: 'SV Tübingen' })
    expect(clubConflict(referee({ club: 'TSG Hohenstaufen' }), g)).toBe(true)
    expect(clubConflict(referee({ club: 'TSG Hohen' }), g)).toBe(false)
    expect(teamsOf({ home_team: null, away_team: null, title: 'Liga · X vs. Y' })).toEqual(['X', 'Y'])
  })

  it('picks the higher licence as leader, then more games', () => {
    const a = referee({ license: 'National', season_count: 3 })
    const b = referee({ license: 'National', season_count: 9 })
    const c = referee({ license: 'Grundlizenz', season_count: 30 })
    expect(pickLeader([a, b, c])).toBe(b)
  })

  it('classifies NuLiga games by BW focus and checks blackouts', () => {
    expect(classify(game({ home_state: 'BY', away_state: 'BW' })).label).toBe('Auswärtsspiel BW-Team')
    const r = referee({ blackouts: [{ from: '2099-01-09', to: '2099-01-11', reason: 'Urlaub' }] })
    expect(isBlocked(r, game())?.reason).toBe('Urlaub')
    expect(isBlocked(r, game({ date: '2099-01-12' }))).toBeNull()
  })
})

describe('dates', () => {
  it('formats game labels and abfrage periods', () => {
    expect(gameLabel({ date: '2026-09-26', time: null })).toBe('Sa, 26. Sep · ganztägig')
    const today = new Date()
    const plus = (n: number) => ymd(new Date(today.getFullYear(), today.getMonth(), today.getDate() + n))
    expect(abfrageStatus(null, null).state).toBe('offen')
    expect(abfrageStatus(plus(1), plus(5)).state).toBe('vor')
    expect(abfrageStatus(plus(-5), plus(-1)).state).toBe('beendet')
    expect(abfrageStatus(plus(-1), plus(0)).text).toContain('letzter Tag')
  })

  it('exports ICS events with escaped text', () => {
    const ics = buildIcs([game({ title: 'A, B; C', time: null })], 'Katja')
    expect(ics).toContain(String.raw`SUMMARY:A\, B\; C`)
    expect(ics).toContain('DTSTART;VALUE=DATE:20990110')
  })
})
