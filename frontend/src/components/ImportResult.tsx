import type { ImportResultData } from '../types/import'

type ImportResultProps = {
  result: ImportResultData
  onReset: () => void
}

export function ImportResult({ result, onReset }: ImportResultProps) {
  const { summary } = result

  return (
    <section className="result-panel" aria-labelledby="result-heading" aria-live="polite">
      <div className="result-check" aria-hidden="true">✓</div>
      <p className="eyebrow">Import complete</p>
      <h2 id="result-heading">
        {summary.imported} {summary.imported === 1 ? 'user was' : 'users were'} imported
      </h2>
      <p>
        {summary.invalid > 0 && `${summary.invalid} invalid ${summary.invalid === 1 ? 'row was' : 'rows were'} not imported.`}
        {summary.skipped > 0 && ` ${summary.skipped} duplicate ${summary.skipped === 1 ? 'was' : 'users were'} skipped during import.`}
        {summary.invalid === 0 && summary.skipped === 0 && 'Every user in the file was imported successfully.'}
      </p>
      <dl className="result-stats">
        <div><dt>Found</dt><dd>{summary.total}</dd></div>
        <div><dt>Imported</dt><dd>{summary.imported}</dd></div>
        <div><dt>Not imported</dt><dd>{summary.invalid + summary.skipped}</dd></div>
      </dl>
      <button className="button button--primary" type="button" onClick={onReset}>
        Import another file
      </button>
    </section>
  )
}
