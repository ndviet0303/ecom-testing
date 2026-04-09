<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class PcPartsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Đường dẫn tương đối từ thư mục backend
        $path = base_path('../tests/api/data/pc_parts_test_data.json');
        
        if (!File::exists($path)) {
            $this->command->error("File không tồn tại tại: $path");
            return;
        }

        $json = File::get($path);
        $data = json_decode($json, true);

        foreach ($data as $item) {
            $product = Product::query()->updateOrCreate(
                ['sku' => $item['sku']],
                [
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'brand' => $item['brand'] ?? 'Generic',
                    'base_price_cents' => $item['base_price_cents'] ?? 5000000,
                    'specs' => $item['specs'],
                    'warranty_months' => $item['warranty_months'] ?? 24,
                ]
            );

            Inventory::query()->updateOrCreate(
                ['product_id' => $product->id],
                [
                    'on_hand' => 10,
                    'reserved' => 0,
                    'low_stock_threshold' => 2,
                ]
            );
        }

        $this->command->info('Đã nạp xong ' . count($data) . ' linh kiện PC vào Database.');
    }
}
