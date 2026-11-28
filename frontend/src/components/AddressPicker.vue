<template>
  <v-text-field
    class="mb-2"
    label="Adresse"
    :loading="busy"
    :model-value="address"
    @update:model-value="onType"
  />

  <div class="text-caption text-soft mb-2">{{ hint }}</div>

  <GeoMap
    :height="360"
    pickable
    :points="points"
    @pick="onPick"
  />
</template>

<script lang="ts" setup>
  import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
  import { api } from '@/api'
  import GeoMap from '@/components/GeoMap.vue'
  import { geoOf } from '@/domain/geo'

  const address = defineModel<string>('address', { required: true })
  const lat = defineModel<number | null>('lat', { required: true })
  const lon = defineModel<number | null>('lon', { required: true })

  interface Hit { address: string, lat: number, lon: number }

  const busy = ref(false)
  let timer: ReturnType<typeof setTimeout> | undefined
  let seq = 0

  const placed = computed(() => lat.value != null && lon.value != null)
  const hint = computed(() => {
    if (busy.value) {
      return 'Suche die Adresse …'
    }
    if (!address.value.trim()) {
      return 'Klicke in die Karte oder tippe eine Adresse. Beides setzt dieselbe Position.'
    }
    if (!placed.value && !geoOf(address.value)) {
      return 'Noch keine Position. Klicke in die Karte oder formuliere die Adresse genauer.'
    }
    return placed.value
      ? 'Markierung und Adresse folgen einander. Ziehen verschiebt den Punkt.'
      : 'Vorläufig die Ortsmitte. Ein Klick in die Karte setzt die genaue Position.'
  })
  const points = computed(() => {
    const here = lat.value
    const there = lon.value
    const at = here != null && there != null ? { lat: here, lon: there } : geoOf(address.value)
    return at ? [{ id: 'me', kind: 'ref' as const, lat: at.lat, lon: at.lon, label: 'Standort' }] : []
  })

  function onType (value: string) {
    address.value = value
    clearTimeout(timer)
    const query = value.trim()
    if (query.length < 3) {
      if (!query) {
        lat.value = null
        lon.value = null
      }
      return
    }
    timer = setTimeout(() => void lookup(query, true), 800)
  }

  async function lookup (query: string, replaceAddress: boolean) {
    const mine = ++seq
    busy.value = true
    try {
      const hit = await api.json<Hit | null>('GET', `/geo/search?q=${encodeURIComponent(query)}`)
      if (mine !== seq || address.value.trim() !== query || !hit) {
        return
      }
      lat.value = hit.lat
      lon.value = hit.lon
      if (replaceAddress) {
        address.value = hit.address
      }
    } catch {
      // Ortsmittel aus dem Adresstext bleibt stehen.
    } finally {
      if (mine === seq) {
        busy.value = false
      }
    }
  }

  async function onPick (at: { lat: number, lon: number }) {
    const mine = ++seq
    clearTimeout(timer)
    lat.value = at.lat
    lon.value = at.lon
    busy.value = true
    try {
      const hit = await api.json<Hit | null>('GET', `/geo/reverse?lat=${at.lat}&lon=${at.lon}`)
      if (mine === seq && hit) {
        address.value = hit.address
      }
    } catch {
      // Die gesetzte Position bleibt, die Adresse ändert sich nicht.
    } finally {
      if (mine === seq) {
        busy.value = false
      }
    }
  }

  onMounted(() => {
    const query = address.value.trim()
    if (query.length >= 3 && !placed.value) {
      void lookup(query, false)
    }
  })
  onBeforeUnmount(() => clearTimeout(timer))
</script>
