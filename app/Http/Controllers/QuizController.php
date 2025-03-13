<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Traits\JsonResponseTrait;

class QuizController extends Controller
{
    use JsonResponseTrait;
    public function index()
    {
        return $this->successResponse(Quiz::all(), "Records has been founded", 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'node_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'banner_image' => 'nullable|string',
            'quizContents' => 'required|json',
            'spot_limit' => 'required|integer',
            'entry_fees' => 'required|integer',
            'prize_money' => 'required|integer',
            'is_active' => ['required', Rule::in([0, 1])],
        ]);

        $quiz = Quiz::create($validated);

        return $this->successResponse($quiz, "Quiz has been created", 201);
    }

    public function show(Quiz $quiz)
    {
        return response()->json($quiz, 200);
    }

    public function update(Request $request, Quiz $quiz)
    {
        $validated = $request->validate([
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

        $quiz->update($validated);

        return $this->successResponse($quiz, "Quiz has been updated", 200);
    }

    public function destroy(Quiz $quiz)
    {
        $quiz->delete();
        return $this->successResponse(null, "Quiz has been deleted", 200);
    }
}
