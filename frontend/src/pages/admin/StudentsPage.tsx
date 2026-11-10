import { useState } from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { Plus, Upload } from 'lucide-react';
import { studentsApi } from '../../api/endpoints';
import { getErrorMessage } from '../../api/client';
import { useListState } from '../../hooks/useListState';
import { useAuth } from '../../store/auth-context';
import { DataTable, type Column } from '../../components/DataTable';
import { ConfirmDialog, Modal } from '../../components/Modal';
import { SearchInput } from '../../components/SearchInput';
import { PageHeader, StatusBadge } from '../../components/ui';
import { StudentFormModal } from '../../features/students/StudentFormModal';
import { ImportCsvModal } from '../../features/common/ImportCsvModal';
import type { Issue, Student } from '../../types';
import { formatDate, formatMoney } from '../../utils/format';

export default function StudentsPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const list = useListState({ sort: 'name', order: 'asc' });
  const students = useQuery({
    queryKey: ['students', list.params],
    queryFn: () => studentsApi.list(list.params),
    placeholderData: keepPreviousData,
  });
  const [editing, setEditing] = useState<Student | null>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [importOpen, setImportOpen] = useState(false);
  const [historyFor, setHistoryFor] = useState<Student | null>(null);
  const [deleting, setDeleting] = useState<Student | null>(null);
  const refresh = () => queryClient.invalidateQueries({ queryKey: ['students'] });

  const toggleStatus = useMutation({
    mutationFn: (s: Student) =>
      s.status === 'active' ? studentsApi.suspend(s.id) : studentsApi.activate(s.id),
    onSuccess: (s) => {
      toast.success(s.status === 'suspended' ? `${s.name} suspended` : `${s.name} reactivated`);
      refresh();
    },
    onError: (error) => toast.error(getErrorMessage(error)),
  });
  const remove = useMutation({
    mutationFn: (id: number) => studentsApi.remove(id),
    onSuccess: () => {
      toast.success('Student deleted');
      setDeleting(null);
      refresh();
    },
    onError: (error) => toast.error(getErrorMessage(error)),
  });

  const columns: Column<Student>[] = [
    {
      key: 'name',
      header: 'Student',
      sortable: true,
      render: (s) => (
        <div>
          <p className="font-medium">{s.name}</p>
          <p className="text-xs text-stone-500">{s.email}</p>
        </div>
      ),
    },
    {
      key: 'roll_no',
      header: 'Roll no.',
      sortable: true,
      render: (s) => <span className="font-mono text-xs">{s.roll_no}</span>,
    },
    {
      key: 'department',
      header: 'Department',
      sortable: true,
      render: (s) => `${s.department} · Y${s.year}`,
    },
    { key: 'loans', header: 'On loan', render: (s) => s.active_issues ?? 0 },
    {
      key: 'fines',
      header: 'Unpaid fines',
      render: (s) => (Number(s.unpaid_fines) > 0 ? formatMoney(s.unpaid_fines) : '—'),
    },
    { key: 'status', header: 'Status', render: (s) => <StatusBadge status={s.status} /> },
    {
      key: 'actions',
      header: 'Actions',
      render: (s) => (
        <div className="flex flex-wrap gap-1">
          <button
            type="button"
            className="btn-secondary btn-sm"
            onClick={() => {
              setEditing(s);
              setFormOpen(true);
            }}
          >
            Edit
          </button>
          <button type="button" className="btn-secondary btn-sm" onClick={() => setHistoryFor(s)}>
            History
          </button>
          <button
            type="button"
            className="btn-secondary btn-sm"
            onClick={() => toggleStatus.mutate(s)}
            disabled={toggleStatus.isPending}
          >
            {s.status === 'active' ? 'Suspend' : 'Activate'}
          </button>
          {user?.role === 'admin' && (
            <button type="button" className="btn-ghost btn-sm text-red-600" onClick={() => setDeleting(s)}>
              Delete
            </button>
          )}
        </div>
      ),
    },
  ];

  return (
    <>
      <PageHeader
        title="Students"
        actions={
          <>
            <button type="button" className="btn-secondary" onClick={() => setImportOpen(true)}>
              <Upload className="h-4 w-4" aria-hidden="true" /> Import CSV
            </button>
            <button
              type="button"
              className="btn-primary"
              onClick={() => {
                setEditing(null);
                setFormOpen(true);
              }}
            >
              <Plus className="h-4 w-4" aria-hidden="true" /> Add student
            </button>
          </>
        }
      />
      <div className="mb-4 flex flex-col gap-3 sm:flex-row">
        <SearchInput
          label="Search students"
          placeholder="Name, email or roll no."
          onSearch={(v) => list.setFilter('q', v)}
        />
        <label htmlFor="student-status" className="sr-only">
          Status
        </label>
        <select
          id="student-status"
          className="input sm:w-44"
          value={list.filters.status ?? ''}
          onChange={(e) => list.setFilter('status', e.target.value)}
        >
          <option value="">All statuses</option>
          <option value="active">Active</option>
          <option value="suspended">Suspended</option>
        </select>
      </div>
      <DataTable
        caption="Students"
        columns={columns}
        rows={students.data?.data}
        rowKey={(s) => s.id}
        loading={students.isLoading}
        error={students.error}
        onRetry={() => students.refetch()}
        meta={students.data?.meta}
        onPageChange={list.setPage}
        sort={list.sort}
        order={list.order}
        onSort={list.toggleSort}
        emptyTitle="No students found"
      />
      <StudentFormModal
        open={formOpen}
        student={editing}
        onClose={() => setFormOpen(false)}
        onSaved={refresh}
      />
      <ImportCsvModal
        open={importOpen}
        title="Import students"
        columns="name, email, roll_no, department, year, phone, address"
        onClose={() => setImportOpen(false)}
        onImport={studentsApi.importCsv}
        onDone={refresh}
      />
      <ConfirmDialog
        open={!!deleting}
        title="Delete student"
        danger
        busy={remove.isPending}
        message={`Delete ${deleting?.name}? Students with books on loan or unpaid fines cannot be deleted.`}
        onConfirm={() => deleting && remove.mutate(deleting.id)}
        onCancel={() => setDeleting(null)}
      />
      {historyFor && <HistoryModal student={historyFor} onClose={() => setHistoryFor(null)} />}
    </>
  );
}

function HistoryModal({ student, onClose }: { student: Student; onClose: () => void }) {
  const list = useListState({ sort: 'issue_date', order: 'desc', limit: 10 });
  const history = useQuery({
    queryKey: ['students', student.id, 'history', list.params],
    queryFn: () => studentsApi.history(student.id, list.params),
    placeholderData: keepPreviousData,
  });
  const columns: Column<Issue>[] = [
    { key: 'title', header: 'Book', render: (i) => i.book_title },
    { key: 'issue_date', header: 'Issued', sortable: true, render: (i) => formatDate(i.issue_date) },
    { key: 'due_date', header: 'Due', sortable: true, render: (i) => formatDate(i.due_date) },
    { key: 'return_date', header: 'Returned', render: (i) => formatDate(i.return_date) },
    { key: 'status', header: 'Status', render: (i) => <StatusBadge status={i.status} /> },
    {
      key: 'fine',
      header: 'Fine',
      render: (i) => (Number(i.fine_amount) > 0 ? formatMoney(i.fine_amount) : '—'),
    },
  ];
  return (
    <Modal open title={`Borrowing history — ${student.name}`} onClose={onClose} size="lg">
      <DataTable
        caption="Borrowing history"
        columns={columns}
        rows={history.data?.data}
        rowKey={(i) => i.id}
        loading={history.isLoading}
        error={history.error}
        meta={history.data?.meta}
        onPageChange={list.setPage}
        sort={list.sort}
        order={list.order}
        onSort={list.toggleSort}
        emptyTitle="No borrowing history"
      />
    </Modal>
  );
}
