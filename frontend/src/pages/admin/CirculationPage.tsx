import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { issuesApi } from '../../api/endpoints';
import { getErrorMessage } from '../../api/client';
import { useListState } from '../../hooks/useListState';
import { DataTable, type Column } from '../../components/DataTable';
import { ConfirmDialog } from '../../components/Modal';
import { SearchInput } from '../../components/SearchInput';
import { PageHeader, StatusBadge } from '../../components/ui';
import { IssueForm } from '../../features/circulation/IssueForm';
import { ReturnDialog } from '../../features/circulation/ReturnDialog';
import type { Issue } from '../../types';
import { formatDate, formatMoney } from '../../utils/format';

export default function CirculationPage() {
  const queryClient = useQueryClient();
  const list = useListState({ sort: 'id', order: 'desc', filters: { status: 'active' } });
  const issues = useQuery({
    queryKey: ['issues', list.params],
    queryFn: () => issuesApi.list(list.params),
    placeholderData: keepPreviousData,
  });
  const [returning, setReturning] = useState<Issue | null>(null);
  const [losing, setLosing] = useState<Issue | null>(null);
  const [returnBarcode, setReturnBarcode] = useState('');

  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['issues'] });
    queryClient.invalidateQueries({ queryKey: ['reports'] });
  };
  const doReturn = useMutation({
    mutationFn: (issue: Issue) => issuesApi.returnBook(issue.id),
    onSuccess: (result) => {
      toast.success(
        result.fine_amount > 0
          ? `Returned ${result.days_overdue} day(s) late — fine of ${formatMoney(result.fine_amount)} charged`
          : 'Returned on time',
      );
      if (result.reservation_ready) toast('This copy is now on hold for a reservation.', { icon: '📌' });
      setReturning(null);
      setReturnBarcode('');
      refresh();
    },
    onError: (error) => toast.error(getErrorMessage(error)),
  });
  const renew = useMutation({
    mutationFn: (issue: Issue) => issuesApi.renew(issue.id),
    onSuccess: (issue) => {
      toast.success(`Renewed — due ${formatDate(issue.due_date)}`);
      refresh();
    },
    onError: (error) => toast.error(getErrorMessage(error)),
  });
  const markLost = useMutation({
    mutationFn: (issue: Issue) => issuesApi.markLost(issue.id),
    onSuccess: (result) => {
      toast.success(`Marked lost — ${formatMoney(result.fine_amount)} charged`);
      setLosing(null);
      refresh();
    },
    onError: (error) => toast.error(getErrorMessage(error)),
  });

  const findForReturn = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!returnBarcode.trim()) return;
    try {
      const found = await issuesApi.list({ barcode: returnBarcode.trim(), status: 'active', limit: 1 });
      const issue = found.data[0];
      if (issue) setReturning(issue);
      else toast.error('No active loan for this barcode');
    } catch (error) {
      toast.error(getErrorMessage(error));
    }
  };

  const active = (i: Issue) => i.status === 'issued' || i.status === 'overdue';
  const columns: Column<Issue>[] = [
    {
      key: 'title',
      header: 'Book',
      sortable: true,
      render: (i) => (
        <div>
          <p className="font-medium">{i.book_title}</p>
          <p className="font-mono text-xs text-stone-500">{i.barcode}</p>
        </div>
      ),
    },
    {
      key: 'student',
      header: 'Student',
      sortable: true,
      render: (i) => (
        <div>
          <p>{i.student_name}</p>
          <p className="font-mono text-xs text-stone-500">{i.roll_no}</p>
        </div>
      ),
    },
    { key: 'issue_date', header: 'Issued', sortable: true, render: (i) => formatDate(i.issue_date) },
    {
      key: 'due_date',
      header: 'Due',
      sortable: true,
      render: (i) => (
        <span className={active(i) && i.days_overdue > 0 ? 'font-medium text-red-600 dark:text-red-400' : ''}>
          {formatDate(i.due_date)}
          {active(i) && i.days_overdue > 0 && ` (${i.days_overdue}d late)`}
        </span>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (i) => <StatusBadge status={active(i) && i.days_overdue > 0 ? 'overdue' : i.status} />,
    },
    {
      key: 'actions',
      header: 'Actions',
      render: (i) =>
        active(i) ? (
          <div className="flex flex-wrap gap-1">
            <button type="button" className="btn-primary btn-sm" onClick={() => setReturning(i)}>
              Return
            </button>
            <button
              type="button"
              className="btn-secondary btn-sm"
              onClick={() => renew.mutate(i)}
              disabled={renew.isPending}
            >
              Renew
            </button>
            <button type="button" className="btn-ghost btn-sm text-red-600" onClick={() => setLosing(i)}>
              Lost
            </button>
          </div>
        ) : (
          <span className="text-xs text-stone-500">
            {i.return_date ? `Returned ${formatDate(i.return_date)}` : '—'}
          </span>
        ),
    },
  ];

  return (
    <>
      <PageHeader
        title="Issue & return desk"
        description="Scan or type a roll number and barcode. Returns show the fine before you confirm."
      />
      <div className="grid gap-6 lg:grid-cols-2">
        <section className="card" aria-labelledby="issue-heading">
          <h2 id="issue-heading" className="mb-4 font-semibold">
            Issue a book
          </h2>
          <IssueForm onIssued={refresh} />
        </section>
        <section className="card" aria-labelledby="return-heading">
          <h2 id="return-heading" className="mb-4 font-semibold">
            Return a book
          </h2>
          <form onSubmit={findForReturn} className="flex items-end gap-2">
            <div className="flex-1">
              <label htmlFor="return-barcode" className="label">
                Book barcode
              </label>
              <input
                id="return-barcode"
                className="input font-mono"
                autoComplete="off"
                value={returnBarcode}
                onChange={(e) => setReturnBarcode(e.target.value)}
              />
            </div>
            <button type="submit" className="btn-primary">
              Find loan
            </button>
          </form>
          <p className="mt-3 text-sm text-stone-500">Or use the Return button in the loans list below.</p>
        </section>
      </div>

      <h2 className="mb-3 mt-8 text-lg font-semibold">Loans</h2>
      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
        <SearchInput
          label="Search loans"
          placeholder="Title, barcode, roll no., name"
          onSearch={(v) => list.setFilter('q', v)}
        />
        <label htmlFor="loan-status" className="sr-only">
          Status
        </label>
        <select
          id="loan-status"
          className="input sm:w-40"
          value={list.filters.status ?? ''}
          onChange={(e) => list.setFilter('status', e.target.value)}
        >
          <option value="active">On loan</option>
          <option value="overdue">Flagged overdue</option>
          <option value="returned">Returned</option>
          <option value="lost">Lost</option>
          <option value="">All</option>
        </select>
        <label className="flex items-center gap-2 text-sm">
          From
          <input
            type="date"
            className="input w-auto"
            value={list.filters.from ?? ''}
            onChange={(e) => list.setFilter('from', e.target.value)}
          />
        </label>
        <label className="flex items-center gap-2 text-sm">
          To
          <input
            type="date"
            className="input w-auto"
            value={list.filters.to ?? ''}
            onChange={(e) => list.setFilter('to', e.target.value)}
          />
        </label>
      </div>
      <DataTable
        caption="Loans"
        columns={columns}
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
        emptyTitle="No loans match these filters"
      />

      <ReturnDialog
        issue={returning}
        busy={doReturn.isPending}
        onConfirm={(i) => doReturn.mutate(i)}
        onCancel={() => setReturning(null)}
      />
      <ConfirmDialog
        open={!!losing}
        title="Mark book as lost"
        danger
        busy={markLost.isPending}
        confirmLabel="Mark lost"
        message={`Mark “${losing?.book_title}” (${losing?.barcode}) as lost? The copy is removed from stock and the student is charged the replacement fee plus any overdue fine.`}
        onConfirm={() => losing && markLost.mutate(losing)}
        onCancel={() => setLosing(null)}
      />
    </>
  );
}
