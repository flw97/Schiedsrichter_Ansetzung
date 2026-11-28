import { describe, expect, it } from 'vitest'
import { geoOf, venuesOf } from '../geo'

describe('ansetzung places', () => {
  it('reads the town from the home club when the hall name has none', () => {
    const places = venuesOf([
      { venue: 'Schönbeinhalle', home_team: 'TuS Metzingen' },
      { venue: 'Jahnhalle 2', home_team: 'SpVgg. Mössingen' },
      { venue: 'Jahnhalle 2', home_team: 'SpVgg. Mössingen' },
      { venue: 'Jahnhalle 2', home_team: 'BC Offenburg' },
      { venue: 'noch keine Halle', home_team: 'OSC München' },
    ])
    expect(places.map(p => [p.venue, p.geo.ort])).toEqual([
      ['Jahnhalle 2', 'mössingen'],
      ['Schönbeinhalle', 'metzingen'],
    ])
  })

  it('does not treat Mössingen as Singen', () => {
    expect(geoOf('SpVgg. Mössingen')?.ort).toBe('mössingen')
  })
})
