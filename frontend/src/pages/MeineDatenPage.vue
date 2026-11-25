<template>
  <div class="max-w-sm">
    <PageHeader title="Meine Daten">
      Name, Lizenz und Verein werden aus NuLiga übernommen, die Perspektiv-Team-Zugehörigkeit pflegen die Admins. Telefonnummer, E-Mail, Adresse, Verfügbarkeit und Sperrzeiten kannst du selbst bearbeiten.
    </PageHeader>

    <v-card class="pa-4 mb-4 d-flex ga-3">
      <v-avatar class="font-mono text-primary" color="surface-variant" size="40">{{ initials(me.name) }}</v-avatar>

      <div>
        <div class="text-body-1 font-weight-medium">{{ me.name }}</div>
        <div class="gap-row text-caption text-soft"><v-chip color="primary">{{ me.license }}</v-chip> gültig bis {{ isoToDe(me.license_valid_until) }}</div>

        <div class="gap-row text-caption text-soft mt-1">
          Verein: <strong class="text-high-emphasis">{{ me.club }}</strong>
          <v-chip v-if="me.persp_national" color="accent">Perspektiv-Team National</v-chip>
          <v-chip v-if="me.persp_international" color="accent">Perspektiv-Team International</v-chip>
        </div>
      </div>
    </v-card>

    <v-text-field v-model="form.email" class="mb-3" label="E-Mail" type="email" />
    <v-text-field v-model="form.phone" class="mb-3" label="Telefonnummer für Push-Mitteilungen" />
    <v-text-field v-model="form.address" class="mb-3" label="Adresse (für Standortkarte & Entfernungsberechnung)" />
    <v-switch v-model="form.available" class="mb-3" label="Ich bin für Einsätze verfügbar" />
    <v-btn color="primary" @click="save({ ...form }, 'Gespeichert.')">Speichern</v-btn>

    <h2 class="font-display text-h6 mt-8">Sperrzeiten</h2>
    <div class="text-body-2 text-soft mb-3">Trage Urlaub oder Sperrtage ein. In diesen Zeiträumen wirst du weder angefragt noch angesetzt, und die Saisonplanung berücksichtigt sie.</div>
    <div v-if="blackouts.length === 0" class="text-body-2 text-soft mb-2">Keine Sperrzeiten eingetragen.</div>

    <v-card v-for="(b, i) in blackouts" :key="i" class="px-3 py-1 mb-2 d-flex align-center">
      <div class="flex-grow-1 text-body-2">{{ isoToDe(b.from) }}{{ b.to !== b.from ? ` – ${isoToDe(b.to)}` : '' }}{{ b.reason ? ` · ${b.reason}` : '' }}</div>
      <v-btn color="error" size="small" variant="text" @click="save({ blackouts: blackouts.filter((_, j) => j !== i) })">Entfernen</v-btn>
    </v-card>

    <div class="gap-row mt-2">
      <v-text-field v-model="nb.from" label="von" style="max-width: 170px" type="date" />
      <v-text-field v-model="nb.to" label="bis" style="max-width: 170px" type="date" />
      <v-text-field v-model="nb.reason" maxlength="100" placeholder="Grund (optional)" style="min-width: 140px" />
      <v-btn color="primary" @click="addBlackout">Hinzufügen</v-btn>
    </div>
  </div>
</template>

<script lang="ts" setup>
  import { computed, reactive } from 'vue'
  import PageHeader from '@/components/PageHeader.vue'
  import { isoToDe } from '@/domain/dates'
  import { initials } from '@/domain/rules'
  import { useAppStore } from '@/stores/app'

  const store = useAppStore()
  const me = computed(() => store.me!)
  const form = reactive({ email: me.value.email ?? '', phone: me.value.phone ?? '', address: me.value.address ?? '', available: !!me.value.available })
  const blackouts = computed(() => me.value.blackouts ?? [])
  const nb = reactive({ from: '', to: '', reason: '' })
  const save = (patch: object, success?: string) => store.act('PUT', '/me/referee', patch, success)

  async function addBlackout () {
    if (!nb.from || (nb.to && nb.to < nb.from)) {
      store.toasts.push({ text: nb.from ? 'Das Enddatum liegt vor dem Startdatum.' : 'Bitte ein Startdatum wählen.', color: 'error' })
      return
    }
    const next = [...blackouts.value, { from: nb.from, to: nb.to || nb.from, reason: nb.reason.trim() || null }].toSorted((a, b) => a.from.localeCompare(b.from))
    if (await save({ blackouts: next })) {
      Object.assign(nb, { from: '', to: '', reason: '' })
    }
  }
</script>
