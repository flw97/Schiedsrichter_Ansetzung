# Changed
- Maps (Standorte, Unterkünfte) use OpenStreetMap tiles via Leaflet instead of the simplified SVG map of Baden-Württemberg from `2026-11-25--23-55--prototype-port.md`
  - `frontend/src/components/GeoMap.vue` keeps its interface (`points`, `selected`, `height`, `select` event), so pages are unchanged; markers keep the prototype colours (referees green, venues blue/square, accommodations orange)
  - Tiles are loaded by the browser from `tile.openstreetmap.org` with the required attribution
  ```ts
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap-Mitwirkende' })
  ```
- New dependencies `leaflet`, `@types/leaflet`

# Removed
- SVG map data (`BW_OUTLINE`, rivers, `MAP_CITIES`, `mapX`/`mapY`/`mapPath`) from `frontend/src/domain/geo.ts`
