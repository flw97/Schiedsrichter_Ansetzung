<template>
  <v-card :class="{ 'opacity-60': dim }" :to="to">
    <div class="d-flex align-center ga-4 pa-4">
      <v-avatar color="surface-variant" rounded="lg" size="36">
        <v-icon color="primary" :icon="icon ?? (game.type === 'tournament' ? 'mdi-trophy-outline' : 'mdi-calendar-blank-outline')" size="18" />
      </v-avatar>

      <div class="flex-grow-1" style="min-width: 0">
        <div class="text-body-1 font-weight-medium">{{ game.title }}{{ game.type === 'tournament' && tournamentHint ? ' (Turnier, ganzer Tag)' : '' }}</div>
        <div class="text-body-2 text-soft">{{ dayLabel(game.date, withYear) }} · {{ timeLabel(game.time) }} · {{ game.venue }}<template v-if="!hideCompetition"> · {{ game.competition }}</template></div>
        <div class="text-body-2"><slot /></div>
      </div>

      <div class="d-flex align-center ga-2 flex-shrink-0"><slot name="append" /></div>
    </div>

    <div v-if="$slots.footer" class="px-4 pb-4"><slot name="footer" /></div>
  </v-card>
</template>

<script lang="ts" setup>
  import type { Game } from '@/types'
  import { dayLabel, timeLabel } from '@/domain/dates'

  defineProps<{ game: Game, to?: string, dim?: boolean, icon?: string, withYear?: boolean, hideCompetition?: boolean, tournamentHint?: boolean }>()
</script>
