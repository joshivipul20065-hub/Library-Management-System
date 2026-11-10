export type Role = 'admin' | 'librarian' | 'student';
export type IssueStatus = 'issued' | 'returned' | 'overdue' | 'lost';
export type CopyStatus = 'available' | 'issued' | 'lost' | 'damaged';
export type ReservationStatus = 'pending' | 'ready' | 'fulfilled' | 'cancelled' | 'expired';
export type FineStatus = 'unpaid' | 'paid' | 'waived';
/** MySQL DECIMAL values arrive as strings. */
export type Money = number | string;

export interface User {
  id: number;
  role: Role;
  name: string;
  email: string;
  status: 'active' | 'suspended';
  student_id: number | null;
}

export interface Me extends User {
  last_login_at: string | null;
  student: Student | null;
}

export interface AuthResponse {
  access_token: string;
  token_type: 'Bearer';
  expires_in: number;
  user: User;
}

export interface PageMeta {
  page: number;
  limit: number;
  total: number;
  pages: number;
  unread?: number;
}

export interface Paginated<T> {
  data: T[];
  meta: PageMeta;
}

export interface Book {
  id: number;
  isbn: string;
  title: string;
  publisher: string | null;
  edition: string | null;
  published_year: number | null;
  language: string;
  cover_url: string | null;
  shelf_location: string | null;
  total_copies: number;
  available_copies: number;
  category_id: number | null;
  category_name: string | null;
  authors: string | null;
  created_at: string;
}

export interface BookCopy {
  id: number;
  barcode: string;
  status: CopyStatus;
  created_at: string;
  issue_id: number | null;
  due_date: string | null;
  borrower_name: string | null;
  borrower_roll_no: string | null;
}

export interface BookDetail extends Omit<Book, 'authors'> {
  description: string | null;
  authors: { id: number; name: string }[];
  copies?: BookCopy[];
}

export interface CopyLookup {
  id: number;
  book_id: number;
  barcode: string;
  status: CopyStatus;
  book_title: string;
}

/** Book details found on Open Library, used to pre-fill the book form. */
export interface IsbnLookup {
  isbn: string;
  title: string;
  authors: string[];
  publisher: string | null;
  published_year: number | null;
  subjects: string[];
  cover_url: string | null;
  source_url: string | null;
}

export interface Category {
  id: number;
  name: string;
  description: string | null;
  book_count?: number;
  created_at: string;
}

export interface Author {
  id: number;
  name: string;
  book_count?: number;
  created_at: string;
}

export interface Eligibility {
  can_borrow: boolean;
  reasons: string[];
  active_issues: number;
  limit: number;
  unpaid_fines: number;
}

export interface Student {
  id: number;
  user_id: number;
  name: string;
  email: string;
  status: 'active' | 'suspended';
  roll_no: string;
  department: string;
  year: number;
  phone: string | null;
  address: string | null;
  max_books: number | null;
  created_at: string;
  last_login_at?: string | null;
  active_issues?: number;
  unpaid_fines?: Money;
  eligibility?: Eligibility;
}

export interface Issue {
  id: number;
  copy_id: number;
  student_id: number;
  issue_date: string;
  due_date: string;
  return_date: string | null;
  status: IssueStatus;
  renew_count: number;
  barcode: string;
  book_id: number;
  book_title: string;
  cover_url: string | null;
  isbn: string;
  roll_no: string;
  student_user_id: number;
  student_name: string;
  student_email: string;
  issued_by_name: string | null;
  days_overdue: number;
  fine_amount: Money;
}

export interface ReturnResult {
  issue: Issue;
  days_overdue: number;
  fine_amount: number;
  fine_id: number | null;
  reservation_ready: boolean;
}

export interface FinePreview {
  issue_id: number;
  due_date: string;
  days_overdue: number;
  fine_per_day: number;
  amount: number;
}

export interface Reservation {
  id: number;
  book_id: number;
  student_id: number;
  status: ReservationStatus;
  created_at: string;
  expires_at: string | null;
  book_title: string;
  cover_url?: string | null;
  available_copies?: number;
  roll_no?: string;
  student_name?: string;
  queue_position?: number | null;
}

export interface Fine {
  id: number;
  issue_id: number;
  amount: Money;
  reason: string;
  status: FineStatus;
  paid_at: string | null;
  created_at: string;
  student_id: number;
  due_date: string;
  return_date: string | null;
  book_title: string;
  roll_no: string;
  student_name: string;
  processed_by_name: string | null;
}

export interface Notification {
  id: number;
  title: string;
  body: string;
  read_at: string | null;
  created_at: string;
}

export interface Summary {
  total_titles: number;
  total_copies: number;
  available_copies: number;
  issued: number;
  overdue: number;
  students: number;
  suspended_students: number;
  fines_collected: number;
  fines_outstanding: number;
  active_reservations: number;
}

export interface MonthlyPoint {
  month: string;
  label: string;
  issues: number;
  returns: number;
}

export interface PopularBook {
  id: number;
  title: string;
  isbn: string;
  cover_url: string | null;
  category_name: string | null;
  authors: string | null;
  issue_count: number;
}

export interface CategoryStat {
  id: number;
  name: string;
  issue_count: number;
}

export interface OverdueRow {
  id: number;
  issue_date: string;
  due_date: string;
  days_overdue: number;
  book_title: string;
  barcode: string;
  roll_no: string;
  student_name: string;
  student_email: string;
  phone: string | null;
  estimated_fine: number;
}

export interface AuditLog {
  id: number;
  user_id: number | null;
  user_name: string | null;
  user_role: Role | null;
  action: string;
  entity: string;
  entity_id: number | null;
  metadata: Record<string, unknown> | null;
  ip: string | null;
  created_at: string;
}

export interface Settings {
  loan_days: number;
  max_books_per_student: number;
  max_renewals: number;
  fine_per_day: number;
  reservation_hold_hours: number;
  lost_book_fee: number;
}

export interface Recommendation {
  id: number;
  title: string;
  cover_url: string | null;
  available_copies: number;
  category_name: string | null;
  authors: string | null;
}

export interface StudentDashboardData {
  active_issues: Issue[];
  due_soon: Issue[];
  overdue: Issue[];
  unpaid_fines: number;
  reservations: Reservation[];
  recommendations: Recommendation[];
}

export interface ImportResult {
  created: number;
  failed: number;
  errors: { row: number; message: string; details: Record<string, string> }[];
}

export interface ApiErrorBody {
  error: { code: string; message: string; details: Record<string, unknown> };
}

export type ListParams = Record<string, string | number | undefined>;
