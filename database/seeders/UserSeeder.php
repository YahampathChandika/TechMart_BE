<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UserPrivilege;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Check if admin already exists
        $existingAdmin = User::where('email', 'admin@techmart.com')->first();
        if ($existingAdmin) {
            $this->command->info('Admin user already exists, skipping admin creation.');
        } else {
            // Create main admin user
            $admin = User::create([
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'admin@techmart.com',
                'contact' => '1234567890',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'is_active' => true,
            ]);
            $this->command->info('Main admin user created.');
        }

        // Create additional test users
        $users = [
            [
                'first_name' => 'John',
                'last_name' => 'Manager',
                'email' => 'manager@techmart.com',
                'contact' => '9876543210',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'is_active' => true,
            ],
            [
                'first_name' => 'Alice',
                'last_name' => 'Smith',
                'email' => 'alice@techmart.com',
                'contact' => '5555555555',
                'password' => Hash::make('password123'),
                'role' => 'user',
                'is_active' => true,
            ],
            [
                'first_name' => 'Bob',
                'last_name' => 'Johnson',
                'email' => 'bob@techmart.com',
                'contact' => '4444444444',
                'password' => Hash::make('password123'),
                'role' => 'user',
                'is_active' => true,
            ],
            [
                'first_name' => 'Carol',
                'last_name' => 'Williams',
                'email' => 'carol@techmart.com',
                'contact' => '3333333333',
                'password' => Hash::make('password123'),
                'role' => 'user',
                'is_active' => true,
            ],
            [
                'first_name' => 'David',
                'last_name' => 'Brown',
                'email' => 'david@techmart.com',
                'contact' => '2222222222',
                'password' => Hash::make('password123'),
                'role' => 'user',
                'is_active' => false, // Inactive user for testing
            ],
        ];

        foreach ($users as $userData) {
            $existingUser = User::where('email', $userData['email'])->first();
            if (!$existingUser) {
                $user = User::create($userData);
                
                // Create privileges for regular users
                if ($user->role === 'user') {
                    $privileges = [
                        'alice@techmart.com' => [
                            'can_add_products' => true,
                            'can_update_products' => true,
                            'can_delete_products' => false,
                        ],
                        'bob@techmart.com' => [
                            'can_add_products' => true,
                            'can_update_products' => false,
                            'can_delete_products' => false,
                        ],
                        'carol@techmart.com' => [
                            'can_add_products' => false,
                            'can_update_products' => true,
                            'can_delete_products' => true,
                        ],
                        'david@techmart.com' => [
                            'can_add_products' => false,
                            'can_update_products' => false,
                            'can_delete_products' => false,
                        ],
                    ];

                    if (isset($privileges[$user->email])) {
                        UserPrivilege::create(array_merge([
                            'user_id' => $user->id,
                        ], $privileges[$user->email]));
                    }
                }
                
                $this->command->info("User {$user->email} created with role: {$user->role}");
            }
        }

        $this->command->info('Users seeded successfully!');
    }
}