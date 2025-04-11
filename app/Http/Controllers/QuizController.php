<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

use App\Models\Quiz;
use App\Models\QuizVariant;
use App\Models\UserResponse;
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
        $limit = 2; 
        $offset = ($page - 1) * $limit; 
        $quizzes = Quiz::with(['category', 'quiz_variants'])
                        ->where('end_time', '>', time())
                        ->orderBy('start_time', 'ASC')
                        ->limit(2)
                        ->offset($offset)
                        ->get()
                        ->makeHidden('quizContents');
        return $this->successResponse($quizzes, "Records has been founded", 200);
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
        $bannerPath = null;
        if ($request->hasFile('banner_image') && $request->file('banner_image')->isValid()) {
            $extension = $request->file('banner_image')->getClientOriginalExtension();
            $filename = $data['node_id'] . '-banner.' . $extension;
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

    public function quizByNodeId(Request $request){
        $nodeId = $request->node_id;
        $question_id = $request->question_id;
        $quiz = Quiz::where('node_id', $nodeId)->first();
        $quizResponse = $quiz->quizContents->select('id','question', 'options');
        $question = $quizResponse->where('id', $question_id);

        // return $this->successResponse($quizResponse, "Record has been founded!", 200);
        return $this->successResponse($question, "Record has been founded!", 200);
    }

    public function nextQuestion(Request $request){
        $nodeId = $request->node_id;
        $question_id = $request->question_id;
        $user = Auth::user();
        $quiz = Quiz::where('node_id', $nodeId)->first();
        $question = $quiz->quizContents->where('id', $question_id)->first();

        return $question;
        
    }

    public function leaderboard(Request $request){
        $validator = Validator::make($request->all(), [
            'node_id' => 'required|exists:user_responses,node_id'
        ]);

        if($validator->fails()){
            return $this->errorResponse([], $e->getMessage(), 422);
        }

        $data = $validator->validated();
        $user = Auth::user();

        //you can make all user_responses's status to complete if now_time > quiz.quiz_end_time.

        $leaderboard = DB::table('user_responses as ur')
            ->join('users as u', function($join){
               $join->on('u.id', '=', 'ur.user_id');
            })->where('ur.node_id', $data['node_id'])
                ->selectRaw(
                    'u.name, 
                    ur.score,
                    CASE WHEN u.id = ? THEN true ELSE false END as isUser', 
                    [$user->id]
                    )
                ->limit(10)
                ->orderBy('ur.score', 'DESC')
                ->get();

        $query = UserResponse::where('node_id', $data['node_id']);
        $count = $query->count();
        $userPoints = $query->where('user_id', $user->id)->select('score')->first();
        $result = [
            'topPlayers' => $leaderboard,
            'totalParticipants' => $count,
            'userPoints' => $userPoints
        ];
        if($leaderboard->first()){
            return $this->successResponse($result, "leaderboard has been prepared", 200);
        }
        return $this->errorResponse([], "leaderboard has not prepared yet!", 403);
    }

    public function listVariant(Request $request){
        $nodeId = $request->query('node_id');
        $QuizVariants = Quiz::with('quiz_variants')->where('node_id', $nodeId)->first();
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
        $responses = UserResponse::with('quiz')
            ->where('user_id', $user->id)
            ->get();
            
        return $this->successResponse(
            UserResponseResource::collection($responses), 
            "Responses has been fetched", 
            200
        );
    }
}
