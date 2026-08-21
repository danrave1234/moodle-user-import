import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { ImportResult } from './ImportResult'
import type { ImportResultData } from '../types/import'

const result: ImportResultData = {
  summary: { total: 2, valid: 1, invalid: 1, imported: 1, skipped: 1 },
  imported: [{ rowNumber: 2, name: 'John', surname: 'Smith', email: 'john@example.com' }],
  rejected: [{
    rowNumber: 3,
    name: 'Jane',
    surname: 'Doe',
    email: 'invalid-email',
    status: 'invalid',
    errors: [{ field: 'email', code: 'invalid_email', message: 'Enter a valid email address.' }],
  }],
}

describe('ImportResult', () => {
  it('renders the summary and expands normalized imported users', async () => {
    const user = userEvent.setup()
    render(<ImportResult result={result} onReset={vi.fn()} />)

    expect(screen.getByText('1 user was imported')).toBeInTheDocument()
    expect(screen.getByText('Not imported').nextElementSibling).toHaveTextContent('1')
    expect(screen.queryByText('john@example.com')).not.toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'View imported users (1)' }))

    expect(screen.getByRole('heading', { name: 'Imported users (1)' })).toBeInTheDocument()
    expect(screen.getByText('John')).toBeInTheDocument()
    expect(screen.getByText('john@example.com')).toBeInTheDocument()
  })

  it('expands rejected rows and displays backend reasons', async () => {
    const user = userEvent.setup()
    render(<ImportResult result={result} onReset={vi.fn()} />)

    await user.click(screen.getByRole('button', { name: 'View rejected rows (1)' }))

    expect(screen.getByRole('heading', { name: 'Rows not imported (1)' })).toBeInTheDocument()
    expect(screen.getByText('invalid-email')).toBeInTheDocument()
    expect(screen.getByText('Enter a valid email address.')).toBeInTheDocument()
  })

  it('hides detail actions for empty collections', () => {
    render(<ImportResult result={{
      summary: { total: 0, valid: 0, invalid: 0, imported: 0, skipped: 0 },
      imported: [],
      rejected: [],
    }} onReset={vi.fn()} />)

    expect(screen.queryByRole('button', { name: /rejected rows/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /imported users/i })).not.toBeInTheDocument()
  })
})
