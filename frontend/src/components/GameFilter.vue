<template>
  <div class="gap-row">
    <v-select
      v-model="model.typ"
      aria-label="Ligaspiele oder Turniere"
      :items="[{ title: 'Ligaspiele & Turniere', value: 'alle' }, { title: 'Nur Ligaspiele', value: 'liga' }, { title: 'Nur Turniere', value: 'turnier' }]"
      style="max-width: 220px"
    />

    <v-select
      v-model="model.liga"
      aria-label="Wettbewerb"
      :items="[{ title: 'Alle Wettbewerbe', value: 'alle' }, ...(competitions ?? COMPETITIONS)]"
      style="max-width: 240px"
    />

    <slot />
  </div>
</template>

<script lang="ts" setup>
  import { COMPETITIONS, type GameFilterValue } from '@/domain/rules'

  defineProps<{ competitions?: string[] }>()
  const model = defineModel<GameFilterValue>({ required: true })
</script>
