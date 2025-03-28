<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Quiz;
use App\Models\Lifeline;
use App\Models\LifelineUsage;
use App\Models\UserResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Traits\JsonResponseTrait;



class LifelineUsageController extends Controller
{
    use JsonResponseTrait;
    public function useLifeline(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'lifeline_id' => 'required|exists:lifelines,id',
            'node_id' => 'required|exists:quizzes,node_id',
            'question_id' => 'required'
        ]);

        // If validation fails, return the error response
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $requestedData = $validator->validated();
        $user = Auth::user();
        $quiz = Quiz::where('node_id', $request->node_id)->first();
        $quizContents = collect($quiz->quizContents);
        $this->maxQuestionCount = $quizContents->count();
        $this->nodeId = $requestedData['node_id'];
        $question = $quizContents->where('id', $requestedData['question_id']);
        $this->questionId = collect($question)->value('id');
        $this->correctAnswerId = $question->value('correctAnswerId');
        $userResponse = UserResponse::where('quiz_id', $quiz->id)
                                ->where('user_id', Auth::user()->id)
                                ->first();


 
        // check if the user has attempted the quiz
        if(empty($userResponse)){
            return $this->errorResponse([], 'Unauthorized access to Quiz', 403);
        }

        $this->userResponse = $userResponse;
        
        // Check if user has the lifeline
        $userLifeline = $user->lifelines()
            ->where('lifeline_id', $request->lifeline_id)
            ->first();
            
        if (!$userLifeline || $userLifeline->quantity <= 0) {
            return $this->errorResponse([], 'You do not have this lifeline available', 403);
        }

        if ($this->isAlreadyUsedSpecificLifeline($user->id, $request->lifeline_id, $userResponse->id)){
            return $this->errorResponse([], 'This lifeline has been used. Please try another one!', 403);
        }

        if ($this->isLifelineLimitExceeded($user->id, $request->lifeline_id, $userResponse->id)){
            return $this->errorResponse([], 'Your lifeline Limit exceed for the current quiz', 403);
        }
        
        // Check if this lifeline was already used for this question
        if ($this->hasUsedLifelineForQuestion($user->id, $request->lifeline_id, $request->question_id, $userResponse->id)) {
            return $this->errorResponse([], 'This lifeline has already been used for this question', 403);
        }

        // Process the specific lifeline
        $result = $this->processLifeline($request->lifeline_id, $question);
        if($result->original['error']){
            return $this->errorResponse([], $result->original['message'], 422);
        }

        // Record lifeline usage
        LifelineUsage::create([
            'user_id' => $user->id,
            'lifeline_id' => $request->lifeline_id,
            'user_response_id' => $userResponse->id,
            'question_id' => $request->question_id,
            'used_at' => now(),
            'result_data' => json_encode($result->original['data'])
        ]);
        
        // Decrement lifeline quantity
        $userLifeline->decrement('quantity');
        $userLifeline->update(['last_used_at' => now()]);

        return $this->successResponse($result->original['data'], $result->original['message'], 200);
    }

    // Check if lifeline was used for a specific question
    public function hasUsedLifelineForQuestion($userId, $lifelineId, $questionId, $userResponseId) {
        return LifelineUsage::where('user_id', $userId) //while its not necessary to compare because userResponseId already fetched from authUser
                        ->where('lifeline_id', $lifelineId)
                        ->where('question_id', $questionId)
                        ->where('user_response_id', $userResponseId)
                        ->exists();
    }

    // Check if specific lifeline has already been used in the current quiz
    public function isAlreadyUsedSpecificLifeline(){
        $lifelines = LifelineUsage::where('user_id', $userId)
                            ->where('lifeline_id', $lifelineId)
                            ->where('user_response_id', $userResponseId)
                            ->exists();
    }
    
    // Check if lifeline available for current quiz
    public function isLifelineLimitExceeded($userId, $lifelineId, $userResponseId){
        $lifelines = LifelineUsage::where('user_id', $userId)
                            ->where('user_response_id', $userResponseId)
                            ->get();
        if($lifelines->count() >= 3){
            return true;
        } else {
            return false;
        }
    }
    
    private function processLifeline($lifelineId, $question)
    {
        $lifeline = Lifeline::findOrFail($lifelineId);
        
        // Implement lifeline logic based on type
        switch ($lifeline->name) {
            case '50:50':
                return $this->process5050Lifeline($question);
                
            case 'Skip Question':
                return $this->processSkipQuestionLifeline($question);
                
            case 'Revive Game':
                return $this->processReviveGameLifeline($question);
                
            default:
                throw new \Exception("Unknown lifeline type: {$lifeline->name}");
        }
    }
    
    private function process5050Lifeline($question)
    {
        $correctAnswerId = $question->value('correctAnswerId');
        $options = collect($question->value('options'))->pluck('id');
        
        // Get one random incorrect option
        $incorrectOptions = $options->reject(fn($id) => $id == $correctAnswerId);
        $incorrectOptionId = $incorrectOptions->isNotEmpty() ? $incorrectOptions->random() : null;
            
        // Return IDs of options to remove
        $optionsToKeep = [$correctAnswerId, $incorrectOptionId];
        $optionsToRemove = array_diff($options->toArray(), $optionsToKeep);
         
        $data = [
            'lifeline_type' => '50:50',
            'options_to_remove' => $optionsToRemove
        ];
        return $this->successResponse($data, "2 incorrect options are removed", 200);
    }

    public function processSkipQuestionLifeline($question)
    {
        $request = new Request([
            'node_id' => $this->nodeId,
            'question_id' => $this->questionId,
            'answer_id' => $this->correctAnswerId
        ]);

        $playQuizController = new PlayQuizController();
        $result = $playQuizController->nextQuestion($request);
        // dd($result->original['message']);
        if($result->original['error']){
            return $this->errorResponse([], $result->original['message'], 422);
        }
        $result->original['data']['lifeline_type'] = "skip_question";
        return $this->successResponse($result->original['data'], "Question is Skipped", 200);
    }

    public function processReviveGameLifeline($question)
    {

        $this->userResponse->update(['status' => 'initiated']); //revive the game
        $responseContents = collect($this->userResponse->responseContents)
            ->reject(fn($item) => $item['question_id'] == $this->questionId) //remove the response question from user response
            ->values() // Reset keys
            ->all(); 

        // Update the JSON column in the database
        $this->userResponse->update([
            'responseContents' => $responseContents
        ]);

        $request = new Request([
            'node_id' => $this->nodeId,
            'question_id' => $this->questionId,
            'answer_id' => $this->correctAnswerId
        ]);

        $playQuizController = new PlayQuizController();
        $result = $playQuizController->nextQuestion($request);
        if($result->original['error']){
            return $this->errorResponse([], $result->original['message'], 422);
        }

        $result->original['data']['lifeline_type'] = "revive_game";
        return $this->successResponse($result->original['data'], "Game is Continued", 200);
    }
}
