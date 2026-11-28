// Zugriff auf das Laravel-Backend (/api, im Dev-Server per Vite-Proxy).
// Zugangs- und Refresh-Token liegen nur in der Browser-Sitzung. Kurz vor Ablauf wird das Zugangs-Token
// still erneuert. Lehnt der Server das Refresh-Token ab, endet die Sitzung.

const TOKEN_KEY = 'ansetzung-token'
const REFRESH_KEY = 'ansetzung-refresh'
const EXPIRES_KEY = 'ansetzung-exp'
const REFRESH_LEAD_MS = 60_000

export class ApiError extends Error {
  constructor (public status: number, message: string) {
    super(message)
  }
}

export const SESSION_ENDED = 'Deine Sitzung ist abgelaufen. Bitte erneut anmelden.'

let onUnauthorized: () => void = () => {}
export function setUnauthorizedHandler (fn: () => void) {
  onUnauthorized = fn
}

export const token = {
  get: () => sessionStorage.getItem(TOKEN_KEY),
  refresh: () => sessionStorage.getItem(REFRESH_KEY),
  set (access: string | null, refresh?: string | null, expiresIn?: number) {
    if (!access) {
      sessionStorage.removeItem(TOKEN_KEY)
      sessionStorage.removeItem(REFRESH_KEY)
      sessionStorage.removeItem(EXPIRES_KEY)
      return
    }
    sessionStorage.setItem(TOKEN_KEY, access)
    if (refresh) {
      sessionStorage.setItem(REFRESH_KEY, refresh)
    }
    if (expiresIn != null) {
      sessionStorage.setItem(EXPIRES_KEY, String(Date.now() + expiresIn * 1000))
    }
  },
}

const SESSION_FREE = new Set(['/login', '/refresh', '/logout', '/password/forgot', '/password/reset'])

function isSessionFree (path: string) {
  return SESSION_FREE.has(path.split('?', 2)[0])
}

function expiresSoon () {
  const exp = Number(sessionStorage.getItem(EXPIRES_KEY) ?? 0)
  return !token.get() || exp - Date.now() <= REFRESH_LEAD_MS
}

type RefreshResult = 'ok' | 'rejected' | 'offline'

let refreshing: Promise<RefreshResult> | null = null

function refreshSession (): Promise<RefreshResult> {
  if (!token.refresh()) {
    return Promise.resolve('rejected')
  }
  refreshing ??= performRefresh().finally(() => {
    refreshing = null
  })
  return refreshing
}

async function performRefresh (): Promise<RefreshResult> {
  const current = token.refresh()
  if (!current) {
    return 'rejected'
  }
  try {
    const res = await fetch('/api/refresh', {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ refresh_token: current }),
    })
    if (res.status === 401 || res.status === 422) {
      return 'rejected'
    }
    if (!res.ok) {
      return 'offline'
    }
    const data = await res.json() as { token?: string, refresh_token?: string, expires_in?: number }
    if (!data.token || !data.refresh_token || token.refresh() !== current) {
      return 'rejected'
    }
    token.set(data.token, data.refresh_token, data.expires_in)
    return 'ok'
  } catch {
    return 'offline'
  }
}

async function request (method: string, path: string, body?: unknown, retry = true): Promise<Response> {
  if (retry && !isSessionFree(path) && token.refresh() && expiresSoon()) {
    const refreshed = await refreshSession()
    if (refreshed === 'rejected') {
      onUnauthorized()
      throw new ApiError(401, SESSION_ENDED)
    }
  }
  const headers: Record<string, string> = { Accept: 'application/json' }
  const access = token.get()
  if (access && !isSessionFree(path)) {
    headers.Authorization = `Bearer ${access}`
  }
  if (body !== undefined && !(body instanceof FormData)) {
    headers['Content-Type'] = 'application/json'
  }
  const res = await fetch(`/api${path}`, {
    method,
    headers,
    body: body instanceof FormData ? body : (body === undefined ? undefined : JSON.stringify(body)),
  })
  if (res.status === 401 && retry && !isSessionFree(path) && token.refresh()) {
    const refreshed = await refreshSession()
    if (refreshed === 'ok') {
      return request(method, path, body, false)
    }
    if (refreshed === 'rejected') {
      onUnauthorized()
      throw new ApiError(401, SESSION_ENDED)
    }
  }
  if (!res.ok) {
    const data = await res.json().catch(() => ({}))
    const first = data.errors ? Object.values(data.errors as Record<string, string[]>)[0]?.[0] : null
    throw new ApiError(res.status, first ?? data.message ?? `Fehler ${res.status}`)
  }
  return res
}

export const api = {
  json: async <T>(method: string, path: string, body?: unknown): Promise<T> => (await request(method, path, body)).json(),
  blob: async (path: string): Promise<Blob> => (await request('GET', path)).blob(),
}
