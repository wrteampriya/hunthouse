<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\Demo\ParametersDataSeeder;
use Database\Seeders\Demo\CategoriesDataSeeder;
use Database\Seeders\Demo\PropertiesDataSeeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Parameters/Facilities Data Seeder (5 facilities)
        $parametersDataSeeder = new ParametersDataSeeder();
        $parametersDataSeeder->run();

        // Categories Data Seeder (5 categories)
        $categoriesDataSeeder = new CategoriesDataSeeder();
        $categoriesDataSeeder->run();

        // Properties Data Seeder (8 properties)
        $propertiesDataSeeder = new PropertiesDataSeeder();
        $propertiesDataSeeder->run();
    }
}
