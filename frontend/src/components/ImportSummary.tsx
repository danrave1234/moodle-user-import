import type { PreviewSummary } from '../types/import'

type ImportSummaryProps = {
  summary: PreviewSummary
}

export function ImportSummary({ summary }: ImportSummaryProps) {
  return (
    <section aria-labelledby="summary-heading">
      <div className="section-heading">
        <div>
          <p className="eyebrow">Preview ready</p>
          <h2 id="summary-heading">Check your users</h2>
        </div>
        <p>Only valid users will be imported.</p>
      </div>
      <div className="summary-grid">
        <SummaryCard label="Users found" value={summary.total} tone="neutral" />
        <SummaryCard label="Valid" value={summary.valid} tone="success" />
        <SummaryCard label="Invalid" value={summary.invalid} tone="danger" />
      </div>
    </section>
  )
}

function SummaryCard({ label, value, tone }: { label: string; value: number; tone: string }) {
  return (
    <div className={`summary-card summary-card--${tone}`}>
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  )
}

