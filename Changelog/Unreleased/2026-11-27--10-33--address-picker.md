# Changed
- Mein Standort shows the map all the time and keeps the address and the marker in sync
 - Typing an address moves the marker (`GET /api/geo/search`, Nominatim, debounced)
 - A click or drag writes the address (`GET /api/geo/reverse`) and keeps the clicked coordinates
 - `PUT /api/me/standort` stores `lat` and `lon`. Clearing the address clears both
 - Other maps still use the town table unless a person has saved coordinates (`refGeo`)
