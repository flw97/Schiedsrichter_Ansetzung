import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api, ApiError, setUnauthorizedHandler, token } from './api'

const memory = new Map<string, string>()

function jsonResponse (status: number, body: unknown) {
  return Response.json(body, { status })
}

beforeEach(() => {
  memory.clear()
  vi.stubGlobal('sessionStorage', {
    getItem: (key: string) => memory.get(key) ?? null,
    setItem: (key: string, value: string) => {
      memory.set(key, value)
    },
    removeItem: (key: string) => {
      memory.delete(key)
    },
  })
  setUnauthorizedHandler(() => {})
})

describe('silent refresh', () => {
  it('keeps a fresh access token and does not call refresh', async () => {
    token.set('access-1', 'refresh-1', 3600)
    const fetchMock = vi.fn(async (_url: string, _init?: RequestInit) => jsonResponse(200, { ok: true }))
    vi.stubGlobal('fetch', fetchMock)

    await expect(api.json('GET', '/state')).resolves.toEqual({ ok: true })

    expect(fetchMock).toHaveBeenCalledTimes(1)
    const [url, init] = fetchMock.mock.calls[0]
    expect(url).toBe('/api/state')
    expect((init?.headers as Record<string, string>).Authorization).toBe('Bearer access-1')
  })

  it('renews an expiring access token before the request', async () => {
    token.set('access-old', 'refresh-old', 0)
    const fetchMock = vi.fn(async (url: string, _init?: RequestInit) => {
      if (url === '/api/refresh') {
        return jsonResponse(200, { token: 'access-new', refresh_token: 'refresh-new', expires_in: 900 })
      }
      return jsonResponse(200, { ok: true })
    })
    vi.stubGlobal('fetch', fetchMock)

    await api.json('GET', '/state')

    expect(fetchMock.mock.calls.map(call => call[0])).toEqual(['/api/refresh', '/api/state'])
    expect((fetchMock.mock.calls[1][1]?.headers as Record<string, string>).Authorization).toBe('Bearer access-new')
    expect(token.get()).toBe('access-new')
    expect(token.refresh()).toBe('refresh-new')
  })

  it('retries once after 401 when refresh succeeds', async () => {
    token.set('access-old', 'refresh-old', 3600)
    let stateCalls = 0
    const fetchMock = vi.fn(async (url: string) => {
      if (url === '/api/refresh') {
        return jsonResponse(200, { token: 'access-new', refresh_token: 'refresh-new', expires_in: 900 })
      }
      stateCalls += 1
      return jsonResponse(stateCalls === 1 ? 401 : 200, { ok: true })
    })
    vi.stubGlobal('fetch', fetchMock)

    await expect(api.json('GET', '/state')).resolves.toEqual({ ok: true })
    expect(token.get()).toBe('access-new')
  })

  it('signs out when refresh is rejected', async () => {
    token.set('access-old', 'refresh-old', 0)
    const ended = vi.fn()
    setUnauthorizedHandler(ended)
    vi.stubGlobal('fetch', vi.fn(async () => jsonResponse(401, { message: 'Unauthenticated.' })))

    await expect(api.json('GET', '/state')).rejects.toBeInstanceOf(ApiError)
    expect(ended).toHaveBeenCalledOnce()
  })

  it('keeps the session when refresh cannot reach the server', async () => {
    token.set('access-old', 'refresh-old', 0)
    const ended = vi.fn()
    setUnauthorizedHandler(ended)
    const fetchMock = vi.fn(async (url: string) => {
      if (url === '/api/refresh') {
        throw new Error('offline')
      }
      return jsonResponse(200, { ok: true })
    })
    vi.stubGlobal('fetch', fetchMock)

    await expect(api.json('GET', '/state')).resolves.toEqual({ ok: true })
    expect(ended).not.toHaveBeenCalled()
    expect(token.refresh()).toBe('refresh-old')
  })
})
