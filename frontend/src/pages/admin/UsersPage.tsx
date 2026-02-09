import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useForm } from 'react-hook-form';
import toast from 'react-hot-toast';
import { userApi } from '../../services/adminApi';
import { User } from '../../types/admin.types';

const UsersPage: React.FC = () => {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [editingUser, setEditingUser] = useState<User | null>(null);
  const [searchTerm, setSearchTerm] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['admin', 'users', searchTerm],
    queryFn: () => userApi.list({ search: searchTerm }),
  });

  const { data: roles } = useQuery({
    queryKey: ['admin', 'roles'],
    queryFn: () => userApi.getRoles(),
  });

  const toggleActiveMutation = useMutation({
    mutationFn: (userId: string) => userApi.toggleActive(userId),
    onSuccess: () => {
      queryClient.invalidateQueries(['admin', 'users']);
      toast.success('User status updated');
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.error || 'Failed to update user');
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (userId: string) => userApi.delete(userId),
    onSuccess: () => {
      queryClient.invalidateQueries(['admin', 'users']);
      toast.success('User deleted');
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.error || 'Failed to delete user');
    },
  });

  const handleDelete = (user: User) => {
    if (window.confirm(`Are you sure you want to delete ${user.name}?`)) {
      deleteMutation.mutate(user.id);
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">
          {t('admin.users.title', 'User Management')}
        </h1>
        <button
          onClick={() => setShowCreateModal(true)}
          className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
        >
          {t('admin.users.create', 'Create User')}
        </button>
      </div>

      {/* Search */}
      <div className="bg-white rounded-lg shadow p-4">
        <input
          type="text"
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          placeholder="Search by name or email..."
          className="w-full md:w-1/3 px-4 py-2 border rounded-md"
        />
      </div>

      {/* Users Table */}
      <div className="bg-white rounded-lg shadow overflow-hidden">
        <table className="min-w-full divide-y divide-gray-200">
          <thead className="bg-gray-50">
            <tr>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Roles</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Login</th>
              <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-200">
            {isLoading ? (
              [...Array(5)].map((_, i) => (
                <tr key={i} className="animate-pulse">
                  {[...Array(6)].map((_, j) => (
                    <td key={j} className="px-6 py-4">
                      <div className="h-4 bg-gray-200 rounded"></div>
                    </td>
                  ))}
                </tr>
              ))
            ) : data?.users.length === 0 ? (
              <tr>
                <td colSpan={6} className="px-6 py-4 text-center text-gray-500">
                  No users found
                </td>
              </tr>
            ) : (
              data?.users.map((user) => (
                <tr key={user.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex items-center">
                      {user.avatar ? (
                        <img src={user.avatar} alt="" className="w-8 h-8 rounded-full mr-3" />
                      ) : (
                        <div className="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm mr-3">
                          {user.name.charAt(0).toUpperCase()}
                        </div>
                      )}
                      <span className="font-medium">{user.name}</span>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {user.email}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    {user.roles.map((role) => (
                      <span
                        key={role}
                        className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 mr-1"
                      >
                        {role}
                      </span>
                    ))}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span
                      className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                        user.isActive ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                      }`}
                    >
                      {user.isActive ? 'Active' : 'Inactive'}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {user.lastLoginAt ? new Date(user.lastLoginAt).toLocaleString() : 'Never'}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-right text-sm">
                    <button
                      onClick={() => setEditingUser(user)}
                      className="text-blue-600 hover:text-blue-900 mr-4"
                    >
                      Edit
                    </button>
                    <button
                      onClick={() => toggleActiveMutation.mutate(user.id)}
                      className="text-yellow-600 hover:text-yellow-900 mr-4"
                    >
                      {user.isActive ? 'Deactivate' : 'Activate'}
                    </button>
                    <button
                      onClick={() => handleDelete(user)}
                      className="text-red-600 hover:text-red-900"
                    >
                      Delete
                    </button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* Create/Edit Modal */}
      {(showCreateModal || editingUser) && (
        <UserModal
          user={editingUser}
          roles={roles || []}
          onClose={() => {
            setShowCreateModal(false);
            setEditingUser(null);
          }}
          onSuccess={() => {
            queryClient.invalidateQueries(['admin', 'users']);
            setShowCreateModal(false);
            setEditingUser(null);
          }}
        />
      )}
    </div>
  );
};

interface UserModalProps {
  user: User | null;
  roles: { id: string; name: string }[];
  onClose: () => void;
  onSuccess: () => void;
}

const UserModal: React.FC<UserModalProps> = ({ user, roles, onClose, onSuccess }) => {
  const isEdit = !!user;
  const { register, handleSubmit, formState: { errors } } = useForm({
    defaultValues: user ? {
      name: user.name,
      email: user.email,
      role: user.roles[0] || 'readonly',
    } : {
      name: '',
      email: '',
      password: '',
      password_confirmation: '',
      role: 'readonly',
    },
  });

  const createMutation = useMutation({
    mutationFn: (data: any) => userApi.create(data),
    onSuccess: () => {
      toast.success('User created successfully');
      onSuccess();
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.error || 'Failed to create user');
    },
  });

  const updateMutation = useMutation({
    mutationFn: (data: any) => userApi.update(user!.id, data),
    onSuccess: () => {
      toast.success('User updated successfully');
      onSuccess();
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.error || 'Failed to update user');
    },
  });

  const updateRolesMutation = useMutation({
    mutationFn: (roles: string[]) => userApi.updateRoles(user!.id, roles),
    onSuccess: () => {
      toast.success('Roles updated');
    },
    onError: (error: any) => {
      toast.error(error?.response?.data?.error || 'Failed to update roles');
    },
  });

  const onSubmit = (data: any) => {
    if (isEdit) {
      const { role, ...updateData } = data;
      updateMutation.mutate(updateData);
      if (role !== user?.roles[0]) {
        updateRolesMutation.mutate([role]);
      }
    } else {
      createMutation.mutate(data);
    }
  };

  return (
    <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div className="px-6 py-4 border-b">
          <h2 className="text-lg font-semibold text-gray-900">
            {isEdit ? 'Edit User' : 'Create User'}
          </h2>
        </div>
        
        <form onSubmit={handleSubmit(onSubmit)} className="p-6 space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Name *</label>
            <input
              {...register('name', { required: 'Name is required' })}
              type="text"
              className="w-full px-3 py-2 border rounded-md"
            />
            {errors.name && <p className="text-red-500 text-sm mt-1">{errors.name.message as string}</p>}
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Email *</label>
            <input
              {...register('email', { required: 'Email is required' })}
              type="email"
              className="w-full px-3 py-2 border rounded-md"
            />
            {errors.email && <p className="text-red-500 text-sm mt-1">{errors.email.message as string}</p>}
          </div>
          
          {!isEdit && (
            <>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                <input
                  {...register('password', { required: !isEdit ? 'Password is required' : false })}
                  type="password"
                  className="w-full px-3 py-2 border rounded-md"
                />
                {errors.password && <p className="text-red-500 text-sm mt-1">{errors.password.message as string}</p>}
              </div>
              
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Confirm Password *</label>
                <input
                  {...register('password_confirmation')}
                  type="password"
                  className="w-full px-3 py-2 border rounded-md"
                />
              </div>
            </>
          )}
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Role *</label>
            <select
              {...register('role', { required: 'Role is required' })}
              className="w-full px-3 py-2 border rounded-md"
            >
              {roles.map((role) => (
                <option key={role.id} value={role.name}>
                  {role.name}
                </option>
              ))}
            </select>
          </div>
          
          <div className="flex justify-end space-x-3 pt-4">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 border rounded-md hover:bg-gray-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={createMutation.isLoading || updateMutation.isLoading}
              className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
            >
              {createMutation.isLoading || updateMutation.isLoading ? 'Saving...' : isEdit ? 'Update' : 'Create'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default UsersPage;
