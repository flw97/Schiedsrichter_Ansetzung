// Zentraler Zustand: wird nach jeder Aktion vollständig neu vom Backend geladen (kleine Datenmengen je Verband)

import type { AppState, Referee } from '@/types'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, ApiError, setUnauthorizedHandler, token } from '@/api'
import { abfrageStatus, isPast } from '@/domain/dates'
import { indexResponses } from '@/domain/rules'

export interface Toast { text: string, color?: string, push?: boolean }

export const useAppStore = defineStore('app', () => {
  const state = ref<AppState | null>(null)
  const busy = ref(false)
  const toasts = ref<Toast[]>([])
  const sessionNote = ref('')

  const can = (perm: string) => !!state.value?.me.permissions.includes(perm)
  const referees = computed(() => state.value?.referees ?? [])
  const games = computed(() => state.value?.games ?? [])
  const refById = computed(() => new Map(referees.value.map(r => [r.id, r])))
  const refName = (id: number | null | undefined) => (id ? refById.value.get(id)?.name ?? '?' : '')
  const me = computed<Referee | null>(() => refById.value.get(state.value?.me.referee_id ?? -1) ?? null)
  const responses = computed(() => indexResponses(state.value?.responses ?? []))
  const activeGames = computed(() => games.value.filter(g => g.imported && !isPast(g)))
  const abfrage = computed(() => abfrageStatus(state.value?.settings.abfrage_from ?? null, state.value?.settings.abfrage_to ?? null))

  function clear (note = '') {
    token.set(null)
    state.value = null
    sessionNote.value = note
  }
  setUnauthorizedHandler(() => clear('Deine Sitzung ist abgelaufen. Bitte erneut anmelden.'))

  async function load () {
    state.value = await api.json<AppState>('GET', '/state')
  }

  const notify = (texts: string[]) => toasts.value.push(...texts.map(text => ({ text, push: true })))

  async function login (username: string, password: string) {
    const res = await api.json<{ token: string, pushes: string[] }>('POST', '/login', { username, password })
    token.set(res.token)
    sessionNote.value = ''
    await load()
    notify(res.pushes)
  }

  async function logout (timeout = false) {
    await api.json('POST', '/logout', { timeout }).catch(() => {})
    clear(timeout ? 'Aus Sicherheitsgründen wurdest du nach 15 Minuten Inaktivität abgemeldet.' : '')
  }

  /** Aktion ausführen: zeigt verschickte Push-Mitteilungen bzw. Fehler an und lädt den Zustand neu. Gibt bei Erfolg true zurück. */
  async function act (method: string, path: string, body?: unknown, success?: string): Promise<boolean> {
    busy.value = true
    try {
      const res = await api.json<{ pushes: string[] }>(method, path, body)
      await load()
      notify(res.pushes)
      if (success) {
        toasts.value.push({ text: success, color: 'success' })
      }
      return true
    } catch (error) {
      toasts.value.push({ text: error instanceof ApiError ? error.message : 'Aktion fehlgeschlagen. Bitte erneut versuchen.', color: 'error' })
      return false
    } finally {
      busy.value = false
    }
  }

  return { state, busy, toasts, sessionNote, can, referees, games, refById, refName, me, responses, activeGames, abfrage, load, login, logout, act }
})
