import { z } from 'zod';
import type { FieldValues, Path, UseFormSetError } from 'react-hook-form';
import { getFieldErrors } from '../api/client';

export const passwordRule = z
  .string()
  .min(8, 'At least 8 characters')
  .max(72, 'At most 72 characters')
  .regex(/[A-Za-z]/, 'Include at least one letter')
  .regex(/\d/, 'Include at least one number');

export const newPasswordSchema = z
  .object({ password: passwordRule, confirm: z.string() })
  .refine((v) => v.password === v.confirm, { path: ['confirm'], message: 'Passwords do not match' });
export type NewPasswordValues = z.infer<typeof newPasswordSchema>;

export const optionalText = (max: number) =>
  z.string().trim().max(max, `At most ${max} characters`).optional();
export const optionalInt = (min: number, max: number) =>
  z
    .string()
    .trim()
    .optional()
    .refine(
      (v) => !v || (/^\d+$/.test(v) && Number(v) >= min && Number(v) <= max),
      `Enter a number from ${min} to ${max}`,
    );

/** Copies API 422 field errors onto the matching form fields. Returns true when any were applied. */
export function applyServerErrors<T extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<T>,
  fields: string[],
): boolean {
  const fieldErrors = getFieldErrors(error);
  let applied = false;
  for (const [field, message] of Object.entries(fieldErrors)) {
    if (fields.includes(field)) {
      setError(field as Path<T>, { type: 'server', message });
      applied = true;
    }
  }
  return applied;
}
