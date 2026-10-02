<?php
namespace Database\Seeders;

use System\Database\DB;
use System\Security\Hash;

/**
 * Demo data for the shop admin: run with `php bongo db:seed`.
 * Uses a fixed random seed so every run produces the same data.
 */
class DatabaseSeeder
{
    public function run(): void
    {
        mt_srand(2026);

        // --- Admin + customers ---
        DB::table('users')->insert([
            'name'     => 'Admin',
            'email'    => 'admin@example.com',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);

        $customerNames = [
            'Rahim Uddin', 'Karim Hossain', 'Nusrat Jahan', 'Tanvir Ahmed', 'Farzana Akter',
            'Sakib Hasan', 'Mithila Rahman', 'Arif Chowdhury', 'Sharmin Sultana', 'Imran Kabir',
        ];
        $customerPassword = Hash::make('password');
        $customerIds = [];
        foreach ($customerNames as $i => $name) {
            $customerIds[] = DB::table('users')->insertGetId([
                'name'     => $name,
                'email'    => strtolower(explode(' ', $name)[0]) . ($i + 1) . '@example.com',
                'password' => $customerPassword,
                'role'     => 'customer',
                'phone'    => '+880 17' . str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT),
            ]);
        }

        // --- Categories + products ---
        $catalog = [
            'Electronics'  => [['Wireless Earbuds', 39.99], ['Bluetooth Speaker', 54.50], ['USB-C Charger 65W', 29.00], ['Smart Watch', 129.00], ['Power Bank 20000mAh', 34.99]],
            'Fashion'      => [['Cotton T-Shirt', 12.50], ['Denim Jacket', 59.00], ['Leather Wallet', 24.99], ['Running Sneakers', 79.90]],
            'Home & Kitchen' => [['Non-stick Frying Pan', 27.50], ['Electric Kettle', 32.00], ['Ceramic Mug Set', 18.75], ['Desk Lamp', 22.40]],
            'Books'        => [['PHP from Scratch', 31.00], ['Clean Architecture Notes', 28.50], ['Bangla Poetry Collection', 15.00]],
            'Sports'       => [['Yoga Mat', 21.99], ['Cricket Bat', 45.00], ['Football', 19.50], ['Water Bottle 1L', 9.99]],
        ];

        $products = [];
        $skuNumber = 1000;
        foreach ($catalog as $category => $items) {
            $categoryId = DB::table('categories')->insertGetId([
                'name' => $category,
                'slug' => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($category)), '-'),
            ]);

            foreach ($items as [$name, $price]) {
                $id = DB::table('products')->insertGetId([
                    'category_id' => $categoryId,
                    'name'        => $name,
                    'sku'         => 'BG-' . $skuNumber++,
                    'price'       => $price,
                    'stock'       => mt_rand(0, 6) === 0 ? mt_rand(0, 8) : mt_rand(15, 200),
                    'status'      => mt_rand(0, 9) === 0 ? 'draft' : 'active',
                    'description' => "$name from our $category range.",
                ]);
                $products[] = ['id' => $id, 'name' => $name, 'price' => $price];
            }
        }

        // --- Orders over the last 30 days ---
        $statuses = ['pending', 'processing', 'processing', 'shipped', 'delivered', 'delivered', 'delivered', 'cancelled'];
        $cities = ['Dhaka', 'Chattogram', 'Sylhet', 'Khulna', 'Rajshahi'];

        for ($n = 1; $n <= 40; $n++) {
            $createdAt = date('Y-m-d H:i:s', strtotime('-' . mt_rand(0, 29) . ' days -' . mt_rand(0, 23) . ' hours'));
            $orderId = DB::table('orders')->insertGetId([
                'user_id'          => $customerIds[array_rand($customerIds)],
                'order_number'     => 'ORD-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT),
                'status'           => $statuses[array_rand($statuses)],
                'total'            => 0,
                'shipping_address' => mt_rand(1, 120) . ' Main Road, ' . $cities[array_rand($cities)],
                'created_at'       => $createdAt,
                'updated_at'       => $createdAt,
            ]);

            $total = 0;
            foreach ((array) array_rand($products, mt_rand(1, 3)) as $index) {
                $product = $products[$index];
                $qty = mt_rand(1, 3);
                DB::table('order_items')->insert([
                    'order_id'     => $orderId,
                    'product_id'   => $product['id'],
                    'product_name' => $product['name'],
                    'quantity'     => $qty,
                    'unit_price'   => $product['price'],
                ]);
                $total += $qty * $product['price'];
            }

            DB::table('orders')->where('id', $orderId)->update(['total' => round($total, 2), 'updated_at' => $createdAt]);
        }
    }
}
