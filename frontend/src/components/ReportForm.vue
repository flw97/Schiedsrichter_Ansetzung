<template>
  <v-sheet class="pa-3 mt-3" color="surface-variant" rounded="lg">
    <v-textarea
      v-model="note"
      class="mb-3"
      label="Anmerkungen zum Spiel"
      maxlength="1000"
      placeholder="z. B. Besonderheiten, Verzögerungen, Auffälligkeiten"
      rows="3"
    />

    <div class="text-caption text-soft mb-2">Karten</div>
    <div v-if="cards.length === 0" class="text-body-2 text-soft mb-2">Keine Karten vergeben.</div>

    <div v-for="(k, i) in cards" :key="i" class="gap-row mb-2">
      <span class="card-swatch" :style="{ background: CARD_COLORS[k.color], width: '12px', height: '17px' }" :title="CARD_LABEL[k.color]" />

      <v-select
        v-model="k.team"
        aria-label="Mannschaft"
        bg-color="surface"
        :items="teams"
        style="max-width: 200px"
      />

      <v-select
        v-model="k.given_by"
        aria-label="Karte vergeben von"
        bg-color="surface"
        :items="crew"
        placeholder="vergeben von"
        style="max-width: 200px"
      />

      <v-text-field
        v-model="k.person"
        bg-color="surface"
        maxlength="100"
        placeholder="Spieler/in (optional)"
        style="max-width: 170px"
      />

      <v-text-field
        v-model="k.reason"
        bg-color="surface"
        maxlength="100"
        placeholder="Grund (optional)"
        style="min-width: 140px"
      />

      <v-btn color="error" size="small" variant="text" @click="cards.splice(i, 1)">Entfernen</v-btn>
    </div>

    <div class="gap-row mb-3">
      <v-btn
        v-for="c in ['gelb', 'rot', 'schwarz'] as const"
        :key="c"
        color="surface"
        size="small"
        variant="flat"
        @click="add(c)"
      >
        <span class="card-swatch mr-2" :style="{ background: CARD_COLORS[c] }" /> + {{ CARD_LABEL[c] }}e Karte
      </v-btn>
    </div>

    <div class="gap-row">
      <v-btn color="primary" :loading="store.busy" size="small" @click="save">Speichern</v-btn>
      <v-btn size="small" variant="outlined" @click="emit('close')">Abbrechen</v-btn>
    </div>
  </v-sheet>
</template>

<script lang="ts" setup>
  import type { Card, CardColor, Game } from '@/types'
  import { ref } from 'vue'
  import { assignedIds, CARD_COLORS, CARD_LABEL, teamsOf } from '@/domain/rules'
  import { useAppStore } from '@/stores/app'

  const props = defineProps<{ game: Game }>()
  const emit = defineEmits<{ close: [] }>()
  const store = useAppStore()

  const teams = teamsOf(props.game).length > 0 ? teamsOf(props.game) : ['Heim', 'Gast']
  // Beteiligte Schiedsrichter; Standard: ich selbst, sonst der erste
  const crew = assignedIds(props.game).map(id => store.refName(id))
  const defaultGiver = crew.includes(store.me?.name ?? '') ? store.me!.name : crew[0] ?? ''
  const note = ref(props.game.report?.note ?? '')
  const cards = ref<Card[]>(structuredClone(props.game.report?.cards ?? []))

  const add = (color: CardColor) => cards.value.push({ color, team: teams[0]!, person: '', reason: '', given_by: defaultGiver })

  async function save () {
    if (await store.act('POST', `/games/${props.game.id}/report`, { note: note.value, cards: cards.value }, 'Gespeichert.')) {
      emit('close')
    }
  }
</script>
