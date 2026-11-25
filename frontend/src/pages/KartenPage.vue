<template>
  <PageHeader subtitle="Auswertung der von den Schiedsrichtern erfassten Anmerkungen und Karten (gelb, rot, schwarz)." title="Karten & Notizen" />

  <GameFilter v-model="gf" class="mb-4" :competitions="competitions">
    <v-select v-model="color" :items="[{ title: 'Alle Kartenfarben', value: 'alle' }, ...COLORS.map(c => ({ title: CARD_LABEL[c], value: c }))]" style="max-width: 190px" />
    <v-select v-model="only" :items="[{ title: 'Alle Spiele mit Eintrag', value: 'alle' }, { title: 'Nur mit Karten', value: 'karten' }, { title: 'Nur mit Anmerkung', value: 'notizen' }]" style="max-width: 220px" />
    <v-select v-model="giver" aria-label="Schiedsrichter" :items="['alle', ...store.referees.map(r => r.name)].map(v => ({ title: v === 'alle' ? 'Alle Schiedsrichter' : v, value: v }))" style="max-width: 220px" />
  </GameFilter>

  <div class="gap-row mb-4" style="gap: 12px">
    <StatTile label="Spiele mit Eintrag" :value="reports.length" />

    <StatTile v-for="c in COLORS" :key="c" :value="cardsByGiver.filter(k => k.color === c).length">
      <template #label><span class="card-swatch" :style="{ background: CARD_COLORS[c] }" /> {{ CARD_LABEL[c] }}e Karten</template>
    </StatTile>
  </div>

  <div class="d-flex flex-wrap ga-4 mb-5">
    <v-card v-for="t in [{ title: 'Karten je Mannschaft', rows: byTeam }, { title: 'Karten je Wettbewerb', rows: byLeague }]" :key="t.title" class="flex-grow-1" min-width="300">
      <v-table density="compact">
        <thead><tr><th>{{ t.title }}</th><th v-for="c in COLORS" :key="c" class="text-right"><span class="card-swatch" :style="{ background: CARD_COLORS[c] }" /></th><th class="text-right">Σ</th></tr></thead>

        <tbody>
          <tr v-if="t.rows.length === 0"><td class="text-soft" colspan="5">Keine Daten.</td></tr>
          <tr v-for="r in t.rows" :key="r.name"><td>{{ r.name }}</td><td v-for="c in COLORS" :key="c" class="text-right font-mono">{{ r[c] }}</td><td class="text-right font-mono font-weight-bold">{{ r.total }}</td></tr>
        </tbody>
      </v-table>
    </v-card>
  </div>

  <v-card class="mb-5">
    <v-table density="compact">
      <thead>
        <tr>
          <th>Karten je Schiedsrichter (vergeben von)</th><th class="text-right">Einsätze</th>
          <th v-for="c in COLORS" :key="c" class="text-right"><span class="card-swatch" :style="{ background: CARD_COLORS[c] }" /></th>
          <th class="text-right">Σ</th><th class="text-right">je Einsatz</th>
        </tr>
      </thead>

      <tbody>
        <tr v-if="byRef.length === 0"><td class="text-soft" colspan="7">Keine Daten.</td></tr>

        <tr v-for="r in byRef" :key="r.name" :class="{ 'bg-surface-variant': giver === r.name }">
          <td>{{ r.name }}</td><td class="text-right font-mono">{{ r.games }}</td>
          <td v-for="c in COLORS" :key="c" class="text-right font-mono">{{ r[c] }}</td>
          <td class="text-right font-mono font-weight-bold">{{ r.total }}</td>
          <td class="text-right font-mono">{{ r.games ? num(r.total / r.games, 2) : '–' }}</td>
        </tr>

        <tr v-if="withoutGiver > 0"><td class="text-amber" colspan="7">{{ withoutGiver }} Karte{{ withoutGiver === 1 ? '' : 'n' }} ohne Angabe, wer sie vergeben hat (ältere Einträge).</td></tr>
      </tbody>
    </v-table>
  </v-card>

  <h2 class="font-display text-h6 mb-3">Spiele mit Anmerkungen oder Karten</h2>
  <v-card v-if="reports.length === 0" class="pa-4 text-body-2 text-soft">Noch keine Einträge — Schiedsrichter erfassen sie in ihren Ansetzungen.</v-card>

  <v-card v-for="g in reports" :key="g.id" class="pa-4 mb-2">
    <div class="text-body-1 font-weight-medium">{{ g.title }}</div>
    <div class="text-caption text-soft mb-2">{{ gameLabel(g) }} · {{ g.venue }} · erfasst von {{ g.report!.author }} am {{ isoToDe(g.report!.at) }}</div>
    <ReportView :color="color === 'alle' ? undefined : color" :report="g.report" />
  </v-card>
</template>

<script lang="ts" setup>
  import type { Card, CardColor, Game } from '@/types'
  import { computed, ref } from 'vue'
  import GameFilter from '@/components/GameFilter.vue'
  import PageHeader from '@/components/PageHeader.vue'
  import ReportView from '@/components/ReportView.vue'
  import StatTile from '@/components/StatTile.vue'
  import { gameLabel, isoToDe, isPast, num } from '@/domain/dates'
  import { assignedIds, CARD_COLORS, CARD_LABEL, gameMatches, GF0 } from '@/domain/rules'
  import { useAppStore } from '@/stores/app'

  const COLORS: CardColor[] = ['gelb', 'rot', 'schwarz']
  const store = useAppStore()
  const gf = ref({ ...GF0 })
  const color = ref<'alle' | CardColor>('alle')
  const only = ref('alle')
  const giver = ref('alle')

  const withReport = computed(() => store.games.filter(g => g.report))
  const competitions = computed(() => [...new Set(withReport.value.map(g => g.competition))])
  const reports = computed(() => withReport.value.filter(g => gameMatches(g, gf.value)
    && (only.value === 'alle' || (only.value === 'karten' ? g.report!.cards.length > 0 : !!g.report!.note))
    && (giver.value === 'alle' || g.report!.cards.some(k => k.given_by === giver.value))))
  const allCards = computed(() => reports.value.flatMap(g => g.report!.cards.map(k => ({ ...k, game: g }))))
  const cardsByGiver = computed(() => (giver.value === 'alle' ? allCards.value : allCards.value.filter(k => k.given_by === giver.value)))
  const shown = computed(() => (color.value === 'alle' ? cardsByGiver.value : cardsByGiver.value.filter(k => k.color === color.value)))

  function tally (cards: Card[]) {
    const c = { gelb: 0, rot: 0, schwarz: 0 }
    for (const k of cards) {
      c[k.color]++
    }
    return { ...c, total: c.gelb + c.rot + c.schwarz }
  }
  function group (key: (k: Card & { game: Game }) => string) {
    const m = Map.groupBy(shown.value, key)
    return [...m].map(([name, cards]) => ({ name, ...tally(cards) })).toSorted((a, b) => b.total - a.total)
  }
  const byTeam = computed(() => group(k => k.team))
  const byLeague = computed(() => group(k => k.game.competition))

  // Karten je Schiedsrichter und Einsätze im gewählten Bereich
  const byRef = computed(() => {
    const officiated = store.games.filter(g => g.status === 'confirmed' && isPast(g) && gameMatches(g, gf.value))
    const names = new Set([...store.referees.map(r => r.name), ...allCards.value.map(k => k.given_by).filter((n): n is string => !!n)])
    return [...names].map(name => ({
      name,
      games: officiated.filter(g => assignedIds(g).some(id => store.refName(id) === name)).length,
      ...tally(allCards.value.filter(k => k.given_by === name)),
    })).filter(r => r.games > 0 || r.total > 0).toSorted((a, b) => b.total - a.total || a.name.localeCompare(b.name, 'de'))
  })
  const withoutGiver = computed(() => allCards.value.filter(k => !k.given_by).length)
</script>
