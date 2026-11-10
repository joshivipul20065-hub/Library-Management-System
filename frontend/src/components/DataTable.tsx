import type { ReactNode } from 'react';
import { ArrowDown, ArrowUp, ArrowUpDown, ChevronLeft, ChevronRight } from 'lucide-react';
import type { PageMeta } from '../types';
import { getErrorMessage } from '../api/client';
import { EmptyState, ErrorState, Skeleton } from './ui';

export interface Column<T> {
  key: string;
  header: string;
  render: (row: T) => ReactNode;
  sortable?: boolean;
  className?: string;
}

interface Props<T> {
  caption: string;
  columns: Column<T>[];
  rows: T[] | undefined;
  rowKey: (row: T) => string | number;
  loading: boolean;
  error?: unknown;
  onRetry?: () => void;
  meta?: PageMeta;
  onPageChange?: (page: number) => void;
  sort?: string;
  order?: 'asc' | 'desc';
  onSort?: (key: string) => void;
  emptyTitle?: string;
  emptyMessage?: string;
}

/** Server-paginated, sortable table with loading skeletons and empty/error states. */
export function DataTable<T>(props: Props<T>) {
  const { columns, rows, loading, error, meta, sort, order, onSort } = props;

  const [primary, ...rest] = columns;
  const details = rest.filter((c) => c.key !== 'actions');
  const actions = rest.find((c) => c.key === 'actions');
  const sortable = columns.filter((c) => c.sortable);

  return (
    <div className="card overflow-hidden p-0">
      {/* Phones: each row as a card (first column as its title, the rest as label/value pairs). */}
      <div className="md:hidden">
        {sortable.length > 0 && onSort && (
          <div className="flex items-center gap-2 border-b border-stone-200 px-4 py-2.5 dark:border-stone-800">
            <label htmlFor={`sort-${props.caption}`} className="eyebrow">
              Sort
            </label>
            <select
              id={`sort-${props.caption}`}
              className="input h-8 flex-1 py-0 text-xs"
              value={sort ?? ''}
              onChange={(e) => onSort(e.target.value)}
            >
              {sortable.map((c) => (
                <option key={c.key} value={c.key}>
                  {c.header}
                </option>
              ))}
            </select>
            <button
              type="button"
              className="btn-secondary btn-sm"
              onClick={() => sort && onSort(sort)}
              aria-label={
                order === 'asc'
                  ? 'Sorted ascending, switch to descending'
                  : 'Sorted descending, switch to ascending'
              }
            >
              {order === 'asc' ? <ArrowUp className="h-3.5 w-3.5" /> : <ArrowDown className="h-3.5 w-3.5" />}
            </button>
          </div>
        )}
        <ul className="divide-y divide-stone-100 dark:divide-stone-800" aria-label={props.caption}>
          {loading && !rows
            ? Array.from({ length: 4 }, (_, i) => (
                <li key={i} className="space-y-2 p-4">
                  <Skeleton className="h-5 w-2/3" />
                  <Skeleton className="h-4 w-1/2" />
                </li>
              ))
            : rows?.map((row) => (
                <li key={props.rowKey(row)} className="p-4">
                  {primary && <div className="min-w-0">{primary.render(row)}</div>}
                  {details.length > 0 && (
                    <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                      {details.map((column) => (
                        <div key={column.key} className="min-w-0">
                          <dt className="eyebrow text-[10px]">{column.header}</dt>
                          <dd className="mt-0.5 break-words">{column.render(row)}</dd>
                        </div>
                      ))}
                    </dl>
                  )}
                  {actions && <div className="mt-3">{actions.render(row)}</div>}
                </li>
              ))}
        </ul>
      </div>

      {/* Tablets and up: a regular table. */}
      <div className="hidden overflow-x-auto md:block">
        <table className="min-w-full divide-y divide-stone-200 text-sm dark:divide-stone-800">
          <caption className="sr-only">{props.caption}</caption>
          <thead className="border-b border-stone-200 bg-stone-50/80 dark:border-stone-800 dark:bg-stone-800/40">
            <tr>
              {columns.map((column) => {
                const active = sort === column.key;
                const ariaSort = active ? (order === 'asc' ? 'ascending' : 'descending') : undefined;
                return (
                  <th
                    key={column.key}
                    scope="col"
                    aria-sort={ariaSort}
                    className={`whitespace-nowrap px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.1em] text-stone-500 dark:text-stone-400 ${column.className ?? ''}`}
                  >
                    {column.sortable && onSort ? (
                      <button
                        type="button"
                        className="inline-flex items-center gap-1 hover:text-stone-900 dark:hover:text-white"
                        onClick={() => onSort(column.key)}
                      >
                        {column.header}
                        {active ? (
                          order === 'asc' ? (
                            <ArrowUp className="h-3.5 w-3.5" aria-hidden="true" />
                          ) : (
                            <ArrowDown className="h-3.5 w-3.5" aria-hidden="true" />
                          )
                        ) : (
                          <ArrowUpDown className="h-3.5 w-3.5 opacity-40" aria-hidden="true" />
                        )}
                      </button>
                    ) : (
                      column.header
                    )}
                  </th>
                );
              })}
            </tr>
          </thead>
          <tbody className="divide-y divide-stone-100 dark:divide-stone-800">
            {loading && !rows
              ? Array.from({ length: 5 }, (_, i) => (
                  <tr key={i}>
                    {columns.map((column) => (
                      <td key={column.key} className="px-4 py-3">
                        <Skeleton className="h-4 w-full max-w-[10rem]" />
                      </td>
                    ))}
                  </tr>
                ))
              : rows?.map((row) => (
                  <tr key={props.rowKey(row)} className="hover:bg-stone-50 dark:hover:bg-stone-800/40">
                    {columns.map((column) => (
                      <td key={column.key} className={`px-4 py-3 align-middle ${column.className ?? ''}`}>
                        {column.render(row)}
                      </td>
                    ))}
                  </tr>
                ))}
          </tbody>
        </table>
      </div>
      {error ? (
        <ErrorState message={getErrorMessage(error)} onRetry={props.onRetry} />
      ) : (
        !loading &&
        rows?.length === 0 && (
          <EmptyState title={props.emptyTitle ?? 'Nothing here yet'} message={props.emptyMessage} />
        )
      )}
      {meta && props.onPageChange && meta.total > 0 && (
        <Pagination meta={meta} onPageChange={props.onPageChange} />
      )}
    </div>
  );
}

export function Pagination({ meta, onPageChange }: { meta: PageMeta; onPageChange: (page: number) => void }) {
  const from = (meta.page - 1) * meta.limit + 1;
  const to = Math.min(meta.total, meta.page * meta.limit);
  return (
    <nav
      aria-label="Pagination"
      className="flex items-center justify-between gap-2 border-t border-stone-200 px-4 py-3 text-sm dark:border-stone-800"
    >
      <p className="text-stone-500">
        {from}–{to} of {meta.total}
      </p>
      <div className="flex items-center gap-2">
        <button
          type="button"
          className="btn-secondary btn-sm"
          disabled={meta.page <= 1}
          onClick={() => onPageChange(meta.page - 1)}
          aria-label="Previous page"
        >
          <ChevronLeft className="h-4 w-4" aria-hidden="true" />
        </button>
        <span aria-current="page">
          Page {meta.page} / {meta.pages}
        </span>
        <button
          type="button"
          className="btn-secondary btn-sm"
          disabled={meta.page >= meta.pages}
          onClick={() => onPageChange(meta.page + 1)}
          aria-label="Next page"
        >
          <ChevronRight className="h-4 w-4" aria-hidden="true" />
        </button>
      </div>
    </nav>
  );
}
