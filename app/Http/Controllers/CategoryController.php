<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

use App\Traits\JsonResponseTrait;

class CategoryController extends Controller
{
    use JsonResponseTrait;
    public function index()
    {
        $categories = Category::orderBy('display_order', 'asc')->get();
        return $this->successResponse($categories, "Record has been founded", 200);
    }

    public function quizzesByCategory(Request $request){
        $quizzes = Category::with('quizzes');

        return $this->successResponse($quizzes, "Record has been founded", 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:categories,slug',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
            'icon_color' => 'nullable|string',
            'banner_image' => 'nullable|string',
            'is_active' => 'required|in:0,1',
            'display_order' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $slug = $request->slug ?: Str::slug($request->name);
        $originalSlug = $slug;
        $count = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        $category = Category::create([
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'icon' => $request->icon,
            'icon_color' => $request->icon_color,
            'banner_image' => $request->banner_image,
            'is_active' => $request->is_active,
            'display_order' => $request->display_order,
        ]);

        return $this->successResponse($category, "Record has been created", 201);
    }

    public function show(Category $category)
    {
        return response()->json($category);
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:categories,slug,' . $category->id,
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
            'icon_color' => 'nullable|string',
            'banner_image' => 'nullable|string',
            'is_active' => 'required|in:0,1',
            'display_order' => 'required|integer',
        ]);

        $slug = $request->slug ?: Str::slug($request->name);
        if ($slug !== $category->slug) {
            $originalSlug = $slug;
            $count = 1;
            while (Category::where('slug', $slug)->where('id', '!=', $category->id)->exists()) {
                $slug = $originalSlug . '-' . $count;
                $count++;
            }
        } else {
            $slug = $category->slug;
        }

        $category->update(array_merge($request->all(), ['slug' => $slug]));

        return $this->successResponse($category, "Record has been updated", 200);
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return $this->successResponse(null, "Record has been deleted", 200);
    }
}
