<template>
  <PageHeader title="Lizenzen & Perspektiv-Teams">
    <template #actions>
      <v-btn color="primary" :loading="store.busy" prepend-icon="mdi-sync" @click="store.act('POST', '/referees/sync', undefined, 'Abgleich abgeschlossen.')">
        Alle mit NuLiga abgleichen{{ pending > 0 ? ` (${pending})` : '' }}
      </v-btn>
    </template>
    Lizenzstufen und Gültigkeiten der Schiedsrichter — Lizenzdaten werden, wo verfügbar, automatisch aus NuLiga übernommen. Ohne NuLiga-Daten bleibt die manuelle Pflege möglich.
  </PageHeader>

  <div class="list-gap">
    <v-card v-for="r in store.referees" :key="r.id" class="pa-4">
      <div class="d-flex align-center ga-3">
        <v-avatar class="font-mono text-caption text-primary" color="surface-variant" size="32">{{ initials(r.name) }}</v-avatar>

        <div class="flex-grow-1">
          <div class="text-body-1 font-weight-medium">{{ r.name }}</div>

          <div class="gap-row text-caption text-soft">
            <v-chip color="primary">{{ r.license }}</v-chip>
            <span>gültig bis {{ isoToDe(r.license_valid_until) }}</span>
            <span v-if="reminderDate(r)">· Erinnerung {{ r.license_reminded_for === r.license_valid_until ? 'gesendet' : `ab ${reminderDate(r)!.toLocaleDateString('de-DE')}` }}</span>
            <span>· {{ r.license_synced_at ? `zuletzt aus NuLiga synchronisiert am ${isoToDe(r.license_synced_at)}` : 'noch nie mit NuLiga synchronisiert' }}</span>
          </div>
        </div>

        <v-chip v-if="!diff(r)" color="error">Keine NuLiga-Daten</v-chip>
        <v-chip v-else-if="diff(r) === 'same'" color="success">Aktuell</v-chip>

        <v-btn
          v-else
          color="warning"
          prepend-icon="mdi-sync"
          size="small"
          variant="tonal"
          @click="store.act('POST', `/referees/${r.id}/sync`)"
        >
          Update übernehmen: {{ nuliga(r)!.license }}, bis {{ isoToDe(nuliga(r)!.valid_until) }}
        </v-btn>
      </div>

      <v-sheet v-if="editId === r.id" class="pa-3 mt-3 gap-row" color="surface-variant" rounded="lg">
        <v-select
          v-model="edit.license"
          bg-color="surface"
          :items="LICENSE_LEVELS"
          label="Lizenzstufe"
          style="max-width: 220px"
        />

        <v-text-field
          v-model="edit.until"
          bg-color="surface"
          label="Gültig bis"
          style="max-width: 200px"
          type="date"
        />

        <v-btn color="primary" size="small" @click="save(r.id)">Speichern</v-btn>
        <v-btn size="small" variant="outlined" @click="editId = null">Abbrechen</v-btn>
      </v-sheet>

      <v-btn
        v-else
        class="px-0 mt-2"
        size="small"
        variant="text"
        @click="startEdit(r)"
      >Manuell bearbeiten</v-btn>

      <v-divider class="my-3" />

      <div class="gap-row text-caption text-soft">
        <span>Verein (aus NuLiga): <strong class="text-high-emphasis">{{ r.club || '—' }}</strong></span>
        <span>· Perspektiv-Team (nur Admins):</span>

        <v-chip
          v-for="[key, label] in TEAMS"
          :key="key"
          :color="r[key] ? 'primary' : undefined"
          :prepend-icon="r[key] ? 'mdi-check' : undefined"
          :variant="r[key] ? 'tonal' : 'outlined'"
          @click="store.act('PUT', `/referees/${r.id}/teams`, { [key]: !r[key] })"
        >{{ label }}</v-chip>
      </div>
    </v-card>
  </div>
</template>

<script lang="ts" setup>
  import type { Referee } from '@/types'
  import { computed, reactive, ref } from 'vue'
  import PageHeader from '@/components/PageHeader.vue'
  import { isoToDe } from '@/domain/dates'
  import { initials, LICENSE_LEVELS, reminderDate } from '@/domain/rules'
  import { useAppStore } from '@/stores/app'

  const TEAMS = [['persp_national', 'National'], ['persp_international', 'International']] as const
  const store = useAppStore()
  const nuliga = (r: Referee) => store.state!.nuliga_licenses?.[r.id] ?? null
  function diff (r: Referee) {
    const n = nuliga(r)
    return n && (n.license !== r.license || n.valid_until !== r.license_valid_until ? 'diff' : 'same')
  }
  const pending = computed(() => store.referees.filter(r => diff(r) === 'diff').length)

  const editId = ref<number | null>(null)
  const edit = reactive({ license: '', until: '' })
  function startEdit (r: Referee) {
    editId.value = r.id
    Object.assign(edit, { license: r.license, until: r.license_valid_until ?? '' })
  }
  async function save (id: number) {
    if (edit.until && await store.act('PUT', `/referees/${id}/license`, { license: edit.license, license_valid_until: edit.until })) {
      editId.value = null
    }
  }
</script>
