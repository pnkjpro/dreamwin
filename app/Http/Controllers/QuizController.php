<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

use App\Models\Quiz;
use App\Models\QuizVariant;
use App\Models\User;
use App\Models\UserResponse;
use App\Models\LifelineUsage;
use App\Models\Leaderboard;
use App\Models\FundTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\UserResponseResource;


use Illuminate\Support\Facades\Validator;
use App\Traits\JsonResponseTrait;

class QuizController extends Controller
{
    use JsonResponseTrait;
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $limit = Config::get('himpri.constant.homepagePaginationLimit'); 
        $offset = ($page - 1) * $limit; 
        $quizzes = Quiz::with(['category', 'quiz_variants'])
                        ->where('end_time', '>', time())
                        ->orderBy('start_time', 'ASC');
        // $quizzes = Quiz::with(['category', 'quiz_variants'])
        //                 ->where('end_time', '>', time())
        //                 ->orderBy('start_time', 'ASC')
        //                 ->limit($limit)
        //                 ->offset($offset)
        //                 ->get()
        //                 ->makeHidden('quizContents');
        $totalCount = $quizzes->count();
        $quizzes = $quizzes->limit($limit)->offset($offset)->get()->makeHidden('quizContents');

        return $this->successResponse([$quizzes, $totalCount], "Records has been founded", 200);
    }

    public function store(Request $request)
    {
        
        $validator = Validator::make($request->all(),[
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'banner_image' => 'nullable|string',
            'quizContents' => 'required|array',
            'quizVariants' => 'required|array',
            // 'quizContents.*.question' => 'required|string',
            // 'quizContents.*.options' => 'required|array|min:2',
            // 'quizContents.*.options.*.id' => 'required|integer',
            // 'quizContents.*.options.*.option' => 'required|string',
            // 'quizContents.*.correctAnswerId' => 'required|integer',
            'spot_limit' => 'required|integer',
            'entry_fees' => 'required|integer',
            'prize_money' => 'required|integer',
            'start_time' => 'required',
            'end_time' => 'required',
            'quiz_timer' => 'required|integer',
            'winners' => 'required|integer',
            'banner_image' => 'nullable|file|image|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['node_id'] = $this->generateNodeId();
        $data['totalQuestion'] = count($data['quizContents']);
        $data['start_time'] = strtotime($request->start_time);
        $data['end_time'] = strtotime($request->end_time);
        $data['quiz_over_at'] = $data['end_time'] + ($data['totalQuestion'] * $data['quiz_timer']) + 300;
        $bannerPath = null;
        if ($request->hasFile('banner_image') && $request->file('banner_image')->isValid()) {
            $extension = $request->file('banner_image')->getClientOriginalExtension();
            $filename = $data['node_id'] . time() . '-banner.' . $extension;
            $bannerPath = $request->file('banner_image')->storeAs('quiz/banners', $filename, 'public');
        }
        $data['banner_image'] = $bannerPath;


        $quiz = Quiz::create($data);

        foreach($data['quizVariants'] as $variant){
            $this->createVariant(new Request([
                'quiz_id' => $quiz->id,
                'entry_fee' => $variant['entry_fee'],
                'prize' => $variant['prize'],
                'prize_contents' => $variant['prize_contents'],
                'slot_limit' => $variant['slot_limit'],
                'status' => 'active'
            ]));
        }

        return $this->successResponse($quiz, "Quiz has been created", 201);
    }

    public function generateNodeId(){
        $latestNodeId = Quiz::latest()->value('node_id');
        $nodeId = $latestNodeId ? $latestNodeId + 1 : 1000;
        while (Quiz::where('node_id', $nodeId)->exists()) {
            $nodeId++;
        }
        return $nodeId;
    }

    public function createVariant(Request $request){
        $validator = Validator::make($request->all(),[
            'quiz_id' => 'required|exists:quizzes,id',
            'entry_fee' => 'required|numeric',
            'prize' => 'required|numeric',
            'prize_contents' => 'required|array',
            'slot_limit' => 'required|numeric',
            'status' => 'nullable|in:active,inactive'
        ]);

        if($validator->fails()){
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $variant = QuizVariant::create($data);
        return $this->successResponse($variant, "Quiz has been created", 201);
    }

    public function userResponse(Request $request){
        $quiz = Quiz::where('node_id', $request->node_id)->first();
        $userResponseContents = $request->userResponseContents;
        $correctAnswers = [];
        foreach($quiz->quizContents as $question){
            $correctAnswers[$question['id']] = $question['correctAnswerId'];
        }
        $score = 0;
        foreach($userResponseContents as $response){
            if($response['answerId'] == $correctAnswers[$response['questionId']]){
                $score++;
            }
        }
        
        $userResponseModal = new userResponse();
        $userResponseModal->user_id = $request->user_id;
        $userResponseModal->quiz_id = $quiz->id;
        $userResponseModal->score = $score;
        $userResponseModal->responseContents = $userResponseContents;
        $userResponseModal->save();

        return $this->successResponse($userResponseModal, "Quiz has been submitted successfully!", 201);
        
    }

    public function nextQuestion(Request $request){
        $nodeId = $request->node_id;
        $question_id = $request->question_id;
        $user = Auth::user();
        $quiz = Quiz::where('node_id', $nodeId)->first();
        $question = $quiz->quizContents->where('id', $question_id)->first();

        return $question;
        
    }

    public function leaderboard(Request $request)
    {
        //Suggestion: if you want to make a job to optimize the query follow here https://chatgpt.com/c/67fbed85-fe18-8002-895c-f56fa15cb4a3
        $validator = Validator::make($request->all(), [
            'node_id' => 'required|exists:user_responses,node_id'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse([], $validator->errors()->first(), 422);
        }

        $data = $validator->validated();
        $user = Auth::user();
        $quiz = Quiz::where('node_id', $data['node_id'])->first();
        $winnersLimit = $quiz->winners;

        if ($quiz->quiz_over_at > time()) {
            return $this->errorResponse([], "Quiz is not ended yet, leaderboard cannot be processed!", 403);
        }

        if (!$quiz->is_prize_distributed) {
            $leaderboard = DB::table('user_responses as ur')
            ->join('users as u', function ($join) {
                $join->on('u.id', '=', 'ur.user_id');
            })
            ->where('ur.node_id', $data['node_id'])
            ->where('ur.score', '>', 0)
            ->selectRaw(
                'u.name, 
                ur.user_id,
                ur.score,
                ur.id as response_id,
                ur.quiz_variant_id,
                (ur.ended_at - ur.started_at) as duration,
                CASE WHEN u.id = ? THEN true ELSE false END as isUser',
                [$user->id]
            )
            ->limit($winnersLimit)
            ->orderBy('ur.score', 'DESC')
            ->orderBy('duration')
            ->orderBy('ur.user_id')
            ->get();

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
        } else {
            $leaderboard = Leaderboard::select('name', 'user_id', 'rank', 'score', 'reward', 'duration')->where('quiz_id', $quiz->id)
                                        ->orderBy('rank')
                                        ->get();
            $leaderboard = $leaderboard->map(function ($entry) use ($user) {
                $entry->isUser = $entry->user_id == $user->id;
                return $entry;
            });
                                        
            
        }

        $query = UserResponse::where('node_id', $data['node_id']);
        $count = $query->count();
        $userPoints = $query->where('user_id', $user->id)->select('score')->first();

        $result = [
            'topPlayers' => $leaderboard,
            'totalParticipants' => $count,
            'userPoints' => $userPoints
        ];

        if ($leaderboard->first()) {
            return $this->successResponse($result, "Leaderboard has been prepared", 200);
        }

        return $this->errorResponse([], "Leaderboard has not been prepared yet!", 403);
    }

    public function showAdminLeaderboard(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'quiz_id' => 'required|exists:quizzes,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse([], $validator->errors()->first(), 422);
        }

        $leaderboard = Leaderboard::with('user')->select('user_id','name', 'rank', 'score', 'duration', 'reward')->where('quiz_id', $request->quiz_id)->orderBy('rank')->get();

        if ($leaderboard->isNotEmpty()) {
            return $this->successResponse($leaderboard, "Leaderboard has been fetched!", 200);
        }

        return $this->errorResponse([], "Leaderboard has not been prepared yet!", 404);
    }

    public function showAnswerKey(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'node_id' => 'required|exists:user_responses,node_id'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse([], $validator->errors()->first(), 422);
        }

        $data = $validator->validated();
        $nodeId = $data['node_id'];
        $userId = Auth::user()->id;

        // Fetch the quiz
        $quiz = Quiz::where('node_id', $nodeId)->first();
        if (!$quiz) {
            return $this->errorResponse([], 'Quiz not found for this node.', 404);
        }

        // Fetch user response
        $userResponse = UserResponse::where('node_id', $nodeId)
                            ->where('user_id', $userId)
                            ->select('id', 'score', 'responseContents')
                            ->first();

        if (!$userResponse) {
            return $this->errorResponse([], 'User has not submitted a response for this quiz.', 404);
        }

        // Get lifeline usage, keyed by question_id
        $lifelineUsage = LifelineUsage::with('lifeline')
                            ->where('user_response_id', $userResponse->id)
                            ->where('user_id', $userId)
                            ->get()
                            ->keyBy('question_id');

        // Ensure casting is properly done
        $quizAnswerSheet = collect($quiz->quizContents ?? [])->keyBy('id');
        $userAnswers = $userResponse->responseContents ?? [];

        $answerKey = [];

        foreach ($userAnswers as $answer) {
            $questionId = $answer['question_id'] ?? null;

            // Skip invalid or missing question ID
            if (!$questionId || !isset($quizAnswerSheet[$questionId])) {
                continue;
            }

            $quizQuestion = $quizAnswerSheet[$questionId];
            $lifelineUsed = isset($lifelineUsage[$questionId]);
            $lifelineType = $lifelineUsed ? ($lifelineUsage[$questionId]['lifeline']['name'] ?? null) : null;

            $answerKey[$questionId] = [
                'question' => $quizQuestion['question'] ?? null,
                'options' => $quizQuestion['options'] ?? [],
                'lifeline_used' => $lifelineUsed,
                'lifeline_type' => $lifelineType,
                'user_answer_id' => null,
                'correct_answer_id' => null,
                'is_correct' => null,
            ];

            if (!$lifelineUsed || $lifelineType !== 'Skip Question') {
                $answerKey[$questionId]['user_answer_id'] = $answer['answer_id'] ?? null;
                $answerKey[$questionId]['correct_answer_id'] = $quizQuestion['correctAnswerId'] ?? null;
                $answerKey[$questionId]['is_correct'] = $answer['is_correct'] ?? null;
            }
        }
        return $this->successResponse($answerKey, "User Response has been fetched Successfully!", 200);
    }


    public function listAdminLeaderboard(Request $request)
    {
        $page = $request->input('page', 1);
        $limit = Config::get('himpri.constant.adminPaginationLimit'); 
        $offset = ($page - 1) * $limit; 
        $leaderboardsQuery = Leaderboard::select(
                'leaderboards.quiz_id',
                'quizzes.title',
                'users.name as top_user_name'
            )
            ->join('quizzes', 'quizzes.id', '=', 'leaderboards.quiz_id')
            ->join('users', 'users.id', '=', 'leaderboards.user_id')
            ->orderByDesc('leaderboards.id');
        $totalCount = $leaderboardsQuery->count();
        $leaderboards = $leaderboardsQuery->limit($limit)->offset($offset)
                                            ->where('leaderboards.rank', 1)
                                            ->get();

        if ($leaderboards->isNotEmpty()) {
            return $this->successResponse([
                'totalCount' => $totalCount,
                'leaderboards' => $leaderboards
            ], "Leaderboard list has been fetched!", 200);
        }

        return $this->errorResponse([], "No leaderboards available!", 404);
    }




    public function listVariant(Request $request){
        $nodeId = $request->query('node_id');
        $QuizVariants = Quiz::with([
            'quiz_variants' => function($query){
                $query->withCount('user_responses');
            }
            ])->where('node_id', $nodeId)->first();
        return $this->successResponse($QuizVariants->makeHidden('quizContents'), "Record has been founded!", 200);
    }

    public function update(Request $request, Quiz $quiz)
    {
        $validator = Validator::make($request->all(),[
            'category_id' => 'sometimes|exists:categories,id',
            'node_id' => 'sometimes|integer',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'banner_image' => 'nullable|string',
            'quizContents' => 'sometimes|json',
            'spot_limit' => 'sometimes|integer',
            'entry_fees' => 'sometimes|integer',
            'prize_money' => 'sometimes|integer',
            'is_active' => ['sometimes', Rule::in([0, 1])],
        ]);

        // If validation fails, return the error response
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $quiz->update($validated);

        return $this->successResponse($quiz, "Quiz has been updated", 200);
    }

    public function destroy(Quiz $quiz)
    {
        $quiz->delete();
        return $this->successResponse(null, "Quiz has been deleted", 200);
    }

    // Create a UserResponseResource
    public function responseList(Request $request)
    {
        $user = Auth::user();
        $page = $request->input('page', 1);
        $limit = Config::get('himpri.constant.dashboardPaginationLimit'); 
        $offset = ($page - 1) * $limit; 
        $responsesQuery = UserResponse::with('quiz')
            ->where('user_id', $user->id)
            ->orderByDesc('id');
        $totalCount = $responsesQuery->count();
        $responses = $responsesQuery->limit($limit)->offset($offset)->get();
            
        return $this->successResponse([
            'totalCount' => $totalCount,
            'responses' => UserResponseResource::collection($responses)
        ], 
            "Responses has been fetched", 
            200
        );
    }

    public function quizList(Request $request)
    {
        $page = $request->input('page', 1);
        $categoryId = $request->input('category', '');
        $limit = Config::get('himpri.constant.adminPaginationLimit'); 
        $offset = ($page - 1) * $limit; 
        $quizQuery = Quiz::with('category')->orderByDesc('id');
        $totalCount = $quizQuery->count();
        // Apply category filter if provided
        if (!empty($categoryId)) {
            $quizQuery->where('category_id', $categoryId);
        }
        $quizzes = $quizQuery->limit($limit)->offset($offset)->get()->makeHidden('quizContents');
        return $this->successResponse([
            "quizzes" => $quizzes, 
            "totalCount" => $totalCount], 
            "Quizzes has been fetched", 200);
    }

    public function quizByNodeId(Request $request){
        $nodeId = $request->node_id;
        $quiz = Quiz::with([
            'user_responses.user',
            'user_responses.lifeline_usages.lifeline'
        ])->where('node_id', $nodeId)->first();

        $quiz->user_responses->transform(function ($userResponse){
            $lifelineMap = $userResponse->lifeline_usages->keyBy('question_id');

            $userResponse->responseContents = collect($userResponse->responseContents)->map(function ($response) use($lifelineMap){
                $questionId = $response['question_id'];

                $lifeline = $lifelineMap->get($questionId);

                $response['lifeline_used'] = $lifeline ? [
                    'name' => $lifeline->lifeline->name
                ] : null;

                return $response;
            })->toArray();

            return $userResponse;
        });

        return $this->successResponse($quiz, "Record has been fetched!", 200);
    }
}
