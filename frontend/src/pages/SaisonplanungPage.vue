<template>
  <PageHeader :title="`Saisonplanung ${seasonLabel()}`">
    Ablauf: Die Schiedsrichter melden in der Saisonabfrage, zu welchen Spielen sie grundsätzlich eingesetzt werden können. Auf Basis der in den Ligen
    vertretenen Mannschaften ({{ LEAGUES.join(', ') }}) und vor allem dieser Rückmeldungen wird eine kostensparende Verteilung vorgeschlagen, die sich
    direkt in der Liste bearbeiten lässt. Danach legen die Admins die Ansetzungen fest. Berücksichtigt werden Zusagen (bevorzugt) und Absagen
    (ausgeschlossen), Lizenzstufe, Terminkollisionen, Sperrzeiten und die Verfügbarkeitsmeldung; der eigene Verein wird nur angesetzt, wenn kein anderer
    Schiedsrichter verfügbar ist. Kosten = Fahrtkosten ({{ eur(KM_RATE) }}/km, Hin- und Rückfahrt); zwei Spiele desselben Vereins am selben Spielort und Tag
    (mind. 2,5 Stunden Abstand) kann ein Schiedsrichter nacheinander übernehmen — die Fahrt wird dann nur einmal berechnet. Spiele außerhalb des
    BW-Fokus fließen erst nach separater Anforderung ein.
  </PageHeader>

  <v-btn
    class="px-0 mb-3"
    color="primary"
    size="small"
    variant="text"
    @click="showTeams = !showTeams"
  >
    {{ showTeams ? 'Mannschaften ausblenden' : `Mannschaften in den Ligen anzeigen (${teamsByLeague.reduce((n, l) => n + l.teams.length, 0)})` }}
  </v-btn>

  <v-card v-if="showTeams" class="pa-4 mb-4">
    <div v-for="l in teamsByLeague" :key="l.league" class="mb-2">
      <div class="text-body-2 font-weight-medium">{{ l.league }} · {{ l.teams.length }} Mannschaften · {{ l.games }} Spiele</div>
      <div class="text-caption text-soft">{{ l.teams.join(', ') }}</div>
    </div>
  </v-card>

  <v-card class="pa-4 mb-4">
    <div class="text-body-2 font-weight-medium mb-3">Abfragezeitraum für die Rückmeldungen der Schiedsrichter</div>

    <div class="gap-row mb-2">
      <v-text-field v-model="period.from" label="von" style="max-width: 190px" type="date" />
      <v-text-field v-model="period.to" label="bis" style="max-width: 190px" type="date" />
      <v-btn color="primary" :disabled="!periodChanged" variant="outlined" @click="savePeriod">Zeitraum speichern</v-btn>
      <v-chip :color="{ offen: 'success', vor: 'warning', beendet: 'error' }[store.abfrage.state]">{{ { offen: 'Läuft', vor: 'Noch nicht gestartet', beendet: 'Beendet' }[store.abfrage.state] }}</v-chip>
    </div>

    <div class="text-body-2 text-soft">{{ store.abfrage.text }}</div>
    <div class="text-body-2">Rückmeldungen: {{ yes + no }} von {{ possible }} möglichen ({{ yes }} zugesagt, {{ no }} abgesagt{{ closed ? `, ${possible - yes - no} ohne Rückmeldung` : '' }})</div>
  </v-card>

  <div class="gap-row mb-4" style="gap: 12px">
    <StatTile label="Ligaspiele" :value="plan.rows.length" />
    <StatTile label="Gesamtkosten" :value="eur(plan.total)" />
    <StatTile label="Ø je Spiel" :value="eur(plan.rows.length > 0 ? plan.total / plan.rows.length : 0)" />
    <StatTile label="Ersparnis*" :value="`${eur(saving)}${base.baseline > 0 ? ` (${Math.round((saving / base.baseline) * 100)} %)` : ''}`" />
    <StatTile label="Manuell angepasst" :value="editedCount" />
  </div>

  <v-card class="pa-4 mb-4">
    <div class="gap-row mb-3" style="gap: 24px">
      <div class="text-body-2" style="min-width: 360px">
        Max. Einsätze je Schiedsrichter: <strong>{{ cap }}</strong> <span class="text-soft">(Minimum ohne Lücken: {{ autoCap }})</span>

        <v-slider
          color="primary"
          density="compact"
          hide-details
          :max="maxCap"
          :min="minCap"
          :model-value="cap"
          :step="1"
          @update:model-value="v => (capInput = v)"
        />
      </div>

      <v-checkbox v-model="minOne" label="Jeder verfügbar gemeldete Schiedsrichter erhält mind. 1 Einsatz" />

      <v-btn
        v-if="editedCount > 0"
        color="error"
        size="small"
        variant="outlined"
        @click="edits = {}"
      >Vorschlag neu berechnen (Änderungen verwerfen)</v-btn>
    </div>

    <div v-for="r in store.referees" :key="r.id" class="gap-row py-1">
      <div class="text-body-2" :class="{ 'text-soft': !r.available }" style="width: 140px">{{ r.name }}</div>

      <template v-if="r.available || stat(r.id).games > 0">
        <v-progress-linear
          bg-color="surface-variant"
          :color="stat(r.id).games > cap ? 'error' : 'primary'"
          height="8"
          :model-value="(stat(r.id).games / cap) * 100"
          rounded
          style="flex: 1; min-width: 80px"
        />

        <div class="font-mono text-caption" :class="stat(r.id).games > cap ? 'text-error' : 'text-soft'" style="width: 210px">{{ stat(r.id).games }} Einsätze · {{ Math.round(stat(r.id).km) }} km · {{ eur(stat(r.id).cost) }}</div>
        <div class="text-caption text-soft" style="width: 150px">{{ respCount(r.id).yes }} zugesagt · {{ respCount(r.id).no }} abgesagt</div>
      </template>

      <v-chip v-else color="error">nicht verfügbar gemeldet — nicht eingeplant</v-chip>
    </div>
  </v-card>

  <GameFilter v-model="gf" class="mb-2" />
  <div v-if="plan.missing > 0" class="text-body-2 text-error mb-1">{{ plan.missing }} Plätze sind unbesetzt — in der Liste ergänzen oder die Obergrenze erhöhen.</div>
  <div v-if="overCap.length > 0" class="text-body-2 text-error mb-1">Obergrenze überschritten: {{ overCap.map(p => `${p.ref.name} (${p.games})`).join(', ') }}</div>

  <v-card>
    <v-table class="plan-table" density="comfortable">
      <thead><tr><th style="width: 100px">Termin</th><th>Spiel</th><th style="width: 240px">Schiedsrichter 1</th><th style="width: 240px">Schiedsrichter 2</th><th class="text-right" style="width: 90px">Kosten</th></tr></thead>

      <tbody>
        <tr v-for="row in plan.rows.filter(r => gameMatches(r.game, gf))" :key="row.game.id" :class="{ edited: row.edited }">
          <td class="font-mono text-caption text-soft">{{ dayLabel(row.game.date) }}<br>{{ timeLabel(row.game.time) }}</td>

          <td>
            <div class="text-body-2 font-weight-medium">{{ row.game.title }}</div>
            <div class="text-caption text-soft">{{ row.game.venue }}</div>
            <div v-if="row.locked" class="text-caption text-success">bestätigt — Änderung unter „Abfragen (Ansetzung)“</div>
            <div v-if="row.edited" class="text-caption text-amber">manuell angepasst · <a class="text-primary cursor-pointer" @click="resetRow(row.game.id)">zurücksetzen</a></div>
            <div v-if="row.doubles.length > 0" class="text-caption text-warning">Doppeleinsatz mit gemeinsamer Fahrt: {{ row.doubles.join(', ') }}</div>
            <div v-for="(it, i) in rowIssues(row, plan.rows, answerObj, closed)" :key="i" class="text-caption" :class="{ error: 'text-error', warning: 'text-amber', muted: 'text-soft' }[it.tone]">{{ it.t }}</div>

            <a class="text-caption text-primary cursor-pointer" @click="openResp[row.game.id] = !openResp[row.game.id]">
              {{ openResp[row.game.id] ? 'Rückmeldungen ausblenden' : `Alle Rückmeldungen anzeigen (${responders(row.game).filter(r => answerObj(row.game.id, r.id)).length} von ${responders(row.game).length})` }}
            </a>

            <div v-if="openResp[row.game.id]" class="pl-2 mt-1" style="border-left: 2px solid var(--line)">
              <div v-for="r in responders(row.game)" :key="r.id" class="text-caption" :class="answerClass(row.game.id, r.id)">
                {{ r.name }} — {{ answerText(row.game.id, r.id) }}<span v-if="answerObj(row.game.id, r.id)?.comment" class="font-italic text-high-emphasis"> „{{ answerObj(row.game.id, r.id)?.comment }}“</span>
              </div>
            </div>
          </td>

          <td v-for="slot in [0, 1]" :key="slot">
            <template v-if="row.locked || readOnly">
              <div v-if="row.refs[slot]" class="text-body-2">{{ row.refs[slot]!.name }}</div>
              <v-chip v-else color="error">unbesetzt</v-chip>
            </template>

            <v-select
              v-else
              :item-props="(r: Referee) => ({ disabled: r.id === row.refs[1 - slot]?.id })"
              :item-title="(r: Referee) => optionLabel(r, row.game)"
              item-value="id"
              :items="[{ id: 0, name: '— offen —' } as Referee, ...store.referees]"
              :model-value="row.refs[slot]?.id ?? 0"
              @update:model-value="(id: number) => setSlot(row, slot, id)"
            />

            <div v-if="row.refs[slot]" class="text-caption text-soft">{{ row.refs[slot]!.license }}{{ row.leader?.id === row.refs[slot]!.id ? ' · Leiter' : '' }}</div>
          </td>

          <td class="font-mono text-right">{{ eur(row.cost) }}</td>
        </tr>
      </tbody>
    </v-table>
  </v-card>

  <div class="text-caption text-soft mt-2">
    * gegenüber dem Durchschnitt einer zufälligen, lizenzkonformen Verteilung (ohne bereits bestätigte Spiele). Entfernungen sind aus den Ortsmitten
    geschätzt (Luftlinie × 1,25); die automatische Verteilung ist ein Näherungsverfahren. Der verantwortliche Leiter ergibt sich bei Ligaspielen aus dem höheren Rang.
  </div>

  <div v-if="!readOnly" class="gap-row mt-4">
    <v-btn color="primary" :disabled="toApply.length === 0" :loading="store.busy" @click="apply('festlegen')">Ansetzungen festlegen ({{ fullCount }} Spiele)</v-btn>
    <v-btn color="primary" :disabled="toApply.length === 0" variant="outlined" @click="apply('vorschlag')">Nur als Vorschläge nach „Abfragen (Ansetzung)“</v-btn>
    <span v-if="fullCount < toApply.length" class="text-caption text-soft">Unvollständig besetzte Spiele bleiben „offen“ und werden nicht festgelegt.</span>
  </div>
</template>

<script lang="ts" setup>
  import type { Game, Referee } from '@/types'
  import { computed, reactive, ref, watch } from 'vue'
  import GameFilter from '@/components/GameFilter.vue'
  import PageHeader from '@/components/PageHeader.vue'
  import StatTile from '@/components/StatTile.vue'
  import { dayLabel, eur, seasonLabel, timeLabel } from '@/domain/dates'
  import { buildSeasonGames, evaluateRows, KM_RATE, type PlanRow, planSeason, rowIssues } from '@/domain/planning'
  import { clubConflict, eligible, gameMatches, GF0, isBlocked, LEAGUES, responseOf, teamsOf } from '@/domain/rules'
  import { useAppStore } from '@/stores/app'

  const store = useAppStore()
  const readOnly = computed(() => !store.can('saisonplanung'))
  const closed = computed(() => store.abfrage.state === 'beendet')
  const games = computed(() => buildSeasonGames(store.games))
  const pool = computed(() => store.referees.filter(r => r.available))
  const answerObj = (gameId: number, refId: number) => responseOf(store.responses, gameId, refId)
  const answer = (gameId: number, refId: number) => answerObj(gameId, refId)?.answer ?? null

  // Obergrenze: Minimum ist die gleichmäßige Verteilung, vorgeschlagen wird die kleinste Grenze ohne Lücken
  const minCap = computed(() => Math.max(1, Math.ceil((games.value.length * 2) / Math.max(1, pool.value.length))))
  const maxCap = computed(() => Math.max(minCap.value, games.value.length))
  const autoCap = computed(() => {
    for (let c = minCap.value; c <= maxCap.value; c++) {
      if (planSeason(games.value, store.referees, { cap: c, minOne: true, answer }).missing === 0) {
        return c
      }
    }
    return maxCap.value
  })
  const capInput = ref<number | null>(null)
  watch(autoCap, c => (capInput.value ??= c), { immediate: true })
  const cap = computed(() => Math.min(Math.max(capInput.value ?? autoCap.value, minCap.value), maxCap.value))
  const minOne = ref(true)
  const base = computed(() => planSeason(games.value, store.referees, { cap: cap.value, minOne: minOne.value, answer }))

  // Manuelle Änderungen: Spiel-ID -> gewählte Schiedsrichter-IDs
  const edits = ref<Record<number, number[]>>({})
  const plan = computed(() => evaluateRows(base.value.rows.map(row => {
    const ids = edits.value[row.game.id]
    return row.locked || !ids ? { ...row, edited: false } : { ...row, refs: ids.map(id => store.refById.get(id)!).filter(Boolean), edited: true }
  }), store.referees))
  const stat = (id: number) => plan.value.perRef.find(p => p.ref.id === id)!
  const saving = computed(() => Math.max(0, base.value.baseline - plan.value.openCost))
  const editedCount = computed(() => plan.value.rows.filter(r => r.edited).length)
  const overCap = computed(() => plan.value.perRef.filter(p => p.games > cap.value))
  const toApply = computed(() => plan.value.rows.filter(r => !r.locked))
  const fullCount = computed(() => toApply.value.filter(r => r.refs.length >= 2).length)

  function setSlot (row: PlanRow, slot: number, id: number) {
    const cur = [row.refs[0]?.id ?? 0, row.refs[1]?.id ?? 0]
    cur[slot] = id
    edits.value = { ...edits.value, [row.game.id]: cur.filter(Boolean) }
  }
  function resetRow (id: number) {
    const { [id]: _, ...rest } = edits.value
    edits.value = rest
  }

  // Rückmeldungsstand der Abfrage
  const possiblePairs = computed(() => games.value.flatMap(g => pool.value.filter(r => eligible(r, g)).map(r => answer(g.id, r.id))))
  const possible = computed(() => possiblePairs.value.length)
  const yes = computed(() => possiblePairs.value.filter(a => a === 'yes').length)
  const no = computed(() => possiblePairs.value.filter(a => a === 'no').length)
  function respCount (refId: number) {
    return {
      yes: games.value.filter(g => answer(g.id, refId) === 'yes').length,
      no: games.value.filter(g => answer(g.id, refId) === 'no').length,
    }
  }
  const responders = (g: Game) => store.referees.filter(r => r.available || answerObj(g.id, r.id))
  const ANSWER: Record<string, string> = { yes: 'zugesagt', no: 'abgesagt' }
  const answerText = (gId: number, rId: number) => (ANSWER[answer(gId, rId) ?? ''] ?? (closed.value ? 'keine Rückmeldung' : 'ausstehend'))
  const answerClass = (gId: number, rId: number) => ({ yes: 'text-success', no: 'text-error' } as Record<string, string>)[answer(gId, rId) ?? ''] ?? 'text-soft'

  const showTeams = ref(false)
  const openResp = reactive<Record<number, boolean>>({})
  const gf = ref({ ...GF0 })
  const teamsByLeague = computed(() => LEAGUES.map(league => {
    const gs = games.value.filter(g => g.competition === league)
    return { league, games: gs.length, teams: [...new Set(gs.flatMap(g => teamsOf(g)))].toSorted((a, b) => a.localeCompare(b, 'de')) }
  }).filter(l => l.games > 0))

  function optionLabel (r: Referee, g: Game) {
    if (!r.id) {
      return r.name
    }
    const flags = [
      ANSWER[answer(g.id, r.id) ?? ''],
      !eligible(r, g) && 'Lizenz',
      clubConflict(r, g) && 'Verein',
      isBlocked(r, g) && 'Sperrzeit',
      !r.available && 'nicht verfügbar',
    ].filter(Boolean)
    return flags.length > 0 ? `${r.name} (${flags.join(', ')})` : r.name
  }

  const period = reactive({ from: '', to: '' })
  watch(() => store.state!.settings, s => Object.assign(period, { from: s.abfrage_from ?? '', to: s.abfrage_to ?? '' }), { immediate: true })
  const periodChanged = computed(() => period.from !== (store.state!.settings.abfrage_from ?? '') || period.to !== (store.state!.settings.abfrage_to ?? ''))
  const savePeriod = () => store.act('PUT', '/settings/abfrage', { from: period.from || null, to: period.to || null }, 'Abfragezeitraum gespeichert.')

  async function apply (mode: 'festlegen' | 'vorschlag') {
    const rows = toApply.value.map(r => ({ game_id: r.game.id, referee_ids: r.refs.map(x => x.id) }))
    const ok = await store.act('POST', '/season-plan', { mode, rows }, mode === 'festlegen'
      ? 'Festgelegt — die Schiedsrichter sehen ihre Ansetzungen jetzt in ihren Ansetzungen.'
      : 'Übernommen — in „Abfragen (Ansetzung)“ je Spiel prüfen und bestätigen.')
    if (ok) {
      edits.value = {}
    }
  }
</script>

<style scoped>
.plan-table td { vertical-align: top; padding-top: 10px !important; padding-bottom: 10px !important; }
.edited { background: var(--mint); }
</style>
