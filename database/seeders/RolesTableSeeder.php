<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Membuat role admin jika belum dibuat, jika sudah ada tidak akan di duplikasi
        Role::firstOrCreate(['name' => 'admin']);
    }
}
