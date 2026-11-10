import { useCallback, useState } from 'react';

type Theme = 'light' | 'dark';

const current = (): Theme => (document.documentElement.classList.contains('dark') ? 'dark' : 'light');

/** Dark-mode toggle; the initial theme is applied by an inline script in index.html to avoid a flash. */
export function useTheme(): { theme: Theme; toggle: () => void } {
  const [theme, setTheme] = useState<Theme>(current);
  const toggle = useCallback(() => {
    const next: Theme = current() === 'dark' ? 'light' : 'dark';
    document.documentElement.classList.toggle('dark', next === 'dark');
    try {
      localStorage.setItem('theme', next);
    } catch {
      // Storage may be unavailable (private mode); the toggle still works for this page view.
    }
    setTheme(next);
  }, []);
  return { theme, toggle };
}
