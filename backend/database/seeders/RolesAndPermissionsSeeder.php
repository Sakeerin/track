<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Shipment permissions
            'shipments.view',
            'shipments.search',
            'shipments.export',
            
            // Event permissions
            'events.view',
            'events.create',
            'events.update',
            'events.delete',
            
            // Subscription permissions
            'subscriptions.view',
            'subscriptions.manage',
            
            // User management permissions
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.manage-roles',
            
            // Configuration permissions
            'config.view',
            'config.update',
            'config.facilities',
            'config.event-codes',
            'config.eta-rules',
            
            // Monitoring permissions
            'monitoring.view',
            'monitoring.dashboard',
            'monitoring.alerts',
            
            // Audit permissions
            'audit.view',
            'audit.export',
            
            // API key management
            'api-keys.view',
            'api-keys.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        
        // Admin - full access
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo(Permission::all());

        // Operations - can manage shipments and events
        $opsRole = Role::firstOrCreate(['name' => 'ops', 'guard_name' => 'web']);
        $opsRole->givePermissionTo([
            'shipments.view',
            'shipments.search',
            'shipments.export',
            'events.view',
            'events.create',
            'events.update',
            'subscriptions.view',
            'subscriptions.manage',
            'monitoring.view',
            'monitoring.dashboard',
            'config.view',
        ]);

        // Customer Service - can view and manage subscriptions
        $csRole = Role::firstOrCreate(['name' => 'cs', 'guard_name' => 'web']);
        $csRole->givePermissionTo([
            'shipments.view',
            'shipments.search',
            'events.view',
            'subscriptions.view',
            'subscriptions.manage',
        ]);

        // Read-only - can only view
        $readonlyRole = Role::firstOrCreate(['name' => 'readonly', 'guard_name' => 'web']);
        $readonlyRole->givePermissionTo([
            'shipments.view',
            'shipments.search',
            'events.view',
            'subscriptions.view',
            'monitoring.view',
        ]);
    }
}
