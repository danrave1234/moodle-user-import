import { useId, useState, type DragEvent } from 'react'
import { FileCsvIcon } from './icons'

type CsvDropzoneProps = {
  file?: File
  disabled: boolean
  onFile: (file: File) => void
}

export function CsvDropzone({ file, disabled, onFile }: CsvDropzoneProps) {
  const inputId = useId()
  const [dragging, setDragging] = useState(false)

  const handleDrop = (event: DragEvent<HTMLDivElement>) => {
    event.preventDefault()
    setDragging(false)

    const droppedFile = event.dataTransfer.files[0]
    if (!disabled && droppedFile) {
      onFile(droppedFile)
    }
  }

  return (
    <div
      className={`dropzone${dragging ? ' dropzone--active' : ''}${disabled ? ' dropzone--disabled' : ''}`}
      onDragEnter={(event) => {
        event.preventDefault()
        if (!disabled) setDragging(true)
      }}
      onDragOver={(event) => event.preventDefault()}
      onDragLeave={() => setDragging(false)}
      onDrop={handleDrop}
    >
      <div className="upload-mark" aria-hidden="true"><FileCsvIcon /></div>
      <div>
        <p className="dropzone__title">Drop your CSV file here</p>
        <p className="dropzone__help">or choose it from your computer</p>
      </div>
      <input
        id={inputId}
        className="visually-hidden"
        type="file"
        accept=".csv,text/csv"
        disabled={disabled}
        onChange={(event) => {
          const selectedFile = event.target.files?.[0]
          if (selectedFile) onFile(selectedFile)
          event.target.value = ''
        }}
      />
      <label className="button button--secondary" htmlFor={inputId}>
        Choose CSV file
      </label>
      {file && (
        <div className="selected-file" aria-live="polite">
          <span className="selected-file__name">{file.name}</span>
          <span>{formatFileSize(file.size)}</span>
        </div>
      )}
    </div>
  )
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
