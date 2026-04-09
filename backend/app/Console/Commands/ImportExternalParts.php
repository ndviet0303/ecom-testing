<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportExternalParts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-external-parts {--limit=100 : Limit parts per category}';

    protected $description = 'Import real PC components from GitHub datasets with modern filtering (2021+)';

    private array $urls = [
        'CPU' => 'https://raw.githubusercontent.com/docyx/pc-part-dataset/main/data/json/cpu.json',
        'GPU' => 'https://raw.githubusercontent.com/docyx/pc-part-dataset/main/data/json/video-card.json',
        'Motherboard' => 'https://raw.githubusercontent.com/docyx/pc-part-dataset/main/data/json/motherboard.json',
        'RAM' => 'https://raw.githubusercontent.com/docyx/pc-part-dataset/main/data/json/memory.json',
        'PSU' => 'https://raw.githubusercontent.com/docyx/pc-part-dataset/main/data/json/power-supply.json',
        'Case' => 'https://raw.githubusercontent.com/docyx/pc-part-dataset/main/data/json/case.json',
        'Storage' => 'https://raw.githubusercontent.com/docyx/pc-part-dataset/main/data/json/internal-hard-drive.json',
        'Monitor' => 'https://raw.githubusercontent.com/docyx/pc-part-dataset/main/data/json/monitor.json',
    ];

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $this->info("Starting import of PC components (Limit: {$limit} per category)...");

        foreach ($this->urls as $category => $url) {
            $this->warn("Fetching {$category} data...");
            try {
                $response = \Illuminate\Support\Facades\Http::get($url);
                if (!$response->successful()) {
                    $this->error("Failed to fetch {$category}");
                    continue;
                }

                $data = $response->json();
                $count = 0;

                foreach ($data as $item) {
                    if ($count >= $limit) break;

                    if (!$this->shouldImport($category, $item)) {
                        continue;
                    }

                    $this->importItem($category, $item);
                    $count++;
                }

                $this->info("Imported {$count} items for {$category}");
            } catch (\Exception $e) {
                $this->error("Error importing {$category}: " . $e->getMessage());
            }
        }

        $this->info('Import completed successfully!');
    }

    private function shouldImport(string $category, array $item): bool
    {
        $name = strtolower($item['name'] ?? '');
        
        switch ($category) {
            case 'CPU':
                return preg_match('/(ryzen.[579].(5|7|9)[\d]00)|(core.i[579].1[234])|(core.ultra)/i', $name);
            case 'GPU':
                $chipset = strtolower($item['chipset'] ?? '');
                return preg_match('/(rtx.[345]0)|(rx.(6|7|9)[\d]00)|(arc.(a|b))/i', $chipset ?: $name);
            case 'Motherboard':
                $socket = strtolower($item['socket'] ?? '');
                return in_array($socket, ['lga1700', 'am4', 'am5', 'lga1851']);
            case 'RAM':
                $speed = $item['speed'] ?? [];
                $generation = $speed[0] ?? 0;
                return $generation === 4 || $generation === 5;
            case 'PSU':
                $efficiency = strtolower($item['efficiency'] ?? '');
                return str_contains($efficiency, 'gold') || str_contains($efficiency, 'platinum') || str_contains($efficiency, 'titanium');
            case 'Case':
                $type = strtolower($item['type'] ?? '');
                return str_contains($type, 'mid tower') || str_contains($type, 'full tower');
            case 'Storage':
                $capacity = (int) ($item['capacity'] ?? 0);
                return $capacity >= 500 && (str_contains($name, 'ssd') || str_contains($name, 'nvme'));
            case 'Monitor':
                $refresh = (int) ($item['refresh_rate'] ?? 0);
                return $refresh >= 144;
        }

        return true;
    }

    private function importItem(string $category, array $item)
    {
        $sku = 'EXT-' . strtoupper(substr($category, 0, 3)) . '-' . hash('crc32', $item['name']);
        
        $priceCents = isset($item['price']) ? (int) ($item['price'] * 100) : rand(100, 2000) * 100;
        
        $images = [
            'CPU' => 'https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea?q=80&w=400&auto=format&fit=crop',
            'GPU' => 'https://images.unsplash.com/photo-1591488320449-011701bb6704?q=80&w=600&auto=format&fit=crop',
            'Motherboard' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?q=80&w=600&auto=format&fit=crop',
            'RAM' => 'https://images.unsplash.com/photo-1541029071515-84cc54f84dc5?q=80&w=600&auto=format&fit=crop',
            'PSU' => 'https://images.unsplash.com/photo-1587202395160-221142215d7a?q=80&w=600&auto=format&fit=crop',
            'Case' => 'https://images.unsplash.com/photo-1547082299-de196ea013d6?q=80&w=600&auto=format&fit=crop',
            'Storage' => 'https://images.unsplash.com/photo-1597872200370-496de2f54e0c?q=80&w=600&auto=format&fit=crop',
            'Monitor' => 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?q=80&w=600&auto=format&fit=crop',
        ];

        $product = \App\Models\Product::updateOrCreate(
            ['sku' => $sku],
            [
                'name' => $item['name'],
                'brand' => explode(' ', $item['name'])[0] ?? 'Generic',
                'category' => $category,
                'description' => "High performance {$category} for modern PC builds.",
                'base_price_cents' => $priceCents,
                'sale_price_cents' => (int) ($priceCents * 0.95), // 5% off "special"
                'image_url' => $images[$category] ?? 'https://images.unsplash.com/photo-1591799264318-7e6ef8ddb7ea?q=80&w=400&auto=format&fit=crop',
                'specs' => $this->mapSpecs($category, $item),
                'warranty_months' => 36,
            ]
        );

        // Ensure inventory exists
        \App\Models\Inventory::updateOrCreate(
            ['product_id' => $product->id],
            [
                'on_hand' => rand(5, 25),
                'reserved' => 0,
                'low_stock_threshold' => 3,
            ]
        );
    }

    private function mapSpecs(string $category, array $item): array
    {
        switch ($category) {
            case 'CPU':
                return [
                    'socket' => $item['socket'] ?? 'Unknown',
                    'cores' => $item['core_count'] ?? 0,
                    'clock' => $item['core_clock'] ?? 0,
                    'boost' => $item['boost_clock'] ?? 0,
                    'tdp' => $item['tdp'] ?? 0,
                ];
            case 'Motherboard':
                return [
                    'socket' => $item['socket'] ?? 'Unknown',
                    'form_factor' => $item['form_factor'] ?? 'ATX',
                    'ram_slots' => $item['memory_slots'] ?? 4,
                    'max_ram' => $item['max_memory'] ?? 128,
                ];
            case 'GPU':
                return [
                    'chipset' => $item['chipset'] ?? 'Unknown',
                    'vram' => $item['memory'] ?? 0,
                    'length' => $item['length'] ?? 0,
                    'psu_req' => 650, // Default estimate
                ];
            case 'RAM':
                return [
                    'speed' => $item['speed'] ?? [3200],
                    'type' => $item['type'] ?? 'DDR4',
                    'modules' => $item['modules'] ?? '2 x 8GB',
                ];
            case 'Case':
                return [
                    'type' => $item['type'] ?? 'Mid Tower',
                    'side_panel' => $item['side_panel'] ?? 'Windowed',
                    'color' => $item['color'] ?? 'Black',
                ];
            case 'Storage':
                return [
                    'type' => $item['type'] ?? 'SSD',
                    'capacity' => $item['capacity'] ?? 0,
                    'interface' => $item['interface'] ?? 'NVMe',
                    'cache' => $item['cache'] ?? 'N/A',
                ];
            case 'Monitor':
                return [
                    'screen_size' => $item['screen_size'] ?? 0,
                    'resolution' => $item['resolution'] ?? '1920 x 1080',
                    'refresh_rate' => $item['refresh_rate'] ?? 60,
                    'panel_type' => $item['panel_type'] ?? 'IPS',
                ];
            default:
                return $item;
        }
    }
}
