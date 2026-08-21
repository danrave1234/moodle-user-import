import { useState } from 'react'
import { ApiError, importUsers, previewUsers } from './api/imports'
import { CsvDropzone } from './components/CsvDropzone'
import { ImportResult } from './components/ImportResult'
import { ImportSummary } from './components/ImportSummary'
import { ImportTable } from './components/ImportTable'
import type { ImportPreview, ImportResultData } from './types/import'
import './styles.css'

type AppState =
  | { status: 'idle' }
  | { status: 'selected'; file: File }
  | { status: 'previewing'; file: File }
  | { status: 'preview'; file: File; preview: ImportPreview; cached: boolean }
  | { status: 'importing'; file: File; preview: ImportPreview }
  | { status: 'complete'; file: File; result: ImportResultData }
  | { status: 'error'; file: File; message: string; preview?: ImportPreview }

export default function App() {
  const [state, setState] = useState<AppState>({ status: 'idle' })
  const file = 'file' in state ? state.file : undefined
  const isBusy = state.status === 'previewing' || state.status === 'importing'

  const handlePreview = async (selectedFile: File) => {
    if (!selectedFile.name.toLowerCase().endsWith('.csv')) {
      setState({ status: 'error', file: selectedFile, message: 'Choose a file with a .csv extension.' })
      return
    }

    setState({ status: 'previewing', file: selectedFile })
    try {
      const { preview, cached } = await previewUsers(selectedFile)
      setState({ status: 'preview', file: selectedFile, preview, cached })
    } catch (error) {
      setState({ status: 'error', file: selectedFile, message: errorMessage(error) })
    }
  }

  const handleImport = async (selectedFile: File, preview: ImportPreview) => {
    setState({ status: 'importing', file: selectedFile, preview })
    try {
      const result = await importUsers(selectedFile)
      setState({ status: 'complete', file: selectedFile, result })
    } catch (error) {
      setState({ status: 'error', file: selectedFile, preview, message: errorMessage(error) })
    }
  }

  return (
    <main>
      <header className="page-header">
        <div className="brand-mark" aria-hidden="true">M</div>
        <div>
          <p className="eyebrow">User administration</p>
          <h1>User Import</h1>
          <p className="page-intro">Add users in three clear steps: choose a CSV, review it, then import.</p>
        </div>
      </header>

      <ol className="steps" aria-label="Import progress">
        <Step number="1" label="Choose file" active={state.status !== 'idle'} />
        <Step number="2" label="Review users" active={['preview', 'importing', 'complete'].includes(state.status) || (state.status === 'error' && Boolean(state.preview))} />
        <Step number="3" label="Import" active={state.status === 'complete'} />
      </ol>

      {state.status === 'complete' ? (
        <ImportResult result={state.result} onReset={() => setState({ status: 'idle' })} />
      ) : (
        <div className="workspace-card">
          <section aria-labelledby="upload-heading">
            <div className="section-heading">
              <div>
                <p className="eyebrow">Step 1</p>
                <h2 id="upload-heading">Choose your user list</h2>
              </div>
              <p>The file needs name, surname, and email columns.</p>
            </div>

            <CsvDropzone
              file={file}
              disabled={isBusy}
              onFile={(selectedFile) => setState({ status: 'selected', file: selectedFile })}
            />

            <div className="upload-actions">
              {state.status !== 'idle' && (
                <button className="button button--quiet" type="button" disabled={isBusy} onClick={() => setState({ status: 'idle' })}>
                  Start over
                </button>
              )}
              <button
                className="button button--primary"
                type="button"
                disabled={!file || isBusy}
                onClick={() => file && void handlePreview(file)}
              >
                {state.status === 'previewing' && <span className="spinner" aria-hidden="true" />}
                {state.status === 'previewing' ? 'Previewing…' : state.status === 'preview' ? 'Preview again' : 'Preview users'}
              </button>
            </div>
          </section>

          {state.status === 'previewing' && (
            <div className="loading-panel" role="status" aria-live="polite">
              <span className="spinner spinner--large" aria-hidden="true" />
              <div><strong>Checking your file</strong><p>We’re normalizing names, validating emails, and checking duplicates.</p></div>
            </div>
          )}

          {state.status === 'error' && (
            <div className="alert alert--error" role="alert">
              <div><strong>We couldn’t finish that step</strong><p>{state.message}</p></div>
              {state.preview && (
                <button className="button button--secondary" type="button" onClick={() => setState({ status: 'preview', file: state.file, preview: state.preview!, cached: false })}>
                  Back to preview
                </button>
              )}
            </div>
          )}

          {(state.status === 'preview' || state.status === 'importing') && (
            <section className="preview-section">
              <ImportSummary summary={state.preview.summary} />
              {state.status === 'preview' && state.cached && (
                <p className="cache-note" role="status">Showing a recent preview of this file.</p>
              )}
              <ImportTable rows={state.preview.rows} />
              <div className="import-bar">
                <div>
                  <strong>Ready to continue?</strong>
                  <p>The server checks the original file again before saving.</p>
                </div>
                <button
                  className="button button--primary"
                  type="button"
                  disabled={state.preview.summary.valid === 0 || state.status === 'importing'}
                  onClick={() => void handleImport(state.file, state.preview)}
                >
                  {state.status === 'importing' && <span className="spinner" aria-hidden="true" />}
                  {state.status === 'importing' ? 'Importing…' : `Import ${state.preview.summary.valid} ${state.preview.summary.valid === 1 ? 'user' : 'users'}`}
                </button>
              </div>
            </section>
          )}
        </div>
      )}
    </main>
  )
}

function Step({ number, label, active }: { number: string; label: string; active: boolean }) {
  return (
    <li className={active ? 'step step--active' : 'step'}>
      <span>{number}</span>
      {label}
    </li>
  )
}

function errorMessage(error: unknown): string {
  return error instanceof ApiError ? error.message : 'Something went wrong. Please try again.'
}
