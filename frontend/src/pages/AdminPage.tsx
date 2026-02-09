import React from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, RequireAuth } from '../contexts/AuthContext';
import AdminLayout from '../components/admin/AdminLayout';
import LoginPage from './admin/LoginPage';
import DashboardPage from './admin/DashboardPage';
import ShipmentsPage from './admin/ShipmentsPage';
import ShipmentDetailPage from './admin/ShipmentDetailPage';
import UsersPage from './admin/UsersPage';
import ConfigPage from './admin/ConfigPage';
import AuditLogsPage from './admin/AuditLogsPage';

const AdminPage: React.FC = () => {
  return (
    <AuthProvider>
      <Routes>
        {/* Public route - Login */}
        <Route path="login" element={<LoginPage />} />
        
        {/* Protected routes */}
        <Route
          path="*"
          element={
            <RequireAuth>
              <AdminLayout>
                <Routes>
                  <Route path="/" element={<DashboardPage />} />
                  <Route path="shipments" element={<ShipmentsPage />} />
                  <Route path="shipments/:id" element={<ShipmentDetailPage />} />
                  <Route
                    path="users"
                    element={
                      <RequireAuth roles={['admin']}>
                        <UsersPage />
                      </RequireAuth>
                    }
                  />
                  <Route
                    path="config"
                    element={
                      <RequireAuth roles={['admin']}>
                        <ConfigPage />
                      </RequireAuth>
                    }
                  />
                  <Route
                    path="audit"
                    element={
                      <RequireAuth roles={['admin']}>
                        <AuditLogsPage />
                      </RequireAuth>
                    }
                  />
                  <Route path="*" element={<Navigate to="/admin" replace />} />
                </Routes>
              </AdminLayout>
            </RequireAuth>
          }
        />
      </Routes>
    </AuthProvider>
  );
};

export default AdminPage;
