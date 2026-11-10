import { useState } from 'react';
import { Link, useLocation, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { ArrowLeft, Pencil } from 'lucide-react';
import { BookFormModal } from '../../features/books/BookFormModal';
import { booksApi, meApi, reservationsApi } from '../../api/endpoints';
import { getErrorMessage } from '../../api/client';
import { isStaff, useAuth } from '../../store/auth-context';
import { BookCover, ErrorState, PageLoader, StatusBadge } from '../../components/ui';
import { formatDate } from '../../utils/format';

export default function BookDetailPage() {
  const id = Number(useParams().id);
  const { pathname } = useLocation();
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [editing, setEditing] = useState(false);
  const book = useQuery({
    queryKey: ['book', id],
    queryFn: () => booksApi.get(id),
    enabled: Number.isInteger(id),
  });
  const copies = useQuery({
    queryKey: ['book', id, 'copies'],
    queryFn: () => booksApi.copies(id),
    enabled: isStaff(user?.role),
  });
  const myReservations = useQuery({
    queryKey: ['me', 'reservations', 'active'],
    queryFn: () => meApi.reservations({ status: 'active', limit: 50 }),
    enabled: user?.role === 'student',
  });
  const reserve = useMutation({
    mutationFn: () => reservationsApi.create(id),
    onSuccess: (reservation) => {
      toast.success(
        reservation.status === 'ready'
          ? 'Reserved! A copy is waiting for you at the desk.'
          : 'Reserved. We will notify you when a copy is ready.',
      );
      queryClient.invalidateQueries({ queryKey: ['me'] });
    },
    onError: (error) => toast.error(getErrorMessage(error)),
  });

  if (book.isLoading) return <PageLoader />;
  if (book.error || !book.data) {
    return (
      <ErrorState message={getErrorMessage(book.error, 'Book not found')} onRetry={() => book.refetch()} />
    );
  }

  const b = book.data;
  const existing = myReservations.data?.data.find((r) => r.book_id === b.id);
  // The same page is mounted in the admin, student and public layouts; adapt to the one it is in.
  const section = pathname.startsWith('/admin')
    ? 'admin'
    : pathname.startsWith('/student')
      ? 'student'
      : 'public';
  const back = {
    admin: { to: '/admin/books', label: 'Back to books' },
    student: { to: '/student/catalog', label: 'Back to catalogue' },
    public: { to: '/catalog', label: 'Back to catalogue' },
  }[section];

  return (
    <div className={section === 'public' ? 'mx-auto max-w-5xl px-4 py-8' : ''}>
      <Link to={back.to} className="mb-4 inline-flex items-center gap-1 text-sm link">
        <ArrowLeft className="h-4 w-4" aria-hidden="true" /> {back.label}
      </Link>
      <div className="card flex flex-col items-center gap-6 p-6 sm:flex-row sm:items-start sm:p-8">
        <BookCover url={b.cover_url} title={b.title} showTitle className="aspect-[2/3] w-40 sm:w-48" />
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <h1 className="text-3xl font-semibold leading-tight">{b.title}</h1>
            {isStaff(user?.role) && (
              <button type="button" className="btn-secondary btn-sm" onClick={() => setEditing(true)}>
                <Pencil className="h-4 w-4" aria-hidden="true" /> Edit book
              </button>
            )}
          </div>
          <p className="mt-1 text-stone-600 dark:text-stone-300">
            {b.authors.map((a) => a.name).join(', ') || 'Unknown author'}
          </p>
          <dl className="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
            {[
              ['ISBN', b.isbn],
              ['Category', b.category_name ?? '—'],
              ['Publisher', b.publisher ?? '—'],
              ['Edition', b.edition ?? '—'],
              ['Year', b.published_year ?? '—'],
              ['Language', b.language],
              ['Shelf', b.shelf_location ?? '—'],
              ['Availability', `${b.available_copies} of ${b.total_copies}`],
            ].map(([label, value]) => (
              <div key={label}>
                <dt className="text-stone-500">{label}</dt>
                <dd className="font-medium">{value}</dd>
              </div>
            ))}
          </dl>
          {b.description && (
            <p className="mt-4 text-sm leading-relaxed text-stone-600 dark:text-stone-300">{b.description}</p>
          )}
          <div className="mt-6">
            {user?.role === 'student' &&
              (existing ? (
                <p className="text-sm">
                  Your reservation: <StatusBadge status={existing.status} />
                  {existing.status === 'ready' &&
                    existing.expires_at &&
                    ` — collect by ${formatDate(existing.expires_at)}`}
                </p>
              ) : (
                <button
                  type="button"
                  className="btn-primary"
                  onClick={() => reserve.mutate()}
                  disabled={reserve.isPending}
                >
                  {reserve.isPending ? 'Reserving…' : 'Reserve this book'}
                </button>
              ))}
            {!user && (
              <Link to="/login" state={{ from: `/student/books/${b.id}` }} className="btn-primary">
                Sign in to reserve
              </Link>
            )}
          </div>
        </div>
      </div>

      {isStaff(user?.role) && (
        <section className="card mt-6 overflow-x-auto" aria-labelledby="copies-heading">
          <h2 id="copies-heading" className="mb-3 font-semibold">
            Copies
          </h2>
          <table className="min-w-full text-sm">
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
        </section>
      )}

      {isStaff(user?.role) && (
        <BookFormModal
          open={editing}
          book={b}
          onClose={() => setEditing(false)}
          onSaved={() => {
            queryClient.invalidateQueries({ queryKey: ['book', id] });
            queryClient.invalidateQueries({ queryKey: ['books'] });
          }}
        />
      )}
    </div>
  );
}
