import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { Bell } from 'lucide-react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { meApi } from '../api/endpoints';
import { formatDateTime } from '../utils/format';

export function NotificationBell() {
  const [open, setOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);
  const queryClient = useQueryClient();
  const { data } = useQuery({
    queryKey: ['notifications', 'bell'],
    queryFn: () => meApi.notifications({ limit: 5 }),
    refetchInterval: 60_000,
  });
  const markAll = useMutation({
    mutationFn: meApi.readAllNotifications,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['notifications'] }),
  });
  const unread = data?.meta.unread ?? 0;

  useEffect(() => {
    if (!open) return;
    const close = (event: MouseEvent | KeyboardEvent) => {
      if (
        event instanceof KeyboardEvent
          ? event.key === 'Escape'
          : !containerRef.current?.contains(event.target as Node)
      ) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', close);
    document.addEventListener('keydown', close);
    return () => {
      document.removeEventListener('mousedown', close);
      document.removeEventListener('keydown', close);
    };
  }, [open]);

  return (
    <div className="relative" ref={containerRef}>
      <button
        type="button"
        className="btn-ghost relative p-2"
        aria-label={unread > 0 ? `Notifications, ${unread} unread` : 'Notifications'}
        aria-expanded={open}
        aria-haspopup="true"
        onClick={() => setOpen((o) => !o)}
      >
        <Bell className="h-5 w-5" aria-hidden="true" />
        {unread > 0 && (
          <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white">
            {unread > 9 ? '9+' : unread}
          </span>
        )}
      </button>
      {open && (
        <div className="absolute right-0 z-40 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-xl border border-stone-200 bg-white shadow-lg dark:border-stone-800 dark:bg-stone-900">
          <div className="flex items-center justify-between border-b border-stone-200 px-4 py-2 dark:border-stone-800">
            <p className="text-sm font-semibold">Notifications</p>
            {unread > 0 && (
              <button type="button" className="text-xs link" onClick={() => markAll.mutate()}>
                Mark all read
              </button>
            )}
          </div>
          <ul className="max-h-80 divide-y divide-stone-100 overflow-y-auto dark:divide-stone-800">
            {data?.data.length === 0 && (
              <li className="px-4 py-6 text-center text-sm text-stone-500">You're all caught up.</li>
            )}
            {data?.data.map((n) => (
              <li
                key={n.id}
                className={`px-4 py-3 text-sm ${n.read_at ? '' : 'bg-brand-50/60 dark:bg-brand-900/20'}`}
              >
                <p className="font-medium">{n.title}</p>
                <p className="mt-0.5 line-clamp-2 text-stone-600 dark:text-stone-400">{n.body}</p>
                <p className="mt-1 text-xs text-stone-400">{formatDateTime(n.created_at)}</p>
              </li>
            ))}
          </ul>
          <Link
            to="/notifications"
            className="block border-t border-stone-200 px-4 py-2 text-center text-sm link dark:border-stone-800"
            onClick={() => setOpen(false)}
          >
            View all
          </Link>
        </div>
      )}
    </div>
  );
}
