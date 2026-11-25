<template>
  <PageHeader :subtitle="subtitle" title="Archiv" />

  <GameFilter v-model="gf" class="mb-3" :competitions="competitions">
    <v-text-field v-model="q" placeholder="Suche: Spiel, Halle, Schiedsrichter" prepend-inner-icon="mdi-magnify" style="min-width: 240px" />
    <v-select v-if="!isRef" v-model="st" :items="[{ title: 'Alle Spiele', value: 'alle' }, { title: 'Besetzt', value: 'besetzt' }, { title: 'Nicht besetzt', value: 'unbesetzt' }]" style="max-width: 180px" />
  </GameFilter>

  <div class="text-caption text-soft mb-3">{{ base.length }} Spiel{{ base.length === 1 ? '' : 'e' }} im Archiv · {{ base.filter(g => g.report?.cards.length).length }} mit Karten{{ rows.length !== base.length ? ` · ${rows.length} angezeigt` : '' }}</div>
  <v-card v-if="rows.length === 0" class="pa-4 text-body-2 text-soft">{{ base.length === 0 ? 'Noch keine vergangenen Spiele im Archiv.' : 'Keine Spiele für diesen Filter.' }}</v-card>

  <div class="list-gap max-w-md">
    <GameSummary v-for="g in rows" :key="g.id" :game="g" with-year>
      <div v-if="crew(g).length > 0">{{ crew(g).join(' · ') }}</div>

      <template #append>
        <v-chip :color="g.status === 'confirmed' ? 'success' : 'error'">{{ g.status === 'confirmed' ? 'Besetzt' : 'Nicht besetzt' }}</v-chip>
      </template>

      <template #footer>
        <ReportView :report="g.report" with-author />
        <ArchiveEdit v-if="editId === g.id" :game="g" @close="editId = null" />
        <ReportForm v-else-if="reportId === g.id" :game="g" @close="reportId = null" />

        <div v-else class="gap-row">
          <v-btn
            v-if="isRef || canEdit"
            class="px-0"
            color="primary"
            size="small"
            variant="text"
            @click="reportId = g.id"
          >{{ g.report ? 'Anmerkung / Karten bearbeiten' : 'Anmerkung / Karten erfassen' }}</v-btn>

          <v-btn
            v-if="canEdit"
            color="primary"
            size="small"
            variant="text"
            @click="editId = g.id"
          >Termin, Halle &amp; Besetzung bearbeiten</v-btn>
        </div>
      </template>
    </GameSummary>
  </div>
</template>

<script lang="ts" setup>
  import type { Game } from '@/types'
  import { computed, ref } from 'vue'
  import ArchiveEdit from '@/components/ArchiveEdit.vue'
  import GameFilter from '@/components/GameFilter.vue'
  import GameSummary from '@/components/GameSummary.vue'
  import PageHeader from '@/components/PageHeader.vue'
  import ReportForm from '@/components/ReportForm.vue'
  import ReportView from '@/components/ReportView.vue'
  import { byDate, isPast } from '@/domain/dates'
  import { assignedIds, gameMatches, GF0 } from '@/domain/rules'
  import { useAppStore } from '@/stores/app'

  const store = useAppStore()
  const isRef = computed(() => store.state!.me.role === 'schiri')
  const canEdit = computed(() => store.can('ansetzen'))
  const subtitle = computed(() => isRef.value
    ? 'Deine vergangenen Einsätze. Anmerkungen und Karten kannst du hier auch nachträglich erfassen.'
    : `Spiele mit Datum in der Vergangenheit werden automatisch hierher verschoben.${canEdit.value ? ' Termin, Halle, Besetzung sowie Anmerkungen und Karten lassen sich nachträglich bearbeiten.' : ''}`)
  const q = ref('')
  const st = ref('alle')
  const gf = ref({ ...GF0 })
  const editId = ref<number | null>(null)
  const reportId = ref<number | null>(null)

  const base = computed(() => store.games.filter(g => g.imported && isPast(g)
    && (!isRef.value || (g.status === 'confirmed' && assignedIds(g).includes(store.me?.id ?? -1)))))
  const competitions = computed(() => [...new Set(base.value.map(g => g.competition))])
  function crew (g: Game) {
    return [
      g.leader_id && `${store.refName(g.leader_id)} (Leiter)`,
      ...g.confirmed_ids.filter(id => id !== g.leader_id).map(id => store.refName(id)),
      g.head_referee_id && `${store.refName(g.head_referee_id)} (Referee)`,
    ].filter((x): x is string => !!x)
  }
  const rows = computed(() => base.value
    .filter(g => gameMatches(g, gf.value) && (st.value === 'alle' || (st.value === 'besetzt') === (g.status === 'confirmed')))
    .filter(g => !q.value.trim() || `${g.title} ${g.venue} ${crew(g).join(' ')}`.toLowerCase().includes(q.value.trim().toLowerCase()))
    .toSorted((a, b) => byDate(b, a)))
</script>
