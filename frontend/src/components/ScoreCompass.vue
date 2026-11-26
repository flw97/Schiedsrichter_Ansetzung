<template>
  <svg :height="size" :viewBox="`0 0 ${size} ${size}`" :width="size">
    <circle
      class="face"
      :cx="c"
      :cy="c"
      :r="r + 12"
    />

    <path
      v-for="a in arcs"
      :key="a.key"
      :d="a.d"
      :fill="a.color"
      opacity="0.85"
    />

    <circle class="hub" :cx="c" :cy="c" r="27" />

    <text
      class="score font-display"
      font-size="19"
      text-anchor="middle"
      :x="c"
      :y="c - 2"
    >{{ score }}</text>

    <text
      class="score-label font-mono"
      font-size="8"
      text-anchor="middle"
      :x="c"
      :y="c + 13"
    >SCORE</text>

    <line
      class="needle"
      stroke-linecap="round"
      stroke-width="2"
      :x1="c"
      :x2="needle[0]"
      :y1="c"
      :y2="needle[1]"
    />

    <circle class="needle-tip" :cx="needle[0]" :cy="needle[1]" r="3" />
  </svg>
</template>

<script lang="ts" setup>
  import type { Weights } from '@/domain/rules'
  import { computed } from 'vue'

  // Anteil der Gewichte als Kreissegmente, Zeiger = Score des besten Vorschlags
  const props = defineProps<{ weights: Weights, score: number }>()
  const size = 120
  const c = size / 2
  const r = 46
  const xy = (deg: number, radius = r) => [c + radius * Math.cos((deg * Math.PI) / 180), c + radius * Math.sin((deg * Math.PI) / 180)]

  const arcs = computed(() => {
    const segs = [
      { key: 'distance', color: 'rgb(var(--v-theme-chart-distance))', value: props.weights.distance },
      { key: 'avail', color: 'rgb(var(--v-theme-chart-avail))', value: props.weights.avail },
      { key: 'fair', color: 'rgb(var(--v-theme-chart-fair))', value: props.weights.fair },
    ]
    const total = segs.reduce((s, x) => s + x.value, 0) || 1
    let start = -90
    return segs.map(s => {
      const sweep = (s.value / total) * 360
      const [x1, y1] = xy(start)
      const [x2, y2] = xy(start + sweep)
      start += sweep
      return { ...s, d: `M ${c} ${c} L ${x1} ${y1} A ${r} ${r} 0 ${sweep > 180 ? 1 : 0} 1 ${x2} ${y2} Z` }
    })
  })
  const needle = computed(() => xy(-90 + (props.score / 100) * 360, r + 9))
</script>

<style scoped>
.face { fill: var(--surface); stroke: var(--line); }
.hub { fill: var(--surface); }
.score { fill: var(--ink); }
.score-label { fill: var(--ink-soft); }
.needle { stroke: var(--ink); }
.needle-tip { fill: var(--ink); }
</style>
