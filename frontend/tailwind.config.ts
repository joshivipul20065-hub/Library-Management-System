import type { Config } from 'tailwindcss';
import defaultTheme from 'tailwindcss/defaultTheme';

export default {
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  darkMode: 'class',
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Inter Variable"', ...defaultTheme.fontFamily.sans],
        display: ['"Fraunces Variable"', 'Georgia', ...defaultTheme.fontFamily.serif],
      },
      colors: {
        // Library green: primary actions, active navigation, the sidebar.
        brand: {
          50: '#effaf7',
          100: '#d8f1ea',
          200: '#b3e2d6',
          300: '#80cbbb',
          400: '#4fae9c',
          500: '#319281',
          600: '#237569',
          700: '#1e5f56',
          800: '#1c4d47',
          900: '#1a403c',
          950: '#0b2422',
        },
        // Warm brass accent: highlights and the active-nav marker, used sparingly.
        accent: {
          50: '#fdf8ec',
          100: '#f9ecc9',
          200: '#f2d78f',
          300: '#ebbf55',
          400: '#e5a92f',
          500: '#d58a1c',
          600: '#bb6a15',
          700: '#9b4d15',
        },
        // Page background: warm paper rather than cold grey.
        paper: '#f7f5f0',
      },
      boxShadow: {
        card: '0 1px 2px rgba(28, 25, 23, 0.04), 0 1px 3px rgba(28, 25, 23, 0.06)',
        lift: '0 10px 30px -12px rgba(28, 25, 23, 0.25)',
        cover: '0 1px 2px rgba(0,0,0,0.12), 0 8px 24px -8px rgba(0,0,0,0.35)',
      },
    },
  },
  plugins: [],
} satisfies Config;
