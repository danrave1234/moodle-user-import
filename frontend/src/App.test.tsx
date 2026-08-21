import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import App from './App'
import { clearPreviewCache } from './api/imports'
import type { ImportPreview } from './types/import'

const validRow = {
  rowNumber: 2,
  name: 'John',
  surname: 'Smith',
  email: 'john@example.com',
  status: 'valid' as const,
  errors: [],
}

describe('User import', () => {
  beforeEach(() => {
    clearPreviewCache()
    localStorage.removeItem('user-import-theme')
    delete document.documentElement.dataset.theme
    vi.restoreAllMocks()
  })

  it('switches between light and dark mode', async () => {
    const user = userEvent.setup()
    render(<App />)

    await user.click(screen.getByRole('button', { name: 'Switch to dark mode' }))

    expect(document.documentElement.dataset.theme).toBe('dark')
    expect(screen.getByRole('button', { name: 'Switch to light mode' })).toBeInTheDocument()
  })

  it('lets a user choose a CSV file', async () => {
    const user = userEvent.setup()
    render(<App />)

    await user.upload(screen.getByLabelText('Choose CSV file'), csvFile())

    expect(screen.getByText('Your file is ready to preview')).toBeInTheDocument()
    expect(screen.getByText('users.csv')).toBeInTheDocument()
    expect(screen.getByText('CSV file · 46 B')).toBeInTheDocument()
    expect(screen.getByLabelText('Replace file')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Preview users' })).toBeEnabled()
  })

  it('shows preview counts, normalized rows, errors, and the valid import count', async () => {
    mockResponse({
      summary: { total: 2, valid: 1, invalid: 1 },
      rows: [
        validRow,
        {
          rowNumber: 3,
          name: 'Jane',
          surname: 'Doe',
          email: 'invalid-email',
          status: 'invalid',
          errors: [{ field: 'email', code: 'invalid_email', message: 'Enter a valid email address.' }],
        },
      ],
    })
    const user = userEvent.setup()
    render(<App />)

    await user.upload(screen.getByLabelText('Choose CSV file'), csvFile())
    await user.click(screen.getByRole('button', { name: 'Preview users' }))

    expect(await screen.findByText('Check your users')).toBeInTheDocument()
    expect(screen.getByText('John')).toBeInTheDocument()
    expect(screen.getByText('Enter a valid email address.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Import 1 user' })).toBeEnabled()
  })

  it('disables import when every row is invalid', async () => {
    mockResponse({
      summary: { total: 1, valid: 0, invalid: 1 },
      rows: [{ ...validRow, status: 'invalid', errors: [{ field: 'email', code: 'duplicate_in_database', message: 'This email already exists.' }] }],
    })
    const user = userEvent.setup()
    render(<App />)

    await user.upload(screen.getByLabelText('Choose CSV file'), csvFile())
    await user.click(screen.getByRole('button', { name: 'Preview users' }))

    expect(await screen.findByRole('button', { name: 'Import 0 users' })).toBeDisabled()
    expect(screen.getByText('This email already exists.')).toBeInTheDocument()
  })

  it('shows a clear loading state while previewing', async () => {
    vi.spyOn(globalThis, 'fetch').mockReturnValue(new Promise(() => undefined))
    const user = userEvent.setup()
    render(<App />)

    await user.upload(screen.getByLabelText('Choose CSV file'), csvFile())
    await user.click(screen.getByRole('button', { name: 'Preview users' }))

    expect(screen.getByRole('status')).toHaveTextContent('Checking your file')
    expect(screen.getByRole('button', { name: 'Previewing…' })).toBeDisabled()
  })
})

function csvFile(): File {
  return new File(['name,surname,email\nJohn,Smith,john@example.com'], 'users.csv', { type: 'text/csv' })
}

function mockResponse(preview: ImportPreview): void {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(JSON.stringify(preview), {
    status: 200,
    headers: { 'Content-Type': 'application/json' },
  }))
}
