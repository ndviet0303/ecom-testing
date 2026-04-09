<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        // Admin
        User::query()->updateOrCreate(
            ['email' => 'admin@ziet.dev'],
            [
                'name' => 'System Admin',
                'password' => $password,
                'role' => User::ROLE_ADMIN,
            ]
        );

        // Staff
        User::query()->updateOrCreate(
            ['email' => 'staff@ziet.dev'],
            [
                'name' => 'Order Manager',
                'password' => $password,
                'role' => User::ROLE_STAFF,
            ]
        );

        // Customer
        $customer = User::query()->updateOrCreate(
            ['email' => 'customer@ziet.dev'],
            [
                'name' => 'Loyal Customer',
                'password' => $password,
                'role' => User::ROLE_CUSTOMER,
            ]
        );

        // Thêm địa chỉ cho khách hàng
        Address::query()->updateOrCreate(
            ['user_id' => $customer->id, 'is_default' => true],
            [
                'recipient_name' => 'Loyal Customer',
                'phone' => '0901234567',
                'line1' => '123 Đường Láng',
                'district' => 'Đống Đa',
                'province' => 'Hà Nội',
                'ward' => 'Láng Thượng',
            ]
        );
    }
}
