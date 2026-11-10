import { useMutation, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { issuesApi } from '../../api/endpoints';
import { getErrorMessage } from '../../api/client';
import type { Issue } from '../../types';
import { formatDate } from '../../utils/format';

export function RenewButton({ issue }: { issue: Pick<Issue, 'id' | 'status' | 'book_title'> }) {
  const queryClient = useQueryClient();
  const renew = useMutation({
    mutationFn: () => issuesApi.renew(issue.id),
    onSuccess: (updated) => {
      toast.success(`Renewed — now due ${formatDate(updated.due_date)}`);
      queryClient.invalidateQueries({ queryKey: ['me'] });
      queryClient.invalidateQueries({ queryKey: ['issues'] });
    },
    onError: (error) => toast.error(getErrorMessage(error)),
  });

  if (issue.status !== 'issued' && issue.status !== 'overdue') return null;
  return (
    <button
      type="button"
      className="btn-secondary btn-sm"
      onClick={() => renew.mutate()}
      disabled={renew.isPending}
      aria-label={`Renew ${issue.book_title}`}
    >
      {renew.isPending ? 'Renewing…' : 'Renew'}
    </button>
  );
}
