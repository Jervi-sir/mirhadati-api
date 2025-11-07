<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('roles')->insert([
            ['code' => 'admin', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'host',  'created_at' => now(), 'updated_at' => now()],
            ['code' => 'user',  'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('users')->insert([
            [
                'name' => 'Admin User',
                'phone' => '0558054300',
                'password' => Hash::make('password'),
                'password_plain_text' => 'password',
                'role_id' => 1, // admin
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Host Example',
                'phone' => '0558054301',
                'password' => Hash::make('password'),
                'password_plain_text' => 'password',
                'role_id' => 2, // host
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Regular User',
                'phone' => '0558054302',
                'password' => Hash::make('password'),
                'password_plain_text' => 'password',
                'role_id' => 3, // user
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
