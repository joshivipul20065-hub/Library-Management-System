import { Link, NavLink, Outlet } from 'react-router-dom';
import { BookOpen, Library, Moon, Sun } from 'lucide-react';
import { homeFor, useAuth } from '../store/auth-context';
import { useTheme } from '../store/theme';

export function Logo({ inverted = false }: { inverted?: boolean }) {
  return (
    <span className="flex min-w-0 items-center gap-2.5">
      <span
        className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-xl shadow-sm ${
          inverted ? 'bg-accent-400 text-brand-950' : 'bg-brand-800 text-accent-300'
        }`}
      >
        <Library className="h-5 w-5" aria-hidden="true" />
      </span>
      <span className="min-w-0 leading-tight">
        <span className={`block font-display text-[15px] font-semibold ${inverted ? 'text-white' : ''}`}>
          Library
        </span>
        <span
          className={`block truncate text-[10px] font-semibold uppercase tracking-[0.06em] min-[400px]:tracking-[0.16em] ${
            inverted ? 'text-brand-200/70' : 'text-stone-500'
          }`}
        >
          Management System
        </span>
      </span>
    </span>
  );
}

export function PublicLayout() {
  const { user } = useAuth();
  const { theme, toggle } = useTheme();
  const navClass = ({ isActive }: { isActive: boolean }) =>
    `rounded-lg px-2 py-2 text-sm font-medium transition sm:px-3 ${
      isActive
        ? 'text-brand-700 dark:text-brand-300'
        : 'text-stone-600 hover:text-stone-900 dark:text-stone-300 dark:hover:text-white'
    }`;

  return (
    <div className="flex min-h-screen flex-col">
      <a
        href="#main"
        className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 btn-primary"
      >
        Skip to content
      </a>
      <header className="sticky top-0 z-30 border-b border-stone-200/70 bg-paper/85 backdrop-blur-md dark:border-stone-800 dark:bg-stone-950/85">
        <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-2 px-3 min-[400px]:px-4 sm:px-6">
          <Link to="/" aria-label="Library Management System home" className="min-w-0">
            <Logo />
          </Link>
          <nav aria-label="Main" className="flex shrink-0 items-center gap-0.5 min-[400px]:gap-1">
            <NavLink to="/catalog" className={navClass} aria-label="Catalogue">
              <BookOpen className="h-5 w-5 sm:hidden" aria-hidden="true" />
              <span className="hidden sm:inline">Catalogue</span>
            </NavLink>
            <button
              type="button"
              className="btn-ghost p-2"
              onClick={toggle}
              aria-label={theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'}
            >
              {theme === 'dark' ? (
                <Sun className="h-5 w-5" aria-hidden="true" />
              ) : (
                <Moon className="h-5 w-5" aria-hidden="true" />
              )}
            </button>
            {user ? (
              <Link to={homeFor(user.role)} className="btn-primary">
                Dashboard
              </Link>
            ) : (
              <Link to="/login" className="btn-primary">
                Sign in
              </Link>
            )}
          </nav>
        </div>
      </header>
      <main id="main" className="flex-1">
        <Outlet />
      </main>
      <footer className="border-t border-stone-200/70 dark:border-stone-800">
        <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-4 py-6 text-sm text-stone-500 sm:flex-row sm:px-6">
          <p>© {new Date().getFullYear()} Library Management System</p>
          <p>
            Book data and covers from{' '}
            <a href="https://openlibrary.org" className="link" target="_blank" rel="noopener noreferrer">
              Open Library
            </a>
          </p>
        </div>
      </footer>
    </div>
  );
}
