import type { ImportRow } from '../types/import'

type ImportTableProps = {
  rows: ImportRow[]
}

export function ImportTable({ rows }: ImportTableProps) {
  return (
    <div className="table-shell">
      <table>
        <caption className="visually-hidden">Normalized users and their validation status</caption>
        <thead>
          <tr>
            <th scope="col">Row</th>
            <th scope="col">Name</th>
            <th scope="col">Surname</th>
            <th scope="col">Email</th>
            <th scope="col">Status</th>
            <th scope="col">Details</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.rowNumber} className={row.status === 'invalid' ? 'row--invalid' : undefined}>
              <td data-label="Row">{row.rowNumber}</td>
              <td data-label="Name">{row.name || <span className="empty-value">Missing</span>}</td>
              <td data-label="Surname">{row.surname || <span className="empty-value">Missing</span>}</td>
              <td data-label="Email" className="email-cell">
                {row.email || <span className="empty-value">Missing</span>}
              </td>
              <td data-label="Status">
                <span className={`status-badge status-badge--${row.status}`}>
                  {row.status === 'valid' ? 'Valid' : 'Needs attention'}
                </span>
              </td>
              <td data-label="Details">
                {row.errors.length > 0 ? (
                  <ul className="error-list">
                    {row.errors.map((error) => (
                      <li key={`${error.field}-${error.code}`}>{error.message}</li>
                    ))}
                  </ul>
                ) : (
                  <span className="ready-text">Ready to import</span>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

