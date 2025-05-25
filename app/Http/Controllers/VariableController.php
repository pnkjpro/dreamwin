<?php

namespace App\Http\Controllers;

use App\Models\Variable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Traits\JsonResponseTrait;

class VariableController extends Controller
{
    use JsonResponseTrait;
    // List all variables
    public function index()
    {
        return response()->json(Variable::all());
    }

    // Store new variable
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:variables,name',
            'value' => 'required|array',
        ]);

        $variable = Variable::create($validated);
        return response()->json($variable, 201);
    }

    // Show variable by name
    public function show($name)
    {
        $variable = Variable::where('name', $name)->firstOrFail();
        return response()->json($variable);
    }

    // Update variable by name
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'name' => 'required|exists:variables,name',
            'value' => 'required|array'
        ]);
        $validated = $validator->validated();
        $variable = Variable::where('name', $validated['name'])->firstOrFail();


        $name = $validated['name'];
        $isUpdated = $variable->update(['value' => $validated['value']]);
        if($isUpdated>0){
            return $this->successResponse([], "{$name} has been updated successfully!", 200);
        }
        return $this->errorResponse([], "Failed to update {$name}", 400);
    }

    // Delete variable by name
    public function destroy($name)
    {
        $variable = Variable::where('name', $name)->firstOrFail();
        $variable->delete();

        return response()->json(['message' => 'Variable deleted']);
    }
}