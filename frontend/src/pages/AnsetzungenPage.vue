<template>
  <PageHeader title="Abfragen (Ansetzung)">
    <template #actions>
      <span class="font-mono text-caption text-soft">{{ count('match') }} Begegnungen · {{ count('tournament') }} Turnier{{ count('tournament') === 1 ? '' : 'e' }}</span>
      <v-btn v-if="canEdit && !showForm" color="primary" prepend-icon="mdi-plus" @click="showForm = true">Veranstaltung anlegen</v-btn>
    </template>
  </PageHeader>

  <v-card v-if="showForm" class="pa-4 mb-4">
    <v-btn-toggle
      v-model="form.type"
      class="mb-4"
      color="primary"
      density="compact"
      mandatory
      variant="outlined"
    >
      <v-btn value="match">Begegnung</v-btn>
      <v-btn value="tournament">Turnier</v-btn>
    </v-btn-toggle>

    <v-text-field
      v-model="form.title"
      class="mb-3"
      :label="form.type === 'tournament' ? 'Bezeichnung des Turniers' : 'Begegnung (z. B. Team A vs. Team B)'"
      :placeholder="form.type === 'tournament' ? 'Kreismeisterschaft U15' : 'TSV Waldkirch vs. BC Nordstadt'"
    />

    <div class="gap-row mb-3">
      <v-text-field v-model="form.date" label="Datum" style="max-width: 200px" type="date" />

      <v-text-field
        v-if="form.type === 'match'"
        v-model="form.time"
        label="Uhrzeit"
        style="max-width: 160px"
        type="time"
      />

      <v-text-field v-model="form.venue" label="Austragungsort" placeholder="Sporthalle Waldkirch" />
    </div>

    <div class="gap-row mb-2">
      <v-select v-model="form.competition" :items="COMPETITIONS" label="Wettbewerb" style="max-width: 280px" />

      <v-text-field
        :disabled="league"
        label="Anzahl Schiedsrichter"
        min="1"
        :model-value="league ? 2 : form.needed_count"
        style="max-width: 200px"
        type="number"
        @update:model-value="v => (form.needed_count = Number(v))"
      />
    </div>

    <div class="text-caption text-soft mb-4">
      Erforderliche Lizenz: <strong>{{ requiredLevelLabel(requiredLevel(form.competition)) }}</strong>
      <template v-if="form.type === 'tournament'"> · wird als ein Termin für den gesamten Turniertag angelegt, zusätzlich wird 1 Referee (Oberschiedsrichter) angesetzt.</template>
      <template v-if="league"> · Ligaspiel: 2 Schiedsrichter, der ranghöhere wird verantwortlicher Leiter.</template>
    </div>

    <div class="gap-row">
      <v-btn color="primary" :loading="store.busy" @click="create">Anlegen</v-btn>
      <v-btn variant="outlined" @click="showForm = false">Abbrechen</v-btn>
    </div>
  </v-card>

  <GameFilter v-model="gf" class="mb-3">
    <span v-if="shown.length !== store.activeGames.length" class="text-caption text-soft">{{ shown.length }} von {{ store.activeGames.length }} Spielen</span>
  </GameFilter>

  <v-card v-if="shown.length === 0" class="pa-4 text-body-2 text-soft">Keine Spiele für diesen Filter.</v-card>

  <div class="list-gap">
    <GameSummary v-for="g in shown" :key="g.id" :game="g" :to="`/ansetzungen/${g.id}`">
      <div class="text-soft">{{ g.type === 'tournament' ? 'Mind. ' : '' }}{{ g.needed_count }} SR{{ g.type === 'tournament' ? ' + 1 Referee' : '' }} benötigt</div>
      <div v-if="g.leader_id || g.head_referee_id">{{ [g.leader_id && `Leiter: ${store.refName(g.leader_id)}`, g.head_referee_id && `Referee: ${store.refName(g.head_referee_id)}`].filter(Boolean).join(' · ') }}</div>
      <div v-if="g.requested_by" class="text-soft">Auf Anforderung von {{ g.requested_by }}</div>
      <div v-if="g.status === 'open' && g.planned_ids.length > 0" class="text-amber">Gespeicherte Auswahl (unvollständig): {{ g.planned_ids.map(id => store.refName(id)).join(', ') }}</div>
      <div v-if="g.reopened_note && g.status !== 'confirmed'" class="text-amber">{{ g.reopened_note }}</div>

      <template v-if="g.schedule_change">
        <div class="text-amber">Termin geändert: {{ g.schedule_change.old }} → {{ g.schedule_change.new }}</div>
        <div class="text-amber">Neubestätigung: {{ g.reconfirmed_ids.length }} von {{ assignedIds(g).length }}</div>
      </template>

      <div v-if="canEdit && g.status !== 'confirmed'" class="text-soft">{{ responseSummary(g) }}</div>

      <template #append>
        <v-chip v-if="g.status === 'open'" color="error">Offen</v-chip>
        <v-chip v-else-if="g.status === 'proposed'" color="warning">Vorschlag bereit</v-chip>
        <v-chip v-else color="success">{{ g.type === 'tournament' ? `${assignedIds(g).length} besetzt (mind. ${g.needed_count + 1})` : `${g.confirmed_ids.length}/${g.needed_count} besetzt` }}</v-chip>
        <v-icon color="grey" icon="mdi-chevron-right" />
      </template>
    </GameSummary>
  </div>
</template>

<script lang="ts" setup>
  import type { Game, GameType } from '@/types'
  import { computed, reactive, ref } from 'vue'
  import GameFilter from '@/components/GameFilter.vue'
  import GameSummary from '@/components/GameSummary.vue'
  import PageHeader from '@/components/PageHeader.vue'
  import { assignedIds, COMPETITIONS, eligible, gameMatches, GF0, isLeague, requiredLevel, requiredLevelLabel, responseOf } from '@/domain/rules'
  import { notifyError } from '@/notify'
  import { useAppStore } from '@/stores/app'

  const store = useAppStore()
  const canEdit = computed(() => store.can('ansetzen'))
  const showForm = ref(false)
  const gf = ref({ ...GF0 })
  const shown = computed(() => store.activeGames.filter(g => gameMatches(g, gf.value)))
  const count = (t: GameType) => store.activeGames.filter(g => g.type === t).length

  const empty = () => ({ type: 'match' as GameType, title: '', date: '', time: '', venue: '', competition: COMPETITIONS[0]!, needed_count: 1 })
  const form = reactive(empty())
  const league = computed(() => isLeague(form))

  async function create () {
    if (!form.title || !form.date || !form.venue || (form.type === 'match' && !form.time)) {
      notifyError('Bitte alle Felder ausfüllen.')
      return
    }
    if (await store.act('POST', '/games', { ...form, time: form.time || null }, 'Veranstaltung angelegt.')) {
      Object.assign(form, empty())
      showForm.value = false
    }
  }

  function responseSummary (g: Game) {
    const list = store.referees.filter(r => eligible(r, g))
    const answers = list.map(r => responseOf(store.responses, g.id, r.id))
    const yes = answers.filter(a => a?.answer === 'yes').length
    const no = answers.filter(a => a?.answer === 'no').length
    const comments = answers.filter(a => a?.comment).length
    const closed = store.abfrage.state === 'beendet'
    return `Rückmeldungen: ${yes} zugesagt · ${no} abgesagt · ${list.length - yes - no} ${closed ? 'keine Rückmeldung' : 'ausstehend'}${comments ? ` · ${comments} Kommentar${comments === 1 ? '' : 'e'}` : ''}`
  }
</script>
