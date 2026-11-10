import { useState } from 'react';
import toast from 'react-hot-toast';
import { Modal } from '../../components/Modal';
import { Alert } from '../../components/ui';
import { getErrorMessage } from '../../api/client';
import type { ImportResult } from '../../types';

interface Props {
  open: boolean;
  title: string;
  columns: string;
  onClose: () => void;
  onImport: (file: File) => Promise<ImportResult>;
  onDone: () => void;
}

const MAX_BYTES = 2 * 1024 * 1024;

export function ImportCsvModal({ open, title, columns, onClose, onImport, onDone }: Props) {
  const [file, setFile] = useState<File | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState<ImportResult | null>(null);

  const close = () => {
    setFile(null);
    setError(null);
    setResult(null);
    onClose();
  };

  const submit = async () => {
    if (!file) return setError('Choose a CSV file first.');
    if (!file.name.toLowerCase().endsWith('.csv')) return setError('Only .csv files are accepted.');
    if (file.size > MAX_BYTES) return setError('The file is larger than 2 MB.');
    setBusy(true);
    setError(null);
    try {
      const imported = await onImport(file);
      setResult(imported);
      toast.success(`Imported ${imported.created} row(s)`);
      onDone();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  return (
    <Modal
      open={open}
      title={title}
      onClose={close}
      footer={
        <>
          <button type="button" className="btn-secondary" onClick={close}>
            Close
          </button>
          {!result && (
            <button type="button" className="btn-primary" onClick={submit} disabled={busy}>
              {busy ? 'Importing…' : 'Import'}
            </button>
          )}
        </>
      }
    >
      <div className="space-y-4 text-sm">
        <p>
          Upload a UTF-8 CSV with a header row. Columns:{' '}
          <code className="rounded bg-stone-100 px-1 dark:bg-stone-800">{columns}</code>
        </p>
        {error && <Alert tone="error">{error}</Alert>}
        {result ? (
          <>
            <Alert tone={result.failed ? 'warning' : 'success'}>
              {result.created} created, {result.failed} failed.
            </Alert>
            {result.errors.length > 0 && (
              <ul className="max-h-48 space-y-1 overflow-y-auto text-xs">
                {result.errors.map((e) => (
                  <li key={e.row}>
                    Row {e.row}: {e.message}
                    {Object.values(e.details).length > 0 && ` (${Object.values(e.details).join('; ')})`}
                  </li>
                ))}
              </ul>
            )}
          </>
        ) : (
          <div>
            <label htmlFor="csv-file" className="label">
              CSV file
            </label>
            <input
              id="csv-file"
              type="file"
              accept=".csv,text/csv"
              className="input"
              onChange={(e) => setFile(e.target.files?.[0] ?? null)}
            />
          </div>
        )}
      </div>
    </Modal>
  );
}
