<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'permissions.view',
            'permissions.create',
            'permissions.update',
            'permissions.delete',
            'users.manage',
            'audit.view',
            'settings.manage',
            'schedule.manage',
            'special_activity.manage',
            'content.view',
            'content.create',
            'content.update',
            'content.delete',
            'content.publish',
            'playlist.manage',
            'notification.send',
            'notification.schedule',
            'streaming.start',
            'streaming.stop',
            'statistics.view',
            'speaker.view',
            'speaker.create',
            'speaker.update',
            'speaker.delete',
        ];

        foreach ($permissions as $name) {
            Permission::updateOrCreate(['name' => $name]);
        }

        $superAdmin = Role::updateOrCreate(['name' => Role::SUPER_ADMIN], ['label' => 'Super Administrateur']);
        $admin = Role::updateOrCreate(['name' => Role::ADMIN], ['label' => 'Administrateur / Communication']);
        Role::updateOrCreate(['name' => Role::USER], ['label' => 'Utilisateur']);

        $superAdmin->permissions()->sync(Permission::pluck('id')->all());
        $admin->permissions()->sync(
            Permission::whereIn('name', [
                'roles.view',
                'permissions.view',
                'schedule.manage',
                'special_activity.manage',
                'content.view',
                'content.create',
                'content.update',
                'content.delete',
                'content.publish',
                'playlist.manage',
                'notification.send',
                'notification.schedule',
                'streaming.start',
                'streaming.stop',
                'statistics.view',
                'speaker.view',
                'speaker.create',
                'speaker.update',
                'speaker.delete',
            ])->pluck('id')->all(),
        );

        // Create users for each role
        $superAdminUser = User::updateOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $superAdminUser->syncRoles([Role::SUPER_ADMIN]);

        $adminUser = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $adminUser->syncRoles([Role::ADMIN]);

        $regularUser = User::updateOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'User',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $regularUser->syncRoles([Role::USER]);
    }
}
