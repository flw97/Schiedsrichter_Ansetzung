<template>
  <div v-if="game">
    <v-btn class="mb-3 px-0" prepend-icon="mdi-chevron-left" to="/ansetzungen" variant="text">Zurück zur Übersicht</v-btn>
    <h1 class="font-display text-h5">{{ game.title }}</h1>
    <div class="text-body-2 text-soft">{{ gameLabel(game) }} · {{ game.venue }}</div>

    <div class="text-caption text-soft mb-5">
      {{ game.competition }} · benötigt {{ tournament ? 'mindestens ' : '' }}<strong>{{ game.needed_count }}</strong> Schiedsrichter{{ tournament ? ' (mehr möglich) + 1 Referee (Oberschiedsrichter)' : '' }}{{ league ? ' (einer als verantwortlicher Leiter)' : '' }}
      · mind. <strong>{{ requiredLevelLabel(need) }}</strong>{{ tournament ? ' · gilt für den gesamten Turniertag' : '' }}
    </div>

    <div class="d-flex flex-wrap ga-5 align-start">
      <v-card class="pa-4" width="260">
        <div class="d-flex align-center ga-2 mb-3 text-body-2 font-weight-medium"><v-icon color="primary" icon="mdi-gauge" size="18" /> Gewichtung</div>

        <div v-for="w in WEIGHTS" :key="w.key" class="mb-2">
          <div class="d-flex justify-space-between text-body-2 text-soft"><span>{{ w.label }}</span><span class="font-mono" :style="{ color: w.color }">{{ Math.round(weights[w.key] * 100) }}%</span></div>

          <v-slider
            v-model="weights[w.key]"
            :color="w.color"
            density="compact"
            hide-details
            :max="1"
            :min="0"
            :step="0.01"
          />
        </div>

        <div class="d-flex justify-center mt-2"><ScoreCompass :score="ranked[0]?.score ?? 0" :weights="weights" /></div>
      </v-card>

      <div class="flex-grow-1" style="min-width: 300px">
        <template v-if="tournament">
          <div class="text-caption font-weight-bold mb-2" :class="headId ? 'text-success' : 'text-warning'">Referee (Oberschiedsrichter) — 1 Person</div>

          <v-radio-group
            v-model="headId"
            class="mb-4"
            :disabled="decided"
            hide-details
            @update:model-value="selected = selected.filter(id => id !== headId)"
          >
            <v-card
              v-for="r in ranked"
              :key="r.id"
              class="px-3 mb-2"
              :class="{ 'opacity-50': !r.available }"
              :style="headId === r.id ? 'border-color: rgb(var(--v-theme-accent))' : ''"
            >
              <v-radio color="accent" :disabled="locked(r, headId === r.id)" :value="r.id">
                <template #label>
                  <span class="text-body-2"><strong>{{ r.name }}</strong> · {{ r.license }}</span>
                  <span class="ml-2 gap-row"><RefChips :item="r" /></span>
                </template>
              </v-radio>
            </v-card>
          </v-radio-group>
        </template>

        <div class="text-caption font-weight-bold mb-2" :class="canConfirm ? 'text-success' : 'text-warning'">
          {{ tournament ? `${selected.length} ausgewählt (mindestens ${game.needed_count}, weitere möglich)` : `${selected.length} von ${game.needed_count} benötigten Schiedsrichtern ausgewählt` }}
        </div>

        <div v-if="league" class="text-caption text-soft mb-2">Der ranghöhere Schiedsrichter (höhere Lizenzstufe, bei Gleichstand mehr Einsätze in der Saison) wird automatisch zum verantwortlichen Leiter ernannt.</div>
        <div v-if="ranked.some(r => r.conflict)" class="text-caption text-soft mb-2">Schiedsrichter dürfen nicht ihren eigenen Verein leiten — außer, es stehen nicht genug andere zur Verfügung{{ ownClubAllowed ? ' (hier der Fall)' : '' }}.</div>

        <v-card
          v-for="(r, i) in pool"
          :key="r.id"
          class="mb-2"
          :class="{ 'opacity-50': !r.available }"
          :style="selected.includes(r.id) ? 'border-color: rgb(var(--v-theme-primary))' : ''"
        >
          <div class="d-flex align-center ga-3 pa-3">
            <v-checkbox-btn v-model="selected" :disabled="decided || locked(r, selected.includes(r.id))" :value="r.id" />
            <v-avatar class="font-mono text-caption" :color="selected.includes(r.id) ? 'primary' : 'surface-variant'" size="32">{{ r.score }}</v-avatar>

            <div class="flex-grow-1">
              <div class="gap-row text-body-2 font-weight-bold">
                {{ r.name }}
                <v-chip v-if="i < game.needed_count" color="primary">Top-Vorschlag</v-chip>
                <v-chip v-if="leader?.id === r.id" color="accent">Verantwortlicher Leiter</v-chip>
                <RefChips :item="r" />
              </div>

              <div class="text-caption text-soft">
                {{ [r.license, r.club, r.persp_international && 'Perspektiv-Team International', r.persp_national && 'Perspektiv-Team National', `${r.km} km`, `${r.monthly_load ?? 0} Einsätze/Monat`].filter(Boolean).join(' · ') }}
                <span v-if="!r.available" class="text-error"> · nicht verfügbar</span>
              </div>

              <div v-if="canEdit" class="text-caption mt-1">
                <span v-if="!r.response" :class="closed ? 'text-error' : 'text-soft'">{{ closed ? 'Keine Rückmeldung' : 'Rückmeldung ausstehend' }}</span>
                <div v-if="r.response?.comment" class="font-italic">„{{ r.response.comment }}“</div>
              </div>
            </div>
          </div>
        </v-card>

        <template v-if="ineligible.length > 0">
          <div class="text-caption text-soft mt-4 mb-2">Nicht lizenziert für diese Veranstaltung:</div>

          <v-sheet
            v-for="r in ineligible"
            :key="r.id"
            class="px-4 py-2 mb-2 text-body-2 text-soft opacity-70"
            color="surface-variant"
            rounded="lg"
          >
            {{ r.name }} · {{ r.license }} (benötigt: {{ requiredLevelLabel(need) }})
          </v-sheet>
        </template>

        <div v-if="!canEdit" class="text-body-2 text-soft mt-4">Nur Lesezugriff — mit deiner Rolle können Ansetzungen nicht geändert werden.</div>

        <div v-else-if="!decided" class="mt-4">
          <v-btn
            :color="'primary'"
            :loading="store.busy"
            prepend-icon="mdi-check"
            :variant="canConfirm ? 'flat' : 'outlined'"
            @click="submit"
          >
            {{ canConfirm ? 'Auswahl bestätigen' : 'Auswahl speichern' }}
          </v-btn>

          <div v-if="!canConfirm" class="text-caption text-soft mt-2">
            Noch nicht vollständig besetzt ({{ selected.length }} von {{ game.needed_count }}{{ tournament ? `, Referee ${headId ? 'gewählt' : 'fehlt'}` : '' }}).
            Speichern legt die Auswahl ab, ohne die Schiedsrichter zu benachrichtigen; bestätigt wird bei vollständiger Besetzung.
          </div>
        </div>

        <div v-else class="mt-4">
          <div class="text-body-2 text-success mb-2">✓ Ansetzung bestätigt — alle eingesetzten Schiedsrichter wurden per Push benachrichtigt.</div>

          <ConfirmAction
            confirm-label="Ja, rückgängig machen"
            label="Ansetzung rückgängig machen"
            text="Ansetzung zurücknehmen? Die Schiedsrichter werden benachrichtigt, das Spiel erscheint wieder in der Abfrage."
            variant="outlined"
            @confirm="store.act('POST', `/games/${game.id}/undo`)"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
  import type { Referee } from '@/types'
  import { computed, defineComponent, h, reactive, ref, watch } from 'vue'
  import { useRoute } from 'vue-router'
  import { VChip } from 'vuetify/components'
  import ConfirmAction from '@/components/ConfirmAction.vue'
  import ScoreCompass from '@/components/ScoreCompass.vue'
  import { gameLabel } from '@/domain/dates'
  import { distKm } from '@/domain/geo'
  import { clubConflict, isBlocked, isLeague, licenseRank, pickLeader, requiredLevel, requiredLevelLabel, responseOf, weightedScore, type Weights } from '@/domain/rules'
  import { useAppStore } from '@/stores/app'

  const store = useAppStore()
  const route = useRoute()
  const game = computed(() => store.games.find(g => g.id === Number(route.params.id)))
  const canEdit = computed(() => store.can('ansetzen'))
  const closed = computed(() => store.abfrage.state === 'beendet')
  const tournament = computed(() => game.value?.type === 'tournament')
  const league = computed(() => !!game.value && isLeague(game.value))
  const need = computed(() => (game.value ? requiredLevel(game.value.competition) : 0))

  const WEIGHTS = [
    { key: 'distance', label: 'Entfernung', color: '#274472' },
    { key: 'avail', label: 'Verfügbarkeit', color: '#5C8A72' },
    { key: 'fair', label: 'Faire Verteilung', color: '#D98F3E' },
  ] as const
  const weights = reactive<Weights>({ distance: 0.4, avail: 0.35, fair: 0.25 })

  const scored = computed(() => {
    const g = game.value!
    return store.referees.map(r => {
      const km = distKm(r, g.venue)
      const response = responseOf(store.responses, g.id, r.id)
      return {
        ...r,
        km,
        response,
        score: weightedScore(r, km, weights),
        conflict: clubConflict(r, g),
        blocked: !!isBlocked(r, g),
        declined: response?.answer === 'no',
        zusage: response?.answer === 'yes',
        eligible: licenseRank(r.license) >= need.value,
      }
    })
  })
  type Scored = (typeof scored.value)[number]
  const usable = (r: Scored) => !!r.available && !r.blocked && !r.declined
  // Reihenfolge: Verfügbare ohne Vereinsbezug, dann Verfügbare aus dem eigenen Verein, zuletzt nicht Verfügbare
  const tier = (r: Scored) => (r.conflict ? 1 : 0) + (usable(r) ? 0 : 2)
  const ranked = computed(() => scored.value.filter(r => r.eligible).toSorted((a, b) => tier(a) - tier(b) || Number(b.zusage) - Number(a.zusage) || b.score - a.score))
  const ineligible = computed(() => scored.value.filter(r => !r.eligible))
  const ownClubAllowed = computed(() => ranked.value.filter(r => !r.conflict && usable(r)).length < game.value!.needed_count + (tournament.value ? 1 : 0))

  const headId = ref<number | null>(null)
  const selected = ref<number[]>([])
  const pool = computed(() => ranked.value.filter(r => r.id !== headId.value))
  const decided = computed(() => !canEdit.value || game.value?.status === 'confirmed')
  const chosen = computed(() => ranked.value.filter(r => selected.value.includes(r.id)))
  const leader = computed(() => (league.value ? pickLeader(chosen.value) : null))
  const canConfirm = computed(() => (tournament.value ? selected.value.length >= game.value!.needed_count && !!headId.value : selected.value.length === game.value!.needed_count))
  // Eigener Verein nur als Notlösung; Sperrzeit oder Absage schließen aus (bereits Gewählte bleiben abwählbar)
  const locked = (r: Scored, isSel: boolean) => !isSel && ((r.conflict && !ownClubAllowed.value) || r.blocked || r.declined)

  // Vorbelegung: bestätigte bzw. gespeicherte Auswahl, sonst Turnier-Referee = höchste Lizenz und die besten Vorschläge
  watch(() => game.value?.id, () => {
    const g = game.value
    if (!g) {
      return
    }
    if (g.status === 'confirmed') {
      headId.value = g.head_referee_id
      selected.value = [...g.confirmed_ids]
    } else if (g.planned_ids.length > 0 || g.planned_head_referee_id) {
      headId.value = g.planned_head_referee_id
      selected.value = [...g.planned_ids]
    } else {
      headId.value = tournament.value ? (ranked.value.toSorted((a, b) => Number(usable(b)) - Number(usable(a)) || licenseRank(b.license) - licenseRank(a.license) || b.score - a.score)[0]?.id ?? null) : null
      selected.value = pool.value.slice(0, g.needed_count).map(r => r.id)
    }
  }, { immediate: true })

  async function submit () {
    const body = { referee_ids: selected.value, head_referee_id: headId.value }
    await (canConfirm.value
      ? store.act('POST', `/games/${game.value!.id}/confirm`, body)
      : store.act('POST', `/games/${game.value!.id}/selection`, body, 'Auswahl gespeichert — noch nicht vollständig, daher keine Benachrichtigung.'))
  }

  // Hinweis-Chips je Person: Verein, Sperrzeit, Rückmeldung
  const RefChips = defineComponent({
    props: { item: { type: Object as () => Scored & Referee, required: true } },
    setup: p => () => [
      p.item.conflict && h(VChip, { color: 'error' }, () => 'Eigener Verein'),
      p.item.blocked && h(VChip, { color: 'error' }, () => 'Sperrzeit'),
      canEdit.value && p.item.response && h(VChip, { color: p.item.zusage ? 'success' : 'error' }, () => (p.item.zusage ? 'Zugesagt' : 'Abgesagt')),
    ],
  })
</script>
