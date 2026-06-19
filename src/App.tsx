import React, { Suspense } from 'react';
import { BrowserRouter, Routes, Route, Navigate, useLocation } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import { Navbar } from './components/Navbar';
import { LoadingSpinner } from './components/LoadingSpinner';

// --- Lazy-loaded page components (route-level code splitting) ---
// Public / Auth
const Login = React.lazy(() => import('./pages/Login').then(m => ({ default: m.Login })));
const Register = React.lazy(() => import('./pages/Register').then(m => ({ default: m.Register })));
const ForgotPassword = React.lazy(() => import('./pages/ForgotPassword').then(m => ({ default: m.ForgotPassword })));
const ResetPassword = React.lazy(() => import('./pages/ResetPassword').then(m => ({ default: m.ResetPassword })));
const Landing = React.lazy(() => import('./pages/Landing').then(m => ({ default: m.Landing })));

// Student
const StudentDashboard = React.lazy(() => import('./pages/student/StudentDashboard').then(m => ({ default: m.StudentDashboard })));
const EventRegistration = React.lazy(() => import('./pages/student/EventRegistration').then(m => ({ default: m.EventRegistration })));
const StudentCertificates = React.lazy(() => import('./pages/student/StudentCertificates').then(m => ({ default: m.StudentCertificates })));
const ViewCertificate = React.lazy(() => import('./pages/student/ViewCertificate').then(m => ({ default: m.ViewCertificate })));
const StudentProfile = React.lazy(() => import('./pages/student/Profile').then(m => ({ default: m.Profile })));
const StudentAnnouncements = React.lazy(() => import('./pages/student/Announcements').then(m => ({ default: m.Announcements })));

// Scanner
const ScannerDashboard = React.lazy(() => import('./pages/scanner/ScannerDashboard').then(m => ({ default: m.ScannerDashboard })));
const ScanGate = React.lazy(() => import('./pages/scanner/ScanGate').then(m => ({ default: m.ScanGate })));

// Admin
const AdminDashboard = React.lazy(() => import('./pages/admin/AdminDashboard').then(m => ({ default: m.AdminDashboard })));
const CreateEvent = React.lazy(() => import('./pages/admin/CreateEvent').then(m => ({ default: m.CreateEvent })));
const ManageRegistrations = React.lazy(() => import('./pages/admin/ManageRegistrations').then(m => ({ default: m.ManageRegistrations })));
const VerifyPayments = React.lazy(() => import('./pages/admin/VerifyPayments').then(m => ({ default: m.VerifyPayments })));
const ManageEvents = React.lazy(() => import('./pages/admin/ManageEvents').then(m => ({ default: m.ManageEvents })));
const EditEvent = React.lazy(() => import('./pages/admin/EditEvent').then(m => ({ default: m.EditEvent })));
const ManageBatches = React.lazy(() => import('./pages/admin/ManageBatches').then(m => ({ default: m.ManageBatches })));
const ManageStudents = React.lazy(() => import('./pages/admin/ManageStudents').then(m => ({ default: m.ManageStudents })));
const ManageScanners = React.lazy(() => import('./pages/admin/ManageScanners').then(m => ({ default: m.ManageScanners })));
const AttendanceLogs = React.lazy(() => import('./pages/admin/AttendanceLogs').then(m => ({ default: m.AttendanceLogs })));
const ExportReports = React.lazy(() => import('./pages/admin/ExportReports').then(m => ({ default: m.ExportReports })));
const CertificatesManager = React.lazy(() => import('./pages/admin/CertificatesManager').then(m => ({ default: m.CertificatesManager })));
const GalleryManager = React.lazy(() => import('./pages/admin/GalleryManager').then(m => ({ default: m.GalleryManager })));
const AdminAnnouncements = React.lazy(() => import('./pages/admin/Announcements').then(m => ({ default: m.Announcements })));
const FoodReport = React.lazy(() => import('./pages/admin/FoodReport').then(m => ({ default: m.FoodReport })));



// Enforces role-based route access controls
const ProtectedRoute: React.FC<{ children: React.ReactNode; allowedRoles: ('student' | 'scanner' | 'admin')[] }> = ({
  children,
  allowedRoles,
}) => {
  const { user, profile, loading } = useAuth();
  const location = useLocation();

  if (loading) {
    return <LoadingSpinner />;
  }

  if (!user || !profile) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  if (!allowedRoles.includes(profile.role)) {
    return <Navigate to="/" replace />;
  }

  return <>{children}</>;
};

const Layout: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  return (
    <>
      <Navbar />
      <div style={{ flex: 1 }}>{children}</div>
      <footer className="footer no-print">
        <div className="container">
          &copy; {new Date().getFullYear()} Accolades Connect. Department of Computer Application. All Rights Reserved.
        </div>
      </footer>
    </>
  );
};

export const App: React.FC = () => {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Suspense fallback={<LoadingSpinner />}>
          <Routes>
            {/* Public / Auth Routes */}
            <Route path="/login" element={<Login />} />
            <Route path="/register" element={<Register />} />
            <Route path="/forgot-password" element={<ForgotPassword />} />
            <Route path="/reset-password" element={<ResetPassword />} />

            {/* Student Routes */}
            <Route
              path="/student"
              element={
                <ProtectedRoute allowedRoles={['student']}>
                  <Layout>
                    <StudentDashboard />
                  </Layout>
                </ProtectedRoute>
              }
            />
            <Route
              path="/student/profile"
              element={
                <ProtectedRoute allowedRoles={['student']}>
                  <Layout>
                    <StudentProfile />
                  </Layout>
                </ProtectedRoute>
              }
            />
            <Route
              path="/student/announcements"
              element={
                <ProtectedRoute allowedRoles={['student']}>
                  <Layout>
                    <StudentAnnouncements />
                  </Layout>
                </ProtectedRoute>
              }
            />
            <Route
              path="/student/register-event/:id"
              element={
                <ProtectedRoute allowedRoles={['student']}>
                  <Layout>
                    <EventRegistration />
                  </Layout>
                </ProtectedRoute>
              }
            />
            <Route
              path="/student/certificates"
              element={
                <ProtectedRoute allowedRoles={['student']}>
                  <Layout>
                    <StudentCertificates />
                  </Layout>
                </ProtectedRoute>
              }
            />
            <Route
              path="/student/view-certificate/:id"
              element={
                <ProtectedRoute allowedRoles={['student']}>
                  <Layout>
                    <ViewCertificate />
                  </Layout>
                </ProtectedRoute>
              }
            />

            {/* Scanner Routes */}
            <Route
              path="/scanner"
              element={
                <ProtectedRoute allowedRoles={['scanner', 'admin']}>
                  <Layout>
                    <ScannerDashboard />
                  </Layout>
                </ProtectedRoute>
              }
            />
            <Route
              path="/scanner/scan/:eventId"
              element={
                <ProtectedRoute allowedRoles={['scanner', 'admin']}>
                  <Layout>
                    <ScanGate />
                  </Layout>
                </ProtectedRoute>
              }
            />

            {/* Admin Routes */}
            <Route
              path="/admin"
              element={
                <ProtectedRoute allowedRoles={['admin']}>
                  <Layout>
                    <AdminDashboard />
                  </Layout>
                </ProtectedRoute>
              }
            />
            <Route
              path="/admin/announcements"
              element={
                <ProtectedRoute allowedRoles={['admin']}>
                  <Layout>
                    <AdminAnnouncements />
                  </Layout>
                </ProtectedRoute>
              }
            />
            <Route
              path="/admin/food-report"
              element={
                <ProtectedRoute allowedRoles={['admin']}>
                  <Layout>
                    <FoodReport />
                  </Layout>
                </ProtectedRoute>
              }
            />
            <Route
              path="/admin/events"
              element={
                <ProtectedRoute allowedRoles={['admin']}>
                  <Layout>
                    <ManageEvents />
                  </Layout>
                </ProtectedRoute>
              }
            />
            <Route
              path="/admin/events/create"
              element={
                <ProtectedRoute allowedRoles={['admin']}>
                  <Layout>
                    <CreateEvent />
                  </Layout>
                </ProtectedRoute>
              }
            />
            <Route
              path="/admin/events/edit/:id"
              element={
                <ProtectedRoute allowedRoles={['admin']}>
                  <Layout>
                    <EditEvent />
                  </Layout>
                </ProtectedRoute>
              }
            />
            <Route
              path="/admin/registrations"
              element={
                <ProtectedRoute allowedRoles={['admin']}>
                  <Layout>
                    <ManageRegistrations />
                  </Layout>
                </ProtectedRoute>
              }
            />
            <Route
              path="/admin/payments"
              element={
                <ProtectedRoute allowedRoles={['admin']}>
                  <Layout>
                    <VerifyPayments />
                  </Layout>
                </ProtectedRoute>
              }
            />
            <Route
              path="/admin/batches"
              element={
                <ProtectedRoute allowedRoles={['admin']}>
                  <Layout>
                    <ManageBatches />
                  </Layout>
                </ProtectedRoute>
              }
            />
            <Route
              path="/admin/students"
              element={
                <ProtectedRoute allowedRoles={['admin']}>
                  <Layout>
                    <ManageStudents />
                  </Layout>
                </ProtectedRoute>
              }
            />
            <Route
              path="/admin/scanners"
              element={
                <ProtectedRoute allowedRoles={['admin']}>
                  <Layout>
                    <ManageScanners />
                  </Layout>
                </ProtectedRoute>
              }
            />
            <Route
              path="/admin/attendance"
              element={
                <ProtectedRoute allowedRoles={['admin']}>
                  <Layout>
                    <AttendanceLogs />
                  </Layout>
                </ProtectedRoute>
              }
            />
            <Route
              path="/admin/reports"
              element={
                <ProtectedRoute allowedRoles={['admin']}>
                  <Layout>
                    <ExportReports />
                  </Layout>
                </ProtectedRoute>
              }
            />

            <Route
              path="/admin/certificates"
              element={
                <ProtectedRoute allowedRoles={['admin']}>
                  <Layout>
                    <CertificatesManager />
                  </Layout>
                </ProtectedRoute>
              }
            />
            <Route
              path="/admin/gallery"
              element={
                <ProtectedRoute allowedRoles={['admin']}>
                  <Layout>
                    <GalleryManager />
                  </Layout>
                </ProtectedRoute>
              }
            />

            {/* Public Landing Page */}
            <Route path="/" element={<Layout><Landing /></Layout>} />
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </Suspense>
      </BrowserRouter>
    </AuthProvider>
  );
};

export default App;
