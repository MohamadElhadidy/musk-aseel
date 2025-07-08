<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
     public function run(): void
    {
        Coupon::create([
            'code' => 'WELCOME10',
            'description' => ['en' => '10% off for new customers', 'ar' => 'خصم 10% للعملاء الجدد'],
            'type' => 'percentage',
            'value' => 10,
            'minimum_amount' => 100,
            'usage_limit' => 1000,
            'usage_limit_per_user' => 1,
            'is_active' => true,
            'valid_from' => now(),
            'valid_until' => now()->addMonths(6),
        ]);

        Coupon::create([
            'code' => 'SAVE50',
            'description' => ['en' => 'Save $50 on orders over $500', 'ar' => 'وفر 50 دولار على الطلبات التي تزيد عن 500 دولار'],
            'type' => 'fixed',
            'value' => 50,
            'minimum_amount' => 500,
            'is_active' => true,
            'valid_from' => now(),
            'valid_until' => now()->addMonths(3),
        ]);
    }
}
