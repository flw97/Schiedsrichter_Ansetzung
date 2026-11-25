<template>
  <div class="max-w-md">
    <PageHeader title="Abfrage">
      Teile mit, zu welchen Spielen du grundsätzlich eingesetzt werden kannst. Die Admins legen die Ansetzungen danach fest; sie erscheinen dann in deinen Ansetzungen. Kommentare sehen alle Admins.
    </PageHeader>

    <v-alert class="mb-3" :color="{ offen: 'primary', vor: 'warning', beendet: 'error' }[store.abfrage.state]" density="compact" variant="tonal">
      {{ store.abfrage.text }}{{ { offen: '', vor: ' — Antworten sind ab dann möglich.', beendet: ' — Antworten sind nicht mehr möglich.' }[store.abfrage.state] }}
    </v-alert>

    <GameFilter v-model="gf" class="mb-3" />

    <v-btn
      v-if="open && unanswered.length > 1"
      class="mb-3"
      color="primary"
      variant="outlined"
      @click="allYes"
    >Alle {{ unanswered.length }} offenen Spiele als „kann“ melden</v-btn>

    <div v-if="hiddenBlocked > 0" class="text-caption text-soft mb-2">{{ hiddenBlocked }} Spiel{{ hiddenBlocked === 1 ? ' wird' : 'e werden' }} wegen deiner Sperrzeiten nicht abgefragt.</div>
    <v-card v-if="requests.length === 0" class="pa-4 text-body-2 text-soft">Aktuell keine offenen Anfragen.</v-card>

    <div class="list-gap">
      <v-card v-for="g in requests" :key="g.id" class="pa-4">
        <div class="text-body-2 font-weight-medium">{{ g.title }}{{ g.type === 'tournament' ? ' (Turnier, ganzer Tag)' : '' }}</div>
        <v-chip v-if="g.reopened_note" class="my-1" color="warning">Erneut angefragt · {{ g.reopened_note }}</v-chip>
        <div v-if="clubConflict(me, g)" class="text-caption text-soft">Eigener Verein — Ansetzung nur, wenn kein anderer Schiedsrichter verfügbar ist.</div>
        <div class="text-body-2 text-soft mb-3">{{ gameLabel(g) }} · {{ g.venue }} · {{ g.competition }}</div>

        <template v-if="mine(g)">
          <div class="gap-row">
            <v-chip :color="mine(g)!.answer === 'yes' ? 'success' : 'error'">{{ mine(g)!.answer === 'yes' ? 'Du kannst' : 'Du kannst nicht' }}</v-chip>

            <v-btn
              v-if="open"
              color="primary"
              size="small"
              variant="text"
              @click="respond(g.id, null)"
            >Antwort ändern</v-btn>
          </div>

          <div v-if="mine(g)!.comment" class="text-caption text-soft font-italic mt-1">„{{ mine(g)!.comment }}“</div>
        </template>

        <div v-else-if="store.abfrage.state === 'beendet'" class="gap-row"><v-chip color="error">Keine Rückmeldung</v-chip><span class="text-caption text-soft">Die Abfrage ist beendet.</span></div>

        <template v-else>
          <v-text-field
            v-model="comments[g.id]"
            class="mb-2"
            :disabled="!open"
            maxlength="500"
            placeholder="Kommentar für die Admins (optional)"
          />

          <div class="d-flex ga-2">
            <v-btn class="flex-grow-1" color="primary" :disabled="!open" @click="respond(g.id, 'yes')">Ich kann</v-btn>

            <v-btn
              class="flex-grow-1"
              color="error"
              :disabled="!open"
              variant="outlined"
              @click="respond(g.id, 'no')"
            >Ich kann nicht</v-btn>
          </div>
        </template>
      </v-card>
    </div>
  </div>
</template>

<script lang="ts" setup>
  import type { Game } from '@/types'
  import { computed, reactive, ref } from 'vue'
  import GameFilter from '@/components/GameFilter.vue'
  import PageHeader from '@/components/PageHeader.vue'
  import { byDate, gameLabel, isPast } from '@/domain/dates'
  import { buildSeasonGames } from '@/domain/planning'
  import { clubConflict, eligible, gameMatches, GF0, isBlocked, responseOf } from '@/domain/rules'
  import { useAppStore } from '@/stores/app'

  const store = useAppStore()
  const me = computed(() => store.me!)
  const open = computed(() => store.abfrage.state === 'offen')
  const gf = ref({ ...GF0 })
  const comments = reactive<Record<number, string>>({})

  // Abgefragt werden alle noch nicht bestätigten Ligaspiele der Saison (inkl. bereitgestellter NuLiga-Spiele) und übrigen Veranstaltungen, für die ich lizenziert bin
  const askable = computed(() => {
    const league = buildSeasonGames(store.games)
    const others = store.activeGames.filter(g => !league.includes(g))
    return [...league, ...others].filter(g => g.status !== 'confirmed' && !isPast(g) && eligible(me.value, g)).toSorted(byDate)
  })
  const requests = computed(() => askable.value.filter(g => !isBlocked(me.value, g) && gameMatches(g, gf.value)))
  const hiddenBlocked = computed(() => askable.value.filter(g => isBlocked(me.value, g)).length)
  const mine = (g: Game) => responseOf(store.responses, g.id, me.value.id)
  const unanswered = computed(() => requests.value.filter(g => !mine(g)))

  const respond = (id: number, answer: 'yes' | 'no' | null) => store.act('PUT', `/games/${id}/response`, { answer, comment: comments[id] || null })
  async function allYes () {
    for (const g of unanswered.value) {
      await respond(g.id, 'yes')
    }
  }
</script>
