<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use App\Models\Province;

class ProvincesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $provinces = $this->fetchProvinces();

        if ($provinces === null) {
            $this->command->error('Failed to fetch provinces from API.');
            return;
        }

        $this->storeProvinces($provinces);

        $this->command->info('Provinces table seeded successfully.');
    }

    private function fetchProvinces(): ?array
    {
        $response = Http::withHeaders([
            'Key' => config('rajaongkir.api_key'),
            'Accept' => 'application/json',
        ])->get(config('rajaongkir.endpoints.province'));

        if ($response->failed()) {
            $this->command->error('API Response Status: ' . $response->status());
            $this->command->error('API Response Body: ' . $response->body());
            return null;
        }

        $data = $response->json();

        return $data['data'] ?? null;
    }

    private function storeProvinces(array $provinces): void
    {
        // transform data using collection laravel
        $data = collect($provinces)->map(function ($province) {
            return [
                'id' => $province['id'],
                'name' => $province['name'],
            ];
        })->toArray();

        // bulk insert untuk performa optimal
        Province::insert($data);

        // cara yang kurang baik
        // foreach ($provinces as $province) {
        //     // 35 kali query ke database!
        //     Province::create($province);
        // }
    }
}
