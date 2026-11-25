<template>
  <div class="max-w-lg">
    <PageHeader subtitle="Wohnorte der Schiedsrichter (aus den hinterlegten Adressen), Spielorte und Unterkünfte auf der OpenStreetMap-Karte. Entfernungen sind Luftlinie × 1,25 als Näherung für die Fahrstrecke." title="Standorte" />

    <div class="gap-row mb-2" style="gap: 16px">
      <v-checkbox
        v-for="l in LAYERS"
        :key="l.kind"
        v-model="layers"
        :color="l.color"
        :value="l.kind"
      >
        <template #label><span class="swatch mr-1" :style="{ background: l.color, borderRadius: l.kind === 'hall' ? '2px' : '5px' }" />{{ l.label }}</template>
      </v-checkbox>
    </div>

    <GeoMap :height="560" :points="all.filter(p => layers.includes(p.kind))" :selected="sel" @select="sel = $event" />
    <div class="text-caption text-soft mt-1">Karte: OpenStreetMap. Die Position wird aus dem Ortsnamen der Adresse bestimmt (Ortsmitte), nicht auf die Hausnummer genau.</div>

    <v-card v-if="selected" class="pa-4 mt-3">
      <div class="text-body-1 font-weight-medium">{{ selected.label }}</div>
      <div class="text-body-2 text-soft">{{ selected.sub }} · Position: {{ selected.genau }}</div>
      <div v-if="nearest.length > 0" class="text-body-2 mt-1">Nächste Spielorte: {{ nearest.map(n => `${n.p.label} (${n.d} km)`).join(' · ') }}</div>
      <a class="text-body-2 text-primary" :href="osmLink(selected)" rel="noopener noreferrer" target="_blank">In OpenStreetMap öffnen</a>
    </v-card>

    <v-card class="mt-4">
      <v-table density="compact">
        <thead><tr><th>Schiedsrichter</th><th>Hinterlegte Adresse</th><th /></tr></thead>

        <tbody>
          <tr
            v-for="r in store.referees"
            :key="r.id"
            :class="{ 'bg-surface-variant': sel === `r${r.id}` }"
            style="cursor: pointer"
            @click="refGeo(r) && (sel = `r${r.id}`)"
          >
            <td class="font-weight-medium">{{ r.name }}</td>
            <td class="text-soft">{{ r.address || '—' }}</td>
            <td class="text-caption" :class="refGeo(r) ? 'text-success' : 'text-error'">{{ refGeo(r) ? `auf der Karte (${refGeo(r)!.genau})` : 'Ort nicht erkannt' }}</td>
          </tr>
        </tbody>
      </v-table>
    </v-card>

    <div v-if="missing.length > 0" class="text-caption text-error mt-2">Für {{ missing.join(', ') }} konnte aus der Adresse kein Ort erkannt werden — bitte Adresse mit Ort angeben.</div>
  </div>
</template>

<script lang="ts" setup>
  import { computed, ref } from 'vue'
  import GeoMap from '@/components/GeoMap.vue'
  import PageHeader from '@/components/PageHeader.vue'
  import { accommodationGeo, type Geo, type MapPoint, osmLink, refGeo, roadKm, VENUE_PLACE, venueGeo } from '@/domain/geo'
  import { useAppStore } from '@/stores/app'

  const LAYERS = [{ kind: 'ref', label: 'Schiedsrichter', color: '#2F6B4F' }, { kind: 'hall', label: 'Spielorte', color: '#2A5A9E' }, { kind: 'hotel', label: 'Unterkünfte', color: '#D9822B' }] as const
  const store = useAppStore()
  const layers = ref<string[]>(['ref', 'hall', 'hotel'])
  const sel = ref<string | null>(null)

  type Point = MapPoint & { sub: string, genau: string }
  const point = (id: string, kind: MapPoint['kind'], g: Geo | null, label: string, sub: string): Point[] => (g ? [{ id, kind, lat: g.lat, lon: g.lon, label, sub, genau: g.genau }] : [])
  const all = computed(() => [
    ...store.referees.flatMap(r => point(`r${r.id}`, 'ref', refGeo(r), r.name, r.address ?? '')),
    ...[...new Set([...Object.keys(VENUE_PLACE), ...store.games.map(g => g.venue)])].flatMap(v => point(`h:${v}`, 'hall', venueGeo(v), v, 'Spielort')),
    ...store.state!.accommodations.flatMap(a => point(`u:${a.id}`, 'hotel', accommodationGeo(a), a.name, `${a.street ?? ''}, ${a.zip ?? ''} ${a.city}`)),
  ])
  const selected = computed(() => all.value.find(p => p.id === sel.value) ?? null)
  const nearest = computed(() => (selected.value
    ? all.value.filter(p => p.kind === 'hall' && p.id !== selected.value!.id).map(p => ({ p, d: roadKm(selected.value!, p) })).toSorted((a, b) => a.d - b.d).slice(0, 4)
    : []))
  const missing = computed(() => store.referees.filter(r => !refGeo(r)).map(r => r.name))
</script>

<style scoped>
.swatch { width: 10px; height: 10px; display: inline-block; }
</style>
