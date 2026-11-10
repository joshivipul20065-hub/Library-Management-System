import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { ArrowRight, BellRing, BookOpenCheck, CalendarClock, Search } from 'lucide-react';
import { booksApi } from '../../api/endpoints';
import { BookCover, Skeleton } from '../../components/ui';

const FEATURES = [
  {
    icon: Search,
    title: 'Search the catalogue',
    text: 'Find books by title, author, ISBN or category, with live availability.',
  },
  {
    icon: CalendarClock,
    title: 'Reserve online',
    text: 'Reserve a title that is out on loan and we hold it for you when it returns.',
  },
  {
    icon: BookOpenCheck,
    title: 'Manage your loans',
    text: 'Check due dates, renew books and look back at your reading history.',
  },
  {
    icon: BellRing,
    title: 'Stay informed',
    text: 'Get reminders before books are due and alerts when a reservation is ready.',
  },
];

export default function LandingPage() {
  const navigate = useNavigate();
  const [query, setQuery] = useState('');
  const shelf = useQuery({
    queryKey: ['books', 'landing'],
    queryFn: () => booksApi.list({ limit: 40, sort: 'created_at', order: 'desc' }),
    staleTime: 5 * 60_000,
  });
  const withCovers = (shelf.data?.data ?? []).filter((b) => b.cover_url);
  const fan = withCovers.slice(0, 5);
  const newest = withCovers.slice(5, 17);

  return (
    <>
      <section className="relative overflow-hidden border-b border-stone-200/70 dark:border-stone-800">
        <div
          className="pointer-events-none absolute inset-0 bg-[radial-gradient(60rem_30rem_at_80%_-10%,theme(colors.brand.100),transparent),radial-gradient(40rem_20rem_at_0%_110%,theme(colors.accent.100),transparent)] dark:bg-[radial-gradient(60rem_30rem_at_80%_-10%,theme(colors.brand.950),transparent)]"
          aria-hidden="true"
        />
        <div className="relative mx-auto grid max-w-6xl items-center gap-12 px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-[1.1fr_1fr] lg:py-24">
          <div>
            <p className="eyebrow text-brand-700 dark:text-brand-300">Your campus library, online</p>
            <h1 className="mt-3 text-4xl font-semibold leading-[1.08] sm:text-5xl lg:text-6xl">
              Find your next{' '}
              <em className="font-display italic text-brand-700 dark:text-brand-300">great read</em>.
            </h1>
            <p className="mt-5 max-w-xl text-lg text-stone-600 dark:text-stone-300">
              Browse the collection, reserve books that are out on loan and keep track of everything you've
              borrowed — all in one place.
            </p>
            <form
              role="search"
              className="mt-8 flex max-w-xl flex-col gap-2 sm:flex-row"
              onSubmit={(e) => {
                e.preventDefault();
                navigate(query.trim() ? `/catalog?q=${encodeURIComponent(query.trim())}` : '/catalog');
              }}
            >
              <label htmlFor="hero-search" className="sr-only">
                Search books
              </label>
              <div className="relative flex-1">
                <Search
                  className="pointer-events-none absolute left-3.5 top-3 h-5 w-5 text-stone-400"
                  aria-hidden="true"
                />
                <input
                  id="hero-search"
                  type="search"
                  className="input h-11 pl-11 text-base"
                  placeholder="Title, author or ISBN"
                  value={query}
                  onChange={(e) => setQuery(e.target.value)}
                />
              </div>
              <button type="submit" className="btn-primary h-11 px-5 text-base">
                Search
              </button>
            </form>
            <p className="mt-4 text-sm text-stone-500">
              Have an account?{' '}
              <Link to="/login" className="link">
                Sign in
              </Link>{' '}
              to reserve and renew.
            </p>
          </div>

          {/* A fanned stack of real covers from the collection. */}
          <div className="relative mx-auto hidden h-80 w-full max-w-md sm:block" aria-hidden="true">
            {fan.map((book, i) => {
              const offset = i - (fan.length - 1) / 2;
              return (
                <div
                  key={book.id}
                  className="absolute left-1/2 top-1/2"
                  style={{
                    transform: `translate(-50%, -50%) translateX(${offset * 72}px) translateY(${Math.abs(offset) * 14}px) rotate(${offset * 7}deg)`,
                    zIndex: 10 - Math.abs(offset),
                  }}
                >
                  <BookCover url={book.cover_url} title={book.title} className="h-60 w-40" />
                </div>
              );
            })}
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-6xl px-4 py-14 sm:px-6" aria-labelledby="new-heading">
        <div className="mb-6 flex items-end justify-between gap-4">
          <div>
            <p className="eyebrow">Recently added</p>
            <h2 id="new-heading" className="mt-1 text-2xl font-semibold sm:text-3xl">
              New on the shelves
            </h2>
          </div>
          <Link to="/catalog" className="link inline-flex shrink-0 items-center gap-1 text-sm">
            Full catalogue <ArrowRight className="h-4 w-4" aria-hidden="true" />
          </Link>
        </div>
        <ul className="grid grid-cols-3 gap-x-4 gap-y-6 sm:grid-cols-4 md:grid-cols-6">
          {shelf.isLoading &&
            Array.from({ length: 6 }, (_, i) => (
              <li key={i}>
                <Skeleton className="aspect-[2/3] w-full rounded-md" />
              </li>
            ))}
          {newest.map((book) => (
            <li key={book.id} className="min-w-0">
              <Link to={`/books/${book.id}`} className="group block">
                <BookCover
                  url={book.cover_url}
                  title={book.title}
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

      <section
        className="border-t border-stone-200/70 bg-white/60 dark:border-stone-800 dark:bg-stone-900/40"
        aria-label="Features"
      >
        <div className="mx-auto grid max-w-6xl gap-8 px-4 py-14 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
          {FEATURES.map(({ icon: Icon, title, text }) => (
            <div key={title}>
              <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-700 dark:bg-brand-900/50 dark:text-brand-300">
                <Icon className="h-5 w-5" aria-hidden="true" />
              </span>
              <h2 className="mt-4 text-lg font-semibold">{title}</h2>
              <p className="mt-1 text-sm leading-relaxed text-stone-600 dark:text-stone-400">{text}</p>
            </div>
          ))}
        </div>
      </section>
    </>
  );
}
