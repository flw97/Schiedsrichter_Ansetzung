// Zentraler Zustand: wird nach jeder Aktion vollständig neu vom Backend geladen (kleine Datenmengen je Verband).
// Push-Mitteilungen kommen zusätzlich einmal pro Sekunde, damit sie auch ohne eigene Aktion erscheinen.

import type { AppState, Referee } from '@/types'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, ApiError, SESSION_ENDED, setUnauthorizedHandler, token } from '@/api'
import { abfrageStatus, isPast } from '@/domain/dates'
import { indexResponses } from '@/domain/rules'
import { notifyError, notifyPush, notifySuccess } from '@/notify'

type Notice = AppState['pushes'][number]

const POLL_MS = 1000
const PUSH_DEDUP_MS = 4000

export const useAppStore = defineStore('app', () => {
  const state = ref<AppState | null>(null)
  const busy = ref(false)

  let pollTimer: ReturnType<typeof setInterval> | undefined
  let sessionEnded = false
  let pulling = false
  let primed = false
  let seenNotificationId = 0
  const pushSeenAt = new Map<string, number>()

  const can = (perm: string) => !!state.value?.me.permissions.includes(perm)
  const referees = computed(() => state.value?.referees ?? [])
  const games = computed(() => state.value?.games ?? [])
  const refById = computed(() => new Map(referees.value.map(r => [r.id, r])))
  const refName = (id: number | null | undefined) => (id ? refById.value.get(id)?.name ?? '?' : '')
  const me = computed<Referee | null>(() => refById.value.get(state.value?.me.referee_id ?? -1) ?? null)
  const responses = computed(() => indexResponses(state.value?.responses ?? []))
  const activeGames = computed(() => games.value.filter(g => g.imported && !isPast(g)))
  const abfrage = computed(() => abfrageStatus(state.value?.settings.abfrage_from ?? null, state.value?.settings.abfrage_to ?? null))

  function clear () {
    stopPoll()
    token.set(null)
    state.value = null
  }
  setUnauthorizedHandler(() => {
    if (sessionEnded) {
      return
    }
    sessionEnded = true
    clear()
    notifyError(SESSION_ENDED)
  })

  function noteSeen () {
    for (const notice of state.value?.pushes ?? []) {
      if (notice.id > seenNotificationId) {
        seenNotificationId = notice.id
      }
    }
  }

  function showPush (text: string) {
    const now = Date.now()
    if ((pushSeenAt.get(text) ?? 0) > now) {
      return
    }
    pushSeenAt.set(text, now + PUSH_DEDUP_MS)
    notifyPush(text)
  }

  function mergeNotices (items: Notice[]) {
    if (!state.value || items.length === 0) {
      return
    }
    const known = new Set(state.value.pushes.map(notice => notice.id))
    const added = items.filter(notice => !known.has(notice.id))
    if (added.length === 0) {
      return
    }
    state.value.pushes = added.toReversed().concat(state.value.pushes).slice(0, 30)
  }

  async function pullNotifications () {
    if (pulling || !token.get() || !state.value) {
      return
    }
    pulling = true
    try {
      const res = await api.json<{ notifications: Notice[] }>('GET', `/notifications?after=${seenNotificationId}`)
      if (!state.value) {
        return
      }
      const fresh = res.notifications.filter(notice => notice.id > seenNotificationId)
      if (fresh.length === 0) {
        return
      }
      mergeNotices(fresh)
      noteSeen()
      for (const notice of fresh) {
        showPush(notice.text)
      }
    } catch {
      // Der nächste Versuch folgt in einer Sekunde. 401 räumt die Sitzung über den api-Handler ab.
    } finally {
      pulling = false
    }
  }

  function armPoll () {
    if (pollTimer !== undefined) {
      return
    }
    pollTimer = setInterval(() => {
      void pullNotifications()
    }, POLL_MS)
  }

  function stopPoll () {
    clearInterval(pollTimer)
    pollTimer = undefined
    pulling = false
    primed = false
    seenNotificationId = 0
    pushSeenAt.clear()
  }

  async function load (): Promise<Notice[]> {
    const prev = seenNotificationId
    const first = !primed
    state.value = await api.json<AppState>('GET', '/state')
    primed = true
    const arrived = first ? [] : state.value.pushes.filter(notice => notice.id > prev).toSorted((a, b) => a.id - b.id)
    noteSeen()
    armPoll()
    return arrived
  }

  async function login (username: string, password: string) {
    const res = await api.json<{ token: string, refresh_token: string, expires_in: number, pushes: string[] }>('POST', '/login', { username, password })
    sessionEnded = false
    token.set(res.token, res.refresh_token, res.expires_in)
    await load()
    for (const text of res.pushes) {
      showPush(text)
    }
  }

  async function logout () {
    const refresh = token.refresh()
    if (refresh) {
      await fetch('/api/logout', {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: refresh }),
      }).catch(() => {})
    }
    clear()
  }

  /** Aktion ausführen: Erfolg und Fehler als Toast, verschickte Push-Mitteilungen ebenfalls. Lädt den Zustand neu. */
  async function act (method: string, path: string, body?: unknown, success?: string): Promise<boolean> {
    busy.value = true
    try {
      const res = await api.json<{ pushes: string[] }>(method, path, body)
      const arrived = await load()
      const sent = new Set(res.pushes)
      for (const notice of arrived) {
        if (!sent.has(notice.text)) {
          showPush(notice.text)
        }
      }
      for (const text of res.pushes) {
        showPush(text)
      }
      if (success) {
        notifySuccess(success)
      }
      return true
    } catch (error) {
      if (!(error instanceof ApiError && error.message === SESSION_ENDED)) {
        notifyError(error instanceof ApiError ? error.message : 'Aktion fehlgeschlagen. Bitte erneut versuchen.')
      }
      return false
    } finally {
      busy.value = false
    }
  }

  return { state, busy, can, referees, games, refById, refName, me, responses, activeGames, abfrage, load, login, logout, act }
})
