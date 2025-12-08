<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Primero crear los roles
        $this->call(RoleSeeder::class);

        // Obtener los roles
        $adminRole = Role::where('name', 'admin')->first();
        $userRole = Role::where('name', 'user')->first();

        // Crear usuario administrador
        $admin = User::create([
            'name' => 'Admin',
            'last_name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => Hash::make('admin123456'),
        ]);
        $admin->roles()->attach($adminRole->id);

        // Crear usuario de prueba
        $testUser = User::create([
            'name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => Hash::make('test123456'),
        ]);
        $testUser->roles()->attach($userRole->id);
    }
}
