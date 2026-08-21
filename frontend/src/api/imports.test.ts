import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError, importUsers, previewUsers } from './imports'
import type { ImportPreview, ImportResultData } from '../types/import'

const firstPreview: ImportPreview = {
  summary: { total: 1, valid: 1, invalid: 0 },
  rows: [{ rowNumber: 2, name: 'John', surname: 'Smith', email: 'john@example.com', status: 'valid', errors: [] }],
}

const secondPreview: ImportPreview = {
  summary: { total: 1, valid: 1, invalid: 0 },
  rows: [{ rowNumber: 2, name: 'Jane', surname: 'Smith', email: 'jane@example.com', status: 'valid', errors: [] }],
}

describe('import API', () => {
  beforeEach(() => vi.restoreAllMocks())

  it('requests a fresh preview every time, even for the same File', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse(firstPreview))
    const file = csvFile('John,Smith,john@example.com')

    await previewUsers(file)
    await previewUsers(file)

    expect(fetch).toHaveBeenCalledTimes(2)
    expect(fetch).toHaveBeenNthCalledWith(1, '/api/imports/preview', expect.objectContaining({ method: 'POST' }))
  })

  it('cannot reuse one file preview for another file with matching metadata', async () => {
    vi.spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(jsonResponse(firstPreview))
      .mockResolvedValueOnce(jsonResponse(secondPreview))
    const firstFile = csvFile('John,Smith,john@example.com', 1)
    const secondFile = csvFile('Jane,Smith,jane@example.com', 1)

    expect(firstFile.size).toBe(secondFile.size)
    expect((await previewUsers(firstFile)).rows[0].name).toBe('John')
    expect((await previewUsers(secondFile)).rows[0].name).toBe('Jane')
  })

  it('surfaces a normal API error message', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse({
      error: { status: 422, message: 'Missing required CSV headers: email.' },
    }, 422))

    await expect(previewUsers(csvFile('John,Smith,john@example.com')))
      .rejects.toEqual(new ApiError('Missing required CSV headers: email.'))
  })

  it('uploads the selected original File when importing', async () => {
    const result: ImportResultData = {
      summary: { total: 1, valid: 1, invalid: 0, imported: 1, skipped: 0 },
      imported: [{ rowNumber: 2, name: 'John', surname: 'Smith', email: 'john@example.com' }],
      rejected: [],
    }
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse(result))
    const file = csvFile('John,Smith,john@example.com')

    await importUsers(file)

    const options = fetch.mock.calls[0][1]
    expect(options?.body).toBeInstanceOf(FormData)
    expect((options?.body as FormData).get('file')).toBe(file)
  })
})

function csvFile(row: string, lastModified = 1): File {
  return new File([`name,surname,email\n${row}`], 'users.csv', { type: 'text/csv', lastModified })
}

function jsonResponse(payload: unknown, status = 200): Response {
  return new Response(JSON.stringify(payload), { status, headers: { 'Content-Type': 'application/json' } })
}
