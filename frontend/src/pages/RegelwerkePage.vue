<template>
  <div class="max-w-md">
    <PageHeader title="Regelwerke">
      {{ isAdmin
        ? 'Stelle hier die gültigen Regelwerke als PDF ein. Schiedsrichter sehen immer die aktuelle Version; frühere Fassungen bleiben für Admins abrufbar.'
        : 'Die aktuell gültigen Regelwerke — immer in der neuesten Fassung. „Öffnen“ zeigt das PDF direkt in der App.' }}
    </PageHeader>

    <v-alert v-if="isReferee && openReads > 0" class="mb-4" color="warning" variant="tonal">
      {{ openReads === 1 ? '1 Regelwerk wartet' : `${openReads} Regelwerke warten` }} auf deine Lesebestätigung.
    </v-alert>

    <v-card v-if="isAdmin" class="pa-4 mb-4">
      <div class="d-flex align-center ga-2 text-body-2 font-weight-medium mb-3"><v-icon color="primary" icon="mdi-upload" size="18" /> Regelwerk hochladen</div>

      <div class="gap-row mb-3">
        <v-text-field v-model="form.title" placeholder="Titel, z. B. Spielregeln" style="min-width: 200px" />
        <v-select v-model="form.category" :items="CATEGORIES" style="max-width: 240px" />
        <v-text-field v-model="form.version" placeholder="Version / Stand" style="max-width: 150px" />
        <v-text-field v-model="form.valid_from" placeholder="Gültig ab (TT.MM.JJJJ)" style="max-width: 190px" />
      </div>

      <v-checkbox v-model="form.mandatory" class="mb-2" label="Lesebestätigung erforderlich (benachrichtigt alle Schiedsrichter)" />

      <div class="gap-row">
        <v-file-input
          v-model="file"
          accept="application/pdf,.pdf"
          density="compact"
          hide-details
          label="PDF-Datei"
          prepend-icon=""
          style="max-width: 320px"
          variant="outlined"
        />

        <v-btn color="primary" :loading="store.busy" @click="upload()">Hochladen</v-btn>
        <span class="text-caption text-soft">nur PDF, bis 20 MB</span>
      </div>
    </v-card>

    <v-text-field v-model="q" class="mb-4" placeholder="Regelwerke durchsuchen …" prepend-inner-icon="mdi-magnify" />

    <v-card v-if="groups.length === 0" class="pa-4 text-body-2 text-soft">
      {{ q ? 'Keine Treffer.' : isAdmin ? 'Noch keine Regelwerke eingestellt — lade oben das erste PDF hoch.' : 'Aktuell sind noch keine Regelwerke hinterlegt.' }}
    </v-card>

    <div v-for="g in groups" :key="g.category" class="mb-5">
      <div class="text-caption text-uppercase font-weight-bold text-soft mb-2">{{ g.category }}</div>

      <v-card v-for="d in g.items" :key="d.id" class="pa-4 mb-2">
        <div class="d-flex align-center ga-3">
          <v-avatar color="surface-variant" rounded="lg" size="36"><v-icon color="primary" icon="mdi-file-document-outline" size="18" /></v-avatar>

          <div class="flex-grow-1">
            <div class="gap-row text-body-2 font-weight-medium">
              {{ d.title }}
              <v-chip v-if="Date.now() - new Date(d.updated_at).getTime() < 30 * 86_400_000" color="accent">Aktualisiert</v-chip>
              <v-chip v-if="d.mandatory" color="primary">Pflicht</v-chip>
            </div>

            <div class="text-caption text-soft">Version {{ d.version }}{{ d.valid_from ? ` · gültig ab ${d.valid_from}` : '' }} · {{ fmtSize(d.size) }} · eingestellt am {{ new Date(d.updated_at).toLocaleDateString('de-DE') }}</div>
          </div>

          <v-btn color="primary" size="small" @click="open(d)">Öffnen</v-btn>
          <v-btn color="primary" size="small" variant="outlined" @click="download(d)">Speichern</v-btn>
        </div>

        <template v-if="isReferee && d.mandatory">
          <v-divider class="my-3" />

          <div v-if="needsRead(d)" class="gap-row">
            <v-chip color="warning">Bestätigung offen</v-chip>
            <v-btn color="primary" size="small" variant="outlined" @click="confirmRead(d)">Gelesen &amp; verstanden</v-btn>
          </div>

          <div v-else class="text-body-2 text-success"><v-icon icon="mdi-check-circle-outline" size="16" /> Kenntnisnahme bestätigt</div>
        </template>

        <template v-if="isAdmin">
          <v-divider class="my-3" />

          <div v-if="d.mandatory" class="text-caption mb-2" :class="pendingReaders(d).length > 0 ? 'text-amber' : 'text-success'">
            Lesebestätigung: {{ store.referees.length - pendingReaders(d).length }} von {{ store.referees.length }}{{ pendingReaders(d).length > 0 ? ` · ausstehend: ${pendingReaders(d).join(', ')}` : ' · alle bestätigt' }}
          </div>

          <div v-if="versionId === d.id" class="gap-row">
            <v-text-field v-model="form.version" placeholder="Neue Version / Stand" style="max-width: 170px" />
            <v-text-field v-model="form.valid_from" placeholder="Gültig ab" style="max-width: 140px" />
            <v-checkbox v-model="form.mandatory" label="Lesebestätigung" />

            <v-file-input
              v-model="file"
              accept="application/pdf,.pdf"
              density="compact"
              hide-details
              label="PDF-Datei"
              prepend-icon=""
              style="max-width: 260px"
              variant="outlined"
            />

            <v-btn color="primary" :loading="store.busy" size="small" @click="upload(d.id)">Hochladen</v-btn>
            <v-btn size="small" variant="outlined" @click="versionId = null">Abbrechen</v-btn>
          </div>

          <div v-else class="gap-row">
            <v-btn
              class="px-0"
              color="primary"
              size="small"
              variant="text"
              @click="startVersion(d)"
            >Neue Version hochladen</v-btn>

            <ConfirmAction label="Löschen" size="small" text="Regelwerk samt allen früheren Versionen endgültig löschen?" @confirm="store.act('DELETE', `/rulebooks/${d.id}`)" />

            <span v-if="d.versions.length > 0" class="text-caption text-soft">
              Frühere Versionen:
              <a v-for="v in d.versions" :key="v.id" class="text-primary cursor-pointer mr-2" @click="open(d, v)">{{ v.version }}</a>
            </span>
          </div>
        </template>
      </v-card>
    </div>

    <!-- Anzeige im integrierten PDF-Betrachter des Browsers (mit Suche, Zoom und Druck) -->
    <v-dialog v-model="viewer.open" fullscreen>
      <v-card>
        <v-toolbar color="primary" density="compact">
          <v-toolbar-title class="text-body-1">{{ viewer.title }} <span class="opacity-70">· {{ viewer.label }}</span></v-toolbar-title>

          <template v-if="viewerDoc && isReferee && viewerDoc.mandatory && !viewer.old">
            <v-btn v-if="needsRead(viewerDoc)" variant="tonal" @click="confirmRead(viewerDoc)">Gelesen &amp; verstanden</v-btn>
            <span v-else class="text-body-2 mr-4">✓ Kenntnisnahme bestätigt</span>
          </template>

          <v-btn icon="mdi-close" @click="closeViewer" />
        </v-toolbar>

        <iframe
          v-if="viewer.url"
          class="flex-grow-1"
          :src="viewer.url"
          style="border: 0; width: 100%; height: calc(100vh - 48px)"
          :title="viewer.title"
        />
      </v-card>
    </v-dialog>
  </div>
</template>

<script lang="ts" setup>
  import type { Rulebook } from '@/types'
  import { computed, reactive, ref } from 'vue'
  import { api } from '@/api'
  import ConfirmAction from '@/components/ConfirmAction.vue'
  import PageHeader from '@/components/PageHeader.vue'
  import { useAppStore } from '@/stores/app'

  const CATEGORIES = ['Spielregeln', 'Wettspielordnung', 'Durchführungsbestimmungen', 'Schiedsrichter-Leitfaden', 'Formulare', 'Sonstiges']
  const store = useAppStore()
  const isAdmin = computed(() => store.can('regel_verwalten'))
  const isReferee = computed(() => store.can('eigene'))
  const books = computed(() => store.state!.rulebooks)
  const reads = computed(() => store.state!.rulebook_reads)
  const fmtSize = (b: number) => (b >= 1_048_576 ? `${(b / 1_048_576).toFixed(1).replace('.', ',')} MB` : `${Math.max(1, Math.round(b / 1024))} KB`)

  const readVersion = (d: Rulebook, userId: number | null | undefined) => reads.value.find(r => r.rulebook_id === d.id && r.user_id === userId)?.version
  const needsRead = (d: Rulebook) => d.mandatory && readVersion(d, store.state!.me.id) !== d.version
  const openReads = computed(() => books.value.filter(d => needsRead(d)).length)
  const pendingReaders = (d: Rulebook) => store.referees.filter(r => readVersion(d, r.user_id) !== d.version).map(r => r.name)
  const confirmRead = (d: Rulebook) => store.act('POST', `/rulebooks/${d.id}/read`)

  const q = ref('')
  const groups = computed(() => {
    const t = q.value.trim().toLowerCase()
    const visible = books.value.filter(d => !t || `${d.title} ${d.category} ${d.filename}`.toLowerCase().includes(t))
    return CATEGORIES.map(category => ({ category, items: visible.filter(d => d.category === category) })).filter(g => g.items.length > 0)
  })

  const emptyForm = () => ({ title: '', category: CATEGORIES[0]!, version: '', valid_from: '', mandatory: false })
  const form = reactive(emptyForm())
  const file = ref<File | null>(null)
  const versionId = ref<number | null>(null)
  function startVersion (d: Rulebook) {
    versionId.value = d.id
    Object.assign(form, { version: '', valid_from: '', mandatory: d.mandatory })
    file.value = null
  }
  async function upload (id?: number) {
    if (!file.value) {
      store.toasts.push({ text: 'Bitte eine PDF-Datei auswählen.', color: 'error' })
      return
    }
    const data = new FormData()
    for (const [k, v] of Object.entries(id ? { version: form.version, valid_from: form.valid_from, mandatory: form.mandatory } : form)) {
      data.append(k, typeof v === 'boolean' ? (v ? '1' : '0') : v)
    }
    data.append('file', file.value)
    if (await store.act('POST', id ? `/rulebooks/${id}/versions` : '/rulebooks', data, id ? 'Neue Version eingestellt.' : 'Regelwerk hochgeladen — für alle Schiedsrichter sichtbar.')) {
      Object.assign(form, emptyForm())
      file.value = null
      versionId.value = null
    }
  }

  const fileUrl = (d: Rulebook, v?: Rulebook['versions'][number]) => `/rulebooks/${d.id}/file${v ? `/${v.id}` : ''}`
  const viewer = reactive({ open: false, url: '', title: '', label: '', docId: 0, old: false })
  const viewerDoc = computed(() => books.value.find(d => d.id === viewer.docId))
  async function open (d: Rulebook, v?: Rulebook['versions'][number]) {
    const blob = await api.blob(fileUrl(d, v))
    Object.assign(viewer, { open: true, url: URL.createObjectURL(blob), title: d.title, label: v ? `Version ${v.version} (frühere Fassung)` : `Version ${d.version}`, docId: d.id, old: !!v })
  }
  function closeViewer () {
    URL.revokeObjectURL(viewer.url)
    Object.assign(viewer, { open: false, url: '' })
  }
  async function download (d: Rulebook) {
    const a = document.createElement('a')
    a.href = URL.createObjectURL(await api.blob(fileUrl(d)))
    a.download = /\.pdf$/i.test(d.filename) ? d.filename : `${d.title}.pdf`
    a.click()
    URL.revokeObjectURL(a.href)
  }
</script>
