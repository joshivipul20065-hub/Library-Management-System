import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { IssueForm } from '../features/circulation/IssueForm';
import { booksApi, issuesApi, studentsApi } from '../api/endpoints';
import type { Issue, Student } from '../types';
import { admin, authState, renderWithProviders } from './test-utils';

vi.mock('react-hot-toast', () => ({ default: { success: vi.fn(), error: vi.fn() } }));

const eligible: Student = {
  id: 1,
  user_id: 3,
  name: 'Asha Rao',
  email: 'asha@test',
  status: 'active',
  roll_no: 'CS-1',
  department: 'Computer Science',
  year: 2,
  phone: null,
  address: null,
  max_books: null,
  created_at: '2026-01-01',
  eligibility: { can_borrow: true, reasons: [], active_issues: 1, limit: 5, unpaid_fines: 0 },
};

const renderForm = (onIssued = vi.fn()) =>
  renderWithProviders(<IssueForm onIssued={onIssued} />, {
    auth: authState({ user: admin }),
    path: '/desk',
    routePath: '/desk',
  });

describe('Issue desk form', () => {
  beforeEach(() => vi.restoreAllMocks());

  it('requires a roll number and a barcode', async () => {
    const create = vi.spyOn(issuesApi, 'create');
    renderForm();

    await userEvent.click(screen.getByRole('button', { name: 'Issue book' }));

    expect(await screen.findByText('Scan or type the student roll number')).toBeInTheDocument();
    expect(screen.getByText('Scan or type the book barcode')).toBeInTheDocument();
    expect(create).not.toHaveBeenCalled();
  });

  it('shows why an ineligible student cannot borrow and disables issuing', async () => {
    vi.spyOn(studentsApi, 'byRollNo').mockResolvedValue({
      ...eligible,
      eligibility: {
        can_borrow: false,
        reasons: ['Unpaid fines of 25.00'],
        active_issues: 1,
        limit: 5,
        unpaid_fines: 25,
      },
    });
    renderForm();

    await userEvent.type(screen.getByLabelText('Student roll number'), 'CS-1');
    await userEvent.tab();

    expect(await screen.findByText(/Cannot borrow: Unpaid fines of 25.00/)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Issue book' })).toBeDisabled();
  });

  it('issues the book and clears the barcode for the next scan', async () => {
    vi.spyOn(studentsApi, 'byRollNo').mockResolvedValue(eligible);
    vi.spyOn(booksApi, 'copyByBarcode').mockResolvedValue({
      id: 9,
      book_id: 4,
      barcode: 'LIB-1',
      status: 'available',
      book_title: 'Clean Code',
    });
    const issued = {
      id: 77,
      book_title: 'Clean Code',
      student_name: 'Asha Rao',
      due_date: '2026-03-15',
    } as Issue;
    const create = vi.spyOn(issuesApi, 'create').mockResolvedValue(issued);
    const onIssued = vi.fn();
    renderForm(onIssued);

    await userEvent.type(screen.getByLabelText('Student roll number'), 'CS-1');
    await userEvent.type(screen.getByLabelText('Book barcode'), 'LIB-1');
    await userEvent.tab();
    expect(await screen.findByText('Clean Code')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Issue book' }));

    await waitFor(() => expect(create).toHaveBeenCalledWith({ roll_no: 'CS-1', barcode: 'LIB-1' }));
    expect(onIssued).toHaveBeenCalledWith(issued);
    expect(screen.getByLabelText('Book barcode')).toHaveValue('');
  });
});
