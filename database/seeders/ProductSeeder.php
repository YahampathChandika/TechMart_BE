<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\User;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Get the first admin user (should exist from your testing)
        $admin = User::where('role', 'admin')->first();
        
        if (!$admin) {
            $this->command->error('No admin user found. Please create an admin user first.');
            return;
        }

        $products = [
            [
                'brand' => 'Apple',
                'name' => 'iPhone 15 Pro Max',
                'quantity' => 25,
                'cost_price' => 999.99,
                'sell_price' => 1199.99,
                'description' => 'The most advanced iPhone yet with titanium design, A17 Pro chip, and professional camera system. Features ProMotion display and advanced computational photography.',
                'rating' => 5,
                'is_active' => true,
                'created_by' => $admin->id,
            ],
            [
                'brand' => 'Samsung',
                'name' => 'Galaxy S24 Ultra',
                'quantity' => 18,
                'cost_price' => 899.99,
                'sell_price' => 1099.99,
                'description' => 'Premium Android flagship with S Pen, 200MP camera, and AI-powered features. 6.8-inch Dynamic AMOLED display with 120Hz refresh rate.',
                'rating' => 5,
                'is_active' => true,
                'created_by' => $admin->id,
            ],
            [
                'brand' => 'Sony',
                'name' => 'WH-1000XM5 Headphones',
                'quantity' => 45,
                'cost_price' => 299.99,
                'sell_price' => 399.99,
                'description' => 'Industry-leading noise canceling wireless headphones with 30-hour battery life. Crystal clear hands-free calling and Alexa voice control.',
                'rating' => 4,
                'is_active' => true,
                'created_by' => $admin->id,
            ],
            [
                'brand' => 'Dell',
                'name' => 'XPS 13 Laptop',
                'quantity' => 12,
                'cost_price' => 1199.99,
                'sell_price' => 1499.99,
                'description' => 'Ultra-thin and light laptop with 13.4-inch InfinityEdge display. Intel Core i7 processor, 16GB RAM, and 512GB SSD for powerful performance.',
                'rating' => 4,
                'is_active' => true,
                'created_by' => $admin->id,
            ],
            [
                'brand' => 'Nintendo',
                'name' => 'Switch OLED Console',
                'quantity' => 32,
                'cost_price' => 279.99,
                'sell_price' => 349.99,
                'description' => 'Enhanced Nintendo Switch with vibrant 7-inch OLED screen, enhanced audio, and improved kickstand. Perfect for gaming on-the-go or docked.',
                'rating' => 5,
                'is_active' => true,
                'created_by' => $admin->id,
            ],
            [
                'brand' => 'Amazon',
                'name' => 'Echo Dot (5th Gen)',
                'quantity' => 67,
                'cost_price' => 29.99,
                'sell_price' => 49.99,
                'description' => 'Smart speaker with Alexa, improved audio quality, and temperature sensor. Control smart home devices with voice commands.',
                'rating' => 4,
                'is_active' => true,
                'created_by' => $admin->id,
            ],
            [
                'brand' => 'Google',
                'name' => 'Pixel 8 Pro',
                'quantity' => 22,
                'cost_price' => 799.99,
                'sell_price' => 999.99,
                'description' => 'Google\'s flagship phone with advanced AI photography, Titan M security chip, and pure Android experience. 6.7-inch LTPO OLED display.',
                'rating' => 4,
                'is_active' => true,
                'created_by' => $admin->id,
            ],
            [
                'brand' => 'Microsoft',
                'name' => 'Surface Pro 9',
                'quantity' => 15,
                'cost_price' => 899.99,
                'sell_price' => 1199.99,
                'description' => '2-in-1 laptop with detachable keyboard and Surface Pen support. Intel 12th Gen processors and all-day battery life.',
                'rating' => 4,
                'is_active' => true,
                'created_by' => $admin->id,
            ],
            [
                'brand' => 'Bose',
                'name' => 'QuietComfort Earbuds II',
                'quantity' => 38,
                'cost_price' => 199.99,
                'sell_price' => 279.99,
                'description' => 'Wireless earbuds with personalized noise cancellation and premium audio quality. CustomTune technology for optimal fit.',
                'rating' => 5,
                'is_active' => true,
                'created_by' => $admin->id,
            ],
            [
                'brand' => 'HP',
                'name' => 'Spectre x360 16',
                'quantity' => 8,
                'cost_price' => 1099.99,
                'sell_price' => 1399.99,
                'description' => 'Premium convertible laptop with 4K OLED touchscreen, Intel Evo platform, and gem-cut design. Perfect for creators and professionals.',
                'rating' => 4,
                'is_active' => true,
                'created_by' => $admin->id,
            ],
            [
                'brand' => 'LG',
                'name' => 'OLED55C3PUA 55" TV',
                'quantity' => 5,
                'cost_price' => 1199.99,
                'sell_price' => 1499.99,
                'description' => '55-inch OLED 4K Smart TV with α9 Gen6 AI Processor, Dolby Vision IQ, and webOS 23. Perfect blacks and infinite contrast.',
                'rating' => 5,
                'is_active' => true,
                'created_by' => $admin->id,
            ],
            [
                'brand' => 'Logitech',
                'name' => 'MX Master 3S Mouse',
                'quantity' => 42,
                'cost_price' => 69.99,
                'sell_price' => 99.99,
                'description' => 'Advanced wireless mouse with ultra-fast scrolling, customizable buttons, and multi-device connectivity. Quiet clicks and ergonomic design.',
                'rating' => 4,
                'is_active' => true,
                'created_by' => $admin->id,
            ],
            // Add some inactive products for testing
            [
                'brand' => 'OnePlus',
                'name' => 'OnePlus 11 Pro (Discontinued)',
                'quantity' => 3,
                'cost_price' => 699.99,
                'sell_price' => 899.99,
                'description' => 'Previous generation flagship with Snapdragon 8 Gen 2, fast charging, and OxygenOS. Discontinued model.',
                'rating' => 4,
                'is_active' => false,
                'created_by' => $admin->id,
            ],
            [
                'brand' => 'JBL',
                'name' => 'Flip 6 Speaker (Out of Stock)',
                'quantity' => 0,
                'cost_price' => 89.99,
                'sell_price' => 129.99,
                'description' => 'Portable Bluetooth speaker with powerful sound and rugged design. IP67 waterproof rating.',
                'rating' => 4,
                'is_active' => true,
                'created_by' => $admin->id,
            ],
        ];

        foreach ($products as $productData) {
            Product::create($productData);
        }

        $this->command->info('Products seeded successfully!');
    }
}