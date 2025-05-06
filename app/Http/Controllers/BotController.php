<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BotAction;
use App\Models\Quiz;
use App\Models\User;
use App\Models\QuizVariant;
use App\Models\UserLifeline;
use App\Models\UserResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;

use App\Traits\JsonResponseTrait;

class BotController extends Controller
{
    use JsonResponseTrait;
    public function createActions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'quiz_id' => 'required|exists:quizzes,id',
            'variant_id' => 'required|exists:quiz_variants,id',
            'rank' => 'required|numeric',
            'duration' => 'required|numeric',
            'question_attempts' => 'required|numeric'
        ]);

        if($validator->fails()){
            return $this->errorResponse([], $validator->errors()->first(), 422);
        }
        $data = $validator->validated();
        $questions = Quiz::findOrFail($data['quiz_id'])->first();
        $variant = QuizVariant::findOrFail($data['variant_id'])->first();
        
        $quizContents = collect($questions->quizContents)->take($data['question_attempts']);
        $botResponse = [];
        foreach($quizContents as $key => $question){
            $botResponse[$key]['question_id'] = $question['id'];
            $botResponse[$key]['answer_id'] = $question['correctAnswerId'];
            $botResponse[$key]['is_correct'] = true;
        }

        $response = UserResponse::create([
            'user_id' => $data['user_id'],
            'quiz_id' => $data['quiz_id'],
            'node_id' => $questions->node_id,
            'quiz_variant_id' => $data['variant_id'],
            'score' => $data['question_attempts'],
            'responseContents' => $botResponse,
            'status' => 'initiated',
            'started_at' => $questions->start_time,
            'ended_at' => $questions->start_time + $data['duration']
        ]);
        dd($botResponse);
        
    }

    public function createBot(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:30'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse([], $validator->errors()->first(), 422);
        }

        $data = $validator->validated();

        $baseSlug = $this->generateSlug($data['name']);
        $slug = $baseSlug;
        $email = $slug . "@himpri.com";
        $count = 1;

        while (User::where('email', $email)->exists()) {
            $slug = $baseSlug . "-" . $count;
            $email = $slug . "@himpri.com";
            $count++;
        }

        $avatars = Config::get('himpri.constant.avatars');
        $randomAvatar = '/avatars/' . Arr::random($avatars);

        DB::beginTransaction();
        try {
            $bot = User::create([
                'name' => $data['name'],
                'avatar' => $randomAvatar,
                'email' => $email,
                'funds' => 100000,
                'refer_code' => 'himpri_black', //this is to identify a bot user
                'password' => Hash::make("sentryisbob")
            ]);

            for ($i = 1; $i <= 3; $i++) {
                UserLifeline::create([
                    'user_id' => $bot->id,
                    'lifeline_id' => $i,
                    'quantity' => 10000
                ]);
            }
            DB::commit();
            return $this->successResponse($bot, 'Bot created successfully', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse([], 'Failed to create bot: ' . $e->getMessage(), 500);
        }
    }



    private function generateSlug($name)
    {
        return Str::slug($name, '_');
    }
}
