<template>
  <PageHeader subtitle="Einsatzstatistik, Lizenzen und Leistungsnachweise der Schiedsrichter" title="Statistik" />

  <div class="list-gap">
    <v-card v-for="r in sorted" :key="r.id" class="pa-4">
      <div class="d-flex align-center ga-3 mb-2">
        <v-avatar class="font-mono text-caption text-primary" color="surface-variant" size="32">{{ initials(r.name) }}</v-avatar>

        <div class="flex-grow-1">
          <div class="text-body-1 font-weight-medium">{{ r.name }}</div>
          <div class="gap-row text-caption text-soft"><v-chip v-if="r.license" color="primary">{{ r.license }}</v-chip><span v-else>noch keine Lizenz</span><span v-if="r.license_valid_until"> gültig bis {{ isoToDe(r.license_valid_until) }}</span></div>
        </div>

        <div class="text-right">
          <div class="font-display text-h6">{{ r.season_count }}</div>
          <div class="text-caption text-soft">Einsätze/Saison</div>
        </div>
      </div>

      <v-progress-linear
        bg-color="surface-variant"
        class="mb-2"
        color="success"
        height="6"
        :model-value="((r.season_count ?? 0) / max) * 100"
        rounded
      />

      <div class="text-caption text-soft mb-2"><v-icon icon="mdi-trending-up" size="12" /> {{ r.trend }} · zuletzt {{ r.last_game }}</div>
      <div v-for="(n, i) in r.proofs" :key="i" class="text-caption text-soft"><v-icon color="accent" icon="mdi-medal-outline" size="12" /> {{ isoToDe(n.date) }} · {{ n.text }}</div>

      <v-sheet v-if="openId === r.id" class="pa-3 mt-2 gap-row" color="surface-variant" rounded="lg">
        <v-text-field v-model="proof.text" bg-color="surface" placeholder="Beschreibung des Leistungsnachweises" style="min-width: 260px" />

        <v-text-field
          v-model="proof.until"
          bg-color="surface"
          label="Neues Gültig-bis-Datum"
          style="max-width: 220px"
          type="date"
        />

        <v-btn color="primary" size="small" @click="save(r.id)">Speichern</v-btn>
        <v-btn size="small" variant="outlined" @click="openId = null">Abbrechen</v-btn>
      </v-sheet>

      <v-btn
        v-else-if="store.can('personen')"
        class="px-0 mt-1"
        color="primary"
        prepend-icon="mdi-plus"
        size="small"
        variant="text"
        @click="openId = r.id"
      >Leistungsnachweis eintragen</v-btn>
    </v-card>
  </div>
</template>

<script lang="ts" setup>
  import { computed, reactive, ref } from 'vue'
  import PageHeader from '@/components/PageHeader.vue'
  import { isoToDe } from '@/domain/dates'
  import { initials } from '@/domain/rules'
  import { useAppStore } from '@/stores/app'

  const store = useAppStore()
  const sorted = computed(() => [...store.referees].toSorted((a, b) => (b.season_count ?? 0) - (a.season_count ?? 0)))
  const max = computed(() => Math.max(1, ...store.referees.map(r => r.season_count ?? 0)))
  const openId = ref<number | null>(null)
  const proof = reactive({ text: '', until: '' })

  async function save (id: number) {
    if (proof.text && proof.until && await store.act('POST', `/referees/${id}/proofs`, { text: proof.text, license_valid_until: proof.until })) {
      Object.assign(proof, { text: '', until: '' })
      openId.value = null
    }
  }
</script>
