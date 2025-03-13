<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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
            'name' => 'Hint',
            'description' => 'Provides a helpful hint for the current question.',
            'icon' => 'hint.svg',
            'cost' => 75,
            'effect_description' => 'Shows a hint about the correct answer'
        ]);
        
        Lifeline::create([
            'name' => 'Extra Time',
            'description' => 'Adds 30 seconds to the timer for the current question.',
            'icon' => 'time.svg',
            'cost' => 120,
            'effect_description' => 'Adds 30 seconds to the question timer'
        ]);
    }
}
