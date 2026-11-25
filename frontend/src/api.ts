// Zugriff auf das Laravel-Backend (/api, im Dev-Server per Vite-Proxy). Token liegt nur in der Browser-Sitzung.

const TOKEN_KEY = 'ansetzung-token'

export class ApiError extends Error {
  constructor (public status: number, message: string) {
    super(message)
  }
}

let onUnauthorized: () => void = () => {}
export function setUnauthorizedHandler (fn: () => void) {
  onUnauthorized = fn
}

export const token = {
  get: () => sessionStorage.getItem(TOKEN_KEY),
  set: (t: string | null) => (t ? sessionStorage.setItem(TOKEN_KEY, t) : sessionStorage.removeItem(TOKEN_KEY)),
}

async function request (method: string, path: string, body?: unknown): Promise<Response> {
  const headers: Record<string, string> = { Accept: 'application/json' }
  const t = token.get()
  if (t) {
    headers.Authorization = `Bearer ${t}`
  }
  if (body !== undefined && !(body instanceof FormData)) {
    headers['Content-Type'] = 'application/json'
  }
  const res = await fetch(`/api${path}`, {
    method,
    headers,
    body: body instanceof FormData ? body : (body === undefined ? undefined : JSON.stringify(body)),
  })
  if (res.status === 401 && t) {
    onUnauthorized()
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
