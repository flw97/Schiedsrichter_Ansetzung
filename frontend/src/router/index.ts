/**
 * router/index.ts
 *
 * Seiten je Bereich; `perm` ist die benötigte Berechtigung (siehe backend/app/Support/Permissions.php).
 */

import { createRouter, createWebHistory, type RouteMeta, type RouteRecordRaw } from 'vue-router'

declare module 'vue-router' {
  interface RouteMeta { perm?: string, title?: string, icon?: string, nav?: 'admin' | 'schiri' | 'both' }
}

const pages = import.meta.glob(['@/pages/*.vue', '!@/pages/LoginPage.vue'])
const page = (name: string) => pages[`/src/pages/${name}.vue`]!

export const routes: (RouteRecordRaw & { path: string, meta: RouteMeta })[] = [
  { path: '/ansetzungen', component: page('AnsetzungenPage'), meta: { perm: 'einsehen', title: 'Abfragen (Ansetzung)', icon: 'mdi-calendar-month-outline', nav: 'admin' } },
  { path: '/ansetzungen/:id', component: page('GameDetailPage'), meta: { perm: 'einsehen' } },
  { path: '/import', component: page('ImportPage'), meta: { perm: 'ansetzen', title: 'NuLiga-Import', icon: 'mdi-sync', nav: 'admin' } },
  { path: '/statistik', component: page('StatistikPage'), meta: { perm: 'einsehen', title: 'Statistik', icon: 'mdi-chart-bar', nav: 'admin' } },
  { path: '/karten', component: page('KartenPage'), meta: { perm: 'einsehen', title: 'Karten & Notizen', icon: 'mdi-message-outline', nav: 'admin' } },
  { path: '/abfrage', component: page('AbfragePage'), meta: { perm: 'eigene', title: 'Abfrage', icon: 'mdi-inbox-outline', nav: 'schiri' } },
  { path: '/meine-ansetzungen', component: page('MeineAnsetzungenPage'), meta: { perm: 'eigene', title: 'Ansetzungen', icon: 'mdi-calendar-check-outline', nav: 'schiri' } },
  { path: '/archiv', component: page('ArchivPage'), meta: { title: 'Archiv', icon: 'mdi-archive-outline', nav: 'both' } },
  { path: '/saisonplanung', component: page('SaisonplanungPage'), meta: { perm: 'saisonplanung', title: 'Saisonplanung', icon: 'mdi-calendar-clock-outline', nav: 'admin' } },
  { path: '/lizenzen', component: page('LizenzenPage'), meta: { perm: 'personen', title: 'Lizenzen', icon: 'mdi-medal-outline', nav: 'admin' } },
  { path: '/schiedsrichter', component: page('SchiedsrichterPage'), meta: { perm: 'personen', title: 'Schiedsrichter', icon: 'mdi-account-group-outline', nav: 'admin' } },
  { path: '/beobachtung', component: page('BeobachtungPage'), meta: { perm: 'personen', title: 'Beobachtung', icon: 'mdi-trending-up', nav: 'admin' } },
  { path: '/regelwerke', component: page('RegelwerkePage'), meta: { perm: 'regel_lesen', title: 'Regelwerke', icon: 'mdi-book-open-outline', nav: 'both' } },
  { path: '/unterkuenfte', component: page('UnterkuenftePage'), meta: { perm: 'unterkunft_lesen', title: 'Unterkünfte', icon: 'mdi-bed-outline', nav: 'both' } },
  { path: '/standorte', component: page('StandortePage'), meta: { perm: 'einsehen', title: 'Standorte', icon: 'mdi-map-marker-outline', nav: 'admin' } },
  { path: '/meine-daten', component: page('MeineDatenPage'), meta: { perm: 'eigene', title: 'Meine Daten', icon: 'mdi-account-outline', nav: 'schiri' } },
  { path: '/rollen', component: page('RollenPage'), meta: { perm: 'rollen', title: 'Rollen', icon: 'mdi-shield-check-outline', nav: 'admin' } },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [...routes, { path: '/:pathMatch(.*)*', component: { render: () => null } }],
})

export default router
