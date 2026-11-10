import { useState } from 'react';
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { getErrorMessage } from '../../api/client';
import { homeFor, useAuth } from '../../store/auth-context';
import { Alert, Field } from '../../components/ui';
import { BookSpines } from '../../components/BookSpines';

const schema = z.object({
  email: z.string().trim().min(1, 'Email is required').email('Enter a valid email address'),
  password: z.string().min(1, 'Password is required'),
});
type FormValues = z.infer<typeof schema>;

export default function LoginPage() {
  const { user, login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [error, setError] = useState<string | null>(null);
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { email: '', password: '' } });

  const from = (location.state as { from?: string } | null)?.from;

  if (user) {
    return <Navigate to={from ?? homeFor(user.role)} replace />;
  }

  const onSubmit = handleSubmit(async ({ email, password }) => {
    setError(null);
    try {
      const signedIn = await login(email, password);
      navigate(from ?? homeFor(signedIn.role), { replace: true });
    } catch (err) {
      setError(getErrorMessage(err, 'Sign in failed'));
    }
  });

  return (
    <div className="mx-auto grid max-w-6xl gap-10 px-4 py-10 sm:px-6 sm:py-16 lg:grid-cols-2 lg:items-stretch">
      <aside
        className="relative hidden overflow-hidden rounded-3xl bg-brand-950 p-10 text-white shadow-lift lg:flex lg:flex-col lg:justify-between"
        aria-label="About the library"
      >
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(30rem_20rem_at_100%_0%,rgba(229,169,47,0.25),transparent)]" />
        <div className="relative">
          <p className="eyebrow text-accent-300">Welcome back</p>
          <p className="mt-4 font-display text-4xl font-semibold leading-tight">
            Every book you need, one sign-in away.
          </p>
          <p className="mt-4 max-w-sm text-brand-100/80">
            Check your loans, renew before the due date and reserve titles that are out on loan.
          </p>
        </div>
        <BookSpines className="relative mt-10 h-44" />
      </aside>

      <div className="mx-auto w-full max-w-md self-center">
        <h1 className="text-3xl font-semibold">Sign in</h1>
        <p className="mt-2 text-sm text-stone-600 dark:text-stone-400">
          Use your library account to continue.
        </p>
        <form className="card mt-6 space-y-4 p-6" onSubmit={onSubmit} noValidate>
          {error && <Alert tone="error">{error}</Alert>}
          <Field label="Email" error={errors.email?.message}>
            <input type="email" autoComplete="email" className="input" {...register('email')} />
          </Field>
          <Field label="Password" error={errors.password?.message}>
            <input
              type="password"
              autoComplete="current-password"
              className="input"
              {...register('password')}
            />
          </Field>
          <button type="submit" className="btn-primary w-full" disabled={isSubmitting}>
            {isSubmitting ? 'Signing in…' : 'Sign in'}
          </button>
          <p className="text-center text-sm">
            <Link to="/forgot-password" className="link">
              Forgot your password?
            </Link>
          </p>
        </form>
        {import.meta.env.DEV && (
          <div className="mt-4 rounded-xl border border-dashed border-stone-300 p-3 text-xs text-stone-500 dark:border-stone-700">
            <p className="font-semibold text-stone-600 dark:text-stone-300">Demo accounts</p>
            <ul className="mt-1 space-y-0.5 break-words">
              <li>admin@library.test / Admin@123</li>
              <li>librarian@library.test / Librarian@123</li>
              <li>student1@library.test / Student@123</li>
            </ul>
          </div>
        )}
      </div>
    </div>
  );
}
