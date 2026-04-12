<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\City;
use App\Models\Province;
use Illuminate\Support\Facades\Http;

class CitiesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $provinces = Province::all();

        if ($provinces->isEmpty()) {
            $this->command->error('Tidak ada provinsi yang ditemukan di database. Silakan seed provinsi terlebih dahulu.');
            return;
        }

        $allCities = $this->fetchAllCities($provinces);

        if (empty($allCities)) {
            $this->command->error('Gagal mengambil kota dari API.');
            return;
        }

        $this->storeCities($allCities);

        $this->command->info('Tabel kota berhasil di-seed.');
    }

    private function fetchAllCities($provinces): array
    {
        $allCities = [];

        foreach ($provinces as $province) {
            $response = Http::withHeaders([
                'Key' => config('rajaongkir.api_key'),
                'Accept' => 'application/json',
            ])->get(config('rajaongkir.endpoints.city') . '/' . $province->id);

            if ($response->failed()) {
                $this->command->warn("Gagal mengambil kota untuk ID provinsi: {$province->id}");
                continue;
            }

            $cities = $response->json()['data'] ?? [];

            foreach ($cities as &$city) {
                $city['province_id'] = $province->id;
            }

            if (!empty($cities)) {
                $allCities = array_merge($allCities, $cities);
            }
        }

        return $allCities;
    }

    private function storeCities(array $cities): void
    {
        $data = collect($cities)->map(function ($city) {
            return [
                'id' => $city['id'],
                'province_id' => $city['province_id'],
                'name' => $city['name'],
            ];
        })->toArray();

        City::insert($data);
    }
}
