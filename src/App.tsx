import React from 'react';
import { BrowserRouter, Routes, Route, Navigate, useLocation } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import { Navbar } from './components/Navbar';
import { Login } from './pages/Login';
import { Register } from './pages/Register';
import { ForgotPassword } from './pages/ForgotPassword';
import { ResetPassword } from './pages/ResetPassword';
import { StudentDashboard } from './pages/student/StudentDashboard';
import { EventRegistration } from './pages/student/EventRegistration';
import { StudentCertificates } from './pages/student/StudentCertificates';
import { ViewCertificate } from './pages/student/ViewCertificate';
import { ScannerDashboard } from './pages/scanner/ScannerDashboard';
import { ScanGate } from './pages/scanner/ScanGate';
import { AdminDashboard } from './pages/admin/AdminDashboard';
import { CreateEvent } from './pages/admin/CreateEvent';
import { ManageRegistrations } from './pages/admin/ManageRegistrations';
import { VerifyPayments } from './pages/admin/VerifyPayments';

// Route helper to redirect logged-in users to their respective homeboards
const RootRedirector: React.FC = () => {
  const { user, profile, loading } = useAuth();

  if (loading) {
    return <div style={{ display: 'flex', justifyContent: 'center', padding: '5rem' }}>Verifying session...</div>;
  }

  if (!user || !profile) {
    return <Navigate to="/login" replace />;
  }

  if (profile.role === 'admin') {
    return <Navigate to="/admin" replace />;
  } else if (profile.role === 'scanner') {
    return <Navigate to="/scanner" replace />;
  } else {
    return <Navigate to="/student" replace />;
  }
};

// Enforces role-based route access controls
const ProtectedRoute: React.FC<{ children: React.ReactNode; allowedRoles: ('student' | 'scanner' | 'admin')[] }> = ({
  children,
  allowedRoles,
}) => {
  const { user, profile, loading } = useAuth();
  const location = useLocation();

  if (loading) {
    return <div style={{ display: 'flex', justifyContent: 'center', padding: '5rem' }}>Verifying permissions...</div>;
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

          {/* Root Redirector */}
          <Route path="/" element={<RootRedirector />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
};

export default App;
