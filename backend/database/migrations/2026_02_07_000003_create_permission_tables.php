<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates tables for spatie/laravel-permission package
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teams = config('permission.teams');

        if (empty($tableNames)) {
            throw new \Exception('Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        }

        // Permissions table
        Schema::create($tableNames['permissions'] ?? 'permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        // Roles table
        Schema::create($tableNames['roles'] ?? 'roles', function (Blueprint $table) use ($teams, $columnNames) {
            $table->uuid('id')->primary();
            if ($teams || config('permission.testing')) {
                $table->unsignedBigInteger($columnNames['team_foreign_key'] ?? 'team_id')->nullable();
                $table->index($columnNames['team_foreign_key'] ?? 'team_id', 'roles_team_foreign_key_index');
            }
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            if ($teams || config('permission.testing')) {
                $table->unique([$columnNames['team_foreign_key'] ?? 'team_id', 'name', 'guard_name']);
            } else {
                $table->unique(['name', 'guard_name']);
            }
        });

        // Model has permissions (pivot)
        Schema::create($tableNames['model_has_permissions'] ?? 'model_has_permissions', function (Blueprint $table) use ($tableNames, $columnNames, $teams) {
            $table->uuid($columnNames['permission_pivot_key'] ?? 'permission_id');
            $table->string('model_type');
            $table->uuid($columnNames['model_morph_key'] ?? 'model_id');
            $table->index([$columnNames['model_morph_key'] ?? 'model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->foreign($columnNames['permission_pivot_key'] ?? 'permission_id')
                ->references('id')
                ->on($tableNames['permissions'] ?? 'permissions')
                ->onDelete('cascade');

            if ($teams) {
                $table->unsignedBigInteger($columnNames['team_foreign_key'] ?? 'team_id');
                $table->index($columnNames['team_foreign_key'] ?? 'team_id', 'model_has_permissions_team_foreign_key_index');
                $table->primary([
                    $columnNames['team_foreign_key'] ?? 'team_id',
                    $columnNames['permission_pivot_key'] ?? 'permission_id',
                    $columnNames['model_morph_key'] ?? 'model_id',
                    'model_type'
                ], 'model_has_permissions_permission_model_type_primary');
            } else {
                $table->primary([
                    $columnNames['permission_pivot_key'] ?? 'permission_id',
                    $columnNames['model_morph_key'] ?? 'model_id',
                    'model_type'
                ], 'model_has_permissions_permission_model_type_primary');
            }
        });

        // Model has roles (pivot)
        Schema::create($tableNames['model_has_roles'] ?? 'model_has_roles', function (Blueprint $table) use ($tableNames, $columnNames, $teams) {
            $table->uuid($columnNames['role_pivot_key'] ?? 'role_id');
            $table->string('model_type');
            $table->uuid($columnNames['model_morph_key'] ?? 'model_id');
            $table->index([$columnNames['model_morph_key'] ?? 'model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign($columnNames['role_pivot_key'] ?? 'role_id')
                ->references('id')
                ->on($tableNames['roles'] ?? 'roles')
                ->onDelete('cascade');

            if ($teams) {
                $table->unsignedBigInteger($columnNames['team_foreign_key'] ?? 'team_id');
                $table->index($columnNames['team_foreign_key'] ?? 'team_id', 'model_has_roles_team_foreign_key_index');
                $table->primary([
                    $columnNames['team_foreign_key'] ?? 'team_id',
                    $columnNames['role_pivot_key'] ?? 'role_id',
                    $columnNames['model_morph_key'] ?? 'model_id',
                    'model_type'
                ], 'model_has_roles_role_model_type_primary');
            } else {
                $table->primary([
                    $columnNames['role_pivot_key'] ?? 'role_id',
                    $columnNames['model_morph_key'] ?? 'model_id',
                    'model_type'
                ], 'model_has_roles_role_model_type_primary');
            }
        });

        // Role has permissions (pivot)
        Schema::create($tableNames['role_has_permissions'] ?? 'role_has_permissions', function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->uuid($columnNames['permission_pivot_key'] ?? 'permission_id');
            $table->uuid($columnNames['role_pivot_key'] ?? 'role_id');

            $table->foreign($columnNames['permission_pivot_key'] ?? 'permission_id')
                ->references('id')
                ->on($tableNames['permissions'] ?? 'permissions')
                ->onDelete('cascade');

            $table->foreign($columnNames['role_pivot_key'] ?? 'role_id')
                ->references('id')
                ->on($tableNames['roles'] ?? 'roles')
                ->onDelete('cascade');

            $table->primary([
                $columnNames['permission_pivot_key'] ?? 'permission_id',
                $columnNames['role_pivot_key'] ?? 'role_id'
            ], 'role_has_permissions_permission_id_role_id_primary');
        });

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        if (empty($tableNames)) {
            throw new \Exception('Error: config/permission.php not found. Please publish the package config before proceeding.');
        }

        Schema::drop($tableNames['role_has_permissions'] ?? 'role_has_permissions');
        Schema::drop($tableNames['model_has_roles'] ?? 'model_has_roles');
        Schema::drop($tableNames['model_has_permissions'] ?? 'model_has_permissions');
        Schema::drop($tableNames['roles'] ?? 'roles');
        Schema::drop($tableNames['permissions'] ?? 'permissions');
    }
};
