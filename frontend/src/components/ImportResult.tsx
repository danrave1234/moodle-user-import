import { useState } from 'react'
import type { ImportResultData } from '../types/import'
import { CheckCircleIcon } from './icons'

type ImportResultProps = {
  result: ImportResultData
  onReset: () => void
}

export function ImportResult({ result, onReset }: ImportResultProps) {
  const { summary } = result
  const [showImported, setShowImported] = useState(false)
  const [showRejected, setShowRejected] = useState(false)

  return (
    <section className="result-panel" aria-labelledby="result-heading" aria-live="polite">
      <div className="result-check" aria-hidden="true"><CheckCircleIcon /></div>
      <p className="eyebrow">Import complete</p>
      <h2 id="result-heading">
        {summary.imported} {summary.imported === 1 ? 'user was' : 'users were'} imported
      </h2>
      <p>
        {summary.skipped > 0
          ? `${summary.skipped} ${summary.skipped === 1 ? 'row was' : 'rows were'} not imported.`
          : 'Every user in the file was imported successfully.'}
      </p>
      <dl className="result-stats">
        <div><dt>Found</dt><dd>{summary.total}</dd></div>
        <div><dt>Imported</dt><dd>{summary.imported}</dd></div>
        <div><dt>Not imported</dt><dd>{summary.skipped}</dd></div>
      </dl>
      <button className="button button--primary" type="button" onClick={onReset}>
        Import another file
      </button>
      <div className="result-detail-actions">
        {result.imported.length > 0 && (
          <button className="button button--quiet" type="button" aria-expanded={showImported} onClick={() => setShowImported(!showImported)}>
            {showImported ? 'Hide' : 'View'} imported users ({result.imported.length})
          </button>
        )}
        {result.rejected.length > 0 && (
          <button className="button button--quiet" type="button" aria-expanded={showRejected} onClick={() => setShowRejected(!showRejected)}>
            {showRejected ? 'Hide' : 'View'} rejected rows ({result.rejected.length})
          </button>
        )}
      </div>

      {showImported && (
        <section className="result-details" aria-labelledby="imported-users-heading">
          <h3 id="imported-users-heading">Imported users ({result.imported.length})</h3>
          <div className="table-shell">
            <table>
              <thead><tr><th>Row</th><th>Name</th><th>Surname</th><th>Email</th></tr></thead>
              <tbody>
                {result.imported.map((row) => (
                  <tr key={row.rowNumber}>
                    <td data-label="Row">{row.rowNumber}</td><td data-label="Name">{row.name}</td>
                    <td data-label="Surname">{row.surname}</td><td data-label="Email" className="email-cell">{row.email}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}

      {showRejected && (
        <section className="result-details" aria-labelledby="rejected-rows-heading">
          <h3 id="rejected-rows-heading">Rows not imported ({result.rejected.length})</h3>
          <div className="table-shell">
            <table>
              <thead><tr><th>Row</th><th>Name</th><th>Surname</th><th>Email</th><th>Reason</th></tr></thead>
              <tbody>
                {result.rejected.map((row) => (
                  <tr key={row.rowNumber}>
                    <td data-label="Row">{row.rowNumber}</td><td data-label="Name">{row.name || <span className="empty-value">Missing</span>}</td>
                    <td data-label="Surname">{row.surname || <span className="empty-value">Missing</span>}</td>
                    <td data-label="Email" className="email-cell">{row.email || <span className="empty-value">Missing</span>}</td>
                    <td data-label="Reason"><ul className="error-list">{row.errors.map((error) => <li key={`${error.field}-${error.code}`}>{error.message}</li>)}</ul></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}
    </section>
  )
}
