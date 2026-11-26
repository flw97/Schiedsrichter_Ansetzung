<template>
  <div class="max-w-sm">
    <PageHeader subtitle="Deine endgültig bestätigten Ansetzungen" title="Ansetzungen" />
    <GameFilter v-model="gf" class="mb-3" />
    <v-card v-if="shown.length === 0" class="pa-4 text-body-2 text-soft">Aktuell keine bestätigten Ansetzungen.</v-card>

    <div class="list-gap">
      <GameSummary v-for="g in shown" :key="g.id" :game="g" hide-competition>
        <div v-if="role(g)" class="text-soft">Rolle: {{ role(g) }}</div>
        <template #append><v-chip color="success">Bestätigt</v-chip></template>

        <template #footer>
          <v-alert
            v-if="g.schedule_change && !g.reconfirmed_ids.includes(me.id)"
            class="mb-3"
            color="warning"
            density="compact"
            variant="tonal"
          >
            <div class="text-body-2 mb-2"><strong>Termin geändert:</strong> {{ g.schedule_change.old }} → {{ g.schedule_change.new }}. Bitte bestätige, dass du auch am neuen Termin kannst.</div>

            <div class="gap-row">
              <v-btn color="primary" size="small" @click="store.act('POST', `/games/${g.id}/reconfirm`)">Neuer Termin passt</v-btn>

              <ConfirmAction
                confirm-label="Ja, zurückgeben"
                label="Passt nicht — zurückgeben"
                reason="Neuer Termin passt nicht"
                size="small"
                :text="WITHDRAW_TEXT"
                variant="outlined"
                @confirm="r => withdraw(g.id, r)"
              />
            </div>
          </v-alert>

          <div v-else-if="g.schedule_change" class="text-caption text-success mb-2">✓ Neuer Termin bestätigt ({{ g.schedule_change.new }})</div>
          <ReportForm v-if="reportId === g.id" :game="g" @close="reportId = null" />

          <template v-else>
            <ReportView :report="g.report" />

            <v-btn
              class="px-0"
              color="primary"
              size="small"
              variant="text"
              @click="reportId = g.id"
            >{{ g.report ? 'Anmerkung / Karten bearbeiten' : 'Anmerkung / Karten erfassen' }}</v-btn>
          </template>

          <div><ConfirmAction
            class="px-0"
            confirm-label="Ja, zurückgeben"
            label="Ansetzung zurückgeben"
            reason=""
            size="small"
            :text="WITHDRAW_TEXT"
            @confirm="r => withdraw(g.id, r)"
          /></div>
        </template>
      </GameSummary>
    </div>

    <v-card class="pa-4 mt-5">
      <div class="d-flex align-center ga-2 text-body-2 font-weight-medium mb-2"><v-icon color="primary" icon="mdi-calendar-clock-outline" size="18" /> Kalender</div>
      <div class="text-caption text-soft mb-3">Alle bestätigten Ansetzungen als Kalenderdatei, z. B. für Google Kalender oder Outlook.</div>

      <v-btn
        color="primary"
        :disabled="mineAll.length === 0"
        prepend-icon="mdi-download"
        size="small"
        @click="downloadIcs"
      >Als .ics-Datei herunterladen</v-btn>
    </v-card>

    <v-card class="pa-4 mt-3 d-flex align-center ga-4">
      <v-icon color="success" icon="mdi-trending-up" size="26" />

      <div>
        <div class="font-display text-h6">{{ me.season_count }} Einsätze</div>
        <div class="text-caption text-soft">diese Saison · {{ me.license ? `Lizenz ${me.license}${me.license_valid_until ? `, gültig bis ${isoToDe(me.license_valid_until)}` : ''}` : 'Lizenz noch nicht eingetragen' }}</div>
      </div>
    </v-card>
  </div>
</template>

<script lang="ts" setup>
  import type { Game } from '@/types'
  import { computed, ref } from 'vue'
  import ConfirmAction from '@/components/ConfirmAction.vue'
  import GameFilter from '@/components/GameFilter.vue'
  import GameSummary from '@/components/GameSummary.vue'
  import PageHeader from '@/components/PageHeader.vue'
  import ReportForm from '@/components/ReportForm.vue'
  import ReportView from '@/components/ReportView.vue'
  import { isoToDe } from '@/domain/dates'
  import { buildIcs } from '@/domain/ics'
  import { assignedIds, gameMatches, GF0 } from '@/domain/rules'
  import { useAppStore } from '@/stores/app'

  const WITHDRAW_TEXT = 'Ansetzung wirklich zurückgeben? Das Spiel erscheint dann wieder in der Abfrage, der Ansetzer wird benachrichtigt.'
  const store = useAppStore()
  const me = computed(() => store.me!)
  const gf = ref({ ...GF0 })
  const reportId = ref<number | null>(null)
  const mineAll = computed(() => store.activeGames.filter(g => g.status === 'confirmed' && assignedIds(g).includes(me.value.id)))
  const shown = computed(() => mineAll.value.filter(g => gameMatches(g, gf.value)))
  const role = (g: Game) => (g.head_referee_id === me.value.id ? 'Referee' : (g.leader_id === me.value.id ? 'verantwortlicher Leiter' : ''))
  const withdraw = (id: number, reason: string) => store.act('POST', `/games/${id}/withdraw`, { reason })

  function downloadIcs () {
    const a = document.createElement('a')
    a.href = URL.createObjectURL(new Blob([buildIcs(mineAll.value, me.value.name)], { type: 'text/calendar' }))
    a.download = 'ansetzungen.ics'
    a.click()
    URL.revokeObjectURL(a.href)
  }
</script>
