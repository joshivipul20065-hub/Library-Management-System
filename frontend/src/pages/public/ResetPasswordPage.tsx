import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { authApi } from '../../api/endpoints';
import { getErrorMessage } from '../../api/client';
import { Alert, Field } from '../../components/ui';
import { newPasswordSchema, type NewPasswordValues } from '../../utils/validation';

export default function ResetPasswordPage() {
  const [params] = useSearchParams();
  const token = params.get('token') ?? '';
  const [done, setDone] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<NewPasswordValues>({ resolver: zodResolver(newPasswordSchema) });

  const onSubmit = handleSubmit(async ({ password }) => {
    setError(null);
    try {
      await authApi.resetPassword(token, password);
      setDone(true);
    } catch (err) {
      setError(getErrorMessage(err));
    }
  });

  return (
    <div className="mx-auto max-w-md px-4 py-16">
      <div className="card">
        <h1 className="text-2xl font-semibold">Choose a new password</h1>
        {!token ? (
          <div className="mt-6">
            <Alert tone="error">This reset link is incomplete. Request a new one.</Alert>
          </div>
        ) : done ? (
          <div className="mt-6 space-y-4">
            <Alert tone="success">Your password has been updated.</Alert>
            <Link to="/login" className="btn-primary w-full">
              Sign in
            </Link>
          </div>
        ) : (
          <form className="mt-6 space-y-4" onSubmit={onSubmit} noValidate>
            {error && <Alert tone="error">{error}</Alert>}
            <Field
              label="New password"
              error={errors.password?.message}
              hint="At least 8 characters with a letter and a number"
            >
              <input
                type="password"
                autoComplete="new-password"
                className="input"
                {...register('password')}
              />
            </Field>
            <Field label="Confirm password" error={errors.confirm?.message}>
              <input type="password" autoComplete="new-password" className="input" {...register('confirm')} />
            </Field>
            <button type="submit" className="btn-primary w-full" disabled={isSubmitting}>
              {isSubmitting ? 'Saving…' : 'Save password'}
            </button>
          </form>
        )}
      </div>
    </div>
  );
}
