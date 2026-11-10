import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { meApi } from '../../api/endpoints';
import { useListState } from '../../hooks/useListState';
import { DataTable, type Column } from '../../components/DataTable';
import { Alert, PageHeader, StatusBadge } from '../../components/ui';
import type { Fine } from '../../types';
import { formatDate, formatMoney } from '../../utils/format';

export default function MyFinesPage() {
  const list = useListState({ sort: 'created_at', order: 'desc' });
  const fines = useQuery({
    queryKey: ['me', 'fines', list.params],
    queryFn: () => meApi.fines(list.params),
    placeholderData: keepPreviousData,
  });
  const dashboard = useQuery({ queryKey: ['me', 'dashboard'], queryFn: meApi.dashboard });
  const owed = dashboard.data?.unpaid_fines ?? 0;

  const columns: Column<Fine>[] = [
    { key: 'book', header: 'Book', render: (f) => <span className="font-medium">{f.book_title}</span> },
    { key: 'reason', header: 'Reason', render: (f) => f.reason },
    { key: 'amount', header: 'Amount', sortable: true, render: (f) => formatMoney(f.amount) },
    { key: 'created_at', header: 'Charged', sortable: true, render: (f) => formatDate(f.created_at) },
    { key: 'status', header: 'Status', render: (f) => <StatusBadge status={f.status} /> },
    { key: 'paid_at', header: 'Settled', render: (f) => formatDate(f.paid_at) },
  ];

  return (
    <>
      <PageHeader
        title="My fines"
        description="Fines are charged per day a book is returned late. Pay at the library desk."
      />
      <div className="mb-4">
        {owed > 0 ? (
          <Alert tone="warning">
            Outstanding balance: <strong>{formatMoney(owed)}</strong>. You can't borrow new books until it is
            paid.
          </Alert>
        ) : (
          <Alert tone="success">You have no outstanding fines.</Alert>
        )}
      </div>
      <DataTable
        caption="My fines"
        columns={columns}
        rows={fines.data?.data}
        rowKey={(f) => f.id}
        loading={fines.isLoading}
        error={fines.error}
        meta={fines.data?.meta}
        onPageChange={list.setPage}
        sort={list.sort}
        order={list.order}
        onSort={list.toggleSort}
        emptyTitle="No fines"
        emptyMessage="Return books on time to keep it that way."
      />
    </>
  );
}
