import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { authApi, meApi } from '../../api/endpoints';
import { getErrorMessage, tokenStore } from '../../api/client';
import { useAuth } from '../../store/auth-context';
import { Field, PageHeader, PageLoader } from '../../components/ui';
import { applyServerErrors, optionalText, passwordRule } from '../../utils/validation';
import { formatDateTime, nullIfEmpty } from '../../utils/format';

const profileSchema = z.object({
  name: z.string().trim().min(1, 'Name is required').max(120),
  phone: z
    .string()
    .trim()
    .optional()
    .refine((v) => !v || /^[0-9+\-\s().]{6,30}$/.test(v), 'Enter a valid phone number'),
  address: optionalText(255),
});
type ProfileValues = z.infer<typeof profileSchema>;

const passwordSchema = z
  .object({
    current_password: z.string().min(1, 'Enter your current password'),
    new_password: passwordRule,
    confirm: z.string(),
  })
  .refine((v) => v.new_password === v.confirm, { path: ['confirm'], message: 'Passwords do not match' });
type PasswordValues = z.infer<typeof passwordSchema>;

export default function ProfilePage() {
  const { setUser } = useAuth();
  const queryClient = useQueryClient();
  const me = useQuery({ queryKey: ['auth', 'me'], queryFn: authApi.me });
  const profile = useForm<ProfileValues>({ resolver: zodResolver(profileSchema) });
  const password = useForm<PasswordValues>({ resolver: zodResolver(passwordSchema) });
  const { reset } = profile;

  useEffect(() => {
    if (me.data) {
      reset({
        name: me.data.name,
        phone: me.data.student?.phone ?? '',
        address: me.data.student?.address ?? '',
      });
    }
  }, [me.data, reset]);

  if (me.isLoading || !me.data) return <PageLoader />;
  const isStudent = me.data.role === 'student';

  const saveProfile = profile.handleSubmit(async (values) => {
    try {
      const updated = await meApi.updateProfile({
        name: values.name,
        phone: nullIfEmpty(values.phone),
        address: nullIfEmpty(values.address),
      });
      setUser(updated);
      queryClient.setQueryData(['auth', 'me'], updated);
      toast.success('Profile updated');
    } catch (error) {
      if (!applyServerErrors(error, profile.setError, ['name', 'phone', 'address']))
        toast.error(getErrorMessage(error));
    }
  });

  const changePassword = password.handleSubmit(async (values) => {
    try {
      const session = await authApi.changePassword(values.current_password, values.new_password);
      tokenStore.set(session.access_token);
      password.reset({ current_password: '', new_password: '', confirm: '' });
      toast.success('Password changed. Other sessions have been signed out.');
    } catch (error) {
      if (!applyServerErrors(error, password.setError, ['current_password', 'new_password']))
        toast.error(getErrorMessage(error));
    }
  });

  return (
    <>
      <PageHeader
        title="Profile"
        description={`${me.data.email} · last sign-in ${formatDateTime(me.data.last_login_at)}`}
      />
      <div className="grid gap-6 lg:grid-cols-2">
        <form className="card space-y-4" onSubmit={saveProfile} noValidate aria-labelledby="profile-heading">
          <h2 id="profile-heading" className="font-semibold">
            Personal details
          </h2>
          {isStudent && me.data.student && (
            <p className="text-sm text-stone-500">
              Roll no. {me.data.student.roll_no} · {me.data.student.department} · Year {me.data.student.year}
            </p>
          )}
          <Field label="Name" error={profile.formState.errors.name?.message}>
            <input className="input" autoComplete="name" {...profile.register('name')} />
          </Field>
          {isStudent && (
            <>
              <Field label="Phone" error={profile.formState.errors.phone?.message}>
                <input className="input" type="tel" autoComplete="tel" {...profile.register('phone')} />
              </Field>
              <Field label="Address" error={profile.formState.errors.address?.message}>
                <textarea
                  className="input"
                  rows={2}
                  autoComplete="street-address"
                  {...profile.register('address')}
                />
              </Field>
            </>
          )}
          <button type="submit" className="btn-primary" disabled={profile.formState.isSubmitting}>
            Save changes
          </button>
        </form>

        <form
          className="card space-y-4"
          onSubmit={changePassword}
          noValidate
          aria-labelledby="password-heading"
        >
          <h2 id="password-heading" className="font-semibold">
            Change password
          </h2>
          <Field label="Current password" error={password.formState.errors.current_password?.message}>
            <input
              className="input"
              type="password"
              autoComplete="current-password"
              {...password.register('current_password')}
            />
          </Field>
          <Field
            label="New password"
            error={password.formState.errors.new_password?.message}
            hint="At least 8 characters with a letter and a number"
          >
            <input
              className="input"
              type="password"
              autoComplete="new-password"
              {...password.register('new_password')}
            />
          </Field>
          <Field label="Confirm new password" error={password.formState.errors.confirm?.message}>
            <input
              className="input"
              type="password"
              autoComplete="new-password"
              {...password.register('confirm')}
            />
          </Field>
          <button type="submit" className="btn-primary" disabled={password.formState.isSubmitting}>
            Update password
          </button>
        </form>
      </div>
    </>
  );
}
