import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { reservationsApi } from '../../api/endpoints';
import { getErrorMessage } from '../../api/client';
import { useListState } from '../../hooks/useListState';
import { DataTable, type Column } from '../../components/DataTable';
import { SearchInput } from '../../components/SearchInput';
import { PageHeader, StatusBadge } from '../../components/ui';
import type { Reservation } from '../../types';
import { formatDate, formatDateTime } from '../../utils/format';

export default function ReservationsPage() {
  const queryClient = useQueryClient();
  const list = useListState({ sort: 'created_at', order: 'asc', filters: { status: 'active' } });
  const reservations = useQuery({
    queryKey: ['reservations', list.params],
    queryFn: () => reservationsApi.list(list.params),
    placeholderData: keepPreviousData,
  });
  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['reservations'] });
    queryClient.invalidateQueries({ queryKey: ['issues'] });
  };
  const fulfil = useMutation({
    mutationFn: (r: Reservation) => reservationsApi.fulfil(r.id),
    onSuccess: (issue) => {
      toast.success(`Issued ${issue.barcode} to ${issue.student_name}, due ${formatDate(issue.due_date)}`);
      refresh();
    },
    onError: (error) => toast.error(getErrorMessage(error)),
  });
  const cancel = useMutation({
    mutationFn: (r: Reservation) => reservationsApi.cancel(r.id),
    onSuccess: () => {
      toast.success('Reservation cancelled');
      refresh();
    },
    onError: (error) => toast.error(getErrorMessage(error)),
  });

  const columns: Column<Reservation>[] = [
    { key: 'book', header: 'Book', render: (r) => <span className="font-medium">{r.book_title}</span> },
    { key: 'student', header: 'Student', render: (r) => `${r.student_name} (${r.roll_no})` },
    { key: 'created_at', header: 'Reserved', sortable: true, render: (r) => formatDateTime(r.created_at) },
    { key: 'status', header: 'Status', sortable: true, render: (r) => <StatusBadge status={r.status} /> },
    {
      key: 'queue',
      header: 'Queue / hold',
      render: (r) =>
        r.status === 'ready'
          ? `Held until ${formatDateTime(r.expires_at)}`
          : r.status === 'pending'
            ? `#${r.queue_position ?? '—'} · ${r.available_copies ?? 0} on shelf`
            : '—',
    },
    {
      key: 'actions',
      header: 'Actions',
      render: (r) =>
        (r.status === 'pending' || r.status === 'ready') && (
          <div className="flex gap-1">
            <button
              type="button"
              className="btn-primary btn-sm"
              onClick={() => fulfil.mutate(r)}
              disabled={fulfil.isPending}
            >
              Issue
            </button>
            <button
              type="button"
              className="btn-ghost btn-sm text-red-600"
              onClick={() => cancel.mutate(r)}
              disabled={cancel.isPending}
            >
              Cancel
            </button>
          </div>
        ),
    },
  ];

  return (
    <>
      <PageHeader
        title="Reservations"
        description="Ready holds expire automatically if not collected in time."
      />
      <div className="mb-4 flex flex-col gap-3 sm:flex-row">
        <SearchInput
          label="Search reservations"
          placeholder="Title, student, roll no."
          onSearch={(v) => list.setFilter('q', v)}
        />
        <label htmlFor="reservation-status" className="sr-only">
          Status
        </label>
        <select
          id="reservation-status"
          className="input sm:w-44"
          value={list.filters.status ?? ''}
          onChange={(e) => list.setFilter('status', e.target.value)}
        >
          <option value="active">Active</option>
          <option value="pending">Pending</option>
          <option value="ready">Ready</option>
          <option value="fulfilled">Fulfilled</option>
          <option value="expired">Expired</option>
          <option value="cancelled">Cancelled</option>
          <option value="">All</option>
        </select>
      </div>
      <DataTable
        caption="Reservations"
        columns={columns}
        rows={reservations.data?.data}
        rowKey={(r) => r.id}
        loading={reservations.isLoading}
        error={reservations.error}
        meta={reservations.data?.meta}
        onPageChange={list.setPage}
        sort={list.sort}
        order={list.order}
        onSort={list.toggleSort}
        emptyTitle="No reservations in this view"
      />
    </>
  );
}
