/// <reference types="vitest/config" />
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

const apiTarget = process.env.VITE_PROXY_TARGET ?? 'http://localhost:8080';

export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    watch: process.env.VITE_POLL === '1' ? { usePolling: true, interval: 300 } : undefined,
    proxy: {
      '/api': {
        target: apiTarget,
        changeOrigin: false,
        xfwd: true,
        // Requests through this proxy are same-origin for the browser, so CORS does not apply. Dropping Origin lets
        // the app work from any address (127.0.0.1, a LAN IP for phone testing) without editing CORS_ORIGIN.
        configure: (proxy) => proxy.on('proxyReq', (req) => req.removeHeader('origin')),
      },
      '/uploads': { target: apiTarget, changeOrigin: false },
    },
  },
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./src/setupTests.ts'],
    css: false,
  },
});
