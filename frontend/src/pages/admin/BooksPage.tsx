import { useState } from 'react';
import { Link } from 'react-router-dom';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { Plus, Upload } from 'lucide-react';
import { booksApi, categoriesApi } from '../../api/endpoints';
import { getErrorMessage } from '../../api/client';
import { useListState } from '../../hooks/useListState';
import { useAuth } from '../../store/auth-context';
import { DataTable, type Column } from '../../components/DataTable';
import { ConfirmDialog, Modal } from '../../components/Modal';
import { SearchInput } from '../../components/SearchInput';
import { BookCover, PageHeader, StatusBadge } from '../../components/ui';
import { BookFormModal } from '../../features/books/BookFormModal';
import { ImportCsvModal } from '../../features/common/ImportCsvModal';
import type { Book, BookDetail } from '../../types';
import { formatDate } from '../../utils/format';

export default function BooksPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const list = useListState({ sort: 'title', order: 'asc' });
  const books = useQuery({
    queryKey: ['books', list.params],
    queryFn: () => booksApi.list(list.params),
    placeholderData: keepPreviousData,
  });
  const categories = useQuery({
    queryKey: ['categories', 'all'],
    queryFn: () => categoriesApi.list({ limit: 100 }),
  });
  const [editing, setEditing] = useState<BookDetail | null>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [importOpen, setImportOpen] = useState(false);
  const [deleting, setDeleting] = useState<Book | null>(null);
  const [copiesFor, setCopiesFor] = useState<Book | null>(null);
  const [copyCount, setCopyCount] = useState('1');

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['books'] });
  const copies = useQuery({
    queryKey: ['book', copiesFor?.id, 'copies'],
    queryFn: () => booksApi.copies(copiesFor!.id),
    enabled: !!copiesFor,
  });
  const remove = useMutation({
    mutationFn: (id: number) => booksApi.remove(id),
    onSuccess: () => {
      toast.success('Book deleted');
      setDeleting(null);
      refresh();
    },
    onError: (error) => toast.error(getErrorMessage(error)),
  });
  const addCopies = useMutation({
    mutationFn: () => booksApi.addCopies(copiesFor!.id, Number(copyCount)),
    onSuccess: () => {
      toast.success('Copies added');
      copies.refetch();
      refresh();
    },
    onError: (error) => toast.error(getErrorMessage(error)),
  });

  const openEdit = async (book: Book) => {
    try {
      setEditing(await booksApi.get(book.id));
      setFormOpen(true);
    } catch (error) {
      toast.error(getErrorMessage(error));
    }
  };

  const columns: Column<Book>[] = [
    {
      key: 'title',
      header: 'Title',
      sortable: true,
      render: (b) => (
        <div className="flex items-center gap-3">
          <BookCover url={b.cover_url} title={b.title} className="h-12 w-9" />
          <div>
            <Link to={`/admin/books/${b.id}`} className="font-medium hover:underline">
              {b.title}
            </Link>
            <p className="text-xs text-stone-500">{b.authors ?? '—'}</p>
          </div>
        </div>
      ),
    },
    {
      key: 'isbn',
      header: 'ISBN',
      sortable: true,
      render: (b) => <span className="font-mono text-xs">{b.isbn}</span>,
    },
    { key: 'category', header: 'Category', render: (b) => b.category_name ?? '—' },
    { key: 'shelf', header: 'Shelf', render: (b) => b.shelf_location ?? '—' },
    {
      key: 'available',
      header: 'Available',
      sortable: true,
      render: (b) => `${b.available_copies} / ${b.total_copies}`,
    },
    {
      key: 'actions',
      header: 'Actions',
      render: (b) => (
        <div className="flex flex-wrap gap-1">
          <button type="button" className="btn-secondary btn-sm" onClick={() => openEdit(b)}>
            Edit
          </button>
          <button type="button" className="btn-secondary btn-sm" onClick={() => setCopiesFor(b)}>
            Copies
          </button>
          {user?.role === 'admin' && (
            <button type="button" className="btn-ghost btn-sm text-red-600" onClick={() => setDeleting(b)}>
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
        title="Books"
        description="Manage the catalogue, copies and barcodes."
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
              <Plus className="h-4 w-4" aria-hidden="true" /> Add book
            </button>
          </>
        }
      />
      <div className="mb-4 flex flex-col gap-3 sm:flex-row">
        <SearchInput
          label="Search books"
          placeholder="Title, author, ISBN…"
          onSearch={(v) => list.setFilter('q', v)}
        />
        <label htmlFor="books-category" className="sr-only">
          Category
        </label>
        <select
          id="books-category"
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
      </div>
      <DataTable
        caption="Books"
        columns={columns}
        rows={books.data?.data}
        rowKey={(b) => b.id}
        loading={books.isLoading}
        error={books.error}
        onRetry={() => books.refetch()}
        meta={books.data?.meta}
        onPageChange={list.setPage}
        sort={list.sort}
        order={list.order}
        onSort={list.toggleSort}
        emptyTitle="No books found"
      />

      <BookFormModal open={formOpen} book={editing} onClose={() => setFormOpen(false)} onSaved={refresh} />
      <ImportCsvModal
        open={importOpen}
        title="Import books"
        columns="isbn, title, authors (separated by ;), category, publisher, edition, published_year, language, description, shelf_location, copies"
        onClose={() => setImportOpen(false)}
        onImport={booksApi.importCsv}
        onDone={refresh}
      />
      <ConfirmDialog
        open={!!deleting}
        title="Delete book"
        danger
        busy={remove.isPending}
        confirmLabel="Delete"
        message={`Delete “${deleting?.title}”? Pending reservations will be cancelled. Books with copies on loan cannot be deleted.`}
        onConfirm={() => deleting && remove.mutate(deleting.id)}
        onCancel={() => setDeleting(null)}
      />
      <Modal
        open={!!copiesFor}
        title={`Copies of “${copiesFor?.title ?? ''}”`}
        onClose={() => setCopiesFor(null)}
        size="lg"
      >
        <form
          className="mb-4 flex items-end gap-2"
          onSubmit={(e) => {
            e.preventDefault();
            addCopies.mutate();
          }}
        >
          <div>
            <label htmlFor="copy-count" className="label">
              Add copies
            </label>
            <input
              id="copy-count"
              type="number"
              min={1}
              max={100}
              className="input w-24"
              value={copyCount}
              onChange={(e) => setCopyCount(e.target.value)}
            />
          </div>
          <button
            type="submit"
            className="btn-primary"
            disabled={addCopies.isPending || !(Number(copyCount) >= 1 && Number(copyCount) <= 100)}
          >
            Add
          </button>
        </form>
        <div className="overflow-x-auto">
          <table className="min-w-full text-sm">
            <caption className="sr-only">Copies</caption>
            <thead>
              <tr className="text-left text-stone-500">
                <th scope="col" className="py-2 pr-4">
                  Barcode
                </th>
                <th scope="col" className="py-2 pr-4">
                  Status
                </th>
                <th scope="col" className="py-2 pr-4">
                  Borrower
                </th>
                <th scope="col" className="py-2">
                  Due
                </th>
              </tr>
            </thead>
            <tbody>
              {copies.data?.map((c) => (
                <tr key={c.id} className="border-t border-stone-100 dark:border-stone-800">
                  <td className="py-2 pr-4 font-mono">{c.barcode}</td>
                  <td className="py-2 pr-4">
                    <StatusBadge status={c.status} />
                  </td>
                  <td className="py-2 pr-4">
                    {c.borrower_name ? `${c.borrower_name} (${c.borrower_roll_no})` : '—'}
                  </td>
                  <td className="py-2">{formatDate(c.due_date)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Modal>
    </>
  );
}
