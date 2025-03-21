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
            'userResponseId' => 'required|exists:user_responses,id',
            'question_id' => 'required'
        ]);

        // If validation fails, return the error response
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $user = User::findOrFail(1);
        $attempt = UserResponse::findOrFail($request->userResponseId);
        $questionId = $request->question_id;
        $quiz = Quiz::where('node_id', $request->node_id)->first();
        $quizContents = collect($quiz->quizContents);
        $this->maxQuestionCount = $quizContents->count();
        $question = $quizContents->where('id', $questionId);
        
        // Ensure the attempt belongs to the user
        if ($attempt->user_id !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access to quiz attempt'
            ], 403);
        }
        
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
        // dd($user->id, $request->lifeline_id, $attempt->id, $request->question_id);
        
        // Record lifeline usage
        LifelineUsage::create([
            'user_id' => $user->id,
            'lifeline_id' => $request->lifeline_id,
            'user_response_id' => $attempt->id,
            'question_id' => $request->question_id,
            'used_at' => now(),
            'result_data' => json_encode($result)
        ]);
        
        // Decrement lifeline quantity
        $userLifeline->decrement('quantity');
        $userLifeline->update(['last_used_at' => now()]);
        
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
                
            case 'Extra Time':
                return $this->processExtraTimeLifeline($question);
                
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
    
    private function processSkipQuestionLifeline($question)
    {
        if($this->maxQuestionCount == $question->value('id')){
            return [
                'lifeline_type' => 'Skip Question',
                'status' => 'quiz_end',
                'message' => 'This was the last question'
            ];
        }
        
        return [
            'lifeline_type' => 'Skip Question',
            'status' => 'success',
            'next_question' => 'Question Skipped and Proceeded to next question'
        ];
    }
}
