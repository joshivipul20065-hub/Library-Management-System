import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { adminApi } from '../../api/endpoints';
import { getErrorMessage } from '../../api/client';
import { useAuth } from '../../store/auth-context';
import { Alert, Field, PageHeader, PageLoader } from '../../components/ui';
import type { Settings } from '../../types';
import { applyServerErrors } from '../../utils/validation';

const num = (min: number, max: number, integer = true) =>
  z
    .string()
    .trim()
    .refine(
      (v) => (integer ? /^\d+$/ : /^\d+(\.\d{1,2})?$/).test(v) && Number(v) >= min && Number(v) <= max,
      `Enter a value from ${min} to ${max}`,
    );

const schema = z.object({
  loan_days: num(1, 365),
  max_books_per_student: num(1, 50),
  max_renewals: num(0, 20),
  fine_per_day: num(0, 10000, false),
  reservation_hold_hours: num(1, 720),
  lost_book_fee: num(0, 100000, false),
});
type FormValues = z.infer<typeof schema>;

const FIELDS: { name: keyof Settings; label: string; hint: string }[] = [
  { name: 'loan_days', label: 'Loan period (days)', hint: 'Default due date for new loans and renewals' },
  {
    name: 'max_books_per_student',
    label: 'Books per student',
    hint: 'Default limit; can be overridden per student',
  },
  {
    name: 'max_renewals',
    label: 'Maximum renewals',
    hint: 'Renewals are also blocked when the book is reserved',
  },
  { name: 'fine_per_day', label: 'Fine per day (₹)', hint: 'Charged on return for each day late' },
  {
    name: 'reservation_hold_hours',
    label: 'Reservation hold (hours)',
    hint: 'How long a returned copy is held for the next reservation',
  },
  { name: 'lost_book_fee', label: 'Lost book fee (₹)', hint: 'Charged when a loan is marked lost' },
];

export default function SettingsPage() {
  const { user } = useAuth();
  const canEdit = user?.role === 'admin';
  const queryClient = useQueryClient();
  const settings = useQuery({ queryKey: ['settings'], queryFn: adminApi.settings });
  const { register, handleSubmit, reset, setError, formState } = useForm<FormValues>({
    resolver: zodResolver(schema),
  });

  useEffect(() => {
    if (settings.data) {
      reset(Object.fromEntries(Object.entries(settings.data).map(([k, v]) => [k, String(v)])) as FormValues);
    }
  }, [settings.data, reset]);

  if (settings.isLoading) return <PageLoader />;

  const onSubmit = handleSubmit(async (values) => {
    try {
      const saved = await adminApi.updateSettings(
        Object.fromEntries(Object.entries(values).map(([k, v]) => [k, Number(v)])),
      );
      queryClient.setQueryData(['settings'], saved);
      toast.success('Settings saved');
    } catch (error) {
      if (
        !applyServerErrors(
          error,
          setError,
          FIELDS.map((f) => f.name),
        )
      )
        toast.error(getErrorMessage(error));
    }
  });

  return (
    <>
      <PageHeader
        title="Circulation settings"
        description="Values here override the defaults from the server environment."
      />
      {!canEdit && (
        <div className="mb-4">
          <Alert tone="info">Only administrators can change these settings.</Alert>
        </div>
      )}
      <form onSubmit={onSubmit} noValidate className="card grid max-w-3xl gap-4 sm:grid-cols-2">
        <fieldset disabled={!canEdit} className="contents">
          {FIELDS.map((f) => (
            <Field key={f.name} label={f.label} hint={f.hint} error={formState.errors[f.name]?.message}>
              <input className="input" inputMode="decimal" {...register(f.name)} />
            </Field>
          ))}
        </fieldset>
        {canEdit && (
          <div className="sm:col-span-2">
            <button type="submit" className="btn-primary" disabled={formState.isSubmitting}>
              Save settings
            </button>
          </div>
        )}
      </form>
    </>
  );
}
