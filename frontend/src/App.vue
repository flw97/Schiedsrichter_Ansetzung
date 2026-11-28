<template>
  <v-app>
    <router-view v-if="isPublic" />

    <LoginPage v-else-if="!restoring && !store.state" />

    <div v-else-if="restoring" class="restore">
      <img alt="" src="/logo.svg?v=5">
    </div>

    <template v-else-if="store.state">
      <v-navigation-drawer
        v-model="navOpen"
        class="app-nav"
        color="brand"
        :permanent="mdAndUp"
        :temporary="!mdAndUp"
        width="260"
      >
        <div class="brand">
          <img alt="" class="brand-logo" src="/logo.svg?v=5">

          <div>
            <div class="brand-name">Ansetzung</div>
            <div class="brand-role">{{ area }}</div>
          </div>
        </div>

        <div v-for="group in groups" :key="group.label">
          <div class="nav-label">{{ group.label }}</div>

          <v-list density="compact" nav>
            <v-list-item
              v-for="n in group.items"
              :key="n.path"
              :prepend-icon="n.meta.icon"
              :title="n.meta.title"
              :to="n.path"
            >
              <template v-if="badge(n.path)" #append>
                <v-chip class="nav-badge" size="x-small" variant="flat">{{ badge(n.path) }}</v-chip>
              </template>
            </v-list-item>
          </v-list>
        </div>
      </v-navigation-drawer>

      <v-app-bar
        class="app-bar"
        density="comfortable"
        elevation="0"
        flat
        height="64"
      >
        <v-app-bar-nav-icon v-if="!mdAndUp" @click="navOpen = !navOpen" />

        <v-spacer />

        <ThemeToggle />

        <v-menu location="bottom end" max-height="360">
          <template #activator="{ props }">
            <v-btn aria-label="Benachrichtigungen" icon v-bind="props" variant="text">
              <v-badge color="error" :content="pushes.length" :model-value="pushes.length > 0">
                <v-icon icon="mdi-bell-outline" />
              </v-badge>
            </v-btn>
          </template>

          <v-card min-width="300" width="340">
            <v-card-title class="text-subtitle-2">Benachrichtigungen</v-card-title>
            <v-divider />
            <div v-if="pushes.length === 0" class="pa-4 text-body-2 text-soft">Keine neuen Benachrichtigungen</div>

            <v-list v-else density="compact">
              <v-list-item v-for="p in pushes" :key="p.id" :subtitle="fmtDateTime(p.created_at)">
                <div class="text-body-2" style="white-space: normal">{{ p.text }}</div>
              </v-list-item>
            </v-list>
          </v-card>
        </v-menu>

        <v-menu location="bottom end">
          <template #activator="{ props }">
            <button aria-label="Konto" class="user" type="button" v-bind="props">
              <v-avatar color="primary" size="32">
                <span class="user-initials">{{ initials }}</span>
              </v-avatar>

              <span class="user-name">{{ store.state.me.name }}</span>
              <v-icon class="user-chevron" icon="mdi-chevron-down" size="18" />
            </button>
          </template>

          <v-list class="user-menu" density="compact">
            <v-list-item
              v-for="item in accountItems"
              :key="item.to"
              :prepend-icon="item.icon"
              :title="item.title"
              :to="item.to"
            />

            <v-divider v-if="accountItems.length > 0" class="my-1" />
            <v-list-item prepend-icon="mdi-logout" title="Abmelden" @click="store.logout()" />
          </v-list>
        </v-menu>
      </v-app-bar>

      <v-main>
        <div class="page-shell">
          <router-view />
        </div>
      </v-main>
    </template>
  </v-app>
</template>

<script lang="ts" setup>
  import { computed, ref, watch } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import { useDisplay } from 'vuetify'
  import { token } from '@/api'
  import ThemeToggle from '@/components/ThemeToggle.vue'
  import { fmtDateTime, isPast } from '@/domain/dates'
  import { classify, ROLE_LABEL } from '@/domain/rules'
  import LoginPage from '@/pages/LoginPage.vue'
  import { isPublicPath, routes } from '@/router'
  import { useAppStore } from '@/stores/app'
  import { useThemeChoice } from '@/theme'

  useThemeChoice()

  const store = useAppStore()
  const route = useRoute()
  const router = useRouter()
  const isPublic = computed(() => isPublicPath(route.path))
  const { mdAndUp } = useDisplay()
  const navOpen = ref(true)

  const role = computed(() => store.state?.me.role)
  const area = computed(() => ROLE_LABEL[role.value ?? 'admin'])
  const initials = computed(() => store.state?.me.name.slice(0, 2).toUpperCase() ?? '')
  const allowed = (r: (typeof routes)[number]) => !r.meta.perm || store.can(r.meta.perm)
  const nav = computed(() => {
    const side = role.value === 'schiri' ? 'schiri' : 'admin'
    return routes.filter(r => (r.meta.nav === side || r.meta.nav === 'both') && allowed(r))
  })
  const pushes = computed(() => store.state?.pushes ?? [])
  const accountItems = [
    { title: 'Benutzer', icon: 'mdi-account-outline', to: '/mein-benutzer' },
    { title: 'Lizenz', icon: 'mdi-medal-outline', to: '/meine-lizenzen' },
    { title: 'Standort', icon: 'mdi-map-marker-outline', to: '/mein-standort' },
  ]

  const NAV_GROUPS = {
    admin: [
      { label: 'Planung', paths: ['/ansetzungen', '/import', '/saisonplanung'] },
      { label: 'Personen', paths: ['/statistik', '/schiedsrichter', '/lizenzen', '/beobachtung', '/rollen'] },
      { label: 'Betrieb', paths: ['/karten', '/archiv', '/regelwerke', '/unterkuenfte', '/standorte'] },
    ],
    schiri: [
      { label: 'Einsätze', paths: ['/abfrage', '/meine-ansetzungen', '/archiv'] },
      { label: 'Persönlich', paths: ['/regelwerke', '/unterkuenfte', '/meine-daten'] },
    ],
  } as const

  const groups = computed(() => {
    const side = role.value === 'schiri' ? 'schiri' : 'admin'
    const byPath = new Map(nav.value.map(item => [item.path, item]))
    const used = new Set<string>()
    const grouped: { label: string, items: typeof nav.value }[] = NAV_GROUPS[side].map(section => {
      const items = section.paths.flatMap(path => {
        const item = byPath.get(path)
        if (!item) {
          return []
        }
        used.add(path)
        return [item]
      })
      return { label: section.label, items }
    }).filter(section => section.items.length > 0)
    const rest = nav.value.filter(item => !used.has(item.path))
    if (rest.length > 0) {
      grouped.push({ label: 'Weiteres', items: rest })
    }
    return grouped
  })

  // Offene Punkte je Bereich als Zähler in der Navigation
  function badge (path: string) {
    const s = store.state!
    if (path === '/import') {
      return store.games.filter(g => g.nuliga_change && classify(g).standard && !isPast(g)).length
    }
    if (path === '/lizenzen') {
      return store.referees.filter(r => {
        const n = s.nuliga_licenses?.[r.id]
        return n && (n.license !== r.license || n.valid_until !== r.license_valid_until)
      }).length
    }
    if (path === '/regelwerke' && role.value === 'schiri') {
      return s.rulebooks.filter(b => b.mandatory && s.rulebook_reads.find(x => x.rulebook_id === b.id)?.version !== b.version).length
    }
    return 0
  }

  // Unbekannte oder nicht erlaubte Seiten führen zur ersten Seite der Navigation
  watch([() => route.path, nav], async () => {
    await router.isReady()
    if (isPublicPath(route.path)) {
      return
    }
    const match = routes.find(r => route.matched[0]?.path === r.path)
    if (store.state && (!match || !allowed(match))) {
      await router.replace(nav.value[0]?.path ?? '/')
    }
  }, { immediate: true })

  watch(mdAndUp, open => {
    navOpen.value = open
  })
  watch(() => route.path, () => {
    if (!mdAndUp.value) {
      navOpen.value = false
    }
  })

  // Bestehende Sitzung übernehmen, ohne das Anmeldeformular zu zeigen, bis /state geantwortet hat.
  // Ein abgelaufenes Zugangs-Token wird dabei über das Refresh-Token erneuert.
  const restoring = ref(token.get() !== null || token.refresh() !== null)
  if (restoring.value) {
    store.load().finally(() => {
      restoring.value = false
    })
  }
</script>

<style scoped>
.restore {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--green);
}
.restore img { width: 72px; height: 72px; object-fit: contain; }

.app-nav { border-right: 0 !important; color: #fff; }
.brand {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 22px 20px 8px;
}
.brand-logo { width: 46px; height: 46px; object-fit: contain; flex-shrink: 0; }
.brand-name {
  font-size: 1.05rem;
  font-weight: 600;
  letter-spacing: -0.028em;
  line-height: 1.15;
  color: #fff;
}
.brand-role { margin-top: 2px; font-size: 0.75rem; color: rgba(255, 255, 255, 0.72); }
.nav-label {
  padding: 16px 22px 2px;
  font-size: 0.68rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.55);
}
.app-nav :deep(.v-list) { background: transparent; color: #fff; }
.app-nav :deep(.v-list-item) {
  border-radius: 10px !important;
  margin-bottom: 2px;
  min-height: 40px;
  color: rgba(255, 255, 255, 0.88);
}
.app-nav :deep(.v-list-item .v-list-item-title),
.app-nav :deep(.v-list-item .v-icon) { color: inherit; opacity: 1; }
.app-nav :deep(.v-list-item--active) { background: rgba(255, 255, 255, 0.14); color: #fff; }
.app-nav :deep(.v-list-item--active .v-list-item-title) { font-weight: 600; color: #fff; }
.app-nav :deep(.v-list-item:not(.v-list-item--active):hover) { background: rgba(255, 255, 255, 0.08); }
.nav-badge { background: #fff !important; color: var(--green) !important; }

.app-bar { border-bottom: 1px solid var(--line) !important; background: var(--surface) !important; }
.user {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0 16px 0 8px;
  padding: 4px 10px 4px 4px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: var(--surface);
  color: inherit;
  font: inherit;
  cursor: pointer;
}
.user:hover,
.user:focus-visible { background: var(--paper); }
.user-initials { font-size: 0.7rem; font-weight: 600; letter-spacing: 0.03em; color: rgb(var(--v-theme-on-primary)); }
.user-name { font-size: 0.875rem; font-weight: 500; color: var(--ink); }
.user-chevron { color: var(--ink-soft); }
.user-menu { min-width: 180px; }

.page-shell {
  max-width: 1180px;
  margin-inline: auto;
  padding: 32px 32px 64px;
}
@media (max-width: 600px) {
  .page-shell { padding: 20px 16px 48px; }
  .user-name { display: none; }
}
</style>
