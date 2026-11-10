import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import toast from 'react-hot-toast';
import { CheckCircle2, XCircle } from 'lucide-react';
import { booksApi, issuesApi, studentsApi } from '../../api/endpoints';
import { getErrorMessage } from '../../api/client';
import { Alert, Field, Spinner, StatusBadge } from '../../components/ui';
import type { CopyLookup, Issue, Student } from '../../types';
import { formatDate, formatMoney, todayIso } from '../../utils/format';
import { applyServerErrors } from '../../utils/validation';

const issueSchema = z.object({
  roll_no: z.string().trim().min(1, 'Scan or type the student roll number'),
  barcode: z.string().trim().min(1, 'Scan or type the book barcode'),
  due_date: z
    .string()
    .optional()
    .refine((v) => !v || v > todayIso(), 'Due date must be after today'),
});
type FormValues = z.infer<typeof issueSchema>;

/**
 * Issue desk: look up the student (eligibility) and the copy (availability) as soon as each field is filled,
 * then issue. Barcode scanners that type + Enter work out of the box.
 */
export function IssueForm({ onIssued }: { onIssued?: (issue: Issue) => void }) {
  const [student, setStudent] = useState<Student | null>(null);
  const [studentError, setStudentError] = useState<string | null>(null);
  const [copy, setCopy] = useState<CopyLookup | null>(null);
  const [copyError, setCopyError] = useState<string | null>(null);
  const [checking, setChecking] = useState<'student' | 'copy' | null>(null);
  const {
    register,
    handleSubmit,
    resetField,
    setError,
    setFocus,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(issueSchema),
    defaultValues: { roll_no: '', barcode: '', due_date: '' },
  });

  const lookupStudent = async (roll: string) => {
    setStudent(null);
    setStudentError(null);
    if (!roll.trim()) return;
    setChecking('student');
    try {
      setStudent(await studentsApi.byRollNo(roll.trim()));
    } catch (error) {
      setStudentError(getErrorMessage(error));
    } finally {
      setChecking(null);
    }
  };

  const lookupCopy = async (barcode: string) => {
    setCopy(null);
    setCopyError(null);
    if (!barcode.trim()) return;
    setChecking('copy');
    try {
      setCopy(await booksApi.copyByBarcode(barcode.trim()));
    } catch (error) {
      setCopyError(getErrorMessage(error));
    } finally {
      setChecking(null);
    }
  };

  const onSubmit = handleSubmit(async (values) => {
    try {
      const issue = await issuesApi.create({
        roll_no: values.roll_no,
        barcode: values.barcode,
        ...(values.due_date ? { due_date: values.due_date } : {}),
      });
      toast.success(
        `Issued “${issue.book_title}” to ${issue.student_name} — due ${formatDate(issue.due_date)}`,
      );
      resetField('barcode');
      setCopy(null);
      void lookupStudent(values.roll_no);
      setFocus('barcode');
      onIssued?.(issue);
    } catch (error) {
      if (!applyServerErrors(error, setError, ['roll_no', 'barcode', 'due_date'])) {
        setError('root', { message: getErrorMessage(error) });
      }
    }
  });

  const rollField = register('roll_no', {
    onBlur: (e: React.FocusEvent<HTMLInputElement>) => lookupStudent(e.target.value),
  });
  const barcodeField = register('barcode', {
    onBlur: (e: React.FocusEvent<HTMLInputElement>) => lookupCopy(e.target.value),
  });
  const blocked = (student && !student.eligibility?.can_borrow) || (copy && copy.status !== 'available');

  return (
    <form onSubmit={onSubmit} noValidate className="space-y-4" aria-label="Issue a book">
      {errors.root?.message && <Alert tone="error">{errors.root.message}</Alert>}
      <Field label="Student roll number" error={errors.roll_no?.message ?? studentError ?? undefined}>
        <input className="input font-mono" autoComplete="off" {...rollField} />
      </Field>
      {checking === 'student' && <Spinner label="Looking up student" />}
      {student && (
        <div
          className="rounded-lg border border-stone-200 p-3 text-sm dark:border-stone-700"
          aria-live="polite"
        >
          <p className="flex items-center gap-2 font-medium">
            {student.eligibility?.can_borrow ? (
              <CheckCircle2 className="h-4 w-4 text-emerald-600" aria-hidden="true" />
            ) : (
              <XCircle className="h-4 w-4 text-red-600" aria-hidden="true" />
            )}
            {student.name} <StatusBadge status={student.status} />
          </p>
          <p className="mt-1 text-stone-500">
            {student.department} · {student.eligibility?.active_issues ?? 0} of{' '}
            {student.eligibility?.limit ?? '—'} books on loan
            {Number(student.eligibility?.unpaid_fines ?? 0) > 0 &&
              ` · ${formatMoney(student.eligibility?.unpaid_fines)} unpaid`}
          </p>
          {student.eligibility && !student.eligibility.can_borrow && (
            <p className="mt-1 text-red-600 dark:text-red-400">
              Cannot borrow: {student.eligibility.reasons.join('; ')}
            </p>
          )}
        </div>
      )}
      <Field label="Book barcode" error={errors.barcode?.message ?? copyError ?? undefined}>
        <input className="input font-mono" autoComplete="off" {...barcodeField} />
      </Field>
      {checking === 'copy' && <Spinner label="Looking up copy" />}
      {copy && (
        <p className="text-sm" aria-live="polite">
          <span className="font-medium">{copy.book_title}</span> <StatusBadge status={copy.status} />
        </p>
      )}
      <Field
        label="Due date (optional)"
        error={errors.due_date?.message}
        hint="Defaults to the standard loan period"
      >
        <input className="input" type="date" min={todayIso()} {...register('due_date')} />
      </Field>
      <button type="submit" className="btn-primary w-full" disabled={isSubmitting || !!blocked}>
        {isSubmitting ? 'Issuing…' : 'Issue book'}
      </button>
    </form>
  );
}
