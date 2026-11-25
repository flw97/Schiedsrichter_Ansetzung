<template>
  <v-app>
    <LoginPage v-if="!store.state" />

    <template v-else>
      <v-navigation-drawer color="primary" permanent width="220">
        <div class="pa-4 pb-2">
          <div class="d-flex align-center ga-2">
            <span class="dot" />
            <span class="font-display text-h6">Ansetzung</span>
          </div>

          <div class="font-mono text-caption text-uppercase opacity-60">{{ area }}</div>
        </div>

        <v-list density="compact" nav>
          <v-list-item
            v-for="n in nav"
            :key="n.path"
            :prepend-icon="n.meta.icon"
            :title="n.meta.title"
            :to="n.path"
          >
            <template v-if="badge(n.path)" #append>
              <v-chip color="accent" size="x-small" variant="flat">{{ badge(n.path) }}</v-chip>
            </template>
          </v-list-item>
        </v-list>

        <template #append>
          <v-list density="compact" nav>
            <v-list-item class="opacity-70" prepend-icon="mdi-logout" title="Abmelden" @click="store.logout()" />
          </v-list>
        </template>
      </v-navigation-drawer>

      <v-app-bar border density="compact" elevation="0" flat>
        <v-spacer />

        <v-menu location="bottom end" max-height="360">
          <template #activator="{ props }">
            <v-btn icon v-bind="props">
              <v-badge color="error" :content="pushes.length" :model-value="pushes.length > 0">
                <v-icon icon="mdi-bell-outline" />
              </v-badge>
            </v-btn>
          </template>

          <v-card min-width="300" width="320">
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

        <v-avatar class="font-mono mx-2" color="surface-variant" size="28">
          <span class="text-caption text-primary">{{ store.state.me.name.slice(0, 2).toUpperCase() }}</span>
        </v-avatar>

        <span class="text-body-2 text-soft mr-4">{{ store.state.me.name }}</span>
      </v-app-bar>

      <v-main>
        <v-container class="pa-8" fluid style="max-width: 1200px; margin: 0">
          <router-view />
        </v-container>
      </v-main>
    </template>

    <v-snackbar-queue v-model="store.toasts" location="top right" :timeout="4500">
      <template #text="{ item }">
        <div v-if="item.push" class="text-overline opacity-60" style="line-height: 1.4">Push-Benachrichtigung</div>
        {{ item.text }}
      </template>
    </v-snackbar-queue>
  </v-app>
</template>

<script lang="ts" setup>
  import { computed, onBeforeUnmount, watch } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import { token } from '@/api'
  import { fmtDateTime, isPast } from '@/domain/dates'
  import { classify, ROLE_LABEL } from '@/domain/rules'
  import LoginPage from '@/pages/LoginPage.vue'
  import { routes } from '@/router'
  import { useAppStore } from '@/stores/app'

  const store = useAppStore()
  const route = useRoute()
  const router = useRouter()

  const role = computed(() => store.state?.me.role)
  const area = computed(() => (role.value === 'schiri' ? 'Frontend · Schiedsrichter' : `Backend · ${ROLE_LABEL[role.value ?? 'admin']}`))
  const allowed = (r: (typeof routes)[number]) => !r.meta.perm || store.can(r.meta.perm)
  const nav = computed(() => {
    const side = role.value === 'schiri' ? 'schiri' : 'admin'
    return routes.filter(r => (r.meta.nav === side || r.meta.nav === 'both') && allowed(r))
  })
  const pushes = computed(() => store.state?.pushes ?? [])

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
    const match = routes.find(r => route.matched[0]?.path === r.path)
    if (store.state && (!match || !allowed(match))) {
      await router.replace(nav.value[0]?.path ?? '/')
    }
  }, { immediate: true })

  // Automatische Abmeldung nach 15 Minuten Inaktivität
  const IDLE_MS = 15 * 60 * 1000
  const EVENTS = ['mousemove', 'keydown', 'click', 'touchstart', 'scroll'] as const
  let timer: ReturnType<typeof setTimeout> | undefined
  function reset () {
    clearTimeout(timer)
    timer = setTimeout(() => store.logout(true), IDLE_MS)
  }
  watch(() => !!store.state, loggedIn => {
    for (const e of EVENTS) {
      (loggedIn ? window.addEventListener : window.removeEventListener)(e, reset, { passive: true } as AddEventListenerOptions)
    }
    loggedIn ? reset() : clearTimeout(timer)
  })
  onBeforeUnmount(() => clearTimeout(timer))

  // Bestehende Sitzung (Token im Session-Speicher) beim Neuladen der Seite übernehmen
  if (token.get()) {
    store.load().catch(() => {})
  }
</script>

<style scoped>
.dot { width: 8px; height: 8px; border-radius: 50%; background: rgb(var(--v-theme-accent)); display: inline-block; }
</style>
