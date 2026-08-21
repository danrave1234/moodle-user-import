import type { ImportPreview, ImportResultData } from '../types/import'

export class ApiError extends Error {}

export async function previewUsers(file: File): Promise<ImportPreview> {
  return upload<ImportPreview>('/api/imports/preview', file)
}

export async function importUsers(file: File): Promise<ImportResultData> {
  return upload<ImportResultData>('/api/imports', file)
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
