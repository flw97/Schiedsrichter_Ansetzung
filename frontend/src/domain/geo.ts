// Geodaten: Orte und Entfernungen; die Karte selbst kommt von OpenStreetMap (components/GeoMap.vue)

export interface Geo { lat: number, lon: number, ort?: string, genau: string }
export interface MapPoint { id: string, kind: 'ref' | 'hall' | 'hotel', lat: number, lon: number, label: string }

// Ortsmitten (Breite, Länge) — Adressen werden über den Ortsnamen zugeordnet
export const GEO_PLACES: Record<string, [number, number]> = {
  'stuttgart': [48.7758, 9.1829], 'esslingen': [48.7406, 9.3051], 'esslingen am neckar': [48.7406, 9.3051], 'plochingen': [48.7125, 9.4189],
  'kirchheim': [48.6481, 9.4519], 'nürtingen': [48.6277, 9.3414], 'neckartenzlingen': [48.6217, 9.2653], 'reutlingen': [48.4914, 9.2043],
  'metzingen': [48.5372, 9.2878], 'tübingen': [48.5216, 9.0576], 'gomaringen': [48.4528, 9.0958], 'rottenburg': [48.4765, 8.9347], 'herrenberg': [48.5949, 8.867],
  'böblingen': [48.6833, 9.0167], 'sindelfingen': [48.7133, 9.0027], 'leonberg': [48.7986, 9.0141], 'ludwigsburg': [48.8975, 9.192],
  'kornwestheim': [48.8586, 9.185], 'fellbach': [48.8089, 9.2759], 'waiblingen': [48.83, 9.3167], 'schorndorf': [48.8056, 9.5278],
  'filderstadt': [48.6575, 9.22], 'leinfelden-echterdingen': [48.6928, 9.1647], 'ostfildern': [48.7258, 9.2561], 'denkendorf': [48.7078, 9.3175],
  'göppingen': [48.7033, 9.6522], 'geislingen': [48.6247, 9.8281], 'schwäbisch gmünd': [48.8, 9.7965], 'backnang': [48.9439, 9.4292],
  'aalen': [48.8378, 10.0933], 'heidenheim': [48.6761, 10.1544], 'ulm': [48.3984, 9.9916], 'biberach': [48.0979, 9.7899],
  'ravensburg': [47.7811, 9.612], 'friedrichshafen': [47.6541, 9.4797], 'konstanz': [47.6633, 9.1753], 'singen': [47.7595, 8.8403],
  'sigmaringen': [48.0869, 9.2178], 'albstadt': [48.2113, 9.0267], 'balingen': [48.2731, 8.8547], 'tuttlingen': [47.9847, 8.8176],
  'rottweil': [48.1672, 8.6269], 'villingen-schwenningen': [48.0623, 8.4938], 'freudenstadt': [48.4639, 8.4106], 'calw': [48.7144, 8.7392],
  'nagold': [48.5514, 8.7253], 'pforzheim': [48.8922, 8.6946], 'karlsruhe': [49.0069, 8.4037], 'rastatt': [48.8577, 8.2077],
  'baden-baden': [48.7606, 8.2398], 'offenburg': [48.4731, 7.9441], 'lahr': [48.3394, 7.8697], 'emmendingen': [48.1211, 7.8528],
  'freiburg': [47.999, 7.8421], 'waldkirch': [48.0933, 7.9667], 'lörrach': [47.6154, 7.6613], 'weil am rhein': [47.5934, 7.6061],
  'waldshut': [47.6226, 8.2174], 'bruchsal': [49.1244, 8.5981], 'heidelberg': [49.3988, 8.6724], 'mannheim': [49.4875, 8.466],
  'heilbronn': [49.1427, 9.2109], 'mosbach': [49.3528, 9.1486], 'künzelsau': [49.2813, 9.6903], 'schwäbisch hall': [49.1122, 9.7384],
  'crailsheim': [49.1366, 10.07], 'bad mergentheim': [49.4927, 9.7742], 'wertheim': [49.7596, 9.5167],
}
// Spielorte -> Ort bzw. Koordinaten
export const VENUE_PLACE: Record<string, string | [number, number]> = {
  'Sporthalle Waldkirch': 'nürtingen', 'Halle am Steinbruch': 'esslingen', 'Sporthalle Fils': 'göppingen', 'Kreissporthalle Fils': 'göppingen',
  'Hohenstaufenhalle': [48.7196, 9.7119], 'Neckarhalle Neckartenzlingen': 'neckartenzlingen', 'Albhalle Reutlingen': 'reutlingen',
  'Halle Nordstadt': 'stuttgart', 'Neckarhalle Tübingen': 'tübingen',
}

const cache = new Map<string, Geo | null>()
/** Ort in einem Adress- oder Hallentext finden (längster Treffer gewinnt). */
export function geoOf (text?: string | null): Geo | null {
  const t = (text ?? '').toLowerCase()
  if (!cache.has(t)) {
    const best = Object.keys(GEO_PLACES).filter(k => t.includes(k)).toSorted((a, b) => b.length - a.length)[0]
    cache.set(t, best ? { lat: GEO_PLACES[best]![0], lon: GEO_PLACES[best]![1], ort: best, genau: 'Ortsmitte' } : null)
  }
  return cache.get(t)!
}

export function refGeo (r: { lat?: number | null, lon?: number | null, address?: string | null }): Geo | null {
  return r.lat != null && r.lon != null ? { lat: r.lat, lon: r.lon, genau: 'Koordinaten' } : geoOf(r.address)
}

export function accommodationGeo (a: { lat: number | null, lon: number | null, city: string }): Geo | null {
  return a.lat != null && a.lon != null ? { lat: a.lat, lon: a.lon, genau: 'Koordinaten' } : geoOf(a.city)
}

export function venueGeo (v: string): Geo | null {
  const p = VENUE_PLACE[v]
  if (Array.isArray(p)) {
    return { lat: p[0], lon: p[1], ort: v, genau: 'Ort' }
  }
  return p ? { lat: GEO_PLACES[p]![0], lon: GEO_PLACES[p]![1], ort: p, genau: 'Ortsmitte' } : geoOf(v)
}

export function haversineKm (a: Geo, b: Geo) {
  const rad = (x: number) => (x * Math.PI) / 180
  const h = Math.sin(rad(b.lat - a.lat) / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(rad(b.lon - a.lon) / 2) ** 2
  return 2 * 6371 * Math.asin(Math.sqrt(h))
}

/** Fahrstrecke genähert als Luftlinie × 1,25; unbekannte Orte zählen 20 km. */
export const roadKm = (a: Geo, b: Geo) => Math.round(haversineKm(a, b) * 1.25)
export function distKm (r: Parameters<typeof refGeo>[0], venue: string) {
  const a = refGeo(r)
  const v = venueGeo(venue)
  return a && v ? roadKm(a, v) : 20
}

export const osmLink = (g: Geo) => `https://www.openstreetmap.org/?mlat=${g.lat}&mlon=${g.lon}#map=15/${g.lat}/${g.lon}`
