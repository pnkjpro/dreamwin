<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

use App\Models\Quiz;
use App\Models\QuizVariant;
use App\Models\UserResponse;
use Illuminate\Support\Facades\Auth;


use Illuminate\Support\Facades\Validator;
use App\Traits\JsonResponseTrait;

class QuizController extends Controller
{
    use JsonResponseTrait;
    public function index()
    {
        $quizzes = Quiz::with(['category', 'quiz_variants'])->get()->makeHidden('quizContents');
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
            // 'quizContents.*.question' => 'required|string',
            // 'quizContents.*.options' => 'required|array|min:2',
            // 'quizContents.*.options.*.id' => 'required|integer',
            // 'quizContents.*.options.*.option' => 'required|string',
            // 'quizContents.*.correctAnswerId' => 'required|integer',
            'spot_limit' => 'required|integer',
            'entry_fees' => 'required|integer',
            'prize_money' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['node_id'] = $this->generateNodeId();

        $quiz = Quiz::create($data);

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
}
