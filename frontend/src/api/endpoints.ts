import { api } from './client';
import type {
  AuditLog,
  AuthResponse,
  Author,
  Book,
  BookCopy,
  BookDetail,
  Category,
  CategoryStat,
  CopyLookup,
  Fine,
  FinePreview,
  ImportResult,
  IsbnLookup,
  Issue,
  ListParams,
  Me,
  MonthlyPoint,
  Notification,
  OverdueRow,
  Paginated,
  PopularBook,
  Reservation,
  ReturnResult,
  Settings,
  Student,
  StudentDashboardData,
  Summary,
} from '../types';

type Data<T> = { data: T };
const unwrap = <T>(promise: Promise<{ data: Data<T> }>): Promise<T> => promise.then((r) => r.data.data);
const page = <T>(url: string, params?: ListParams): Promise<Paginated<T>> =>
  api.get<Paginated<T>>(url, { params }).then((r) => r.data);

function upload<T>(url: string, field: string, file: File): Promise<T> {
  const form = new FormData();
  form.append(field, file);
  return unwrap<T>(api.post(url, form));
}

export const authApi = {
  login: (email: string, password: string) =>
    api.post<AuthResponse>('/auth/login', { email, password }).then((r) => r.data),
  logout: () => api.post('/auth/logout'),
  me: () => unwrap<Me>(api.get('/auth/me')),
  forgotPassword: (email: string) =>
    api.post<{ message: string }>('/auth/forgot-password', { email }).then((r) => r.data),
  resetPassword: (token: string, password: string) =>
    api.post<{ message: string }>('/auth/reset-password', { token, password }).then((r) => r.data),
  changePassword: (current_password: string, new_password: string) =>
    api.put<AuthResponse>('/auth/change-password', { current_password, new_password }).then((r) => r.data),
};

export type BookInput = {
  isbn: string;
  title: string;
  authors: string[];
  category_id: number | null;
  publisher: string | null;
  edition: string | null;
  published_year: number | null;
  language: string | null;
  shelf_location: string | null;
  description: string | null;
  copies?: number;
  /** Open Library cover link from "Fetch details"; never clears an existing cover. */
  cover_url?: string | null;
};

export const booksApi = {
  list: (params?: ListParams) => page<Book>('/books', params),
  get: (id: number) => unwrap<BookDetail>(api.get(`/books/${id}`)),
  copies: (id: number) => unwrap<BookCopy[]>(api.get(`/books/${id}/copies`)),
  create: (input: BookInput) => unwrap<BookDetail>(api.post('/books', input)),
  update: (id: number, input: BookInput) => unwrap<BookDetail>(api.put(`/books/${id}`, input)),
  remove: (id: number) => api.delete(`/books/${id}`),
  addCopies: (id: number, count: number) => unwrap<BookCopy[]>(api.post(`/books/${id}/copies`, { count })),
  uploadCover: (id: number, file: File) => upload<BookDetail>(`/books/${id}/cover`, 'cover', file),
  removeCover: (id: number) => api.delete(`/books/${id}/cover`),
  importCsv: (file: File) => upload<ImportResult>('/books/import', 'file', file),
  copyByBarcode: (barcode: string) => unwrap<CopyLookup>(api.get(`/copies/${encodeURIComponent(barcode)}`)),
  lookupIsbn: (isbn: string) => unwrap<IsbnLookup>(api.get(`/isbn-lookup/${encodeURIComponent(isbn)}`)),
};

export const categoriesApi = {
  list: (params?: ListParams) => page<Category>('/categories', params),
  create: (input: { name: string; description: string | null }) =>
    unwrap<Category>(api.post('/categories', input)),
  update: (id: number, input: { name: string; description: string | null }) =>
    unwrap<Category>(api.put(`/categories/${id}`, input)),
  remove: (id: number) => api.delete(`/categories/${id}`),
};

export const authorsApi = {
  list: (params?: ListParams) => page<Author>('/authors', params),
  create: (input: { name: string }) => unwrap<Author>(api.post('/authors', input)),
  update: (id: number, input: { name: string }) => unwrap<Author>(api.put(`/authors/${id}`, input)),
  remove: (id: number) => api.delete(`/authors/${id}`),
};

export type StudentInput = {
  name: string;
  email: string;
  roll_no: string;
  department: string;
  year: number;
  phone: string | null;
  address: string | null;
  max_books: number | null;
  password: string | null;
};

export const studentsApi = {
  list: (params?: ListParams) => page<Student>('/students', params),
  get: (id: number) => unwrap<Student>(api.get(`/students/${id}`)),
  byRollNo: (roll: string) => unwrap<Student>(api.get(`/students/by-roll/${encodeURIComponent(roll)}`)),
  create: (input: StudentInput) => unwrap<Student>(api.post('/students', input)),
  update: (id: number, input: StudentInput) => unwrap<Student>(api.put(`/students/${id}`, input)),
  remove: (id: number) => api.delete(`/students/${id}`),
  suspend: (id: number) => unwrap<Student>(api.post(`/students/${id}/suspend`)),
  activate: (id: number) => unwrap<Student>(api.post(`/students/${id}/activate`)),
  history: (id: number, params?: ListParams) => page<Issue>(`/students/${id}/history`, params),
  importCsv: (file: File) => upload<ImportResult>('/students/import', 'file', file),
};

export const issuesApi = {
  list: (params?: ListParams) => page<Issue>('/issues', params),
  create: (input: { roll_no: string; barcode: string; due_date?: string }) =>
    unwrap<Issue>(api.post('/issues', input)),
  returnBook: (id: number) => unwrap<ReturnResult>(api.post(`/issues/${id}/return`)),
  renew: (id: number) => unwrap<Issue>(api.post(`/issues/${id}/renew`)),
  markLost: (id: number) =>
    unwrap<{ issue: Issue; fine_amount: number }>(api.post(`/issues/${id}/mark-lost`)),
  finePreview: (id: number) => unwrap<FinePreview>(api.get(`/issues/${id}/fine-preview`)),
  remind: (id: number) => api.post(`/issues/${id}/remind`),
};

export const reservationsApi = {
  list: (params?: ListParams) => page<Reservation>('/reservations', params),
  create: (bookId: number) => unwrap<Reservation>(api.post('/reservations', { book_id: bookId })),
  cancel: (id: number) => api.delete(`/reservations/${id}`),
  fulfil: (id: number) => unwrap<Issue>(api.post(`/reservations/${id}/fulfil`)),
};

export const finesApi = {
  list: (params?: ListParams) => page<Fine>('/fines', params),
  pay: (id: number) => api.post(`/fines/${id}/pay`),
  waive: (id: number, reason: string) => api.post(`/fines/${id}/waive`, { reason }),
};

export const meApi = {
  dashboard: () => unwrap<StudentDashboardData>(api.get('/me/dashboard')),
  issues: (params?: ListParams) => page<Issue>('/me/issues', params),
  reservations: (params?: ListParams) => page<Reservation>('/me/reservations', params),
  fines: (params?: ListParams) => page<Fine>('/me/fines', params),
  notifications: (params?: ListParams) => page<Notification>('/me/notifications', params),
  readNotification: (id: number) => api.post(`/me/notifications/${id}/read`),
  readAllNotifications: () => api.post('/me/notifications/read-all'),
  updateProfile: (input: { name: string; phone: string | null; address: string | null }) =>
    unwrap<Me>(api.put('/me/profile', input)),
};

export const reportsApi = {
  summary: () => unwrap<Summary>(api.get('/reports/summary')),
  popularBooks: (params?: ListParams) => unwrap<PopularBook[]>(api.get('/reports/popular-books', { params })),
  topCategories: (params?: ListParams) =>
    unwrap<CategoryStat[]>(api.get('/reports/top-categories', { params })),
  overdue: () => unwrap<OverdueRow[]>(api.get('/reports/overdue')),
  monthlyIssues: (params?: ListParams) =>
    unwrap<MonthlyPoint[]>(api.get('/reports/monthly-issues', { params })),
};

export const adminApi = {
  auditLogs: (params?: ListParams) => page<AuditLog>('/audit-logs', params),
  settings: () => unwrap<Settings>(api.get('/settings')),
  updateSettings: (input: Partial<Settings>) => unwrap<Settings>(api.put('/settings', input)),
};

/** Downloads an authenticated CSV export (`?format=csv`). */
export async function downloadCsv(url: string, filename: string, params?: ListParams): Promise<void> {
  const response = await api.get<Blob>(url, { params: { ...params, format: 'csv' }, responseType: 'blob' });
  const href = URL.createObjectURL(response.data);
  const link = document.createElement('a');
  link.href = href;
  link.download = filename;
  link.click();
  URL.revokeObjectURL(href);
}
