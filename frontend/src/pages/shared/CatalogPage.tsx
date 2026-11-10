import { Link, useSearchParams } from 'react-router-dom';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { booksApi, categoriesApi } from '../../api/endpoints';
import { getErrorMessage } from '../../api/client';
import { useListState } from '../../hooks/useListState';
import { SearchInput } from '../../components/SearchInput';
import { Pagination } from '../../components/DataTable';
import { BookCover, EmptyState, ErrorState, PageHeader, Skeleton } from '../../components/ui';

export default function CatalogPage({ detailBase }: { detailBase: string }) {
  // `?q=` comes from the landing-page search.
  const [searchParams] = useSearchParams();
  const initialQuery = searchParams.get('q') ?? '';
  const list = useListState({
    sort: 'title',
    order: 'asc',
    limit: 24,
    filters: initialQuery ? { q: initialQuery } : {},
  });
  const books = useQuery({
    queryKey: ['books', list.params],
    queryFn: () => booksApi.list(list.params),
    placeholderData: keepPreviousData,
  });
  const categories = useQuery({
    queryKey: ['categories', 'all'],
    queryFn: () => categoriesApi.list({ limit: 100 }),
  });

  return (
    <div className={detailBase === '/books' ? 'mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:py-12' : ''}>
      <PageHeader title="Catalogue" description="Search by title, author, ISBN or publisher." />
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        <SearchInput
          label="Search books"
          placeholder="Search title, author, ISBN…"
          value={initialQuery}
          onSearch={(v) => list.setFilter('q', v)}
        />
        <label className="sr-only" htmlFor="category-filter">
          Category
        </label>
        <select
          id="category-filter"
          className="input sm:w-56"
          value={list.filters.category_id ?? ''}
          onChange={(e) => list.setFilter('category_id', e.target.value)}
        >
          <option value="">All categories</option>
          {categories.data?.data.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </select>
        <label className="sr-only" htmlFor="availability-filter">
          Availability
        </label>
        <select
          id="availability-filter"
          className="input sm:w-44"
          value={list.filters.available ?? ''}
          onChange={(e) => list.setFilter('available', e.target.value)}
        >
          <option value="">Any availability</option>
          <option value="1">Available now</option>
          <option value="0">All copies out</option>
        </select>
        <label className="sr-only" htmlFor="sort-select">
          Sort by
        </label>
        <select
          id="sort-select"
          className="input sm:w-44"
          value={`${list.sort}:${list.order}`}
          onChange={(e) => {
            const [key = 'title', direction] = e.target.value.split(':');
            list.setSorting(key, direction === 'desc' ? 'desc' : 'asc');
          }}
        >
          <option value="title:asc">Title A–Z</option>
          <option value="title:desc">Title Z–A</option>
          <option value="published_year:desc">Newest publications</option>
          <option value="created_at:desc">Recently added</option>
        </select>
      </div>

      {books.error ? (
        <ErrorState message={getErrorMessage(books.error)} onRetry={() => books.refetch()} />
      ) : (
        <>
          {books.data && (
            <p className="mb-4 text-sm text-stone-500" aria-live="polite">
              {books.data.meta.total} {books.data.meta.total === 1 ? 'book' : 'books'}
            </p>
          )}
          <ul
            className="grid grid-cols-2 gap-x-4 gap-y-8 min-[480px]:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6"
            aria-busy={books.isFetching}
          >
            {books.isLoading &&
              Array.from({ length: 12 }, (_, i) => (
                <li key={i} className="space-y-2">
                  <Skeleton className="aspect-[2/3] w-full rounded-md" />
                  <Skeleton className="h-4 w-4/5" />
                  <Skeleton className="h-3 w-1/2" />
                </li>
              ))}
            {books.data?.data.map((book) => {
              const available = book.available_copies > 0;
              return (
                <li key={book.id} className="min-w-0">
                  <Link to={`${detailBase}/${book.id}`} className="group block">
                    <div className="relative">
                      <BookCover
                        url={book.cover_url}
                        title={book.title}
                        showTitle
                        className="aspect-[2/3] w-full transition duration-300 group-hover:-translate-y-1 group-hover:shadow-lift"
                      />
                      <span
                        className={`absolute bottom-2 left-2 rounded-full px-2 py-0.5 text-[11px] font-semibold shadow-sm backdrop-blur ${
                          available ? 'bg-white/90 text-brand-800' : 'bg-stone-900/80 text-accent-200'
                        }`}
                      >
                        {available ? `${book.available_copies} available` : 'On loan'}
                      </span>
                    </div>
                    <h2 className="mt-3 line-clamp-2 font-sans text-sm font-semibold leading-snug tracking-normal group-hover:text-brand-700 dark:group-hover:text-brand-300">
                      {book.title}
                    </h2>
                    <p className="mt-0.5 truncate text-xs text-stone-500">
                      {book.authors ?? 'Unknown author'}
                    </p>
                  </Link>
                </li>
              );
            })}
          </ul>
          {books.data?.data.length === 0 && (
            <EmptyState title="No books found" message="Try a different search or clear the filters." />
          )}
          {books.data && books.data.meta.total > 0 && (
            <div className="card mt-6 p-0">
              <Pagination meta={books.data.meta} onPageChange={list.setPage} />
            </div>
          )}
        </>
      )}
    </div>
  );
}
