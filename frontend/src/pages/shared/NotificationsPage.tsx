import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { meApi } from '../../api/endpoints';
import { getErrorMessage } from '../../api/client';
import { useListState } from '../../hooks/useListState';
import { Pagination } from '../../components/DataTable';
import { EmptyState, ErrorState, PageHeader, Skeleton } from '../../components/ui';
import { formatDateTime } from '../../utils/format';

export default function NotificationsPage() {
  const list = useListState({ sort: 'created_at', order: 'desc' });
  const queryClient = useQueryClient();
  const notifications = useQuery({
    queryKey: ['notifications', list.params],
    queryFn: () => meApi.notifications(list.params),
    placeholderData: keepPreviousData,
  });
  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['notifications'] });
  const markRead = useMutation({ mutationFn: meApi.readNotification, onSuccess: invalidate });
  const markAll = useMutation({ mutationFn: meApi.readAllNotifications, onSuccess: invalidate });

  return (
    <>
      <PageHeader
        title="Notifications"
        actions={
          (notifications.data?.meta.unread ?? 0) > 0 && (
            <button type="button" className="btn-secondary" onClick={() => markAll.mutate()}>
              Mark all as read
            </button>
          )
        }
      />
      {notifications.error && (
        <ErrorState message={getErrorMessage(notifications.error)} onRetry={() => notifications.refetch()} />
      )}
      <ul className="space-y-3">
        {notifications.isLoading &&
          Array.from({ length: 3 }, (_, i) => <Skeleton key={i} className="h-20 w-full" />)}
        {notifications.data?.data.map((n) => (
          <li
            key={n.id}
            className={`card flex items-start justify-between gap-4 ${n.read_at ? '' : 'border-brand-200 dark:border-brand-800'}`}
          >
            <div>
              <p className="font-medium">
                {!n.read_at && (
                  <span className="mr-2 inline-block h-2 w-2 rounded-full bg-brand-600" aria-label="Unread" />
                )}
                {n.title}
              </p>
              <p className="mt-1 text-sm text-stone-600 dark:text-stone-300">{n.body}</p>
              <p className="mt-2 text-xs text-stone-400">{formatDateTime(n.created_at)}</p>
            </div>
            {!n.read_at && (
              <button
                type="button"
                className="btn-ghost btn-sm shrink-0"
                onClick={() => markRead.mutate(n.id)}
              >
                Mark read
              </button>
            )}
          </li>
        ))}
      </ul>
      {notifications.data?.data.length === 0 && (
        <EmptyState title="No notifications" message="You're all caught up." />
      )}
      {notifications.data && notifications.data.meta.total > 0 && (
        <div className="card mt-4 p-0">
          <Pagination meta={notifications.data.meta} onPageChange={list.setPage} />
        </div>
      )}
    </>
  );
}
