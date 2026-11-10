import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { finesApi } from '../../api/endpoints';
import { getErrorMessage } from '../../api/client';
import { useListState } from '../../hooks/useListState';
import { useAuth } from '../../store/auth-context';
import { DataTable, type Column } from '../../components/DataTable';
import { ConfirmDialog, Modal } from '../../components/Modal';
import { SearchInput } from '../../components/SearchInput';
import { PageHeader, StatusBadge } from '../../components/ui';
import type { Fine } from '../../types';
import { formatDate, formatMoney } from '../../utils/format';

export default function FinesPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const list = useListState({ sort: 'created_at', order: 'desc', filters: { status: 'unpaid' } });
  const fines = useQuery({
    queryKey: ['fines', list.params],
    queryFn: () => finesApi.list(list.params),
    placeholderData: keepPreviousData,
  });
  const [paying, setPaying] = useState<Fine | null>(null);
  const [waiving, setWaiving] = useState<Fine | null>(null);
  const [reason, setReason] = useState('');
  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['fines'] });
    queryClient.invalidateQueries({ queryKey: ['reports'] });
  };
  const pay = useMutation({
    mutationFn: (f: Fine) => finesApi.pay(f.id),
    onSuccess: () => {
      toast.success('Payment recorded');
      setPaying(null);
      refresh();
    },
    onError: (error) => toast.error(getErrorMessage(error)),
  });
  const waive = useMutation({
    mutationFn: (f: Fine) => finesApi.waive(f.id, reason.trim()),
    onSuccess: () => {
      toast.success('Fine waived');
      setWaiving(null);
      setReason('');
      refresh();
    },
    onError: (error) => toast.error(getErrorMessage(error)),
  });

  const columns: Column<Fine>[] = [
    { key: 'student', header: 'Student', render: (f) => `${f.student_name} (${f.roll_no})` },
    { key: 'book', header: 'Book', render: (f) => f.book_title },
    { key: 'reason', header: 'Reason', render: (f) => f.reason },
    {
      key: 'amount',
      header: 'Amount',
      sortable: true,
      render: (f) => <span className="font-medium">{formatMoney(f.amount)}</span>,
    },
    { key: 'created_at', header: 'Charged', sortable: true, render: (f) => formatDate(f.created_at) },
    {
      key: 'status',
      header: 'Status',
      sortable: true,
      render: (f) => (
        <div>
          <StatusBadge status={f.status} />
          {f.processed_by_name && <p className="mt-0.5 text-xs text-stone-500">by {f.processed_by_name}</p>}
        </div>
      ),
    },
    {
      key: 'actions',
      header: 'Actions',
      render: (f) =>
        f.status === 'unpaid' && (
          <div className="flex gap-1">
            <button type="button" className="btn-primary btn-sm" onClick={() => setPaying(f)}>
              Record payment
            </button>
            {user?.role === 'admin' && (
              <button type="button" className="btn-secondary btn-sm" onClick={() => setWaiving(f)}>
                Waive
              </button>
            )}
          </div>
        ),
    },
  ];

  return (
    <>
      <PageHeader title="Fines" description="Unpaid fines block a student from borrowing." />
      <div className="mb-4 flex flex-col gap-3 sm:flex-row">
        <SearchInput
          label="Search fines"
          placeholder="Student, roll no., title"
          onSearch={(v) => list.setFilter('q', v)}
        />
        <label htmlFor="fine-status" className="sr-only">
          Status
        </label>
        <select
          id="fine-status"
          className="input sm:w-40"
          value={list.filters.status ?? ''}
          onChange={(e) => list.setFilter('status', e.target.value)}
        >
          <option value="unpaid">Unpaid</option>
          <option value="paid">Paid</option>
          <option value="waived">Waived</option>
          <option value="">All</option>
        </select>
      </div>
      <DataTable
        caption="Fines"
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
        emptyTitle="No fines in this view"
      />
      <ConfirmDialog
        open={!!paying}
        title="Record payment"
        busy={pay.isPending}
        confirmLabel="Mark as paid"
        message={`Record a payment of ${formatMoney(paying?.amount)} from ${paying?.student_name}?`}
        onConfirm={() => paying && pay.mutate(paying)}
        onCancel={() => setPaying(null)}
      />
      <Modal
        open={!!waiving}
        title="Waive fine"
        onClose={() => setWaiving(null)}
        footer={
          <>
            <button type="button" className="btn-secondary" onClick={() => setWaiving(null)}>
              Cancel
            </button>
            <button
              type="button"
              className="btn-primary"
              disabled={waive.isPending || reason.trim() === ''}
              onClick={() => waiving && waive.mutate(waiving)}
            >
              Waive {formatMoney(waiving?.amount)}
            </button>
          </>
        }
      >
        <label htmlFor="waive-reason" className="label">
          Reason (recorded in the audit log)
        </label>
        <textarea
          id="waive-reason"
          className="input"
          rows={3}
          maxLength={255}
          value={reason}
          onChange={(e) => setReason(e.target.value)}
        />
      </Modal>
    </>
  );
}
