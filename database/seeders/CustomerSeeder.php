<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ShoppingCart;
use Illuminate\Support\Facades\Hash;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $customers = [
            [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane@example.com',
                'contact' => '5555555555',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ],
            [
                'first_name' => 'Mike',
                'last_name' => 'Wilson',
                'email' => 'mike@example.com',
                'contact' => '6666666666',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ],
            [
                'first_name' => 'Sarah',
                'last_name' => 'Davis',
                'email' => 'sarah@example.com',
                'contact' => '7777777777',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ],
            [
                'first_name' => 'Tom',
                'last_name' => 'Miller',
                'email' => 'tom@example.com',
                'contact' => '8888888888',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ],
            [
                'first_name' => 'Lisa',
                'last_name' => 'Garcia',
                'email' => 'lisa@example.com',
                'contact' => '9999999999',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ],
            [
                'first_name' => 'Chris',
                'last_name' => 'Anderson',
                'email' => 'chris@example.com',
                'contact' => '1111111111',
                'password' => Hash::make('password123'),
                'is_active' => false, // Inactive customer
            ],
            [
                'first_name' => 'Emma',
                'last_name' => 'Taylor',
                'email' => 'emma@example.com',
                'contact' => '2222222222',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ],
            [
                'first_name' => 'Ryan',
                'last_name' => 'Martinez',
                'email' => 'ryan@example.com',
                'contact' => '3333333333',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ],
        ];

        $createdCustomers = [];
        
        foreach ($customers as $customerData) {
            $existingCustomer = Customer::where('email', $customerData['email'])->first();
            if (!$existingCustomer) {
                $customer = Customer::create($customerData);
                $createdCustomers[] = $customer;
                $this->command->info("Customer {$customer->email} created");
            } else {
                $createdCustomers[] = $existingCustomer;
            }
        }

        // Add some products to customers' carts
        $this->addCartItems($createdCustomers);

        $this->command->info('Customers seeded successfully!');
    }

    /**
     * Add random cart items for some customers
     */
    private function addCartItems($customers)
    {
        // Get some products for cart items
        $products = Product::active()->inStock()->take(10)->get();
        
        if ($products->isEmpty()) {
            $this->command->warn('No products available for cart seeding. Run ProductSeeder first.');
            return;
        }

        // Add cart items for first 5 customers
        $customersWithCarts = array_slice($customers, 0, 5);
        
        foreach ($customersWithCarts as $customer) {
            if (!$customer->is_active) continue;
            
            // Random number of cart items (1-4 products)
            $itemCount = rand(1, 4);
            $selectedProducts = $products->random($itemCount);
            
            foreach ($selectedProducts as $product) {
                $quantity = rand(1, 3); // Random quantity
                
                try {
                    ShoppingCart::create([
                        'customer_id' => $customer->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                    ]);
                    
                    $this->command->info("Added {$quantity}x {$product->name} to {$customer->email}'s cart");
                } catch (\Exception $e) {
                    // Skip if duplicate (unique constraint)
                    continue;
                }
            }
        }
    }
}