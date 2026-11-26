<template>
  <div class="max-w-lg" style="max-width: 1100px">
    <PageHeader subtitle="Interne Liste von Übernachtungsmöglichkeiten bei den Spielorten — mit Bewertungen und Kommentaren der Schiedsrichter." title="Unterkünfte" />

    <div class="gap-row mb-4">
      <v-text-field
        v-model="q"
        aria-label="Suche"
        placeholder="Suche: Name oder Ort"
        prepend-inner-icon="mdi-magnify"
        style="min-width: 200px"
      />

      <v-select v-model="venue" aria-label="Spielort" :items="[{ title: 'Alle Spielorte', value: '' }, ...venues]" style="max-width: 260px" />
      <v-btn v-if="canPropose && !form" color="primary" @click="startForm()">Unterkunft eintragen</v-btn>
    </div>

    <v-card v-if="form" class="pa-4 mb-4">
      <div class="text-body-1 font-weight-medium mb-3">{{ form.id ? 'Unterkunft bearbeiten' : 'Unterkunft eintragen' }}</div>

      <div class="gap-row mb-3">
        <v-text-field v-model="form.data.name" label="Name der Unterkunft *" maxlength="80" style="min-width: 200px" />
        <v-text-field v-model="form.data.street" label="Straße und Nr." maxlength="80" style="min-width: 200px" />
        <v-text-field v-model="form.data.zip" label="PLZ" maxlength="10" style="max-width: 110px" />
        <v-text-field v-model="form.data.city" label="Ort *" maxlength="60" style="min-width: 160px" />
        <v-select v-model="form.data.venue" :items="venues" label="Zugehöriger Spielort *" style="min-width: 220px" />
      </div>

      <div class="gap-row mb-3">
        <v-text-field v-model="form.data.lat" label="Breite (optional)" placeholder="48.7406" style="max-width: 160px" />
        <v-text-field v-model="form.data.lon" label="Länge (optional)" placeholder="9.3051" style="max-width: 160px" />
        <span class="text-caption text-soft">Ohne Koordinaten wird die Ortsmitte des Ortes auf der Karte verwendet.</span>
      </div>

      <div class="gap-row">
        <v-btn color="primary" :loading="store.busy" @click="saveForm">Speichern</v-btn>
        <v-btn variant="outlined" @click="form = null">Abbrechen</v-btn>
      </div>
    </v-card>

    <div class="d-flex flex-wrap ga-4 align-start">
      <div class="flex-grow-1" style="flex-basis: 380px">
        <div class="text-caption text-soft mb-2">{{ rows.length }} von {{ items.length }} Unterkünften</div>
        <v-card v-if="rows.length === 0" class="pa-4 text-body-2 text-soft">{{ items.length === 0 ? 'Noch keine Unterkünfte eingetragen.' : 'Keine Unterkunft für diesen Filter.' }}</v-card>

        <v-card v-for="h in rows" :key="h.id" class="pa-4 mb-3" :style="sel === `u:${h.id}` ? 'border-color: rgb(var(--v-theme-primary))' : ''">
          <div class="text-body-1 font-weight-medium">{{ h.name }}</div>
          <div class="text-body-2 text-soft">{{ [h.street, `${h.zip ?? ''} ${h.city}`.trim()].filter(Boolean).join(', ') }}</div>
          <div class="text-body-2 mt-1">Spielort: {{ h.venue }}{{ h.dist !== null ? ` · ${h.dist} km` : '' }}</div>

          <div class="text-body-2 mt-1">
            <template v-if="h.avg !== null"><span class="stars">{{ stars(h.avg) }}</span> {{ num(h.avg) }} ({{ h.reviews.length }})</template>
            <span v-else class="text-soft">Noch keine Bewertung</span>
          </div>

          <v-divider class="my-3" />

          <div class="gap-row">
            <v-btn
              color="primary"
              :href="navLink(h)"
              rel="noopener noreferrer"
              size="small"
              target="_blank"
              variant="text"
            >Navigation starten</v-btn>

            <v-btn
              color="primary"
              :href="routeLink(h)"
              rel="noopener noreferrer"
              size="small"
              target="_blank"
              variant="text"
            >Route zum Spielort</v-btn>

            <v-btn
              v-if="h.geo"
              color="primary"
              size="small"
              variant="text"
              @click="sel = `u:${h.id}`"
            >Auf der Karte zeigen</v-btn>

            <v-btn
              v-if="canPropose"
              color="primary"
              size="small"
              variant="text"
              @click="startReview(h)"
            >{{ mine(h) ? 'Meine Bewertung ändern' : 'Bewerten & kommentieren' }}</v-btn>

            <v-btn
              v-if="comments(h).length > 0"
              color="primary"
              size="small"
              variant="text"
              @click="openCom[h.id] = !openCom[h.id]"
            >
              {{ openCom[h.id] ? 'Kommentare ausblenden' : `Kommentare (${comments(h).length})` }}
            </v-btn>

            <v-btn
              v-if="canManage"
              color="primary"
              size="small"
              variant="text"
              @click="startForm(h)"
            >Bearbeiten</v-btn>

            <ConfirmAction
              v-if="canManage"
              label="Löschen"
              size="small"
              text="Unterkunft wirklich löschen?"
              @confirm="store.act('DELETE', `/accommodations/${h.id}`)"
            />
          </div>

          <div v-if="openCom[h.id]" class="mt-2">
            <div v-for="b in comments(h)" :key="b.id" class="text-body-2 py-2" style="border-top: 1px solid var(--line)">
              <span class="stars">{{ stars(b.stars) }}</span> <strong>{{ b.author }}</strong> <span class="text-caption text-soft">{{ new Date(b.updated_at).toLocaleDateString('de-DE') }}</span>
              <div>{{ b.text }}</div>
            </div>
          </div>

          <v-sheet v-if="reviewId === h.id" class="pa-3 mt-3" color="surface-variant" rounded="lg">
            <v-select
              v-model="review.stars"
              bg-color="surface"
              class="mb-2"
              :items="[5, 4, 3, 2, 1].map(n => ({ title: `${n} — ${stars(n)}`, value: n }))"
              label="Bewertung"
              style="max-width: 220px"
            />

            <v-textarea
              v-model="review.text"
              bg-color="surface"
              class="mb-2"
              maxlength="300"
              placeholder="Kommentar (Lage, Frühstück, Ruhe, Anreise …)"
            />

            <div class="gap-row">
              <v-btn color="primary" size="small" @click="saveReview(h.id)">Speichern</v-btn>
              <v-btn size="small" variant="outlined" @click="reviewId = null">Abbrechen</v-btn>
            </div>
          </v-sheet>
        </v-card>
      </div>

      <div class="flex-grow-1" style="flex-basis: 340px; position: sticky; top: 64px">
        <GeoMap :points="points" :selected="sel" @select="sel = $event" />
        <div class="text-caption text-soft mt-1">Karte: OpenStreetMap. Orange: Unterkünfte, blau: zugehörige Spielorte. Position = Ortsmitte bzw. eingetragene Koordinaten; Entfernung = Luftlinie × 1,25.</div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
  import type { Accommodation } from '@/types'
  import { computed, reactive, ref } from 'vue'
  import ConfirmAction from '@/components/ConfirmAction.vue'
  import GeoMap from '@/components/GeoMap.vue'
  import PageHeader from '@/components/PageHeader.vue'
  import { num } from '@/domain/dates'
  import { accommodationGeo, type MapPoint, roadKm, VENUE_PLACE, venueGeo } from '@/domain/geo'
  import { useAppStore } from '@/stores/app'

  const store = useAppStore()
  const canManage = computed(() => store.can('unterkunft_verwalten'))
  const canPropose = computed(() => store.can('unterkunft_vorschlagen'))
  const items = computed(() => store.state!.accommodations)
  const venues = computed(() => [...new Set([...Object.keys(VENUE_PLACE), ...store.activeGames.map(g => g.venue)])].filter(v => venueGeo(v)).toSorted((a, b) => a.localeCompare(b, 'de')))
  const stars = (n: number) => '★★★★★'.slice(0, Math.round(n)) + '☆☆☆☆☆'.slice(0, 5 - Math.round(n))
  const q = ref('')
  const venue = ref('')
  const sel = ref<string | null>(null)

  const rows = computed(() => items.value.map(h => {
    const geo = accommodationGeo(h)
    const vg = venueGeo(h.venue)
    return { ...h, geo, vg, dist: geo && vg ? roadKm(geo, vg) : null, avg: h.reviews.length > 0 ? h.reviews.reduce((s, b) => s + b.stars, 0) / h.reviews.length : null }
  }).filter(h => (!venue.value || h.venue === venue.value) && (!q.value.trim() || `${h.name} ${h.city} ${h.street ?? ''} ${h.venue}`.toLowerCase().includes(q.value.trim().toLowerCase()))).toSorted((a, b) => a.venue.localeCompare(b.venue, 'de') || (a.dist ?? 999) - (b.dist ?? 999) || a.name.localeCompare(b.name, 'de')))
  const points = computed<MapPoint[]>(() => [
    ...rows.value.flatMap(h => (h.geo ? [{ id: `u:${h.id}`, kind: 'hotel' as const, lat: h.geo.lat, lon: h.geo.lon, label: h.name }] : [])),
    ...[...new Map(rows.value.filter(h => h.vg).map(h => [h.venue, h.vg!])).entries()].map(([v, g]) => ({ id: `v:${v}`, kind: 'hall' as const, lat: g.lat, lon: g.lon, label: v })),
  ])

  // Navigation per Karten-App (Google Maps öffnet auf dem Handy die installierte App)
  const place = (h: Accommodation) => (h.lat != null && h.lon != null ? `${h.lat},${h.lon}` : `${h.street ?? ''} ${h.zip ?? ''} ${h.city}`.trim())
  const navLink = (h: Accommodation) => `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(place(h))}`
  const routeLink = (h: Accommodation) => `https://www.google.com/maps/dir/?api=1&origin=${encodeURIComponent(place(h))}&destination=${encodeURIComponent(h.venue)}`

  const mine = (h: Accommodation) => h.reviews.find(b => b.user_id === store.state!.me.id)
  const comments = (h: Accommodation) => h.reviews.filter(b => b.text)
  const openCom = reactive<Record<number, boolean>>({})
  const reviewId = ref<number | null>(null)
  const review = reactive({ stars: 4, text: '' })
  function startReview (h: Accommodation) {
    reviewId.value = reviewId.value === h.id ? null : h.id
    Object.assign(review, { stars: mine(h)?.stars ?? 4, text: mine(h)?.text ?? '' })
  }
  async function saveReview (id: number) {
    if (await store.act('POST', `/accommodations/${id}/reviews`, review, 'Bewertung gespeichert.')) {
      reviewId.value = null
    }
  }

  const form = ref<{ id: number | null, data: Record<string, string> } | null>(null)
  function startForm (h?: Accommodation) {
    const s = (v: unknown) => (v == null ? '' : String(v))
    form.value = { id: h?.id ?? null, data: { name: s(h?.name), street: s(h?.street), zip: s(h?.zip), city: s(h?.city), venue: h?.venue ?? venue.value, lat: s(h?.lat), lon: s(h?.lon) } }
  }
  async function saveForm () {
    const f = form.value!
    const body = { ...f.data, lat: f.data.lat || null, lon: f.data.lon || null }
    if (await store.act(f.id ? 'PUT' : 'POST', f.id ? `/accommodations/${f.id}` : '/accommodations', body, 'Unterkunft gespeichert.')) {
      form.value = null
    }
  }
</script>

<style scoped>
.stars { color: rgb(var(--v-theme-star)); letter-spacing: 1px; }
</style>
