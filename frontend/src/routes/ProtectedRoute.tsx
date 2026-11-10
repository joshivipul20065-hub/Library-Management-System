import type { ReactNode } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { homeFor, useAuth } from '../store/auth-context';
import type { Role } from '../types';
import { PageLoader } from '../components/ui';

interface Props {
  roles?: Role[];
  children: ReactNode;
}

/**
 * Client-side guard for UX only; the API enforces the same roles on every request.
 * Signed-out users go to /login (and come back afterwards); users with the wrong role go to their own home.
 */
export function ProtectedRoute({ roles, children }: Props) {
  const { user, loading } = useAuth();
  const location = useLocation();

  if (loading) {
    return <PageLoader />;
  }
  if (!user) {
    return <Navigate to="/login" replace state={{ from: location.pathname + location.search }} />;
  }
  if (roles && !roles.includes(user.role)) {
    return <Navigate to={homeFor(user.role)} replace />;
  }
  return <>{children}</>;
}
