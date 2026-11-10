import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ProtectedRoute } from '../routes/ProtectedRoute';
import { admin, authState, renderWithProviders, student } from './test-utils';

const guarded = (
  <ProtectedRoute roles={['admin', 'librarian']}>
    <p>SECRET ADMIN PAGE</p>
  </ProtectedRoute>
);

describe('ProtectedRoute', () => {
  it('redirects signed-out visitors to the login page', () => {
    renderWithProviders(guarded, { path: '/admin/books', routePath: '/admin/books' });
    expect(screen.getByText('LOGIN PAGE')).toBeInTheDocument();
    expect(screen.queryByText('SECRET ADMIN PAGE')).not.toBeInTheDocument();
  });

  it('sends a student who opens an admin page to the student home', () => {
    renderWithProviders(guarded, {
      auth: authState({ user: student }),
      path: '/admin/books',
      routePath: '/admin/books',
    });
    expect(screen.getByText('STUDENT HOME')).toBeInTheDocument();
    expect(screen.queryByText('SECRET ADMIN PAGE')).not.toBeInTheDocument();
  });

  it('renders the page for an allowed role', () => {
    renderWithProviders(guarded, {
      auth: authState({ user: admin }),
      path: '/admin/books',
      routePath: '/admin/books',
    });
    expect(screen.getByText('SECRET ADMIN PAGE')).toBeInTheDocument();
  });

  it('shows a loader while the session is being restored', () => {
    renderWithProviders(guarded, {
      auth: authState({ loading: true }),
      path: '/admin/books',
      routePath: '/admin/books',
    });
    expect(screen.getByRole('status')).toBeInTheDocument();
    expect(screen.queryByText('LOGIN PAGE')).not.toBeInTheDocument();
  });
});
