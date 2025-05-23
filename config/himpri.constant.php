<?php
use Illuminate\Support\Facades\Config;

return [
    'avatars' => ['hippie.png', 'vampire.png', 'pirate.png', 'eighties.png', 'hippie2.png', 'man.png', 'peach.png', 'penguin.png', 'pirate2.png','superhero.png', 'vampire2.png', 'woman.png', 'wrestle.png'],
    'homepagePaginationLimit' => 6,
    'adminPaginationLimit' => 25,
    'dashboardPaginationLimit' => 20,
    'email' => [
        'official' => 'himpriofficial@gmail.com'
<<<<<<< Updated upstream
    ]
=======
    ],
    'referral_reward_amount' => 10,
    'official_notice' => "Dear Contestants, the referral reward for unverified user got deducted. Kindly ask your referred user to verify their account to benefit referral reward!",
    'official_notice_status' => env('OFFICIAL_NOTICE_STATUS', false)
>>>>>>> Stashed changes
];