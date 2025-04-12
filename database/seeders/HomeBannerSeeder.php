<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\HomeBanner;

class HomeBannerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        HomeBanner::create([
            'title' => 'Festive Game',
            'banner_path' => 'home/banners/banner_1.jpg',
            'is_active' => 1
        ]);
        HomeBanner::create([
            'title' => 'Magic Reward Game',
            'banner_path' => 'home/banners/banner_2.jpg',
            'is_active' => 1
        ]);
        HomeBanner::create([
            'title' => 'Dhamaka Game',
            'banner_path' => 'home/banners/banner_3.jpg',
            'is_active' => 1
        ]);
    }
}
