import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { meApi, reservationsApi } from '../../api/endpoints';
import { getErrorMessage } from '../../api/client';
import { useListState } from '../../hooks/useListState';
import { DataTable, type Column } from '../../components/DataTable';
import { PageHeader, StatusBadge } from '../../components/ui';
import { RenewButton } from '../../features/circulation/RenewButton';
import type { Issue, Reservation } from '../../types';
import { formatDate, formatMoney } from '../../utils/format';

type Tab = 'current' | 'history' | 'reservations';

export default function MyBooksPage() {
  const [tab, setTab] = useState<Tab>('current');
  const tabs: { id: Tab; label: string }[] = [
    { id: 'current', label: 'Current loans' },
    { id: 'history', label: 'History' },
    { id: 'reservations', label: 'Reservations' },
  ];

  return (
    <>
      <PageHeader title="My books" />
      <div
        role="tablist"
        aria-label="My books"
        className="mb-4 flex gap-1 border-b border-stone-200 dark:border-stone-800"
      >
        {tabs.map((t) => (
          <button
            key={t.id}
            role="tab"
            id={`tab-${t.id}`}
            aria-selected={tab === t.id}
            aria-controls={`panel-${t.id}`}
            className={`-mb-px border-b-2 px-4 py-2 text-sm font-medium ${
              tab === t.id
                ? 'border-brand-600 text-brand-700 dark:text-brand-300'
                : 'border-transparent text-stone-500 hover:text-stone-800'
            }`}
            onClick={() => setTab(t.id)}
          >
            {t.label}
          </button>
        ))}
      </div>
      <div role="tabpanel" id={`panel-${tab}`} aria-labelledby={`tab-${tab}`}>
        {tab === 'reservations' ? <ReservationsTab /> : <LoansTab active={tab === 'current'} />}
      </div>
    </>
  );
}

function LoansTab({ active }: { active: boolean }) {
  const list = useListState({
    sort: 'issue_date',
    order: 'desc',
    filters: active ? { status: 'active' } : {},
  });
  const issues = useQuery({
    queryKey: ['me', 'issues', list.params],
    queryFn: () => meApi.issues(list.params),
    placeholderData: keepPreviousData,
  });
  const columns: Column<Issue>[] = [
    { key: 'title', header: 'Book', render: (i) => <span className="font-medium">{i.book_title}</span> },
    { key: 'issue_date', header: 'Issued', sortable: true, render: (i) => formatDate(i.issue_date) },
    { key: 'due_date', header: 'Due', sortable: true, render: (i) => formatDate(i.due_date) },
    { key: 'return_date', header: 'Returned', render: (i) => formatDate(i.return_date) },
    { key: 'status', header: 'Status', render: (i) => <StatusBadge status={i.status} /> },
    {
      key: 'fine',
      header: 'Fine',
      render: (i) => (Number(i.fine_amount) > 0 ? formatMoney(i.fine_amount) : '—'),
    },
    { key: 'actions', header: 'Actions', render: (i) => <RenewButton issue={i} /> },
  ];
  return (
    <DataTable
      caption={active ? 'Current loans' : 'Borrowing history'}
      columns={active ? columns : columns.filter((c) => c.key !== 'actions')}
      rows={issues.data?.data}
      rowKey={(i) => i.id}
      loading={issues.isLoading}
      error={issues.error}
      onRetry={() => issues.refetch()}
      meta={issues.data?.meta}
      onPageChange={list.setPage}
      sort={list.sort}
      order={list.order}
      onSort={list.toggleSort}
      emptyTitle={active ? 'No books on loan' : 'No borrowing history yet'}
    />
  );
}

function ReservationsTab() {
  const list = useListState({ sort: 'created_at', order: 'desc' });
  const queryClient = useQueryClient();
  const reservations = useQuery({
    queryKey: ['me', 'reservations', list.params],
    queryFn: () => meApi.reservations(list.params),
    placeholderData: keepPreviousData,
  });
  const cancel = useMutation({
    mutationFn: reservationsApi.cancel,
    onSuccess: () => {
      toast.success('Reservation cancelled');
      queryClient.invalidateQueries({ queryKey: ['me'] });
    },
    onError: (error) => toast.error(getErrorMessage(error)),
  });
  const columns: Column<Reservation>[] = [
    { key: 'title', header: 'Book', render: (r) => <span className="font-medium">{r.book_title}</span> },
    { key: 'created_at', header: 'Reserved', sortable: true, render: (r) => formatDate(r.created_at) },
    { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
    {
      key: 'info',
      header: 'Details',
      render: (r) =>
        r.status === 'ready'
          ? `Collect by ${formatDate(r.expires_at)}`
          : r.status === 'pending'
            ? `Queue position ${r.queue_position ?? '—'}`
            : '—',
    },
    {
      key: 'actions',
      header: 'Actions',
      render: (r) =>
        (r.status === 'pending' || r.status === 'ready') && (
          <button
            type="button"
            className="btn-secondary btn-sm"
            onClick={() => cancel.mutate(r.id)}
            disabled={cancel.isPending}
          >
            Cancel
          </button>
        ),
    },
  ];
  return (
    <DataTable
      caption="My reservations"
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
      emptyTitle="No reservations"
      emptyMessage="Reserve a book from its detail page when all copies are on loan."
    />
  );
}
