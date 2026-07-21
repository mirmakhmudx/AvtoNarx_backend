<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

class SourceSeeder extends Seeder
{
    public function run(): void
    {
        Source::updateOrCreate(
            ['code' => 'olx_uz'],
            [
                'name' => 'OLX.uz',
                'type' => 'marketplace',
                'base_url' => 'https://www.olx.uz',
                'is_active' => false,
                'ingestion_enabled' => false,
                'trust_level' => 'unverified',
                'settings' => [
                    'max_pages' => 10,
                    'requests_per_minute' => 6,
                ],
            ]
        );

        Source::updateOrCreate(
            ['code' => 'avtoelon_uz'],
            [
                'name' => 'AvtoElon.uz',
                'type' => 'marketplace',
                'base_url' => 'https://avtoelon.uz',
                'is_active' => false,
                'ingestion_enabled' => false,
                'trust_level' => 'unverified',
                'settings' => [
                    'max_pages' => 10,
                    'requests_per_minute' => 6,
                ],
            ]
        );

        Source::updateOrCreate(
            ['code' => 'uzum_avto'],
            [
                'name' => 'Uzum Avto',
                'type' => 'manufacturer',
                'base_url' => 'https://webview.uzumavto.uz',
                'is_active' => true,
                'ingestion_enabled' => true,
                'trust_level' => 'official',
                'settings' => [
                    'max_pages' => 20,
                ],
            ]
        );
    }
}
