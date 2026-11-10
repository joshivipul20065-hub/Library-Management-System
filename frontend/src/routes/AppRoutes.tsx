import { lazy, Suspense } from 'react';
import { Route, Routes } from 'react-router-dom';
import { PublicLayout } from '../layouts/PublicLayout';
import { DashboardLayout } from '../layouts/DashboardLayout';
import { ProtectedRoute } from './ProtectedRoute';
import { PageLoader } from '../components/ui';
import { ErrorBoundary } from '../components/ErrorBoundary';

const LandingPage = lazy(() => import('../pages/public/LandingPage'));
const LoginPage = lazy(() => import('../pages/public/LoginPage'));
const ForgotPasswordPage = lazy(() => import('../pages/public/ForgotPasswordPage'));
const ResetPasswordPage = lazy(() => import('../pages/public/ResetPasswordPage'));
const CatalogPage = lazy(() => import('../pages/shared/CatalogPage'));
const BookDetailPage = lazy(() => import('../pages/shared/BookDetailPage'));
const NotificationsPage = lazy(() => import('../pages/shared/NotificationsPage'));
const ProfilePage = lazy(() => import('../pages/shared/ProfilePage'));
const NotFoundPage = lazy(() => import('../pages/shared/NotFoundPage'));

const StudentDashboard = lazy(() => import('../pages/student/StudentDashboard'));
const MyBooksPage = lazy(() => import('../pages/student/MyBooksPage'));
const MyFinesPage = lazy(() => import('../pages/student/MyFinesPage'));

const AdminDashboard = lazy(() => import('../pages/admin/AdminDashboard'));
const BooksPage = lazy(() => import('../pages/admin/BooksPage'));
const TaxonomyPage = lazy(() => import('../pages/admin/TaxonomyPage'));
const StudentsPage = lazy(() => import('../pages/admin/StudentsPage'));
const CirculationPage = lazy(() => import('../pages/admin/CirculationPage'));
const ReservationsPage = lazy(() => import('../pages/admin/ReservationsPage'));
const OverduePage = lazy(() => import('../pages/admin/OverduePage'));
const FinesPage = lazy(() => import('../pages/admin/FinesPage'));
const ReportsPage = lazy(() => import('../pages/admin/ReportsPage'));
const AuditLogPage = lazy(() => import('../pages/admin/AuditLogPage'));
const SettingsPage = lazy(() => import('../pages/admin/SettingsPage'));

export function AppRoutes() {
  return (
    <ErrorBoundary>
      <Suspense fallback={<PageLoader />}>
        <Routes>
          <Route element={<PublicLayout />}>
            <Route index element={<LandingPage />} />
            <Route path="catalog" element={<CatalogPage detailBase="/books" />} />
            <Route path="books/:id" element={<BookDetailPage />} />
            <Route path="login" element={<LoginPage />} />
            <Route path="forgot-password" element={<ForgotPasswordPage />} />
            <Route path="reset-password" element={<ResetPasswordPage />} />
          </Route>

          <Route
            element={
              <ProtectedRoute>
                <DashboardLayout />
              </ProtectedRoute>
            }
          >
            <Route path="notifications" element={<NotificationsPage />} />
            <Route path="profile" element={<ProfilePage />} />
          </Route>

          <Route
            path="student"
            element={
              <ProtectedRoute roles={['student']}>
                <DashboardLayout />
              </ProtectedRoute>
            }
          >
            <Route index element={<StudentDashboard />} />
            <Route path="catalog" element={<CatalogPage detailBase="/student/books" />} />
            <Route path="books/:id" element={<BookDetailPage />} />
            <Route path="my-books" element={<MyBooksPage />} />
            <Route path="fines" element={<MyFinesPage />} />
          </Route>

          <Route
            path="admin"
            element={
              <ProtectedRoute roles={['admin', 'librarian']}>
                <DashboardLayout />
              </ProtectedRoute>
            }
          >
            <Route index element={<AdminDashboard />} />
            <Route path="books" element={<BooksPage />} />
            <Route path="books/:id" element={<BookDetailPage />} />
            <Route path="categories" element={<TaxonomyPage key="categories" kind="categories" />} />
            <Route path="authors" element={<TaxonomyPage key="authors" kind="authors" />} />
            <Route path="students" element={<StudentsPage />} />
            <Route path="circulation" element={<CirculationPage />} />
            <Route path="reservations" element={<ReservationsPage />} />
            <Route path="overdue" element={<OverduePage />} />
            <Route path="fines" element={<FinesPage />} />
            <Route path="reports" element={<ReportsPage />} />
            <Route
              path="audit-logs"
              element={
                <ProtectedRoute roles={['admin']}>
                  <AuditLogPage />
                </ProtectedRoute>
              }
            />
            <Route path="settings" element={<SettingsPage />} />
          </Route>

          <Route element={<PublicLayout />}>
            <Route path="*" element={<NotFoundPage />} />
          </Route>
        </Routes>
      </Suspense>
    </ErrorBoundary>
  );
}
