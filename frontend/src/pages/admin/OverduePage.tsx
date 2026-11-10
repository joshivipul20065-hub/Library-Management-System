import { useQuery, useMutation } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { Download, Send } from 'lucide-react';
import { downloadCsv, issuesApi, reportsApi } from '../../api/endpoints';
import { getErrorMessage } from '../../api/client';
import { DataTable, type Column } from '../../components/DataTable';
import { PageHeader } from '../../components/ui';
import type { OverdueRow } from '../../types';
import { formatDate, formatMoney } from '../../utils/format';

export default function OverduePage() {
  const overdue = useQuery({ queryKey: ['reports', 'overdue'], queryFn: reportsApi.overdue });
  const remind = useMutation({
    mutationFn: (row: OverdueRow) => issuesApi.remind(row.id),
    onSuccess: (_, row) => toast.success(`Reminder sent to ${row.student_name}`),
    onError: (error) => toast.error(getErrorMessage(error)),
  });

  const columns: Column<OverdueRow>[] = [
    {
      key: 'book',
      header: 'Book',
      render: (r) => (
        <div>
          <p className="font-medium">{r.book_title}</p>
          <p className="font-mono text-xs text-stone-500">{r.barcode}</p>
        </div>
      ),
    },
    {
      key: 'student',
      header: 'Student',
      render: (r) => (
        <div>
          <p>
            {r.student_name} ({r.roll_no})
          </p>
          <p className="text-xs text-stone-500">
            {r.student_email}
            {r.phone ? ` · ${r.phone}` : ''}
          </p>
        </div>
      ),
    },
    { key: 'due', header: 'Due', render: (r) => formatDate(r.due_date) },
    {
      key: 'days',
      header: 'Days late',
      render: (r) => <span className="font-medium text-red-600 dark:text-red-400">{r.days_overdue}</span>,
    },
    { key: 'fine', header: 'Fine so far', render: (r) => formatMoney(r.estimated_fine) },
    {
      key: 'actions',
      header: 'Actions',
      render: (r) => (
        <button
          type="button"
          className="btn-secondary btn-sm"
          onClick={() => remind.mutate(r)}
          disabled={remind.isPending}
        >
          <Send className="h-3.5 w-3.5" aria-hidden="true" /> Send reminder
        </button>
      ),
    },
  ];

  return (
    <>
      <PageHeader
        title="Overdue books"
        description="Reminders are sent by email and appear in the student's notifications."
        actions={
          <button
            type="button"
            className="btn-secondary"
            onClick={() =>
              downloadCsv('/reports/overdue', 'overdue.csv').catch((e) => toast.error(getErrorMessage(e)))
            }
          >
            <Download className="h-4 w-4" aria-hidden="true" /> Export CSV
          </button>
        }
      />
      <DataTable
        caption="Overdue books"
        columns={columns}
        rows={overdue.data}
        rowKey={(r) => r.id}
        loading={overdue.isLoading}
        error={overdue.error}
        onRetry={() => overdue.refetch()}
        emptyTitle="Nothing overdue"
        emptyMessage="Every book on loan is within its due date."
      />
    </>
  );
}
