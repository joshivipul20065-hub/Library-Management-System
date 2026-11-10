import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { adminApi } from '../../api/endpoints';
import { useListState } from '../../hooks/useListState';
import { DataTable, type Column } from '../../components/DataTable';
import { SearchInput } from '../../components/SearchInput';
import { PageHeader } from '../../components/ui';
import type { AuditLog } from '../../types';
import { formatDateTime } from '../../utils/format';

const ENTITIES = ['book', 'category', 'author', 'student', 'issue', 'reservation', 'fine', 'settings'];

export default function AuditLogPage() {
  const list = useListState({ sort: 'created_at', order: 'desc', limit: 25 });
  const logs = useQuery({
    queryKey: ['audit', list.params],
    queryFn: () => adminApi.auditLogs(list.params),
    placeholderData: keepPreviousData,
  });

  const columns: Column<AuditLog>[] = [
    {
      key: 'created_at',
      header: 'When',
      sortable: true,
      render: (l) => <span className="whitespace-nowrap">{formatDateTime(l.created_at)}</span>,
    },
    {
      key: 'user',
      header: 'User',
      render: (l) => (l.user_name ? `${l.user_name} (${l.user_role})` : 'System'),
    },
    {
      key: 'action',
      header: 'Action',
      sortable: true,
      render: (l) => <code className="text-xs">{l.action}</code>,
    },
    { key: 'entity', header: 'Entity', render: (l) => `${l.entity}${l.entity_id ? ` #${l.entity_id}` : ''}` },
    {
      key: 'metadata',
      header: 'Details',
      render: (l) =>
        l.metadata ? (
          <details>
            <summary className="cursor-pointer text-xs link">View</summary>
            <pre className="mt-1 max-w-xs overflow-x-auto whitespace-pre-wrap text-xs">
              {JSON.stringify(l.metadata, null, 2)}
            </pre>
          </details>
        ) : (
          '—'
        ),
    },
    { key: 'ip', header: 'IP', render: (l) => <span className="font-mono text-xs">{l.ip ?? '—'}</span> },
  ];

  return (
    <>
      <PageHeader
        title="Audit log"
        description="Every staff change to books, students, loans, fines and settings."
      />
      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
        <SearchInput
          label="Search audit log"
          placeholder="Action or user"
          onSearch={(v) => list.setFilter('q', v)}
        />
        <label htmlFor="audit-entity" className="sr-only">
          Entity
        </label>
        <select
          id="audit-entity"
          className="input sm:w-40"
          value={list.filters.entity ?? ''}
          onChange={(e) => list.setFilter('entity', e.target.value)}
        >
          <option value="">All entities</option>
          {ENTITIES.map((e) => (
            <option key={e} value={e}>
              {e}
            </option>
          ))}
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
        caption="Audit log"
        columns={columns}
        rows={logs.data?.data}
        rowKey={(l) => l.id}
        loading={logs.isLoading}
        error={logs.error}
        meta={logs.data?.meta}
        onPageChange={list.setPage}
        sort={list.sort}
        order={list.order}
        onSort={list.toggleSort}
        emptyTitle="No audit entries"
      />
    </>
  );
}
