import { beforeEach, describe, expect, it, vi } from 'vitest'
import { clearPreviewCache, previewUsers } from './imports'
import type { ImportPreview } from '../types/import'

const preview: ImportPreview = {
  summary: { total: 1, valid: 1, invalid: 0 },
  rows: [{
    rowNumber: 2,
    name: 'John',
    surname: 'Smith',
    email: 'john@example.com',
    status: 'valid',
    errors: [],
  }],
}

describe('preview cache', () => {
  beforeEach(() => {
    clearPreviewCache()
    vi.restoreAllMocks()
  })

  it('reuses a recent preview of the same file', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(JSON.stringify(preview), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    }))
    const file = new File(['name,surname,email\nJohn,Smith,john@example.com'], 'users.csv', {
      type: 'text/csv',
      lastModified: 1,
    })

    const first = await previewUsers(file)
    const second = await previewUsers(file)

    expect(first.cached).toBe(false)
    expect(second.cached).toBe(true)
    expect(second.preview).toEqual(preview)
    expect(fetch).toHaveBeenCalledTimes(1)
  })
})
