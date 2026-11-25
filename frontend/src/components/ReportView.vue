<template>
  <div v-if="report" class="text-body-2">
    <div v-if="report.note" class="mb-1">{{ report.note }}</div>
    <div><CardChip v-for="(c, i) in report.cards.filter(c => !color || c.color === color)" :key="i" :card="c" /></div>
    <div v-if="withAuthor" class="text-caption text-soft">erfasst von {{ report.author }} am {{ isoToDe(report.at) }}</div>
  </div>
</template>

<script lang="ts" setup>
  import type { Report } from '@/types'
  import { isoToDe } from '@/domain/dates'
  import CardChip from './CardChip.vue'

  defineProps<{ report: Report | null, withAuthor?: boolean, color?: string }>()
</script>
