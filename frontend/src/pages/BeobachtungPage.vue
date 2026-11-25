<template>
  <PageHeader title="Beobachtung & Entwicklung">
    {{ members.length > 0 ? 'Beobachtungsbögen für Mitglieder der Perspektiv-Teams — zur Dokumentation der Aufstufung zu National bzw. International.' : 'Noch kein Schiedsrichter im Perspektiv-Team — Zuordnung unter „Lizenzen & Perspektiv-Teams“.' }}
  </PageHeader>

  <template v-if="sel">
    <v-chip-group v-model="selId" class="mb-4" mandatory>
      <v-chip
        v-for="r in members"
        :key="r.id"
        class="py-6"
        color="primary"
        size="default"
        :value="r.id"
        variant="outlined"
      >
        <div>
          <div class="font-weight-medium">{{ r.name }}</div>
          <div class="text-caption text-soft">{{ r.license }} → PT {{ r.persp_international ? 'International' : 'National' }} · {{ count(r.id) }} Beobachtung{{ count(r.id) === 1 ? '' : 'en' }}</div>
        </div>
      </v-chip>
    </v-chip-group>

    <div class="d-flex flex-wrap ga-4 align-start">
      <div class="flex-grow-1" style="flex-basis: 360px">
        <v-card class="pa-4 mb-3">
          <div class="text-body-2 font-weight-medium mb-2">Durchschnitt über {{ mine.length }} Beobachtung{{ mine.length === 1 ? '' : 'en' }}</div>

          <div v-for="[k, label] in CRITERIA" :key="k" class="d-flex align-center ga-3 mb-1">
            <div class="text-caption text-soft" style="width: 200px">{{ label }}</div>

            <v-progress-linear
              bg-color="surface-variant"
              color="primary"
              height="8"
              :model-value="(avg(k) / 5) * 100"
              rounded
            />

            <div class="font-mono text-caption" style="width: 28px">{{ mine.length > 0 ? num(avg(k)) : '–' }}</div>
          </div>
        </v-card>

        <div v-if="mine.length === 0" class="text-body-2 text-soft">Noch keine Beobachtung erfasst.</div>

        <v-card v-for="b in mine" :key="b.id" class="pa-4 mb-2">
          <div class="d-flex align-center ga-2 mb-1">
            <div class="text-body-2 font-weight-medium flex-grow-1">{{ b.game }}</div>
            <v-chip :color="{ 'Aufstufung empfohlen': 'success', 'Noch nicht': 'error' }[b.recommendation] ?? 'warning'">{{ b.recommendation }}</v-chip>
          </div>

          <div class="text-caption text-soft mb-2">{{ isoToDe(b.date) }} · beobachtet von {{ b.observer }} · {{ CRITERIA.map(([k, l]) => `${l.split(' ')[0]} ${b.scores[k]}`).join(' · ') }}</div>
          <div v-if="b.strengths" class="text-body-2"><strong>Stärken:</strong> {{ b.strengths }}</div>
          <div v-if="b.development" class="text-body-2"><strong>Entwicklungsfelder:</strong> {{ b.development }}</div>
        </v-card>
      </div>

      <v-card class="pa-4 flex-grow-1" style="flex-basis: 300px">
        <div class="text-body-2 font-weight-medium mb-3">Neue Beobachtung für {{ sel.name }}</div>
        <v-text-field v-model="form.date" class="mb-2" label="Datum" type="date" />
        <v-text-field v-model="form.game" class="mb-3" label="Beobachtetes Spiel / Turnier" />

        <div v-for="[k, label] in CRITERIA" :key="k" class="d-flex align-center ga-2 mb-2">
          <div class="text-body-2 flex-grow-1">{{ label }}</div>
          <v-select v-model="form.scores[k]" :items="[1, 2, 3, 4, 5]" style="max-width: 90px" />
        </div>

        <v-textarea v-model="form.strengths" class="my-2" label="Stärken" maxlength="1000" />
        <v-textarea v-model="form.development" class="mb-2" label="Entwicklungsfelder" maxlength="1000" />
        <v-select v-model="form.recommendation" class="mb-3" :items="RECOMMENDATIONS" label="Empfehlung" />
        <v-btn color="primary" :disabled="!form.game.trim()" @click="submit">Beobachtung speichern</v-btn>
        <div class="text-caption text-soft mt-2">Bewertung 1 (schwach) bis 5 (sehr stark).</div>
      </v-card>
    </div>
  </template>
</template>

<script lang="ts" setup>
  import { computed, ref } from 'vue'
  import PageHeader from '@/components/PageHeader.vue'
  import { isoToDe, num, todayIso } from '@/domain/dates'
  import { useAppStore } from '@/stores/app'

  const CRITERIA = [['regelkenntnis', 'Regelkenntnis'], ['auftreten', 'Auftreten & Kommunikation'], ['spielleitung', 'Spielleitung & Entscheidungen'], ['teamarbeit', 'Zusammenarbeit']] as const
  const RECOMMENDATIONS = ['Aufstufung empfohlen', 'Weiter beobachten', 'Noch nicht']
  const store = useAppStore()
  const members = computed(() => store.referees.filter(r => r.persp_national || r.persp_international))
  const selId = ref(members.value[0]?.id)
  const sel = computed(() => members.value.find(r => r.id === selId.value) ?? members.value[0])
  const items = computed(() => store.state!.observations ?? [])
  const count = (id: number) => items.value.filter(b => b.referee_id === id).length
  const mine = computed(() => items.value.filter(b => b.referee_id === sel.value?.id).toReversed())
  const avg = (k: string) => (mine.value.length > 0 ? mine.value.reduce((s, b) => s + (b.scores[k] ?? 0), 0) / mine.value.length : 0)

  const empty = () => ({ date: todayIso(), game: '', scores: { regelkenntnis: 3, auftreten: 3, spielleitung: 3, teamarbeit: 3 } as Record<string, number>, strengths: '', development: '', recommendation: 'Weiter beobachten' })
  const form = ref(empty())
  async function submit () {
    if (await store.act('POST', '/observations', { ...form.value, referee_id: sel.value!.id }, 'Beobachtung gespeichert.')) {
      form.value = empty()
    }
  }
</script>
