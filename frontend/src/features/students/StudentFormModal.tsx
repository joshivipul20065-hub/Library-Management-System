import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import toast from 'react-hot-toast';
import { studentsApi, type StudentInput } from '../../api/endpoints';
import { getErrorMessage } from '../../api/client';
import { Modal } from '../../components/Modal';
import { Field } from '../../components/ui';
import type { Student } from '../../types';
import { applyServerErrors, optionalInt, optionalText, passwordRule } from '../../utils/validation';
import { intOrNull, nullIfEmpty } from '../../utils/format';

const schema = z.object({
  name: z.string().trim().min(1, 'Name is required').max(120),
  email: z.string().trim().min(1, 'Email is required').email('Enter a valid email address'),
  roll_no: z
    .string()
    .trim()
    .min(1, 'Roll number is required')
    .max(40)
    .regex(/^[A-Za-z0-9\-/_.]+$/, 'Letters, digits and - / _ . only'),
  department: z.string().trim().min(1, 'Department is required').max(100),
  year: z.string().regex(/^[1-8]$/, 'Select a year'),
  phone: z
    .string()
    .trim()
    .optional()
    .refine((v) => !v || /^[0-9+\-\s().]{6,30}$/.test(v), 'Enter a valid phone number'),
  address: optionalText(255),
  max_books: optionalInt(1, 50),
  password: z.union([z.literal(''), passwordRule]).optional(),
});
type FormValues = z.infer<typeof schema>;
const FIELDS = [
  'name',
  'email',
  'roll_no',
  'department',
  'year',
  'phone',
  'address',
  'max_books',
  'password',
];

interface Props {
  open: boolean;
  student: Student | null;
  onClose: () => void;
  onSaved: () => void;
}

export function StudentFormModal({ open, student, onClose, onSaved }: Props) {
  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({ resolver: zodResolver(schema) });

  useEffect(() => {
    if (!open) return;
    reset({
      name: student?.name ?? '',
      email: student?.email ?? '',
      roll_no: student?.roll_no ?? '',
      department: student?.department ?? '',
      year: String(student?.year ?? 1),
      phone: student?.phone ?? '',
      address: student?.address ?? '',
      max_books: student?.max_books ? String(student.max_books) : '',
      password: '',
    });
  }, [open, student, reset]);

  const onSubmit = handleSubmit(async (values) => {
    const input: StudentInput = {
      name: values.name,
      email: values.email,
      roll_no: values.roll_no,
      department: values.department,
      year: Number(values.year),
      phone: nullIfEmpty(values.phone),
      address: nullIfEmpty(values.address),
      max_books: intOrNull(values.max_books),
      password: nullIfEmpty(values.password),
    };
    try {
      if (student) {
        await studentsApi.update(student.id, input);
        toast.success('Student updated');
      } else {
        await studentsApi.create(input);
        toast.success(
          input.password ? 'Student created' : 'Student created — a password setup link was emailed',
        );
      }
      onSaved();
      onClose();
    } catch (error) {
      if (!applyServerErrors(error, setError, FIELDS)) toast.error(getErrorMessage(error));
    }
  });

  return (
    <Modal
      open={open}
      title={student ? `Edit ${student.name}` : 'Add student'}
      onClose={onClose}
      size="lg"
      footer={
        <>
          <button type="button" className="btn-secondary" onClick={onClose}>
            Cancel
          </button>
          <button type="submit" form="student-form" className="btn-primary" disabled={isSubmitting}>
            {isSubmitting ? 'Saving…' : 'Save'}
          </button>
        </>
      }
    >
      <form id="student-form" onSubmit={onSubmit} noValidate className="grid gap-4 sm:grid-cols-2">
        <Field label="Full name" error={errors.name?.message}>
          <input className="input" autoComplete="off" {...register('name')} />
        </Field>
        <Field label="Email" error={errors.email?.message}>
          <input className="input" type="email" autoComplete="off" {...register('email')} />
        </Field>
        <Field label="Roll number" error={errors.roll_no?.message}>
          <input className="input" {...register('roll_no')} />
        </Field>
        <Field label="Department" error={errors.department?.message}>
          <input className="input" {...register('department')} />
        </Field>
        <Field label="Year" error={errors.year?.message}>
          <select className="input" {...register('year')}>
            {[1, 2, 3, 4, 5, 6, 7, 8].map((y) => (
              <option key={y} value={y}>
                Year {y}
              </option>
            ))}
          </select>
        </Field>
        <Field label="Phone" error={errors.phone?.message}>
          <input className="input" type="tel" {...register('phone')} />
        </Field>
        <Field
          label="Borrowing limit"
          error={errors.max_books?.message}
          hint="Leave blank to use the library default"
        >
          <input className="input" inputMode="numeric" {...register('max_books')} />
        </Field>
        <Field
          label={student ? 'Reset password' : 'Initial password'}
          error={errors.password?.message}
          hint={
            student
              ? 'Leave blank to keep the current password'
              : 'Leave blank to email the student a setup link'
          }
        >
          <input className="input" type="password" autoComplete="new-password" {...register('password')} />
        </Field>
        <div className="sm:col-span-2">
          <Field label="Address" error={errors.address?.message}>
            <textarea className="input" rows={2} {...register('address')} />
          </Field>
        </div>
      </form>
    </Modal>
  );
}
