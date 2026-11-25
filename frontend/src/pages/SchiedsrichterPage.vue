<template>
  <PageHeader subtitle="Alle Schiedsrichter mit Lizenzstufe und Kontaktdaten. Die Liste lässt sich filtern und sortieren." title="Schiedsrichter" />

  <div class="gap-row mb-3">
    <v-btn color="primary" :variant="contacts ? 'outlined' : 'flat'" @click="toggleContacts">{{ contacts ? 'Kontaktdaten ausblenden' : 'Kontaktdaten anzeigen' }}</v-btn>
    <span class="text-caption text-soft">Telefon und E-Mail sind standardmäßig maskiert; das Anzeigen wird protokolliert.</span>
  </div>

  <div class="gap-row mb-4">
    <v-text-field v-model="q" placeholder="Suche: Name, Verein, E-Mail, Telefon" prepend-inner-icon="mdi-magnify" style="min-width: 240px" />
    <v-select v-model="level" :items="[{ title: 'Alle Lizenzstufen', value: 'alle' }, ...LICENSE_LEVELS]" style="max-width: 190px" />
    <v-select v-model="team" :items="[{ title: 'Perspektiv-Team: alle', value: 'alle' }, { title: 'PT National', value: 'national' }, { title: 'PT International', value: 'international' }, { title: 'Ohne Perspektiv-Team', value: 'keins' }]" style="max-width: 220px" />
    <v-select v-model="avail" :items="[{ title: 'Verfügbarkeit: alle', value: 'alle' }, { title: 'Verfügbar gemeldet', value: 'ja' }, { title: 'Nicht verfügbar', value: 'nein' }]" style="max-width: 200px" />
    <v-checkbox v-model="soon" label="Lizenz läuft in < 12 Monaten ab" />
    <v-select v-model="sort" :items="[{ title: 'Sortiert nach Name', value: 'name' }, { title: 'Sortiert nach Lizenzstufe', value: 'lizenz' }, { title: 'Sortiert nach Verein', value: 'verein' }]" style="max-width: 220px" />
  </div>

  <v-card>
    <v-table>
      <thead><tr><th>Name</th><th>Lizenzstufe</th><th>Perspektiv-Team</th><th>Telefon</th><th>E-Mail</th></tr></thead>

      <tbody>
        <tr v-if="rows.length === 0"><td class="text-soft" colspan="5">Keine Schiedsrichter für diese Filter.</td></tr>

        <tr v-for="r in rows" :key="r.id">
          <td class="py-2"><div class="font-weight-medium">{{ r.name }}</div><div class="text-caption text-soft">{{ r.club }}{{ r.available ? '' : ' · nicht verfügbar' }}</div></td>
          <td><v-chip color="primary">{{ r.license }}</v-chip><div class="text-caption text-soft">bis {{ isoToDe(r.license_valid_until) }}</div></td>

          <td>
            <v-chip v-if="r.persp_national" class="mr-1" color="accent">National</v-chip>
            <v-chip v-if="r.persp_international" color="accent">International</v-chip>
            <span v-if="!r.persp_national && !r.persp_international" class="text-soft">—</span>
          </td>

          <td class="font-mono text-body-2">{{ contact(r).phone }}</td>
          <td class="font-mono text-body-2">{{ contact(r).email }}</td>
        </tr>
      </tbody>
    </v-table>
  </v-card>

  <div class="text-caption text-soft mt-2">{{ rows.length }} von {{ store.referees.length }} Schiedsrichtern</div>
</template>

<script lang="ts" setup>
  import type { Referee } from '@/types'
  import { computed, ref } from 'vue'
  import { api } from '@/api'
  import PageHeader from '@/components/PageHeader.vue'
  import { isoToDe } from '@/domain/dates'
  import { LICENSE_LEVELS, licenseRank, reminderDate } from '@/domain/rules'
  import { useAppStore } from '@/stores/app'

  const store = useAppStore()
  const q = ref('')
  const level = ref('alle')
  const team = ref('alle')
  const avail = ref('alle')
  const soon = ref(false)
  const sort = ref('name')

  // Unmaskierte Kontaktdaten werden nur auf Anforderung geladen (serverseitig protokolliert)
  const contacts = ref<Map<number, { email: string | null, phone: string | null }> | null>(null)
  async function toggleContacts () {
    contacts.value = contacts.value ? null : new Map((await api.json<{ id: number, email: string, phone: string }[]>('GET', '/referees/contacts')).map(c => [c.id, c]))
  }
  const contact = (r: Referee) => contacts.value?.get(r.id) ?? r

  const rows = computed(() => store.referees.filter(r => {
    const hay = `${r.name} ${r.club} ${contacts.value ? `${contact(r).email} ${contact(r).phone}` : ''}`.toLowerCase()
    const reminder = reminderDate(r)
    return (!q.value.trim() || hay.includes(q.value.trim().toLowerCase()))
      && (level.value === 'alle' || r.license === level.value)
      && ({ alle: true, national: r.persp_national, international: r.persp_international, keins: !r.persp_national && !r.persp_international }[team.value])
      && (avail.value === 'alle' || (avail.value === 'ja') === r.available)
      && (!soon.value || (!!reminder && new Date() >= reminder))
  }).toSorted((a, b) => sort.value === 'lizenz'
    ? licenseRank(b.license) - licenseRank(a.license) || a.name.localeCompare(b.name, 'de')
    : (sort.value === 'verein' ? (a.club ?? '').localeCompare(b.club ?? '', 'de') : a.name.localeCompare(b.name, 'de'))))
</script>
