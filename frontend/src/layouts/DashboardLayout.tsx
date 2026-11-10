import { useEffect, useState, type ComponentType } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import {
  AlertOctagon,
  BarChart3,
  BookCopy,
  BookMarked,
  BookOpen,
  CalendarClock,
  FolderTree,
  History,
  LayoutDashboard,
  Library,
  LogOut,
  Menu,
  Moon,
  PenTool,
  Receipt,
  ScanLine,
  Settings,
  Sun,
  User as UserIcon,
  Users,
  Wallet,
  X,
} from 'lucide-react';
import { useAuth } from '../store/auth-context';
import { useTheme } from '../store/theme';
import type { Role } from '../types';
import { NotificationBell } from '../components/NotificationBell';

interface NavItem {
  to: string;
  label: string;
  icon: ComponentType<{ className?: string }>;
  roles: Role[];
  end?: boolean;
}
interface NavGroup {
  title: string;
  items: NavItem[];
}

const STAFF: Role[] = ['admin', 'librarian'];
const ALL: Role[] = ['admin', 'librarian', 'student'];
const NAV: NavGroup[] = [
  {
    title: 'Overview',
    items: [
      { to: '/student', label: 'Dashboard', icon: LayoutDashboard, roles: ['student'], end: true },
      { to: '/admin', label: 'Dashboard', icon: LayoutDashboard, roles: STAFF, end: true },
    ],
  },
  {
    title: 'My library',
    items: [
      { to: '/student/catalog', label: 'Browse books', icon: BookOpen, roles: ['student'] },
      { to: '/student/my-books', label: 'My books', icon: BookMarked, roles: ['student'] },
      { to: '/student/fines', label: 'My fines', icon: Wallet, roles: ['student'] },
    ],
  },
  {
    title: 'Circulation',
    items: [
      { to: '/admin/circulation', label: 'Issue / Return', icon: ScanLine, roles: STAFF },
      { to: '/admin/reservations', label: 'Reservations', icon: CalendarClock, roles: STAFF },
      { to: '/admin/overdue', label: 'Overdue', icon: AlertOctagon, roles: STAFF },
      { to: '/admin/fines', label: 'Fines', icon: Receipt, roles: STAFF },
    ],
  },
  {
    title: 'Catalogue',
    items: [
      { to: '/admin/books', label: 'Books', icon: BookCopy, roles: STAFF },
      { to: '/admin/categories', label: 'Categories', icon: FolderTree, roles: STAFF },
      { to: '/admin/authors', label: 'Authors', icon: PenTool, roles: STAFF },
    ],
  },
  {
    title: 'People',
    items: [{ to: '/admin/students', label: 'Students', icon: Users, roles: STAFF }],
  },
  {
    title: 'Insights',
    items: [
      { to: '/admin/reports', label: 'Reports', icon: BarChart3, roles: STAFF },
      { to: '/admin/audit-logs', label: 'Audit log', icon: History, roles: ['admin'] },
    ],
  },
  {
    title: 'Account',
    items: [
      { to: '/admin/settings', label: 'Settings', icon: Settings, roles: STAFF },
      { to: '/profile', label: 'Profile', icon: UserIcon, roles: ALL },
    ],
  },
];

const initials = (name: string) =>
  name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('');

export function DashboardLayout() {
  const { user, logout } = useAuth();
  const { theme, toggle } = useTheme();
  const [menuOpen, setMenuOpen] = useState(false);
  const navigate = useNavigate();
  const location = useLocation();

  // Close the phone drawer on navigation and on Escape.
  useEffect(() => setMenuOpen(false), [location.pathname]);
  useEffect(() => {
    if (!menuOpen) return;
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setMenuOpen(false);
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [menuOpen]);

  const groups = NAV.map((group) => ({
    ...group,
    items: group.items.filter((item) => user && item.roles.includes(user.role)),
  })).filter((group) => group.items.length > 0);

  const nav = (
    <nav aria-label="Dashboard" className="flex flex-col gap-5 px-3 py-5">
      {groups.map((group) => (
        <div key={group.title}>
          <p className="mb-1.5 px-3 text-[10px] font-semibold uppercase tracking-[0.16em] text-brand-200/60">
            {group.title}
          </p>
          <ul className="flex flex-col gap-0.5">
            {group.items.map(({ to, label, icon: Icon, end }) => (
              <li key={to}>
                <NavLink
                  to={to}
                  end={end}
                  className={({ isActive }) =>
                    `relative flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition ${
                      isActive
                        ? 'bg-white/10 text-white before:absolute before:inset-y-2 before:-left-3 before:w-1 before:rounded-r-full before:bg-accent-400'
                        : 'text-brand-100/80 hover:bg-white/5 hover:text-white'
                    }`
                  }
                >
                  <Icon className="h-[18px] w-[18px] shrink-0" aria-hidden="true" />
                  {label}
                </NavLink>
              </li>
            ))}
          </ul>
        </div>
      ))}
    </nav>
  );

  // `extra` leaves room for the drawer's close button.
  const brand = (extra = '') => (
    <Link
      to="/"
      title="Library Management System"
      className={`flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-5 ${extra}`}
    >
      <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-accent-400 text-brand-950 shadow-sm">
        <Library className="h-5 w-5" aria-hidden="true" />
      </span>
      <span className="min-w-0 leading-tight">
        <span className="block truncate font-display text-[15px] font-semibold text-white">Library</span>
        <span className="block truncate text-[11px] font-medium uppercase tracking-[0.14em] text-brand-200/70">
          Management System
        </span>
      </span>
    </Link>
  );

  const sidebarSurface = 'bg-brand-950 text-white';

  return (
    <div className="min-h-screen lg:flex">
      <a
        href="#main"
        className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 btn-primary"
      >
        Skip to content
      </a>

      <aside className={`hidden w-64 shrink-0 lg:block ${sidebarSurface}`}>
        <div className="sticky top-0 flex h-screen flex-col overflow-y-auto">
          {brand()}
          {nav}
        </div>
      </aside>

      {menuOpen && (
        <div
          className="fixed inset-0 z-40 lg:hidden"
          role="dialog"
          aria-modal="true"
          aria-label="Navigation menu"
        >
          <div
            className="absolute inset-0 bg-stone-950/60 backdrop-blur-sm"
            aria-hidden="true"
            onClick={() => setMenuOpen(false)}
          />
          <div
            className={`relative flex h-full w-72 max-w-[85vw] flex-col overflow-y-auto shadow-lift ${sidebarSurface}`}
          >
            <button
              type="button"
              className="absolute right-2 top-3.5 rounded-lg p-2 text-brand-100 hover:bg-white/10"
              onClick={() => setMenuOpen(false)}
              aria-label="Close menu"
            >
              <X className="h-5 w-5" aria-hidden="true" />
            </button>
            {brand('pr-14')}
            {nav}
          </div>
        </div>
      )}

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-30 flex h-16 items-center justify-between gap-2 border-b border-stone-200/80 bg-paper/85 px-4 backdrop-blur-md dark:border-stone-800 dark:bg-stone-950/85 sm:px-6">
          <div className="flex items-center gap-2">
            <button
              type="button"
              className="btn-ghost p-2 lg:hidden"
              onClick={() => setMenuOpen(true)}
              aria-label="Open menu"
            >
              <Menu className="h-5 w-5" aria-hidden="true" />
            </button>
            <Link
              to="/"
              className="flex items-center gap-2 lg:hidden"
              aria-label="Library Management System home"
            >
              <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-800 text-accent-300">
                <Library className="h-4 w-4" aria-hidden="true" />
              </span>
            </Link>
          </div>
          <div className="flex items-center gap-1 sm:gap-2">
            <NotificationBell />
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
            <Link
              to="/profile"
              className="ml-1 flex items-center gap-2.5 rounded-full py-1 pl-1 pr-1 hover:bg-stone-900/5 dark:hover:bg-white/10 sm:pr-3"
            >
              <span
                className="flex h-8 w-8 items-center justify-center rounded-full bg-brand-700 text-xs font-semibold text-white"
                aria-hidden="true"
              >
                {initials(user?.name ?? '')}
              </span>
              <span className="hidden text-left leading-tight sm:block">
                <span className="block max-w-[10rem] truncate text-sm font-medium">{user?.name}</span>
                <span className="block text-xs capitalize text-stone-500">{user?.role}</span>
              </span>
            </Link>
            <button
              type="button"
              className="btn-ghost p-2"
              aria-label="Sign out"
              title="Sign out"
              onClick={async () => {
                await logout();
                navigate('/login');
              }}
            >
              <LogOut className="h-5 w-5" aria-hidden="true" />
            </button>
          </div>
        </header>
        <main id="main" className="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
