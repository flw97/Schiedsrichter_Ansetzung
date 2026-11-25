<template>
  <div class="max-w-lg">
    <PageHeader subtitle="Konten und Rollen verwalten. Die Rolle bestimmt, welche Bereiche jemand sieht und ändern darf. Änderungen gelten ab der nächsten Anmeldung." title="Rollen" />

    <div class="gap-row mb-3">
      <v-text-field v-model="q" placeholder="Suche: Name, Benutzername, E-Mail" prepend-inner-icon="mdi-magnify" style="min-width: 240px" />
      <v-select v-model="rf" :items="[{ title: 'Alle Rollen', value: 'alle' }, ...roleItems]" style="max-width: 200px" />
    </div>

    <v-card class="mb-5">
      <v-table>
        <thead><tr><th>Konto</th><th>E-Mail</th><th style="width: 190px">Rolle</th><th style="width: 130px">Status</th></tr></thead>

        <tbody>
          <tr v-if="rows.length === 0"><td class="text-soft" colspan="4">Keine Konten für diesen Filter.</td></tr>

          <tr v-for="u in rows" :key="u.id" :class="{ 'opacity-60': !u.active }">
            <td class="py-2"><div class="font-weight-medium">{{ u.name }}</div><div class="font-mono text-caption text-soft">{{ u.username }}</div></td>
            <td class="font-mono text-caption">{{ u.email || '—' }}</td>
            <td><v-select :items="roleItems" :model-value="u.role" @update:model-value="(role: string) => store.act('PUT', `/users/${u.id}`, { role })" /></td>
            <td><v-chip :color="u.active ? 'primary' : 'error'" @click="store.act('PUT', `/users/${u.id}`, { active: !u.active })">{{ u.active ? 'Aktiv' : 'Deaktiviert' }}</v-chip></td>
          </tr>
        </tbody>
      </v-table>
    </v-card>

    <v-card class="pa-4 mb-5">
      <div class="text-body-2 font-weight-medium mb-3">Konto anlegen</div>

      <div class="gap-row">
        <v-text-field v-model="nu.name" placeholder="Name" style="min-width: 160px" />
        <v-text-field v-model="nu.username" placeholder="NuLiga-Benutzername" style="min-width: 160px" />
        <v-text-field v-model="nu.email" placeholder="E-Mail (optional)" style="min-width: 170px" />
        <v-text-field v-model="nu.password" placeholder="Startpasswort (mind. 8 Zeichen)" style="min-width: 200px" type="password" />
        <v-select v-model="nu.role" :items="roleItems" style="max-width: 180px" />
        <v-btn color="primary" @click="add">Anlegen</v-btn>
      </div>

      <div class="text-caption text-soft mt-2">Schiedsrichter-Konten werden über den Namen mit dem NuLiga-Profil verknüpft.</div>
    </v-card>

    <v-card class="mb-5">
      <v-table density="compact">
        <thead><tr><th>Berechtigungen je Rolle</th><th v-for="r in ROLES" :key="r" class="text-center">{{ ROLE_LABEL[r] }}</th></tr></thead>

        <tbody>
          <tr v-for="p in store.state!.permission_matrix" :key="p.key">
            <td class="py-2">{{ p.label }}</td>
            <td v-for="r in ROLES" :key="r" class="text-center font-weight-bold" :class="p.roles.includes(r) ? 'text-success' : 'text-disabled'">{{ p.roles.includes(r) ? '✓' : '–' }}</td>
          </tr>
        </tbody>
      </v-table>
    </v-card>

    <v-card class="mb-5">
      <v-card-title class="text-body-1 font-weight-medium">Sicherheit &amp; Datenschutz</v-card-title>

      <v-list density="compact">
        <v-list-item v-for="[ok, text] in SECURITY" :key="text">
          <div class="d-flex ga-3 text-body-2"><span class="font-weight-bold flex-shrink-0" :class="ok ? 'text-success' : 'text-amber'" style="width: 70px">{{ ok ? '✓ aktiv' : '! offen' }}</span>{{ text }}</div>
        </v-list-item>
      </v-list>
    </v-card>

    <template v-for="log in LOGS" :key="log.kind">
      <div class="text-body-2 font-weight-medium mb-2">{{ log.title }}</div>
      <div v-if="entries(log.kind).length === 0" class="text-body-2 text-soft mb-4">Noch keine Einträge.</div>

      <div class="mb-5">
        <div v-for="l in entries(log.kind)" :key="l.id" class="text-caption text-soft py-1" style="border-top: 1px solid #E4DFD1">
          <span class="font-mono">{{ fmtDateTime(l.created_at) }}</span> · {{ l.text }}
        </div>
      </div>
    </template>
  </div>
</template>

<script lang="ts" setup>
  import type { Role } from '@/types'
  import { computed, reactive, ref } from 'vue'
  import PageHeader from '@/components/PageHeader.vue'
  import { fmtDateTime } from '@/domain/dates'
  import { ROLE_LABEL, ROLES } from '@/domain/rules'
  import { useAppStore } from '@/stores/app'

  const SECURITY: [boolean, string][] = [
    [true, 'Anmeldung und Rechteprüfung serverseitig bei jeder Aktion (Laravel, Token je Sitzung); abgelehnte Aktionen werden protokolliert.'],
    [true, 'Automatische Abmeldung nach 15 Minuten Inaktivität (Browser und Server).'],
    [true, 'Kontaktdaten (Telefon, E-Mail) werden serverseitig maskiert; das Anzeigen in der Schiedsrichter-Liste wird protokolliert.'],
    [true, 'Zugriffsprotokoll für Anmeldungen, Abmeldungen, abgelehnte Aktionen und angezeigte Kontaktdaten.'],
    [true, 'Regelwerke: nur PDF (Dateikennung wird geprüft, max. 20 MB), Abruf nur angemeldet; schreiben dürfen nur Master-Admin und Admin.'],
    [true, 'Eingaben werden nur als Text ausgegeben (kein HTML); Kommentare und Notizen sind in der Länge begrenzt.'],
    [true, 'Unterkünfte: Bearbeiten und Löschen nur durch Admins; Bewertungen sind einzelnen Konten zugeordnet.'],
    [false, 'Anmeldung über NuLiga (aktuell lokale Konten mit Passwort).'],
    [false, 'Übertragung per HTTPS und verschlüsselte Datenbank mit Backup- und Löschkonzept im Betrieb.'],
    [false, 'Datenschutz-Dokumentation: Einwilligung für Telefonnummer und Push-Mitteilungen, Verzeichnis der Verarbeitungstätigkeiten, Auftragsverarbeitung.'],
  ]
  const LOGS = [{ kind: 'access', title: 'Zugriffsprotokoll' }, { kind: 'change', title: 'Änderungsprotokoll' }] as const

  const store = useAppStore()
  const roleItems = ROLES.map(r => ({ title: ROLE_LABEL[r], value: r }))
  const q = ref('')
  const rf = ref('alle')
  const rows = computed(() => (store.state!.users ?? []).filter(u => (rf.value === 'alle' || u.role === rf.value)
    && (!q.value.trim() || `${u.name} ${u.username} ${u.email}`.toLowerCase().includes(q.value.trim().toLowerCase()))))
  const entries = (kind: string) => (store.state!.audit ?? []).filter(l => l.kind === kind).slice(0, 20)

  const empty = () => ({ name: '', username: '', email: '', password: '', role: 'schiri' as Role })
  const nu = reactive(empty())
  async function add () {
    if (await store.act('POST', '/users', { ...nu, email: nu.email || null }, 'Konto angelegt.')) {
      Object.assign(nu, empty())
    }
  }
</script>
