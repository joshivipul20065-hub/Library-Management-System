import { useQuery } from '@tanstack/react-query';
import { issuesApi } from '../../api/endpoints';
import { ConfirmDialog } from '../../components/Modal';
import { Spinner } from '../../components/ui';
import type { Issue } from '../../types';
import { formatDate, formatMoney } from '../../utils/format';

/** Confirms a return and previews the fine that will be charged. */
export function ReturnDialog({
  issue,
  busy,
  onConfirm,
  onCancel,
}: {
  issue: Issue | null;
  busy: boolean;
  onConfirm: (issue: Issue) => void;
  onCancel: () => void;
}) {
  const preview = useQuery({
    queryKey: ['issues', issue?.id, 'fine-preview'],
    queryFn: () => issuesApi.finePreview(issue!.id),
    enabled: !!issue,
    staleTime: 0,
  });
  return (
    <ConfirmDialog
      open={!!issue}
      title="Return book"
      confirmLabel="Confirm return"
      busy={busy}
      onConfirm={() => issue && onConfirm(issue)}
      onCancel={onCancel}
      message={
        issue && (
          <div className="space-y-2">
            <p>
              <strong>{issue.book_title}</strong> ({issue.barcode}) borrowed by {issue.student_name} (
              {issue.roll_no}), due {formatDate(issue.due_date)}.
            </p>
            {preview.isLoading && <Spinner label="Calculating fine" />}
            {preview.data &&
              (preview.data.amount > 0 ? (
                <p className="font-medium text-red-600 dark:text-red-400">
                  {preview.data.days_overdue} day(s) late × {formatMoney(preview.data.fine_per_day)} ={' '}
                  {formatMoney(preview.data.amount)} fine
                </p>
              ) : (
                <p className="font-medium text-emerald-600 dark:text-emerald-400">On time — no fine.</p>
              ))}
          </div>
        )
      }
    />
  );
}
