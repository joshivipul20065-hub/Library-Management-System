import { cloneElement, isValidElement, useId, useState, type ReactElement, type ReactNode } from 'react';
import { AlertTriangle, BookOpen, Inbox, Loader2 } from 'lucide-react';

export function Spinner({ label = 'Loading' }: { label?: string }) {
  return (
    <span role="status" className="inline-flex items-center gap-2 text-sm text-stone-500">
      <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
      <span className="sr-only">{label}</span>
    </span>
  );
}

export function Skeleton({ className = '' }: { className?: string }) {
  return (
    <div aria-hidden="true" className={`animate-pulse rounded bg-stone-200 dark:bg-stone-800 ${className}`} />
  );
}

export function PageLoader() {
  return (
    <div className="flex min-h-[40vh] items-center justify-center">
      <Spinner label="Loading page" />
    </div>
  );
}

export function EmptyState({
  title,
  message,
  action,
}: {
  title: string;
  message?: string;
  action?: ReactNode;
}) {
  return (
    <div className="flex flex-col items-center justify-center gap-2 px-4 py-12 text-center">
      <Inbox className="h-10 w-10 text-stone-300 dark:text-stone-600" aria-hidden="true" />
      <p className="font-medium text-stone-700 dark:text-stone-200">{title}</p>
      {message && <p className="max-w-sm text-sm text-stone-500">{message}</p>}
      {action}
    </div>
  );
}

export function ErrorState({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <div role="alert" className="flex flex-col items-center gap-3 px-4 py-10 text-center">
      <AlertTriangle className="h-8 w-8 text-red-500" aria-hidden="true" />
      <p className="text-sm text-stone-600 dark:text-stone-300">{message}</p>
      {onRetry && (
        <button type="button" className="btn-secondary btn-sm" onClick={onRetry}>
          Try again
        </button>
      )}
    </div>
  );
}

const BADGE_COLORS: Record<string, string> = {
  available: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  active: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  returned: 'bg-stone-100 text-stone-700 dark:bg-stone-800 dark:text-stone-300',
  paid: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  fulfilled: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
  ready: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
  issued: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
  pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  unpaid: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
  waived: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300',
  overdue: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
  lost: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
  damaged: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
  suspended: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
  expired: 'bg-stone-100 text-stone-600 dark:bg-stone-800 dark:text-stone-400',
  cancelled: 'bg-stone-100 text-stone-600 dark:bg-stone-800 dark:text-stone-400',
};

export function StatusBadge({ status }: { status: string }) {
  return (
    <span
      className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize ${BADGE_COLORS[status] ?? BADGE_COLORS.returned}`}
    >
      {status}
    </span>
  );
}

export function PageHeader({
  title,
  description,
  actions,
}: {
  title: string;
  description?: string;
  actions?: ReactNode;
}) {
  return (
    <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between lg:mb-8">
      <div className="min-w-0">
        <h1 className="text-[1.75rem] font-semibold leading-tight sm:text-3xl">{title}</h1>
        {description && (
          <p className="mt-1.5 max-w-2xl text-sm text-stone-600 dark:text-stone-400">{description}</p>
        )}
      </div>
      {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
    </div>
  );
}

export function KpiCard({
  label,
  value,
  icon,
  tone = 'brand',
  loading,
}: {
  label: string;
  value: ReactNode;
  icon: ReactNode;
  tone?: 'brand' | 'emerald' | 'amber' | 'red' | 'violet';
  loading?: boolean;
}) {
  const tones = {
    brand: 'bg-brand-50 text-brand-700 dark:bg-brand-900/50 dark:text-brand-300',
    emerald: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    amber: 'bg-accent-50 text-accent-700 dark:bg-accent-700/20 dark:text-accent-300',
    red: 'bg-red-50 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    violet: 'bg-violet-50 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
  };
  return (
    <div className="card relative overflow-hidden p-4 sm:p-5">
      <div className="flex items-start justify-between gap-2">
        <p className="eyebrow leading-snug">{label}</p>
        <div className={`shrink-0 rounded-lg p-1.5 sm:p-2 ${tones[tone]}`} aria-hidden="true">
          {icon}
        </div>
      </div>
      {loading ? (
        <Skeleton className="mt-3 h-8 w-20" />
      ) : (
        <p className="mt-2 truncate font-display text-2xl font-semibold tabular-nums sm:text-[1.9rem]">
          {value}
        </p>
      )}
    </div>
  );
}

export function BookCover({
  url,
  title,
  className = 'h-24 w-16',
  showTitle = false,
}: {
  url: string | null | undefined;
  title: string;
  className?: string;
  /** Print the title on the placeholder (use for large covers). */
  showTitle?: boolean;
}) {
  // Remember which URL failed so a later, different URL is tried again.
  const [failedUrl, setFailedUrl] = useState<string | null>(null);
  if (url && url !== failedUrl) {
    return (
      <img
        src={url}
        alt={`Cover of ${title}`}
        className={`${className} shrink-0 rounded-md bg-stone-200 object-cover shadow-cover dark:bg-stone-800`}
        loading="lazy"
        onError={() => setFailedUrl(url)}
      />
    );
  }
  // A cloth-bound book: dark green board, spine shadow on the left, brass rules.
  return (
    <div
      className={`${className} relative flex shrink-0 flex-col items-center justify-center overflow-hidden rounded-md bg-gradient-to-br from-brand-700 to-brand-900 p-2 text-accent-200 shadow-cover`}
      aria-hidden="true"
    >
      <span className="absolute inset-y-0 left-0 w-[8%] bg-black/20" />
      <span className="absolute inset-x-[14%] top-[10%] h-px bg-accent-300/50" />
      <span className="absolute inset-x-[14%] bottom-[10%] h-px bg-accent-300/50" />
      {showTitle ? (
        <span className="line-clamp-4 px-1 text-center font-display text-sm font-semibold leading-snug">
          {title}
        </span>
      ) : (
        <BookOpen className="h-1/3 w-1/3 opacity-80" />
      )}
    </div>
  );
}

/** Label + control + error message, wired together with ids and ARIA attributes. */
export function Field({
  label,
  error,
  hint,
  children,
}: {
  label: string;
  error?: string;
  hint?: string;
  children: ReactElement<Record<string, unknown>>;
}) {
  const id = useId();
  const describedBy =
    [error ? `${id}-error` : null, hint ? `${id}-hint` : null].filter(Boolean).join(' ') || undefined;
  const control = isValidElement(children)
    ? cloneElement(children, {
        id,
        'aria-invalid': error ? true : undefined,
        'aria-describedby': describedBy,
      })
    : children;
  return (
    <div>
      <label htmlFor={id} className="label">
        {label}
      </label>
      {control}
      {hint && !error && (
        <p id={`${id}-hint`} className="mt-1 text-xs text-stone-500">
          {hint}
        </p>
      )}
      {error && (
        <p id={`${id}-error`} className="mt-1 text-xs text-red-600 dark:text-red-400">
          {error}
        </p>
      )}
    </div>
  );
}

export function Alert({
  tone = 'info',
  children,
}: {
  tone?: 'info' | 'warning' | 'error' | 'success';
  children: ReactNode;
}) {
  const tones = {
    info: 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-900 dark:bg-sky-950/50 dark:text-sky-200',
    warning:
      'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-200',
    error: 'border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950/50 dark:text-red-200',
    success:
      'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200',
  };
  return (
    <div
      role={tone === 'error' ? 'alert' : 'status'}
      className={`rounded-lg border px-4 py-3 text-sm ${tones[tone]}`}
    >
      {children}
    </div>
  );
}
