import { useCallback, useEffect, useMemo, useState } from 'react';
import type { ListParams } from '../types';

export function useDebounce<T>(value: T, delay = 300): T {
  const [debounced, setDebounced] = useState(value);
  useEffect(() => {
    const id = setTimeout(() => setDebounced(value), delay);
    return () => clearTimeout(id);
  }, [value, delay]);
  return debounced;
}

interface Options {
  sort: string;
  order?: 'asc' | 'desc';
  limit?: number;
  filters?: Record<string, string>;
}

/** Pagination, sorting and filter state for a server-side table. */
export function useListState(options: Options) {
  const [page, setPage] = useState(1);
  const [sort, setSort] = useState(options.sort);
  const [order, setOrder] = useState<'asc' | 'desc'>(options.order ?? 'asc');
  const [filters, setFilters] = useState<Record<string, string>>(options.filters ?? {});
  const limit = options.limit ?? 20;

  const setFilter = useCallback((key: string, value: string) => {
    setFilters((current) => ({ ...current, [key]: value }));
    setPage(1);
  }, []);

  const toggleSort = useCallback(
    (key: string) => {
      if (key === sort) {
        setOrder((o) => (o === 'asc' ? 'desc' : 'asc'));
      } else {
        setSort(key);
        setOrder('asc');
      }
      setPage(1);
    },
    [sort],
  );

  const params = useMemo<ListParams>(() => {
    const cleaned = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== ''));
    return { page, limit, sort, order, ...cleaned };
  }, [page, limit, sort, order, filters]);

  const setSorting = useCallback((key: string, direction: 'asc' | 'desc') => {
    setSort(key);
    setOrder(direction);
    setPage(1);
  }, []);

  return { params, page, setPage, sort, order, toggleSort, setSorting, filters, setFilter };
}
