import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { AlertTriangle, BookMarked, CalendarClock, Clock, Wallet } from 'lucide-react';
import { meApi } from '../../api/endpoints';
import { getErrorMessage } from '../../api/client';
import { useAuth } from '../../store/auth-context';
import {
  Alert,
  BookCover,
  EmptyState,
  ErrorState,
  KpiCard,
  Skeleton,
  StatusBadge,
} from '../../components/ui';
import { RenewButton } from '../../features/circulation/RenewButton';
import { formatDate, formatMoney } from '../../utils/format';

export default function StudentDashboard() {
  const { user } = useAuth();
  const dashboard = useQuery({ queryKey: ['me', 'dashboard'], queryFn: meApi.dashboard });
  const d = dashboard.data;

  if (dashboard.error) {
    return <ErrorState message={getErrorMessage(dashboard.error)} onRetry={() => dashboard.refetch()} />;
  }

  return (
    <>
      <section className="relative mb-6 overflow-hidden rounded-3xl bg-brand-950 px-6 py-7 text-white shadow-lift sm:px-8 sm:py-9">
        <div
          className="pointer-events-none absolute inset-0 bg-[radial-gradient(28rem_16rem_at_100%_0%,rgba(229,169,47,0.28),transparent)]"
          aria-hidden="true"
        />
        <div className="relative flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <p className="eyebrow text-accent-300">Your library</p>
            <h1 className="mt-2 text-3xl font-semibold sm:text-4xl">
              Welcome back, {user?.name.split(' ')[0] ?? ''}
            </h1>
            <p className="mt-2 text-brand-100/80">
              {d
                ? d.active_issues.length === 0
                  ? 'You have no books on loan. Find something new to read.'
                  : `You have ${d.active_issues.length} book${d.active_issues.length > 1 ? 's' : ''} on loan${
                      d.due_soon.length ? `, ${d.due_soon.length} due in the next 3 days` : ''
                    }.`
                : 'Loading your account…'}
            </p>
          </div>
          <Link
            to="/student/catalog"
            className="btn shrink-0 bg-accent-400 text-brand-950 hover:bg-accent-300"
          >
            Browse books
          </Link>
        </div>
      </section>

      <div className="space-y-3">
        {user?.status === 'suspended' && (
          <Alert tone="error">Your account is suspended. Please contact the library desk.</Alert>
        )}
        {d && d.overdue.length > 0 && (
          <Alert tone="error">
            <span className="inline-flex items-center gap-2">
              <AlertTriangle className="h-4 w-4" aria-hidden="true" />
              {d.overdue.length} book{d.overdue.length > 1 ? 's are' : ' is'} overdue. Fines grow every day
              until returned.
            </span>
          </Alert>
        )}
        {d && d.due_soon.length > 0 && (
          <Alert tone="warning">
            Due soon: {d.due_soon.map((i) => `"${i.book_title}" (${formatDate(i.due_date)})`).join(', ')}
          </Alert>
        )}
        {d && d.unpaid_fines > 0 && (
          <Alert tone="warning">
            You owe {formatMoney(d.unpaid_fines)} in fines. Unpaid fines block new loans —{' '}
            <Link to="/student/fines" className="link">
              view fines
            </Link>
            .
          </Alert>
        )}
      </div>

      <div className="mt-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <KpiCard
          label="Books borrowed"
          value={d?.active_issues.length ?? 0}
          icon={<BookMarked className="h-5 w-5" />}
          loading={!d}
        />
        <KpiCard
          label="Due in 3 days"
          value={d?.due_soon.length ?? 0}
          icon={<Clock className="h-5 w-5" />}
          tone="amber"
          loading={!d}
        />
        <KpiCard
          label="Fines owed"
          value={formatMoney(d?.unpaid_fines)}
          icon={<Wallet className="h-5 w-5" />}
          tone="red"
          loading={!d}
        />
        <KpiCard
          label="Reservations"
          value={d?.reservations.length ?? 0}
          icon={<CalendarClock className="h-5 w-5" />}
          tone="violet"
          loading={!d}
        />
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-3">
        <section className="card lg:col-span-2" aria-labelledby="current-heading">
          <div className="mb-3 flex items-center justify-between">
            <h2 id="current-heading" className="text-xl font-semibold">
              Currently borrowed
            </h2>
            <Link to="/student/my-books" className="text-sm link">
              History
            </Link>
          </div>
          {!d && <Skeleton className="h-24 w-full" />}
          {d?.active_issues.length === 0 && (
            <EmptyState
              title="No books on loan"
              action={
                <Link to="/student/catalog" className="btn-primary">
                  Browse books
                </Link>
              }
            />
          )}
          <ul className="divide-y divide-stone-100 dark:divide-stone-800">
            {d?.active_issues.map((issue) => (
              <li key={issue.id} className="flex items-center gap-4 py-3">
                <BookCover url={issue.cover_url} title={issue.book_title} className="h-20 w-14" />
                <div className="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                  <div className="min-w-0">
                    <p className="font-medium leading-snug">{issue.book_title}</p>
                    <p className="text-sm text-stone-500">
                      Due {formatDate(issue.due_date)} · renewed {issue.renew_count}×
                    </p>
                  </div>
                  <div className="flex shrink-0 items-center gap-2">
                    <StatusBadge status={issue.days_overdue > 0 ? 'overdue' : issue.status} />
                    <RenewButton issue={issue} />
                  </div>
                </div>
              </li>
            ))}
          </ul>
        </section>

        <section className="card" aria-labelledby="reservations-heading">
          <h2 id="reservations-heading" className="mb-3 text-xl font-semibold">
            My reservations
          </h2>
          {d?.reservations.length === 0 && <p className="text-sm text-stone-500">No active reservations.</p>}
          <ul className="space-y-3">
            {d?.reservations.map((r) => (
              <li key={r.id} className="flex items-start justify-between gap-2 text-sm">
                <div>
                  <p className="font-medium">{r.book_title}</p>
                  <p className="text-stone-500">
                    {r.status === 'ready'
                      ? `Collect by ${formatDate(r.expires_at)}`
                      : `Position ${r.queue_position ?? '—'} in queue`}
                  </p>
                </div>
                <StatusBadge status={r.status} />
              </li>
            ))}
          </ul>
        </section>
      </div>

      <section className="mt-10" aria-labelledby="recommended-heading">
        <p className="eyebrow">Based on what you read</p>
        <h2 id="recommended-heading" className="mb-5 mt-1 text-2xl font-semibold">
          Recommended for you
        </h2>
        <ul className="grid grid-cols-3 gap-x-4 gap-y-6 sm:grid-cols-4 lg:grid-cols-6">
          {d?.recommendations.map((book) => (
            <li key={book.id} className="min-w-0">
              <Link to={`/student/books/${book.id}`} className="group block">
                <BookCover
                  url={book.cover_url}
                  title={book.title}
                  showTitle
                  className="aspect-[2/3] w-full transition duration-300 group-hover:-translate-y-1"
                />
                <p className="mt-2 line-clamp-2 text-sm font-medium leading-snug group-hover:text-brand-700 dark:group-hover:text-brand-300">
                  {book.title}
                </p>
                <p className="truncate text-xs text-stone-500">{book.authors}</p>
              </Link>
            </li>
          ))}
        </ul>
      </section>
    </>
  );
}
