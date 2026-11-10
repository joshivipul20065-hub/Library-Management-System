import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { BookFormModal } from '../features/books/BookFormModal';
import { booksApi, categoriesApi } from '../api/endpoints';
import type { BookDetail } from '../types';
import { admin, authState, renderWithProviders } from './test-utils';

vi.mock('react-hot-toast', () => ({ default: { success: vi.fn(), error: vi.fn() } }));

const saved: BookDetail = {
  id: 42,
  isbn: '9780141439518',
  title: 'Pride and Prejudice',
  publisher: null,
  edition: null,
  published_year: null,
  language: 'English',
  cover_url: null,
  shelf_location: null,
  total_copies: 1,
  available_copies: 1,
  category_id: null,
  category_name: null,
  created_at: '2026-01-01',
  description: null,
  authors: [{ id: 1, name: 'Jane Austen' }],
};

const renderModal = (book: BookDetail | null) =>
  renderWithProviders(<BookFormModal open book={book} onClose={vi.fn()} onSaved={vi.fn()} />, {
    auth: authState({ user: admin }),
    path: '/books',
    routePath: '/books',
  });

describe('Book form cover handling', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    vi.spyOn(categoriesApi, 'list').mockResolvedValue({
      data: [],
      meta: { page: 1, limit: 100, total: 0, pages: 1 },
    });
    URL.createObjectURL = vi.fn(() => 'blob:preview');
    URL.revokeObjectURL = vi.fn();
  });

  it('uploads a cover picked while adding a book, after the book is created', async () => {
    const create = vi.spyOn(booksApi, 'create').mockResolvedValue(saved);
    const upload = vi
      .spyOn(booksApi, 'uploadCover')
      .mockResolvedValue({ ...saved, cover_url: '/uploads/covers/x.png' });
    renderModal(null);

    const file = new File(['png'], 'cover.png', { type: 'image/png' });
    await userEvent.upload(screen.getByLabelText(/Upload image/), file);
    expect(screen.getByText('New image — uploaded when you save.')).toBeInTheDocument();
    expect(upload).not.toHaveBeenCalled();

    await userEvent.type(screen.getByLabelText('ISBN'), '9780141439518');
    await userEvent.type(screen.getByLabelText('Title'), 'Pride and Prejudice');
    await userEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(upload).toHaveBeenCalledWith(42, file));
    expect(create).toHaveBeenCalledWith(
      expect.objectContaining({ title: 'Pride and Prejudice', cover_url: null }),
    );
  });

  it('removes an existing cover only when the form is saved', async () => {
    vi.spyOn(booksApi, 'update').mockResolvedValue(saved);
    const remove = vi.spyOn(booksApi, 'removeCover').mockResolvedValue({} as never);
    renderModal({ ...saved, cover_url: '/uploads/covers/old.png' });

    await userEvent.click(screen.getByRole('button', { name: /Remove cover/ }));
    expect(screen.getByText('The cover will be removed when you save.')).toBeInTheDocument();
    expect(remove).not.toHaveBeenCalled();

    await userEvent.click(screen.getByRole('button', { name: 'Save' }));
    await waitFor(() => expect(remove).toHaveBeenCalledWith(42));
  });
});
