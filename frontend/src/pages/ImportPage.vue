<template>
  <PageHeader title="NuLiga-Import">
    <template #actions>
      <span class="font-mono text-caption text-soft">Letzter Abgleich: {{ lastSync }}</span>
    </template>
    Spiele der {{ LEAGUES.join(', ') }} werden aus dem nuLiga-Crawler gelesen. Regulär übernommen werden zunächst nur
    Heimspiele in Baden-Württemberg — alle anderen Spiele müssen separat von einem Admin angefordert werden.
  </PageHeader>

  <v-card class="pa-4 mb-5 gap-row">
    <v-btn color="primary" :loading="store.busy" prepend-icon="mdi-sync" @click="store.act('POST', '/nuliga/sync', undefined, 'Abgleich abgeschlossen.')">Jetzt mit NuLiga abgleichen</v-btn>
    <span class="text-body-2 text-soft">{{ standard.length > 0 ? `${standard.length} Termin${standard.length === 1 ? '' : 'e'} bereit zur Übernahme` : 'Alles auf dem aktuellen Stand' }}</span>
    <v-spacer />
    <v-btn v-if="standard.length > 0" color="primary" variant="outlined" @click="store.act('POST', '/nuliga/import-all')">Alle übernehmen</v-btn>
  </v-card>

  <GameFilter v-model="gf" class="mb-3" />
  <h2 class="font-display text-h6 mb-3">Heimspiele in Baden-Württemberg</h2>

  <div class="list-gap">
    <GameSummary v-for="g in standard" :key="g.id" :game="g">
      <div :class="g.nuliga_change === 'neu' ? 'text-amber' : 'text-primary'">{{ g.nuliga_change === 'neu' ? 'Neu' : `Änderung: ${g.nuliga_change}` }}</div>

      <template v-if="g.nuliga_change !== 'neu' && g.status === 'confirmed'">
        <div class="text-error">Ansetzung betroffen: {{ assignedIds(g).map(id => store.refName(id)).join(', ') }}</div>
        <div class="text-error">Neubestätigung durch die Angesetzten nötig</div>
      </template>

      <template #append>
        <v-btn
          color="primary"
          prepend-icon="mdi-download"
          size="small"
          variant="outlined"
          @click="store.act('POST', `/nuliga/import/${g.id}`)"
        >Übernehmen</v-btn>
      </template>
    </GameSummary>
  </div>

  <h2 class="font-display text-h6 mt-8 mb-1"><v-icon icon="mdi-lock-outline" size="18" /> Nur auf Anforderung</h2>

  <div class="text-body-2 text-soft mb-3">
    Heimspiele außerhalb Baden-Württembergs, Auswärtsspiele von BW-Mannschaften und Spiele ohne BW-Beteiligung werden nicht über „Alle übernehmen“ importiert. Ein Admin muss sie einzeln anfordern.
  </div>

  <div class="list-gap">
    <GameSummary
      v-for="g in requests"
      :key="g.id"
      class="border-dashed"
      :game="g"
      hide-competition
      icon="mdi-lock-outline"
    >
      <div class="text-soft">Heim: {{ g.home_team }} ({{ g.home_state }}) · Gast: {{ g.away_team }} ({{ g.away_state }})</div>

      <template #append>
        <v-chip color="error">{{ classify(g).label }}</v-chip>

        <ConfirmAction
          color="primary"
          confirm-label="Anfordern & übernehmen"
          label="Anfordern"
          prepend-icon="mdi-download"
          size="small"
          :text="`Dieses Spiel liegt außerhalb des BW-Fokus. Jetzt als ${store.state!.me.name} anfordern und in „Abfragen (Ansetzung)“ übernehmen?`"
          variant="outlined"
          @confirm="store.act('POST', `/nuliga/import/${g.id}`, { request: true })"
        />
      </template>
    </GameSummary>
  </div>
</template>

<script lang="ts" setup>
  import { computed, ref } from 'vue'
  import ConfirmAction from '@/components/ConfirmAction.vue'
  import GameFilter from '@/components/GameFilter.vue'
  import GameSummary from '@/components/GameSummary.vue'
  import PageHeader from '@/components/PageHeader.vue'
  import { fmtDateTime, isPast } from '@/domain/dates'
  import { assignedIds, classify, gameMatches, GF0, LEAGUES } from '@/domain/rules'
  import { useAppStore } from '@/stores/app'

  const store = useAppStore()
  const gf = ref({ ...GF0 })
  const pending = computed(() => store.games.filter(g => g.nuliga_change && !isPast(g) && gameMatches(g, gf.value)))
  const standard = computed(() => pending.value.filter(g => classify(g).standard))
  const requests = computed(() => pending.value.filter(g => !classify(g).standard))
  const lastSync = computed(() => (store.state!.settings.nuliga_last_sync ? fmtDateTime(store.state!.settings.nuliga_last_sync) : 'noch nie'))
</script>
