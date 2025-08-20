<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Traits\JsonResponseTrait;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    use JsonResponseTrait;
    public function index()
    {
        $categories = Category::where('is_active', true)->orderBy('display_order')->get();
        $categories = $categories->transform(function ($category) {
            $category->icon = $category->icon ? asset('storage/' . $category->icon) : null;
            return $category;
        });
        return $this->successResponse($categories, "Record has been founded", 200);
    }

    public function quizzesByCategory(Request $request){
        $quizzes = Category::with('quizzes');

        return $this->successResponse($quizzes, "Record has been founded", 200);
    }
    
    public function updateSorting(Request $request)
    {
        $data = $request->all();

        if (!is_array($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid data format.'
            ], 422);
        }

        foreach ($data['categories'] as $item) {
            if (isset($item['id'], $item['display_order'])) {
                Category::where('id', $item['id'])->update([
                    'display_order' => $item['display_order']
                ]);
            }
        }

        return $this->successResponse([], "Category Sorting has been updated successfully", 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'icon' => 'nullable|file|image|max:2048',
            // 'icon_color' => 'nullable|string',
            // 'banner_image' => 'nullable|file|image|max:2048'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse([], $validator->errors(), 422);
        }

        $validated = $validator->validated();

        $slug = $this->generateSlug($request->name);
        $originalSlug = $slug;
        $count = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        // Handle file uploads with the slug as the filename
        $iconPath = null;
        if ($request->hasFile('icon') && $request->file('icon')->isValid()) {
            $extension = $request->file('icon')->getClientOriginalExtension();
            $filename = $slug . time() . '.' . $extension;
            $iconPath = $request->file('icon')->storeAs('categories/icons', $filename, 'public');
        }

        // $bannerPath = null;
        // if ($request->hasFile('banner_image') && $request->file('banner_image')->isValid()) {
        //     $extension = $request->file('banner_image')->getClientOriginalExtension();
        //     $filename = $slug . '-banner.' . $extension;
        //     $bannerPath = $request->file('banner_image')->storeAs('categories/banners', $filename, 'public');
        // }

        $category = Category::create([
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'icon' => $iconPath, // Store the file path
            // 'icon_color' => $request->icon_color,
            // 'banner_image' => $bannerPath, // Store the file path
            'is_active' => $request->is_active ?? true, // Default to true if not provided
            'display_order' => 1,
        ]);
        return $this->successResponse($category, "Record has been created", 201);
    }

    private function generateSlug($name)
    {
        return Str::slug($name, '-');
    }

    public function show(Category $category)
    {
        return response()->json($category);
    }

    public function update(Request $request, Category $category)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'icon' => 'sometimes|file|image|max:2048',
            // 'icon_color' => 'nullable|string',
            // 'banner_image' => 'nullable|file|image|max:2048'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse([], $validator->errors(), 422);
        }

        $data = $validator->validated();

        // if icon is set delete existing
        if (isset($data['icon'])) {
            Storage::disk('public')->delete($category->icon);
        }

        // Handle file uploads with the slug as the filename
        $iconPath = null;
        if ($request->hasFile('icon') && $request->file('icon')->isValid()) {
            $extension = $request->file('icon')->getClientOriginalExtension();
            $filename = $category->slug . time() . '.' . $extension;
            $iconPath = $request->file('icon')->storeAs('categories/icons', $filename, 'public');
            $data['icon'] = $iconPath; // Update the data array with the new icon path
        }

        $category->update($data);

        return $this->successResponse($category, "Record has been updated", 200);
    }

    public function destroy(Category $category)
    {
        $category->update(['is_active' => false]);
        return $this->successResponse(null, "Record has been deleted", 200);
    }

    public function quizzesByCategoryId(Request $request){
        $categoryId = $request->input('category_id', '');
        if($categoryId){
            $quizzes = Quiz::with(['category', 'quiz_variants'])
                            ->where('end_time', '>', time())
                            ->where('category_id', $categoryId)
                            ->orderBy('start_time', 'ASC')
                            ->get()
                            ->makeHidden('quizContents');
            return $this->successResponse($quizzes, "Records has been founded", 200);
        }
        return $this->errorResponse([], "Invalid Category", 403);
    }
}
