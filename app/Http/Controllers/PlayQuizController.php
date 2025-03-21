<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Quiz;
use App\Models\QuizVariant;
use App\Models\UserResponse;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\QuizController;


use Illuminate\Support\Facades\Validator;
use App\Traits\JsonResponseTrait;

class PlayQuizController extends Controller
{
    use JsonResponseTrait;
    // Game initiated on start button
    public function play_quiz(Request $request){
        $validator = Validator::make($request->all(),[
            'node_id' => 'required|exists:quizzes,node_id',
            'variant_id' => 'required|exists:quiz_variants,id'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $requestData = $validator->validated();
        $user = Auth::user();
        $quiz = Quiz::where('node_id', $requestData['node_id'])->first();
        $quizId = $quiz->id;
        $isUserResponseExists = UserResponse::where('quiz_id', $quizId)->first();
        if(isset($isUserResponseExists)){
            return $this->errorResponse([], "Game Already Started!", 422);
        }
        $userResponse = new UserResponse();
        $userResponse->quiz_id = $quizId;
        $userResponse->user_id = $user->id;
        $userResponse->quiz_variant_id = $requestData['variant_id'];
        $userResponse->save();

        $question = $this->nextQuestion(new Request([
            'node_id' => $requestData['node_id'],
            'question_id' => 0 //initially started
        ]));

        return response()->json(['data' => $question]);

    }

    public function nextQuestion(Request $request){
        $validator = Validator::make($request->all(),[
            'node_id' => 'required|exists:quizzes,node_id'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $nodeId = $request->node_id;
        $questionId = $request->question_id;
        $answerId = $request->answer_id;
        $validatedQuestion = $this->validateQuestion($nodeId, $questionId, $answerId);
        if($validatedQuestion['is_nextQuestion']){
            $nextQuesId = $questionId+1;
            $quiz = Quiz::where('node_id', $nodeId)->first();
            $question = $quiz->quizContents->where('id', $nextQuesId)->select('id', 'question', 'options')->first();
            return $question;
        }
        return $this->errorResponse([], $validatedQuestion['message'], 422);    
    }

    /**
     * 1. qid exists in quiz or not         --done
     *      - exists: proceed
     *      - not exits: return false, "invalid question"
     * 2. qid exists in userResponse        --done
     *      - exists: error: return false, "you have already attempted this question"
     *      - not exists: proceed to check answer and save response
     *                    - incorrect: return false, "incorrect answer, quiz submitted"
     *                    - correct: save response, increase score by 1 then proceed
     * 3. if qid is equal to max length i.e to avoid going to next question     --done
     *      - qid == max_length: "last question, Quiz submitted", return false
     *      - qid < max_length: return true
     * 
     * Note: response["flag", "message", "is_nextQuestion"];
     */
    /**
     * @param int $nodeId The node identifier for the quiz
     * @param int $questionId The question being answered
     * @param int $answerId The user's selected answer
     * @return array Response with success flag, message, and navigation info
     */
    private function validateQuestion($nodeId, $questionId, $answerId){
         $quiz = Quiz::where('node_id', $nodeId)->first();
         $isQuesIdExists = $quiz->quizContents->where('id', $questionId);
         if($isQuesIdExists->isNotEmpty()){
            $userResponse = UserResponse::where('quiz_id', $quiz->id)
                                            ->where('user_id', Auth::user()->id)
                                            ->first();
            $existingResponses = $userResponse->responseContents ?? [];
            $isUserResponseExists = collect($userResponse->responseContents)->where('question_id', $questionId);
            if($isUserResponseExists->isEmpty()){
                $isCorrect = $isQuesIdExists->where('correctAnswerId', $answerId)->isNotEmpty();
                $newResponse = [
                    'question_id' => $questionId,
                    'answer_id' => 2,
                    'is_correct' => $isCorrect
                ];
                $existingResponses[] = $newResponse;
                $userResponse->update([
                    'responseContents' => $existingResponses
                ]);
                if($isCorrect){
                    $userResponse->increment('score', 1);
                    $quizQuesCount = $quiz->quizContents->count();
                    if($questionId == $quizQuesCount){
                        $userResponse->update(['status' => 'completed']);
                        return ['flag' => true, 'message' => "Last question & Correct Answer, quiz submitted", 'is_nextQuestion' => false];
                    } else {
                        return ['flag' => true, 'message' => "Correct Answer, proceed to next question", 'is_nextQuestion' => true];
                    }
                    
                } else {
                    $userResponse->update(['status' => 'completed']);
                    return ['flag' => false, 'message' => "Incorrect answer, quiz submitted", 'is_nextQuestion' => false];
                }
            } else {
                return ['flag' => false, 'message' => "You have already attempted this question", 'is_nextQuestion' => false];
            }
         }else {
            return ['flag' => false, 'message' => "Invalid Question", 'is_nextQuestion' => false];
         }
        
    }
}
