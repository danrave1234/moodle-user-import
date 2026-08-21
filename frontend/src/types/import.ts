export type ImportError = {
  field: string
  code: string
  message: string
}

export type ImportRow = {
  rowNumber: number
  name: string
  surname: string
  email: string
  status: 'valid' | 'invalid'
  errors: ImportError[]
}

export type PreviewSummary = {
  total: number
  valid: number
  invalid: number
}

export type ImportSummary = PreviewSummary & {
  imported: number
  skipped: number
}

export type ImportPreview = {
  summary: PreviewSummary
  rows: ImportRow[]
}

export type ImportResultData = {
  summary: ImportSummary
  rows: ImportRow[]
}

