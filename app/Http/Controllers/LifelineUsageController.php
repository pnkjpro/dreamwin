<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Quiz;
use App\Models\Lifeline;
use App\Models\LifelineUsage;
use App\Models\UserResponse;
use Illuminate\Support\Facades\Validator;


class LifelineUsageController extends Controller
{
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
        $userResponse = UserResponse::where('quiz_id', $quiz->id)
                                ->where('user_id', Auth::user()->id)
                                ->first();

 
        // check if the user has attempted the quiz
        if($userResponse->isEmpty()){
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to Quiz'
            ]);
        }

        $this->userResponse = $userResponse;
        
        // Check if user has the lifeline
        $userLifeline = $user->lifelines()
            ->where('lifeline_id', $request->lifeline_id)
            ->first();
            
        if (!$userLifeline || $userLifeline->quantity <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have this lifeline available'
            ], 403);
        }
        
        // Check if this lifeline was already used for this question
        if ($this->hasUsedLifelineForQuestion($user->id, $request->lifeline_id, $request->question_id, $request->userResponseId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This lifeline has already been used for this question'
            ], 403);
        }
        // Process the specific lifeline
        $result = $this->processLifeline($request->lifeline_id, $question);
        // dd($user->id, $request->lifeline_id, $userResponse->id, $request->question_id);
        
        // Record lifeline usage
        LifelineUsage::create([
            'user_id' => $user->id,
            'lifeline_id' => $request->lifeline_id,
            'user_response_id' => $userResponse->id,
            'question_id' => $request->question_id,
            'used_at' => now(),
            'result_data' => json_encode($result)
        ]);
        
        // Decrement lifeline quantity
        // $userLifeline->decrement('quantity');
        // $userLifeline->update(['last_used_at' => now()]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Lifeline used successfully',
            'data' => $result
        ]);
    }

    // Check if lifeline was used for a specific question
    public function hasUsedLifelineForQuestion($userId, $lifelineId, $questionId, $userResponseId) {
        return LifelineUsage::where('user_id', $userId) //while its not necessary to compare because userResponseId already fetched from authUser
                        ->where('lifeline_id', $lifelineId)
                        ->where('question_id', $questionId)
                        ->where('user_response_id', $userResponseId)
                        ->exists();
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
         
        return [
            'lifeline_type' => '50:50',
            'options_to_remove' => $optionsToRemove
        ];
    }
    
    // private function processSkipQuestionLifeline($question)
    // {
    //     if($this->maxQuestionCount == $question->value('id')){
    //         return [
    //             'lifeline_type' => 'Skip Question',
    //             'status' => 'quiz_end',
    //             'message' => 'This was the last question'
    //         ];
    //     }
        
    //     return [
    //         'lifeline_type' => 'Skip Question',
    //         'status' => 'success',
    //         'next_question' => 'Question Skipped and Proceeded to next question'
    //     ];
    // }

    public function processSkipQuestionLifeline($question)
    {
        /**
         * SkipQuestion means current question is not yet submitted
         */
        $request = new Request([
            'node_id' => $this->nodeId,
            'question_id' => $question->id,
            'answer_id' => $question->correctAnswerId
        ]);

        $playQuizController = new PlayQuizController();
        $result = $playQuizController->nextQuestion($request);
        dd(result);
    }

    public function processReviveGameLifeline($question){

        /**
         * ReviveGame means current question is submitted and that's we need to update
         * userResponse's status, remove current response from responseContents
         */

        $this->userResponse->update(['status' => 'initiated']); //revive the game
        $responseContents = collect($this->userResponse->responseContents)
            ->reject(fn($item) => $item['question_id'] == $questionId)
            ->values() // Reset keys
            ->all(); 

        // Update the JSON column in the database
        $this->userResponse->update([
            'responseContents' => $responseContents
        ]);

        $request = new Request([
            'node_id' => $this->nodeId,
            'question_id' => $question->id,
            'answer_id' => $question->correctAnswerId
        ]);

        $playQuizController = new PlayQuizController();
        $result = $playQuizController->nextQuestion($request);
        dd($result);
    }
}
