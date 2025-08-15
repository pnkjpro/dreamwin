<?php

namespace App\Http\Controllers;

use DB;
use App\Models\ExpertVideo;
use Illuminate\Http\Request;
use App\Models\FundTransaction;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ExpertVideoController extends Controller
{
    use JsonResponseTrait;
    public function listExpertVideos(){
        $videos = ExpertVideo::where('is_active', true)
                                ->where('is_featured', false)
                                ->where('is_deleted', false)
                                ->where('is_premium', true)->get();
        if ($videos->isEmpty()) {
            return $this->errorResponse([], 'No expert videos found!', 404);
        }
        $videos = $videos->map(function ($video) {
            return [
                'id' => $video->id,
                'title' => $video->title,
                'description' => $video->description,
                'videoUrl' => asset('storage/' . $video->video_url),
                'thumbnail' => asset('storage/' . $video->thumbnail),
                'duration' => $video->duration,
                'views' => $video->views,
                'is_active' => $video->is_active,
                'is_featured' => $video->is_featured,
                'is_premium' => $video->is_premium
            ];
        });
        return $this->successResponse($videos, 'Expert videos fetched successfully!', 200);
    }

    public function createExpertVideo(Request $request){
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:225',
            'video' => 'required|file|mimes:mp4,mov,avi|max:512000', // 500MB
            'thumbnail' => 'nullable|file|image|max:2048', // 2MB max
            'duration' => 'nullable|string|max:10'
        ]);
        if ($validator->fails()) {
            return $this->errorResponse([], $validator->errors()->first(), 422);
        }

        $data = $validator->validated();
        //store video file
        $videoPath = $request->file('video')->store('expert_videos', 'public');
        $data['videoUrl'] = $videoPath;
        //store thumbnail if provided
        if ($request->hasFile('thumbnail') && $request->file('thumbnail')->isValid()) {
            $thumbnailPath = $request->file('thumbnail')->store('expert_videos/thumbnails', 'public');
            $data['thumbnail'] = $thumbnailPath;
        } else {
            $data['thumbnail'] = null; // Set to null if no thumbnail is provided
        }

        ExpertVideo::create([
            'title' => $data['title'],
            'description' => $data['description'],
            'videoUrl' => $data['videoUrl'],
            'thumbnail' => $data['thumbnail'] ?? null,
            'duration' => $data['duration'] ?? null,
            'is_active' => true,
            'is_featured' => false,
            'is_premium' => true
        ]);

        return $this->successResponse([], 'Expert video created successfully!', 200);
    }

    public function updateExpertVideo(Request $request){
        $validator = Validator::make($request->all(), [
            'video_id' => 'required|exists:expert_videos,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:225',
            'videoUrl' => 'required|string|max:255',
            'thumbnail' => 'nullable|string|max:255',
            'duration' => 'nullable|string|max:10'
        ]);
        if ($validator->fails()) {
            return $this->errorResponse([], $validator->errors(), 422);
        }

        $data = $validator->validated();
        $video = ExpertVideo::findOrFail($data['video_id']);
        $video->update([
            'title' => $data['title'],
            'description' => $data['description'],
            'videoUrl' => $data['videoUrl'],
            'thumbnail' => $data['thumbnail'] ?? null,
            'duration' => $data['duration'] ?? null,
            'is_active' => true,
            'is_featured' => false,
            'is_premium' => true
        ]);

        return $this->successResponse([], 'Expert video updated successfully!', 200);
    }

    public function deleteExpertVideo(Request $request){
        $validator = Validator::make($request->all(), [
            'video_id' => 'required|exists:expert_videos,id'
        ]);
        if ($validator->fails()) {
            return $this->errorResponse([], $validator->errors(), 422);
        }

        $data = $validator->validated();
        $video = ExpertVideo::findOrFail($data['video_id']);
        // delete video and thumbnail from storage
        Storage::disk('public')->delete($video->videoUrl);
        Storage::disk('public')->delete($video->thumbnail);

        // Soft delete the video by marking it as deleted
        $video->update(['is_deleted' => true]);

        return $this->successResponse([], 'Expert video deleted successfully!', 200);
    }

    public function purchaseExpertVideo(Request $request){
        $validator = Validator::make($request->all(), [
            'video_id' => 'required|exists:expert_videos,id'
        ]);
        if ($validator->fails()) {
            return $this->errorResponse([], $validator->errors(), 422);
        }

        $data = $validator->validated();
        $video = ExpertVideo::findOrFail($data['video_id']);

        $user = Auth::user();
        DB::beginTransaction();
        try {
            FundTransaction::create([
                'user_id' => $user->id,
                'action' => 'expert_video_purchase',
                'amount' => -$video->price,
                'description' => 'Purchase of expert video: ' . $video->title,
                'reference_type' => ExpertVideo::class,
                'reference_id' => $video->id,
                'approved_status' => 'approved'
            ]);
    
            $user->decrement('funds', $video->price);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse([], 'Failed to purchase expert video.', 500);
        }

        return $this->successResponse([], 'Expert video purchased successfully!', 200);
    }

    public function listUserExpertVideos(Request $request)
    {
        $user = Auth::user();

        $videos = ExpertVideo::where('is_active', true)
            ->where('is_deleted', false)
            ->where('is_premium', true)
            ->get();

        $videoIds = $videos->pluck('id');

        // Get all expert videos that the user has purchased
        $purchasedVideoIds = FundTransaction::where('user_id', $user->id)
            ->where('reference_type', ExpertVideo::class)
            ->whereIn('reference_id', $videoIds)
            ->where('action', 'expert_video_purchase')
            ->where('approved_status', 'approved')
            ->pluck('reference_id');

        // map each video to include purchase information
        $videos = $videos->map(function ($video) use ($purchasedVideoIds) {
            return [
                'id' => $video->id,
                'title' => $video->title,
                'description' => $video->description,
                'videoUrl' => asset('storage/' . $video->videoUrl),
                'thumbnail' => asset('storage/' . $video->thumbnail),
                'duration' => $video->duration,
                'price' => $video->price,
                'is_active' => $video->is_active,
                'is_featured' => $video->is_featured,
                'is_premium' => $video->is_premium,
                'purchased' => $purchasedVideoIds->contains($video->id)
            ];
        });

        // include other info i.e locked_count(not purchased), unlocked_count(purchased)
        $lockedCount = $videos->where('purchased', false)->count();
        $unlockedCount = $videos->where('purchased', true)->count();

        return $this->successResponse([
            'videos' => $videos,
            'locked_count' => $lockedCount,
            'unlocked_count' => $unlockedCount
        ], 'User expert videos retrieved successfully!', 200);
    }

}
