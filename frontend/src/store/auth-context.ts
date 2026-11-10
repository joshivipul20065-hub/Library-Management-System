import { createContext, useContext } from 'react';
import type { Role, User } from '../types';

export interface AuthState {
  user: User | null;
  /** True until the initial session restore (refresh cookie) has finished. */
  loading: boolean;
  login: (email: string, password: string) => Promise<User>;
  logout: () => Promise<void>;
  setUser: (user: User) => void;
}

export const AuthContext = createContext<AuthState | null>(null);

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth must be used inside <AuthProvider>');
  }
  return ctx;
}

export const isStaff = (role: Role | undefined): boolean => role === 'admin' || role === 'librarian';

export const homeFor = (role: Role): string => (role === 'student' ? '/student' : '/admin');
