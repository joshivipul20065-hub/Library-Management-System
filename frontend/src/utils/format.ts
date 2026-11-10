import { format, parseISO } from 'date-fns';
import type { Money } from '../types';

const currency = new Intl.NumberFormat('en-IN', {
  style: 'currency',
  currency: 'INR',
  maximumFractionDigits: 2,
});

export function formatMoney(value: Money | null | undefined): string {
  return currency.format(Number(value ?? 0));
}

export function formatDate(value: string | null | undefined): string {
  if (!value) return '—';
  try {
    return format(parseISO(value.replace(' ', 'T')), 'd MMM yyyy');
  } catch {
    return value;
  }
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) return '—';
  try {
    return format(parseISO(value.replace(' ', 'T')), 'd MMM yyyy, HH:mm');
  } catch {
    return value;
  }
}

export function todayIso(): string {
  return format(new Date(), 'yyyy-MM-dd');
}

/** Removes empty values so they are not sent as query parameters. */
export function compact<T extends Record<string, unknown>>(values: T): Partial<T> {
  return Object.fromEntries(
    Object.entries(values).filter(([, v]) => v !== '' && v !== undefined && v !== null),
  ) as Partial<T>;
}

export const nullIfEmpty = (value: string | undefined): string | null => {
  const trimmed = value?.trim() ?? '';
  return trimmed === '' ? null : trimmed;
};

export const intOrNull = (value: string | undefined): number | null => {
  const trimmed = value?.trim() ?? '';
  return trimmed === '' ? null : Number(trimmed);
};
