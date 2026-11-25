<template>
  <v-chip class="mr-1 mb-1" color="default" variant="tonal">
    <span class="card-swatch mr-2" :style="{ background: CARD_COLORS[card.color] }" />
    {{ [CARD_LABEL[card.color], card.team, card.person, card.reason, card.given_by && `vergeben von ${card.given_by}`].filter(Boolean).join(' · ') }}
  </v-chip>
</template>

<script lang="ts" setup>
  import type { Card } from '@/types'
  import { CARD_COLORS, CARD_LABEL } from '@/domain/rules'

  defineProps<{ card: Card }>()
</script>

<style>
.card-swatch { width: 9px; height: 13px; border-radius: 2px; display: inline-block; }
</style>
