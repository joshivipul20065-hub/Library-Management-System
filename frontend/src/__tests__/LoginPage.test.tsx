import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AxiosError, AxiosHeaders } from 'axios';
import { describe, expect, it, vi } from 'vitest';
import LoginPage from '../pages/public/LoginPage';
import { admin, authState, renderWithProviders, student } from './test-utils';

function apiError(status: number, code: string, message: string): AxiosError {
  const config = { headers: new AxiosHeaders() };
  return new AxiosError(message, String(status), config, null, {
    status,
    statusText: '',
    headers: {},
    config,
    data: { error: { code, message, details: {} } },
  });
}

describe('Login flow', () => {
  it('validates the form before calling the API', async () => {
    const login = vi.fn();
    renderWithProviders(<LoginPage />, {
      auth: authState({ login }),
      path: '/login-form',
      routePath: '/login-form',
    });

    await userEvent.click(screen.getByRole('button', { name: 'Sign in' }));

    expect(await screen.findByText('Email is required')).toBeInTheDocument();
    expect(screen.getByText('Password is required')).toBeInTheDocument();
    expect(login).not.toHaveBeenCalled();
  });

  it('signs a student in and sends them to the student dashboard', async () => {
    const login = vi.fn().mockResolvedValue(student);
    renderWithProviders(<LoginPage />, {
      auth: authState({ login }),
      path: '/login-form',
      routePath: '/login-form',
    });

    await userEvent.type(screen.getByLabelText('Email'), 'student1@library.test');
    await userEvent.type(screen.getByLabelText('Password'), 'Student@123');
    await userEvent.click(screen.getByRole('button', { name: 'Sign in' }));

    await waitFor(() => expect(login).toHaveBeenCalledWith('student1@library.test', 'Student@123'));
    expect(await screen.findByText('STUDENT HOME')).toBeInTheDocument();
  });

  it('sends staff to the admin dashboard', async () => {
    const login = vi.fn().mockResolvedValue(admin);
    renderWithProviders(<LoginPage />, {
      auth: authState({ login }),
      path: '/login-form',
      routePath: '/login-form',
    });

    await userEvent.type(screen.getByLabelText('Email'), 'admin@library.test');
    await userEvent.type(screen.getByLabelText('Password'), 'Admin@123');
    await userEvent.click(screen.getByRole('button', { name: 'Sign in' }));

    expect(await screen.findByText('ADMIN HOME')).toBeInTheDocument();
  });

  it('shows the generic server error on bad credentials', async () => {
    const login = vi
      .fn()
      .mockRejectedValue(apiError(401, 'INVALID_CREDENTIALS', 'Invalid email or password'));
    renderWithProviders(<LoginPage />, {
      auth: authState({ login }),
      path: '/login-form',
      routePath: '/login-form',
    });

    await userEvent.type(screen.getByLabelText('Email'), 'admin@library.test');
    await userEvent.type(screen.getByLabelText('Password'), 'wrong');
    await userEvent.click(screen.getByRole('button', { name: 'Sign in' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Invalid email or password');
  });
});
