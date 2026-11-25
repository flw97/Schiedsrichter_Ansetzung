<template>
  <div ref="el" class="geo-map" :style="{ height: `${height ?? 520}px` }" />
</template>

<script lang="ts" setup>
  import type { MapPoint } from '@/domain/geo'
  import L from 'leaflet'
  import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import 'leaflet/dist/leaflet.css'

  // OpenStreetMap-Karte mit Schiedsrichtern (grün), Spielorten (blau, eckig) und Unterkünften (orange)
  const props = defineProps<{ points: MapPoint[], selected?: string | null, height?: number }>()
  const emit = defineEmits<{ select: [id: string] }>()
  const COLORS = { ref: '#2F6B4F', hall: '#2A5A9E', hotel: '#D9822B' }
  const BW_CENTER: L.LatLngExpression = [48.6, 9]

  const el = ref<HTMLElement>()
  let map: L.Map | undefined
  let layer: L.LayerGroup | undefined

  function marker (p: MapPoint) {
    const sel = p.id === props.selected
    const size = sel ? 18 : 13
    const icon = L.divIcon({
      className: '',
      iconSize: [size, size],
      html: `<span class="geo-pin${p.kind === 'hall' ? ' square' : ''}${sel ? ' selected' : ''}" style="background:${COLORS[p.kind]}"></span>`,
    })
    return L.marker([p.lat, p.lon], { icon, title: p.label, zIndexOffset: sel ? 1000 : 0 })
      .bindTooltip(p.label, { permanent: sel, direction: 'right', offset: [size / 2, 0] })
      .on('click', () => emit('select', p.id))
  }

  function draw () {
    layer?.clearLayers()
    for (const p of props.points) {
      layer?.addLayer(marker(p))
    }
  }

  function fit () {
    if (!map) {
      return
    }
    if (props.points.length === 0) {
      map.setView(BW_CENTER, 8)
      return
    }
    map.fitBounds(L.latLngBounds(props.points.map(p => [p.lat, p.lon])), { padding: [40, 40], maxZoom: 13 })
  }

  onMounted(() => {
    map = new L.Map(el.value!, { center: BW_CENTER, zoom: 8 })
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>-Mitwirkende',
    }).addTo(map)
    layer = L.layerGroup().addTo(map)
    draw()
    fit()
  })
  onBeforeUnmount(() => map?.remove())

  // Neue Punktmenge: neu zeichnen und Ausschnitt anpassen; Auswahl: nur neu zeichnen und hinschwenken
  watch(() => props.points.map(p => p.id).join('|'), () => {
    draw()
    fit()
  })
  watch(() => props.selected, id => {
    draw()
    const p = props.points.find(x => x.id === id)
    if (p) {
      map?.panTo([p.lat, p.lon])
    }
  })
</script>

<style scoped>
.geo-map { border: 1px solid #E4DFD1; border-radius: 12px; overflow: hidden; z-index: 0; }
.geo-map :deep(.geo-pin) { display: block; width: 100%; height: 100%; border: 2px solid #fff; border-radius: 50%; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4); }
.geo-map :deep(.geo-pin.square) { border-radius: 2px; }
.geo-map :deep(.geo-pin.selected) { outline: 3px solid rgba(27, 36, 31, 0.35); }
</style>
