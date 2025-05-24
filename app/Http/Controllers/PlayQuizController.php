<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Quiz;
use App\Models\QuizVariant;
use App\Models\UserResponse;
use App\Models\FundTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

use App\Http\Controllers\QuizController;


use Illuminate\Support\Facades\Validator;
use App\Traits\JsonResponseTrait;
use Carbon\Carbon;

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
        $isUserResponseExists = UserResponse::where('quiz_id', $quizId)
                                            ->where('user_id', $user->id)
                                            ->whereIn('status', ['initiated', 'completed'])->first();
        if(isset($isUserResponseExists)){
            return $this->errorResponse([], "Game Already Started!", 422);
        }
        $userResponseModal = UserResponse::where('user_id', $user->id)
            ->where('quiz_id', $quizId)->update([
                    'status' => 'initiated',
                    'started_at' => time(),
                    'ended_at' => time()
                ]);

        $question = $this->nextQuestion(new Request([
            'node_id' => $requestData['node_id'],
            'question_id' => 0, //initially started
            'initiate_game' => true
        ]));

        return $this->successResponse($question->original['data'], "Game Initiated Successfully!", 200);

    }

    public function nextQuestion(Request $request){
        $validator = Validator::make($request->all(),[
            'node_id' => 'required|exists:quizzes,node_id'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $initiate_game = isset($request['initiate_game']) ? $request['initiate_game'] : false;
        $nodeId = $request->node_id;
        $questionId = $request->question_id;
        $answerId = $request->answer_id;
        $validatedQuestion = $this->validateQuestion($nodeId, $questionId, $answerId);
        if($validatedQuestion['is_nextQuestion'] || $initiate_game){
            $nextQuesId = $questionId+1;
            $quiz = Quiz::where('node_id', $nodeId)->first();
            $question = $quiz->quizContents->where('id', $nextQuesId)->select('id', 'question', 'options')->first();
            $participated = UserResponse::where('node_id', $nodeId)->count();
            $question['participated'] = $participated;
            return $this->successResponse($question, "Question Retrieved Successfully", 200);
        }
        if($validatedQuestion['flag']){
            return $this->successResponse($validatedQuestion, $validatedQuestion['message'], 200);
        }
        return $this->errorResponse($validatedQuestion, $validatedQuestion['message'], 422);    
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
                    'answer_id' => $answerId,
                    'is_correct' => $isCorrect
                ];
                $existingResponses[] = $newResponse;
                $userResponse->update([
                    'responseContents' => $existingResponses,
                    'ended_at' => time()
                ]);
                $maxQuestionCount = $quiz->quizContents->count();
                if($isCorrect){
                    $userResponse->increment('score', 1);
                    if($questionId == $maxQuestionCount){
                        $userResponse->update(['status' => 'completed']);
                        return ['flag' => true, 'message' => "Last question & Correct Answer, quiz submitted", 'is_nextQuestion' => false];
                    } else {
                        return ['flag' => true, 'message' => "Correct Answer, proceed to next question", 'is_nextQuestion' => true];
                    }
                    
                } else {
                    // In free game, we're not submitting quiz, user will continue to play.
                    if($quiz->entry_fees == 0){
                        if($questionId == $maxQuestionCount){
                            $userResponse->update(['status' => 'completed']);
                            return ['flag' => true, 'message' => "Last question & Incorrect Answer, quiz submitted", 'is_nextQuestion' => false];
                        }
                        return ['flag' => false, 'message' => "Incorrect answer", 'is_nextQuestion' => true];
                    }
                    // ===================================================================
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

    public function join_quiz(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'node_id' => 'required|exists:quizzes,node_id',
            'variant_id' => 'required|exists:quiz_variants,id'
        ]);
        if ($validator->fails()) {
            return $this->errorResponse([], $validator->errors(), 422);
        }
        
        $user = Auth::user();
        $validated = $validator->validated();
        $quiz = Quiz::where('node_id', $validated['node_id'])->first();
        $variant = QuizVariant::where('quiz_id', $quiz->id)->where('id', $validated['variant_id'])->first();

        $isSpotLimitExceeded = UserResponse::where('quiz_id', $quiz->id)
                                    ->where('quiz_variant_id', $variant->id)
                                    ->count();

        if($isSpotLimitExceeded >= $variant->slot_limit){
            return $this->errorResponse([], "Slot limit is filled completely!", 400);
        }

        $isUserResponseExists = UserResponse::where('quiz_id', $quiz->id)
                                            ->where('user_id', $user->id)
                                            ->whereIn('status', ['joined', 'initiated', 'completed'])->first();
        if(isset($isUserResponseExists)){
            return $this->errorResponse([], "You have already Joined the Game", 422);
        }
        $entryFee = $variant->entry_fee;
        // Check if user has enough funds
        if ($user->funds < $entryFee) {
            return $this->errorResponse([], 'Insufficient funds to join the game', 403);
        }

        DB::beginTransaction();
        try{
            $userResponse = new UserResponse();
            $userResponse->quiz_id = $quiz->id;
            $userResponse->user_id = $user->id;
            $userResponse->node_id = $quiz->node_id;
            $userResponse->quiz_variant_id = $variant->id;
            $userResponse->status = 'joined';
            $userResponse->save();
    
            $user->decrement('funds', $entryFee);

            // If reward has not yet been claimed, process referral reward
            if($entryFee >= 49 && $user->is_reward_given === 0 && isset($user->refer_by)){
                $result = $this->claimReferalRewardAmount($user);
                if(isset($result['error']) && $result['error']){
                    throw new \Exception($result['message']);
                }
            }
    
            // Record the transaction
            FundTransaction::create([
                'user_id' => $user->id,
                'action' => 'quiz_entry',
                'amount' => -$entryFee,
                'description' => "Made Entry of {$entryFee} for {$quiz->title} quiz",
                'reference_id' => $userResponse->id,
                'reference_type' => UserResponse::class,
                'approved_status' => 'approved'
            ]);

            DB::commit();
            return $this->successResponse([], "Entry is made successfully for {$quiz->title}!", 201);
        }catch(\Exception $e){
            DB::rollBack();
            return $this->exceptionHandler($e, $e->getMessage(), 500);
        }    
    }

    private function claimReferalRewardAmount($user){
        $referAmount = Config::get('himpri.constant.referral_reward_amount') ?? 10;
        DB::beginTransaction();
        try{
            FundTransaction::create([
                'user_id' => $user->refer_by,
                'action' => 'referred_reward',
                'amount' => $referAmount,
                'description' => "Referral Amount Credited for {$user->name}!",
                'reference_id' => $user->id,
                'reference_type' => User::class,
                'approved_status' => 'approved'
            ]);
    
            User::where('id', $user->refer_by)->increment('funds', $referAmount);
    
            // now the reward has been claimed
            $user->update(['is_reward_given' => 1]);

            DB::commit();
            return [
                'error' => false,
                'message' => 'Referral reward granted successfully.'
            ];
        } catch(\Exception $e){
            DB::rollBack();
            return [
                'error' => true,
                'message' => 'Something went wrong while processing the referral reward.'
            ];

        }
    }
}
