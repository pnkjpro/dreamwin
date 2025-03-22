<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Lifeline;

class LifelineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Lifeline::create([
            'name' => '50:50',
            'description' => 'Removes two incorrect answers, leaving the correct answer and one incorrect answer.',
            'icon' => 'fifty_fifty.svg',
            'cost' => 100,
            'effect_description' => 'Removes two incorrect options'
        ]);
        
        Lifeline::create([
            'name' => 'Skip Question',
            'description' => 'Skip the current question without penalty.',
            'icon' => 'skip.svg',
            'cost' => 150,
            'effect_description' => 'Skips to the next question without losing points'
        ]);
        
        Lifeline::create([
            'name' => 'Revive Game',
            'description' => 'Revive the Game within 60 seconds of failed quiz',
            'icon' => 'revive.svg',
            'cost' => 199,
            'effect_description' => 'Revive the game without losing points'
        ]);
    }
}
