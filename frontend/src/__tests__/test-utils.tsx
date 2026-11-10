import type { ReactNode } from 'react';
import { render } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { vi } from 'vitest';
import { AuthContext, type AuthState } from '../store/auth-context';
import type { User } from '../types';

export const student: User = {
  id: 3,
  role: 'student',
  name: 'Asha Rao',
  email: 'asha@test',
  status: 'active',
  student_id: 1,
};
export const admin: User = {
  id: 1,
  role: 'admin',
  name: 'Admin',
  email: 'admin@test',
  status: 'active',
  student_id: null,
};

export function authState(overrides: Partial<AuthState> = {}): AuthState {
  return {
    user: null,
    loading: false,
    login: vi.fn(),
    logout: vi.fn(),
    setUser: vi.fn(),
    ...overrides,
  };
}

/** Renders `ui` at `path` with auth + query providers and a set of marker routes for redirects. */
export function renderWithProviders(ui: ReactNode, { auth = authState(), path = '/', routePath = '*' } = {}) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <AuthContext.Provider value={auth}>
        <MemoryRouter
          initialEntries={[path]}
          future={{ v7_startTransition: true, v7_relativeSplatPath: true }}
        >
          <Routes>
            <Route path={routePath} element={ui} />
            <Route path="/login" element={<p>LOGIN PAGE</p>} />
            <Route path="/student" element={<p>STUDENT HOME</p>} />
            <Route path="/admin" element={<p>ADMIN HOME</p>} />
          </Routes>
        </MemoryRouter>
      </AuthContext.Provider>
    </QueryClientProvider>,
  );
}
