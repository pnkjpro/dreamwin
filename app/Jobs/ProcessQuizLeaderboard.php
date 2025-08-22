<?php

namespace App\Jobs;

use App\Models\Quiz;
use App\Models\QuizVariant;
use App\Models\User;
use App\Models\UserResponse;
use App\Models\Leaderboard;
use App\Models\FundTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessQuizLeaderboard implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $quizId;

    /**
     * Create a new job instance.
     */
    public function __construct($quizId)
    {
        $this->quizId = $quizId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $quiz = Quiz::find($this->quizId);
            
            if (!$quiz) {
                Log::error("Quiz not found for leaderboard processing: {$this->quizId}");
                return;
            }

            // Check if prizes are already distributed
            if ($quiz->is_prize_distributed) {
                Log::info("Prizes already distributed for quiz: {$quiz->id}");
                return;
            }

            // Check if quiz is actually over
            if ($quiz->quiz_over_at > time()) {
                Log::warning("Quiz {$quiz->id} is not over yet. Rescheduling...");
                // Reschedule the job for when the quiz actually ends
                $this->dispatch($this->quizId)->delay(now()->addSeconds($quiz->quiz_over_at - time()));
                return;
            }

            $this->processLeaderboard($quiz);
            
            Log::info("Successfully processed leaderboard for quiz: {$quiz->id}");
            
        } catch (\Exception $e) {
            Log::error("Error processing quiz leaderboard: " . $e->getMessage(), [
                'quiz_id' => $this->quizId,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Process the leaderboard and distribute prizes
     */
    private function processLeaderboard(Quiz $quiz): void
    {
        $winnersLimit = $quiz->winners;

        $leaderboard = DB::table('user_responses as ur')
            ->join('users as u', function ($join) {
                $join->on('u.id', '=', 'ur.user_id');
            })
            ->where('ur.node_id', $quiz->node_id)
            ->where('ur.score', '>', 0)
            ->selectRaw(
                'u.name, 
                ur.user_id,
                ur.score,
                ur.id as response_id,
                ur.quiz_variant_id,
                (ur.ended_at - ur.started_at) as duration'
            )
            ->limit($winnersLimit)
            ->orderBy('ur.score', 'DESC')
            ->orderBy('duration')
            ->orderBy('ur.user_id')
            ->get();

        if ($leaderboard->isEmpty()) {
            Log::info("No winners found for quiz: {$quiz->id}");
            $quiz->update(['is_prize_distributed' => 1]);
            return;
        }

        // Preload all needed data
        $variantIds = $leaderboard->pluck('quiz_variant_id')->unique()->toArray();
        $userIds = $leaderboard->pluck('user_id')->unique()->toArray();

        $variants = QuizVariant::whereIn('id', $variantIds)->get()->keyBy('id');
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        DB::transaction(function () use ($leaderboard, $variants, $users, $quiz) {
            foreach ($leaderboard as $key => $rank) {
                $variant = $variants[$rank->quiz_variant_id];
                $winnerUser = $users[$rank->user_id];
                $prizeContents = $variant->prize_contents;
                $rewardAmount = $prizeContents[$key + 1] ?? 0;

                $winnerUser->increment('funds', $rewardAmount);

                FundTransaction::create([
                    'user_id' => $rank->user_id,
                    'action' => 'quiz_reward',
                    'amount' => $rewardAmount,
                    'description' => "Quiz Reward Credited for {$quiz->title}!",
                    'reference_id' => $rank->response_id,
                    'reference_type' => UserResponse::class,
                    'approved_status' => 'approved'
                ]);

                Leaderboard::create([
                    'quiz_id' => $quiz->id,
                    'name' => $rank->name,
                    'user_id' => $rank->user_id,
                    'quiz_variant_id' => $rank->quiz_variant_id,
                    'user_response_id' => $rank->response_id,
                    'score' => $rank->score,
                    'reward' => $rewardAmount,
                    'rank' => $key + 1,
                    'duration' => $rank->duration
                ]);
            }

            $quiz->update(['is_prize_distributed' => 1]);
        });
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessQuizLeaderboard job failed for quiz: {$this->quizId}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}