// Saisonplanung: kostenoptimierte Vorab-Verteilung aller Ligaspiele (Näherungsverfahren)

import type { Game, Referee } from '@/types'
import { byDate, isPast, startMin } from './dates'
import { distKm } from './geo'
import { classify, clubConflict, isBlocked, isLeague, licenseRank, pickLeader, requiredLevel, requiredLevelLabel, teamsOf } from './rules'

export const KM_RATE = 0.3 // Fahrtkostenerstattung in EUR pro km
const CONFLICT_PENALTY = 1000 // eigener Verein nur als Notlösung (fließt nicht in die angezeigten Kosten ein)
const ZUSAGE_BONUS = 30 // Zusagen werden bevorzugt (wirkt wie ein Kostenvorteil)
const SLOTS = 2

export type Answer = (gameId: number, refId: number) => 'yes' | 'no' | null

export interface PlanRow {
  game: Game
  refs: Referee[]
  locked: boolean
  edited?: boolean
  leader: Referee | null
  cost: number
  km: number
  doubles: string[]
  conflicts: string[]
  missing: number
}

export interface PerRef { ref: Referee, games: number, km: number, cost: number }
export interface Plan { rows: PlanRow[], perRef: PerRef[], total: number, openCost: number, baseline: number, missing: number, ownClub: number }

const tripCost = (r: Referee, g: Game) => distKm(r, g.venue, g.home_team) * 2 * KM_RATE // Hin- und Rückfahrt
const clubOfTeam = (t?: string) => (t ?? '').replace(/\s+(?:[IVX]+|\d+)$/, '').trim()

/**
 * Doppeleinsatz: zwei Spiele desselben Vereins am selben Spielort und Tag mit mind. 2,5 Stunden Abstand
 * kann ein Schiedsrichter nacheinander übernehmen (eine Fahrt).
 */
export function sameTrip (a: Game, b: Game) {
  const ca = clubOfTeam(teamsOf(a)[0])
  return !!ca && a.date === b.date && a.venue === b.venue && ca === clubOfTeam(teamsOf(b)[0]) && Math.abs(startMin(a) - startMin(b)) >= 150
}

/** Ligaspiele der Saison: übernommene sowie in NuLiga bereitgestellte BW-Heimspiele, ohne vergangene. */
export function buildSeasonGames (games: Game[]) {
  return games.filter(g => isLeague(g) && !isPast(g) && (g.imported || classify(g).standard)).toSorted(byDate)
}

// Ein Durchlauf der Heuristik aus dem Prototyp; bewusst als eine Funktion, da alle Schritte denselben Zustand teilen
// eslint-disable-next-line complexity
function planOnce (games: Game[], referees: Referee[], opts: { cap: number, minOne: boolean, answer: Answer }, seed: number): Plan {
  const pool = referees.filter(r => r.available)
  const rows: PlanRow[] = games.map(g => ({ game: g, refs: [], locked: false, leader: null, cost: 0, km: 0, doubles: [], conflicts: [], missing: 0 }))
  const mine = new Map<number, Set<number>>(pool.map(r => [r.id, new Set()]))
  const assign = (i: number, r: Referee) => {
    rows[i]!.refs.push(r)
    mine.get(r.id)?.add(i)
  }
  const unassign = (i: number, r: Referee) => {
    rows[i]!.refs = rows[i]!.refs.filter(x => x.id !== r.id)
    mine.get(r.id)?.delete(i)
  }
  // Wer am selben Spielort/Tag schon ein Spiel desselben Vereins hat, fährt nicht noch einmal
  const nShare = (r: Referee, i: number) => 1 + [...(mine.get(r.id) ?? [])].filter(k => k !== i && sameTrip(rows[k]!.game, rows[i]!.game)).length
  const ans = (r: Referee, i: number) => opts.answer(rows[i]!.game.id, r.id)
  const bonus = (r: Referee, i: number) => (ans(r, i) === 'yes' ? ZUSAGE_BONUS : 0)
  const penalty = (r: Referee, i: number) => (clubConflict(r, rows[i]!.game) ? CONFLICT_PENALTY : 0)
  const cost = (r: Referee, i: number) => (nShare(r, i) > 1 ? 0 : tripCost(r, rows[i]!.game)) + penalty(r, i) - bonus(r, i)
  const share = (r: Referee, i: number) => tripCost(r, rows[i]!.game) / nShare(r, i)
  const planCost = () => rows.reduce((s, row, i) => s + row.refs.reduce((x, r) => x + share(r, i) + penalty(r, i) - bonus(r, i), 0), 0)
  const fits = (r: Referee, i: number, leaving?: number) => {
    const g = rows[i]!.game
    const set = mine.get(r.id)
    if (!set || licenseRank(r.license) < requiredLevel(g.competition) || isBlocked(r, g) || ans(r, i) === 'no' || rows[i]!.refs.some(x => x.id === r.id)) {
      return false
    }
    return [...set].every(k => k === leaving || rows[k]!.game.date !== g.date || sameTrip(rows[k]!.game, g))
  }
  const size = (r: Referee) => mine.get(r.id)?.size ?? 0

  // Bestätigte Spiele bleiben fix
  for (const [i, row] of rows.entries()) {
    if (row.game.status === 'confirmed' && row.game.confirmed_ids.length > 0) {
      row.locked = true
      for (const id of row.game.confirmed_ids) {
        const r = referees.find(x => x.id === id)
        if (r) {
          assign(i, r)
        }
      }
    }
  }

  // Engste Spiele zuerst (mit wechselnder Reihenfolge je Durchlauf), jeweils günstigste zulässige Schiedsrichter
  const open = rows.map((_, i) => i).filter(i => !rows[i]!.locked)
  let rnd = seed * 9301 + 49_297
  const jitter = () => {
    rnd = (rnd * 9301 + 49_297) % 233_280
    return rnd / 233_280
  }
  const key = new Map(open.map(i => [i, pool.filter(r => fits(r, i)).length + (seed ? jitter() * 3 : 0)]))
  for (const i of open.toSorted((a, b) => key.get(a)! - key.get(b)!)) {
    while (rows[i]!.refs.length < SLOTS) {
      const c = pool.filter(r => fits(r, i) && size(r) < opts.cap).toSorted((a, b) => cost(a, i) - cost(b, i))[0]
      if (!c) {
        break
      }
      assign(i, c)
    }
  }

  // Jeder verfügbar gemeldete Schiedsrichter soll mindestens einen Einsatz erhalten können
  if (opts.minOne) {
    for (const r of pool) {
      if (size(r) > 0) {
        continue
      }
      let best: { i: number, a: Referee | null, d: number } | null = null
      for (const i of open) {
        if (!fits(r, i) || clubConflict(r, rows[i]!.game)) {
          continue
        }
        if (rows[i]!.refs.length < SLOTS) {
          const d = cost(r, i)
          if (!best || d < best.d) {
            best = { i, a: null, d }
          }
        }
        for (const a of rows[i]!.refs) {
          if (mine.has(a.id) && size(a) < 2) {
            continue
          }
          const d = cost(r, i) - cost(a, i)
          if (!best || d < best.d) {
            best = { i, a, d }
          }
        }
      }
      if (best) {
        if (best.a) {
          unassign(best.i, best.a)
        }
        assign(best.i, r)
      }
    }
  }

  // Verbesserung durch Platzwechsel und Tausch, solange die Gesamtkosten sinken
  const minKeep = opts.minOne ? 2 : 1
  const has = (i: number, r: Referee) => rows[i]!.refs.some(x => x.id === r.id)
  for (let pass = 0; pass < 25; pass++) {
    let improved = false
    for (const i of open) {
      for (const a of rows[i]!.refs.slice()) {
        if (!mine.has(a.id) || size(a) < minKeep || !has(i, a)) {
          continue
        }
        let bestB: Referee | null = null
        let bestCost = planCost()
        for (const b of pool) {
          if (!fits(b, i) || size(b) >= opts.cap) {
            continue
          }
          unassign(i, a)
          assign(i, b)
          const c = planCost()
          unassign(i, b)
          assign(i, a)
          if (c < bestCost - 0.001) {
            bestCost = c
            bestB = b
          }
        }
        if (bestB) {
          unassign(i, a)
          assign(i, bestB)
          improved = true
        }
      }
    }
    for (const i of open) {
      for (const j of open) {
        if (i >= j) {
          continue
        }
        for (const a of rows[i]!.refs.slice()) {
          for (const b of rows[j]!.refs.slice()) {
            if (a.id === b.id || !has(i, a) || !has(j, b) || !fits(b, i, j) || !fits(a, j, i)) {
              continue
            }
            const before = planCost()
            unassign(i, a)
            unassign(j, b)
            assign(i, b)
            assign(j, a)
            if (planCost() < before - 0.001) {
              improved = true
            } else {
              unassign(i, b)
              unassign(j, a)
              assign(i, a)
              assign(j, b)
            }
          }
        }
      }
    }
    if (!improved) {
      break
    }
  }

  const baseline = rows.filter(r => !r.locked).reduce((s, row) => {
    const lic = pool.filter(r => licenseRank(r.license) >= requiredLevel(row.game.competition) && !clubConflict(r, row.game))
    return s + (lic.length > 0 ? (lic.reduce((x, r) => x + tripCost(r, row.game), 0) / lic.length) * SLOTS : 0)
  }, 0)
  return { ...evaluateRows(rows, pool), baseline }
}

/** Mehrere Durchläufe; gewählt wird der Plan mit den wenigsten Lücken, dann wenigsten Vereinskonflikten, dann geringsten Kosten. */
export function planSeason (games: Game[], referees: Referee[], opts: { cap: number, minOne: boolean, answer: Answer }): Plan {
  let best: Plan | null = null
  for (let seed = 0; seed < 40; seed++) {
    const p = planOnce(games, referees, opts, seed)
    if (!best || p.missing < best.missing || (p.missing === best.missing && (p.ownClub < best.ownClub || (p.ownClub === best.ownClub && p.openCost < best.openCost - 0.001)))) {
      best = p
    }
  }
  return best!
}

/** Bewertung eines (ggf. manuell angepassten) Plans: Kosten mit gemeinsamer Fahrt, Leiter, Hinweise. */
export function evaluateRows (rows: Pick<PlanRow, 'game' | 'refs' | 'locked' | 'edited'>[], referees: Referee[]): Omit<Plan, 'baseline'> {
  const nShare = (r: Referee, i: number) => 1 + rows.reduce((n, row, k) => (k !== i && row.refs.some(x => x.id === r.id) && sameTrip(row.game, rows[i]!.game) ? n + 1 : n), 0)
  const out: PlanRow[] = rows.map((row, i) => ({
    ...row,
    leader: pickLeader(row.refs),
    cost: row.refs.reduce((s, r) => s + tripCost(r, row.game) / nShare(r, i), 0),
    km: row.refs.reduce((s, r) => s + (distKm(r, row.game.venue, row.game.home_team) * 2) / nShare(r, i), 0),
    doubles: row.refs.filter(r => nShare(r, i) > 1).map(r => r.name),
    conflicts: row.refs.filter(r => clubConflict(r, row.game)).map(r => r.name),
    missing: Math.max(0, SLOTS - row.refs.length),
  }))
  const perRef = referees.map(r => {
    const idx = out.flatMap((row, i) => (row.refs.some(x => x.id === r.id) ? [i] : []))
    return {
      ref: r,
      games: idx.length,
      km: idx.reduce((s, i) => s + (distKm(r, out[i]!.game.venue, out[i]!.game.home_team) * 2) / nShare(r, i), 0),
      cost: idx.reduce((s, i) => s + tripCost(r, out[i]!.game) / nShare(r, i), 0),
    }
  })
  return {
    rows: out,
    perRef,
    total: out.reduce((s, r) => s + r.cost, 0),
    openCost: out.filter(r => !r.locked).reduce((s, r) => s + r.cost, 0),
    missing: out.reduce((s, r) => s + r.missing, 0),
    ownClub: out.reduce((s, r) => s + r.conflicts.length, 0),
  }
}

export type Tone = 'error' | 'warning' | 'muted'

/** Hinweise zu einer Zeile: Lizenz, Verein, Sperrzeit, Verfügbarkeit, Terminkollision, Rückmeldung (+ Kommentar). */
export function rowIssues (row: PlanRow, rows: PlanRow[], answer: (gameId: number, refId: number) => { answer: 'yes' | 'no', comment: string | null } | null, closed: boolean) {
  const out: { t: string, tone: Tone }[] = []
  const need = requiredLevel(row.game.competition)
  for (const r of row.refs) {
    if (licenseRank(r.license) < need) {
      out.push({ t: `${r.name}: Lizenz reicht nicht (benötigt ${requiredLevelLabel(need)})`, tone: 'error' })
    }
    if (clubConflict(r, row.game)) {
      out.push({ t: `${r.name}: eigener Verein`, tone: 'error' })
    }
    const b = isBlocked(r, row.game)
    if (b) {
      out.push({ t: `${r.name}: Sperrzeit${b.reason ? ` (${b.reason})` : ''}`, tone: 'error' })
    }
    if (!r.available) {
      out.push({ t: `${r.name}: nicht verfügbar gemeldet`, tone: 'error' })
    }
    for (const o of rows) {
      if (o !== row && o.refs.some(x => x.id === r.id) && o.game.date === row.game.date && !sameTrip(o.game, row.game)) {
        out.push({ t: `${r.name}: am selben Tag auch in „${o.game.venue}“ eingeplant`, tone: 'error' })
      }
    }
    const a = answer(row.game.id, r.id)
    if (a?.answer === 'no') {
      out.push({ t: `${r.name}: hat abgesagt${a.comment ? ` — „${a.comment}“` : ''}`, tone: 'error' })
    } else if (a?.comment) {
      out.push({ t: `${r.name}: „${a.comment}“`, tone: 'muted' })
    } else if (!a && !row.locked) {
      out.push({ t: `${r.name}: ${closed ? 'keine Rückmeldung' : 'Rückmeldung ausstehend'}`, tone: closed ? 'error' : 'warning' })
    }
  }
  if (row.refs.length < SLOTS) {
    out.push({ t: row.refs.length === 0 ? 'Beide Plätze offen' : 'Ein Platz offen', tone: 'error' })
  }
  return out
}
