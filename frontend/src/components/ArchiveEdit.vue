<template>
  <v-sheet class="pa-3 mt-2" color="surface-variant" rounded="lg">
    <div class="gap-row mb-3">
      <v-text-field
        v-model="date"
        bg-color="surface"
        label="Datum"
        style="max-width: 190px"
        type="date"
      />

      <v-text-field
        v-model="time"
        bg-color="surface"
        label="Uhrzeit"
        style="max-width: 150px"
        type="time"
      />

      <v-text-field v-model="venue" bg-color="surface" label="Halle" />
    </div>

    <div class="text-caption text-soft mb-2">Besetzung (tatsächlich eingesetzte Schiedsrichter)</div>

    <v-chip-group
      v-model="crew"
      class="mb-2"
      color="primary"
      column
      multiple
    >
      <v-chip
        v-for="r in store.referees"
        :key="r.id"
        filter
        :value="r.id"
        variant="outlined"
      >{{ r.name }}</v-chip>
    </v-chip-group>

    <v-select
      v-if="game.type === 'tournament'"
      v-model="head"
      bg-color="surface"
      class="mb-3"
      clearable
      item-title="name"
      item-value="id"
      :items="store.referees"
      label="Referee (Oberschiedsrichter)"
      style="max-width: 300px"
    />

    <div v-if="isLeague(game)" class="text-caption text-soft">Der verantwortliche Leiter ergibt sich weiterhin aus dem höheren Rang.</div>
    <div class="text-caption text-soft mb-3">Liegt das neue Datum in der Zukunft, wandert das Spiel automatisch zurück in die aktuellen Listen.</div>

    <div class="gap-row">
      <v-btn color="primary" size="small" @click="save">Speichern</v-btn>
      <v-btn size="small" variant="outlined" @click="emit('close')">Abbrechen</v-btn>
    </div>
  </v-sheet>
</template>

<script lang="ts" setup>
  import type { Game } from '@/types'
  import { ref } from 'vue'
  import { isLeague } from '@/domain/rules'
  import { useAppStore } from '@/stores/app'

  // Nachträgliche Korrektur eines archivierten Spiels (Admin): Termin, Halle, Besetzung
  const props = defineProps<{ game: Game }>()
  const emit = defineEmits<{ close: [] }>()
  const store = useAppStore()
  const date = ref(props.game.date)
  const time = ref(props.game.time ?? '')
  const venue = ref(props.game.venue)
  const crew = ref([...props.game.confirmed_ids])
  const head = ref(props.game.head_referee_id)

  async function save () {
    const body = { date: date.value, time: time.value || null, venue: venue.value, referee_ids: crew.value, head_referee_id: head.value }
    if (await store.act('PUT', `/games/${props.game.id}`, body, 'Gespeichert.')) {
      emit('close')
    }
  }
</script>
