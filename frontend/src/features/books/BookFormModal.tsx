import { useEffect, useMemo, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useQuery } from '@tanstack/react-query';
import toast from 'react-hot-toast';
import { booksApi, categoriesApi, type BookInput } from '../../api/endpoints';
import { getErrorMessage } from '../../api/client';
import { Modal } from '../../components/Modal';
import { BookCover, Field } from '../../components/ui';
import { CoverPicker } from './CoverPicker';
import type { BookDetail, IsbnLookup } from '../../types';
import { applyServerErrors, optionalInt, optionalText } from '../../utils/validation';
import { intOrNull, nullIfEmpty } from '../../utils/format';

const schema = z.object({
  isbn: z
    .string()
    .trim()
    .transform((v) => v.replace(/[\s-]/g, '').toUpperCase())
    .refine((v) => /^(\d{9}[\dX]|\d{13})$/.test(v), 'Enter a valid 10 or 13 digit ISBN'),
  title: z.string().trim().min(1, 'Title is required').max(255),
  authors: z.string().trim().max(1000).optional(),
  category_id: z.string().optional(),
  publisher: optionalText(150),
  edition: optionalText(50),
  published_year: optionalInt(1000, new Date().getFullYear() + 1),
  language: optionalText(40),
  shelf_location: optionalText(50),
  description: optionalText(5000),
  copies: optionalInt(0, 100),
});
type FormValues = z.input<typeof schema>;
const FIELDS = [
  'isbn',
  'title',
  'authors',
  'category_id',
  'publisher',
  'edition',
  'published_year',
  'language',
  'shelf_location',
  'description',
  'copies',
];

interface Props {
  open: boolean;
  book: BookDetail | null;
  onClose: () => void;
  onSaved: () => void;
}

/** What happens to the cover when the form is saved. */
type CoverChoice =
  { type: 'keep' } | { type: 'file'; file: File } | { type: 'fetched'; url: string } | { type: 'remove' };

export function BookFormModal({ open, book, onClose, onSaved }: Props) {
  const categories = useQuery({
    queryKey: ['categories', 'all'],
    queryFn: () => categoriesApi.list({ limit: 100 }),
  });
  const [cover, setCover] = useState<CoverChoice>({ type: 'keep' });
  const [lookingUp, setLookingUp] = useState(false);
  const [found, setFound] = useState<IsbnLookup | null>(null);
  const {
    register,
    handleSubmit,
    reset,
    setError,
    clearErrors,
    getValues,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({ resolver: zodResolver(schema) });

  useEffect(() => {
    if (!open) return;
    setFound(null);
    setCover({ type: 'keep' });
    reset({
      isbn: book?.isbn ?? '',
      title: book?.title ?? '',
      authors: book?.authors.map((a) => a.name).join(', ') ?? '',
      category_id: book?.category_id ? String(book.category_id) : '',
      publisher: book?.publisher ?? '',
      edition: book?.edition ?? '',
      published_year: book?.published_year ? String(book.published_year) : '',
      language: book?.language ?? 'English',
      shelf_location: book?.shelf_location ?? '',
      description: book?.description ?? '',
      copies: book ? '' : '1',
    });
  }, [open, book, reset]);

  // Local preview for a picked file; the object URL is released when it changes or the form closes.
  const previewUrl = useMemo(() => (cover.type === 'file' ? URL.createObjectURL(cover.file) : null), [cover]);
  useEffect(() => () => (previewUrl ? URL.revokeObjectURL(previewUrl) : undefined), [previewUrl]);
  const shownCover =
    cover.type === 'file'
      ? previewUrl
      : cover.type === 'fetched'
        ? cover.url
        : cover.type === 'remove'
          ? null
          : (book?.cover_url ?? null);

  const onSubmit = handleSubmit(async (raw) => {
    const values = schema.parse(raw);
    const input: BookInput = {
      isbn: values.isbn,
      title: values.title,
      authors: (values.authors ?? '')
        .split(/[,;]/)
        .map((a) => a.trim())
        .filter(Boolean),
      category_id: intOrNull(values.category_id),
      publisher: nullIfEmpty(values.publisher),
      edition: nullIfEmpty(values.edition),
      published_year: intOrNull(values.published_year),
      language: nullIfEmpty(values.language),
      shelf_location: nullIfEmpty(values.shelf_location),
      description: nullIfEmpty(values.description),
      ...(book ? {} : { copies: intOrNull(values.copies) ?? 0 }),
      cover_url: cover.type === 'fetched' ? cover.url : null,
    };
    let saved: BookDetail;
    try {
      saved = book ? await booksApi.update(book.id, input) : await booksApi.create(input);
    } catch (error) {
      if (!applyServerErrors(error, setError, FIELDS)) toast.error(getErrorMessage(error));
      return;
    }
    // The book is saved; apply the cover change separately so a cover problem never loses the book details.
    try {
      if (cover.type === 'file') {
        await booksApi.uploadCover(saved.id, cover.file);
      } else if (cover.type === 'remove' && book?.cover_url) {
        await booksApi.removeCover(saved.id);
      }
      toast.success(book ? 'Book updated' : 'Book added');
    } catch (error) {
      toast.error(`Book saved, but the cover could not be updated: ${getErrorMessage(error)}`);
    }
    onSaved();
    onClose();
  });

  /** Pre-fills the form from Open Library; staff review the values before saving. */
  const fetchDetails = async () => {
    const isbn = (getValues('isbn') ?? '').replace(/[\s-]/g, '').toUpperCase();
    if (!/^(\d{9}[\dX]|\d{13})$/.test(isbn)) {
      setError('isbn', { type: 'manual', message: 'Enter a valid 10 or 13 digit ISBN first' });
      return;
    }
    clearErrors('isbn');
    setLookingUp(true);
    setFound(null);
    try {
      const details = await booksApi.lookupIsbn(isbn);
      const fill = { shouldDirty: true, shouldValidate: true };
      setValue('isbn', details.isbn, fill);
      setValue('title', details.title, fill);
      if (details.authors.length > 0) setValue('authors', details.authors.join(', '), fill);
      if (details.publisher) setValue('publisher', details.publisher, fill);
      if (details.published_year) setValue('published_year', String(details.published_year), fill);
      if (!getValues('category_id')) {
        // Pick an existing category whose name matches one of the book's subjects.
        const subjects = details.subjects.flatMap((s) => s.split(',')).map((s) => s.trim().toLowerCase());
        const match = categories.data?.data.find((c) => subjects.includes(c.name.toLowerCase()));
        if (match) setValue('category_id', String(match.id), fill);
      }
      // Offer the fetched cover only when no cover is showing, so an existing or chosen image is never replaced.
      if (details.cover_url && !shownCover) setCover({ type: 'fetched', url: details.cover_url });
      setFound(details);
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setLookingUp(false);
    }
  };

  return (
    <Modal
      open={open}
      title={book ? `Edit “${book.title}”` : 'Add book'}
      onClose={onClose}
      size="lg"
      footer={
        <>
          <button type="button" className="btn-secondary" onClick={onClose}>
            Cancel
          </button>
          <button type="submit" form="book-form" className="btn-primary" disabled={isSubmitting}>
            {isSubmitting ? 'Saving…' : 'Save'}
          </button>
        </>
      }
    >
      <form id="book-form" onSubmit={onSubmit} noValidate className="grid gap-4 sm:grid-cols-2">
        <div className="sm:col-span-2">
          <CoverPicker
            url={shownCover}
            title={book?.title ?? found?.title ?? ''}
            note={
              cover.type === 'keep'
                ? undefined
                : cover.type === 'remove'
                  ? 'The cover will be removed when you save.'
                  : cover.type === 'fetched'
                    ? 'Cover from Open Library — saved when you save.'
                    : 'New image — uploaded when you save.'
            }
            onPick={(file) => setCover({ type: 'file', file })}
            onRemove={() => setCover(book?.cover_url ? { type: 'remove' } : { type: 'keep' })}
          />
        </div>
        <div className="flex items-start gap-2">
          <div className="flex-1">
            <Field label="ISBN" error={errors.isbn?.message}>
              <input className="input" {...register('isbn')} />
            </Field>
          </div>
          <button
            type="button"
            className="btn-secondary mt-6 shrink-0"
            onClick={fetchDetails}
            disabled={lookingUp}
          >
            {lookingUp ? 'Fetching…' : 'Fetch details'}
          </button>
        </div>
        <Field label="Title" error={errors.title?.message}>
          <input className="input" {...register('title')} />
        </Field>
        {found && (
          <div
            role="status"
            className="flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200 sm:col-span-2"
          >
            <BookCover url={found.cover_url} title={found.title} className="h-14 w-10" />
            <p>
              Filled from Open Library: <strong>{found.title}</strong>
              {found.authors.length > 0 && ` by ${found.authors.join(', ')}`}. Please check the details before
              saving.
              {found.source_url && (
                <>
                  {' '}
                  <a href={found.source_url} target="_blank" rel="noopener noreferrer" className="link">
                    View record
                  </a>
                </>
              )}
            </p>
          </div>
        )}
        <Field label="Authors" error={errors.authors?.message} hint="Separate multiple authors with commas">
          <input className="input" {...register('authors')} />
        </Field>
        <Field label="Category" error={errors.category_id?.message}>
          <select className="input" {...register('category_id')}>
            <option value="">— None —</option>
            {categories.data?.data.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
        </Field>
        <Field label="Publisher" error={errors.publisher?.message}>
          <input className="input" {...register('publisher')} />
        </Field>
        <Field label="Edition" error={errors.edition?.message}>
          <input className="input" {...register('edition')} />
        </Field>
        <Field label="Published year" error={errors.published_year?.message}>
          <input className="input" inputMode="numeric" {...register('published_year')} />
        </Field>
        <Field label="Language" error={errors.language?.message}>
          <input className="input" {...register('language')} />
        </Field>
        <Field label="Shelf location" error={errors.shelf_location?.message}>
          <input className="input" {...register('shelf_location')} />
        </Field>
        {!book && (
          <Field
            label="Number of copies"
            error={errors.copies?.message}
            hint="Barcodes are generated automatically"
          >
            <input className="input" inputMode="numeric" {...register('copies')} />
          </Field>
        )}
        <div className="sm:col-span-2">
          <Field label="Description" error={errors.description?.message}>
            <textarea className="input" rows={3} {...register('description')} />
          </Field>
        </div>
      </form>
    </Modal>
  );
}
