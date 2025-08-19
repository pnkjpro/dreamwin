<?php

namespace App\Http\Controllers;

use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use App\Models\Winner;
use Illuminate\Support\Facades\Validator;


class WinnerController extends Controller
{
    use JsonResponseTrait;
    public function index()
    {
        $winners = Winner::limit(3)->get();
        $winners->transform(function ($winner) {
            $winner->uid = $winner->id;
            $winner->avatar = $winner->avatar ? asset('storage/' . $winner->avatar) : null;
            return $winner;
        });
        return $this->successResponse($winners, 'Winners retrieved successfully', 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
           'name' => 'required|string|max:255',
           'amount' => 'required|numeric|min:0',
           'contest' => 'required|string|max:255',
           'avatar' => 'required|file|image|max:2048'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse([], $validator->errors(), 422);
        }

        $data = $validator->validated();

        //handle image
        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('winners/avatars', 'public');
        }

        $winner = Winner::create($data);
        return $this->successResponse($winner, 'Winner created successfully', 201);
    }

    public function show($id)
    {
        $winner = Winner::find($id);
        if (!$winner) {
            return $this->errorResponse('Winner not found', 404);
        }
        return $this->successResponse($winner, 'Winner retrieved successfully', 200);
    }

    public function update(Request $request, $id)
    {
        $winner = Winner::find($id);
        if (!$winner) {
            return $this->errorResponse('Winner not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'contest' => 'required|string|max:255',
            'avatar' => 'nullable|file|image|max:2048'
        ]);
        if ($validator->fails()) {
            return $this->errorResponse([], $validator->errors(), 422);
        }

        $data = $validator->validated();
        $winner->update($data);
        return $this->successResponse($winner, 'Winner updated successfully', 200);
    }

    public function destroy($id)
    {
        $winner = Winner::find($id);
        if (!$winner) {
            return $this->errorResponse('Winner not found', 404);
        }
        $winner->delete();
        return $this->successResponse(null, 'Winner deleted successfully', 204);
    }
}
