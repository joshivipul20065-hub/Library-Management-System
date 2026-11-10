import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { authApi } from '../../api/endpoints';
import { getErrorMessage } from '../../api/client';
import { Alert, Field } from '../../components/ui';

const schema = z.object({
  email: z.string().trim().min(1, 'Email is required').email('Enter a valid email address'),
});
type FormValues = z.infer<typeof schema>;

export default function ForgotPasswordPage() {
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({ resolver: zodResolver(schema) });

  const onSubmit = handleSubmit(async ({ email }) => {
    setError(null);
    try {
      setMessage((await authApi.forgotPassword(email)).message);
    } catch (err) {
      setError(getErrorMessage(err));
    }
  });

  return (
    <div className="mx-auto max-w-md px-4 py-16">
      <div className="card">
        <h1 className="text-2xl font-semibold">Reset your password</h1>
        <p className="mt-1 text-sm text-stone-500">
          Enter your account email and we'll send you a reset link.
        </p>
        {message ? (
          <div className="mt-6 space-y-4">
            <Alert tone="success">{message}</Alert>
            <Link to="/login" className="btn-secondary w-full">
              Back to sign in
            </Link>
          </div>
        ) : (
          <form className="mt-6 space-y-4" onSubmit={onSubmit} noValidate>
            {error && <Alert tone="error">{error}</Alert>}
            <Field label="Email" error={errors.email?.message}>
              <input type="email" autoComplete="email" className="input" {...register('email')} />
            </Field>
            <button type="submit" className="btn-primary w-full" disabled={isSubmitting}>
              {isSubmitting ? 'Sending…' : 'Send reset link'}
            </button>
            <p className="text-center text-sm">
              <Link to="/login" className="link">
                Back to sign in
              </Link>
            </p>
          </form>
        )}
      </div>
    </div>
  );
}
