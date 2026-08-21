import type { ImportPreview, ImportResultData } from '../types/import'

const PREVIEW_CACHE_TTL_MS = 30_000
const PREVIEW_CACHE_LIMIT = 5

type CacheEntry = {
  expiresAt: number
  preview: ImportPreview
}

type PreviewResponse = {
  preview: ImportPreview
  cached: boolean
}

const previewCache = new Map<string, CacheEntry>()

export class ApiError extends Error {}

export async function previewUsers(file: File): Promise<PreviewResponse> {
  const key = fileKey(file)
  const cached = previewCache.get(key)

  if (cached && cached.expiresAt > Date.now()) {
    return { preview: cached.preview, cached: true }
  }

  previewCache.delete(key)
  const preview = await upload<ImportPreview>('/api/imports/preview', file)
  rememberPreview(key, preview)

  return { preview, cached: false }
}

export async function importUsers(file: File): Promise<ImportResultData> {
  const result = await upload<ImportResultData>('/api/imports', file)
  clearPreviewCache()

  return result
}

export function clearPreviewCache(): void {
  previewCache.clear()
}

async function upload<T>(url: string, file: File): Promise<T> {
  const body = new FormData()
  body.append('file', file)

  let response: Response
  try {
    response = await fetch(url, {
      method: 'POST',
      body,
      headers: { Accept: 'application/json' },
    })
  } catch {
    throw new ApiError('Could not reach the import service. Check that the backend is running.')
  }

  const payload = (await response.json().catch(() => null)) as unknown
  if (!response.ok) {
    const message = readErrorMessage(payload)
    throw new ApiError(message ?? 'The file could not be processed. Please check it and try again.')
  }

  return payload as T
}

function fileKey(file: File): string {
  return `${file.name}:${file.size}:${file.lastModified}`
}

function rememberPreview(key: string, preview: ImportPreview): void {
  if (previewCache.size >= PREVIEW_CACHE_LIMIT) {
    const oldestKey = previewCache.keys().next().value
    if (typeof oldestKey === 'string') {
      previewCache.delete(oldestKey)
    }
  }

  previewCache.set(key, { preview, expiresAt: Date.now() + PREVIEW_CACHE_TTL_MS })
}

function readErrorMessage(payload: unknown): string | null {
  if (
    typeof payload === 'object' &&
    payload !== null &&
    'error' in payload &&
    typeof payload.error === 'object' &&
    payload.error !== null &&
    'message' in payload.error &&
    typeof payload.error.message === 'string'
  ) {
    return payload.error.message
  }

  return null
}
