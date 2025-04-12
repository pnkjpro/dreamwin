<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\HowVideos;

class HowVideosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        HowVideos::create([
            'title' => 'How to Train Your Dragon',
            'description' => 'the creative visionary behind DreamWorks Animations acclaimed How to Train Your Dragon trilogy, comes a stunning live-action reimagining of the film that launched the beloved franchise.',
            'youtube_id' => '22w7z_lT6YM'
        ]);
        HowVideos::create([
            'title' => 'How to Train Your Dragon',
            'description' => 'the creative visionary behind DreamWorks Animations acclaimed How to Train Your Dragon trilogy, comes a stunning live-action reimagining of the film that launched the beloved franchise.',
            'youtube_id' => '22w7z_lT6YM'
        ]);
        HowVideos::create([
            'title' => 'How to Train Your Dragon',
            'description' => 'the creative visionary behind DreamWorks Animations acclaimed How to Train Your Dragon trilogy, comes a stunning live-action reimagining of the film that launched the beloved franchise.',
            'youtube_id' => '22w7z_lT6YM'
        ]);
    }
}
