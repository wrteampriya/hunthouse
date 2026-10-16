<?php

namespace Database\Seeders\Demo;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createCustomers();
    }

    // Create 5 Customers
    public function createCustomers() {
        Customer::factory()->count(5)->create();
    }
}