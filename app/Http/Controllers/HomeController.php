<?php

namespace App\Http\Controllers;

use App\Models\ExpertVideo;
use App\Models\User;
use App\Models\Variable;
use App\Models\HowVideos;
use App\Models\HomeBanner;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\FeaturedVideo;
use App\Models\FundTransaction;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class HomeController extends Controller
{
    use JsonResponseTrait;
    public function updateBanner(Request $request) {
        $validator = Validator::make($request->all(), [
            'banner_id' => 'required|exists:home_banners,id',
            'title' => 'required|string|max:225',
            'banner_image' => 'required|file|image|max:2048'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse([], $validator->errors(), 422);
        }

        $data = $validator->validated();

        $homeBanner = HomeBanner::findOrFail($data['banner_id']);

        // Optional: Delete old image
        if ($homeBanner->banner_image && Storage::disk('public')->exists($homeBanner->banner_image)) {
            Storage::disk('public')->delete($homeBanner->banner_image);
        }

        if ($request->hasFile('banner_image') && $request->file('banner_image')->isValid()) {
            $extension = $request->file('banner_image')->getClientOriginalExtension();
            $filename = now()->format('YmdHis') . '-banner.' . $extension;
            $bannerPath = $request->file('banner_image')->storeAs('home/banners', $filename, 'public');
            $data['banner_image'] = $bannerPath;
        }

        $homeBanner->update([
            'title' => $data['title'],
            'banner_path' => $data['banner_image'],
        ]);

        return $this->successResponse([], 'Banner updated successfully!', 200);
    }

    public function listBanner(){
        $official_notice = Variable::where('name', 'official_notice')->firstOrFail();
        $banners = HomeBanner::all();
        return $this->successResponse([
            'banners' => $banners,
            'official_notice' => $official_notice['value']['official_notice'],
            'official_notice_status' => Config::get('himpri.constant.official_notice_status'),
        ], "banners has been fetched!", 200);
    }

    public function listFeaturedVideos(){
        $videos = FeaturedVideo::where('is_active', true)
                                ->where('is_featured', true)
                                ->where('is_premium', false)
                                ->get();
        return $this->successResponse($videos, 'Featured videos fetched successfully!', 200);
    }

    public function createFeaturedVideo(Request $request){
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:225',
            'youtubeUrl' => 'required|string|max:225',
            'thumbnail' => 'nullable|string|max:255',
            'duration' => 'nullable|string|max:10',
            'views' => 'nullable|string|max:10'
        ]);
        if ($validator->fails()) {
            return $this->errorResponse([], $validator->errors(), 422);
        }

        $data = $validator->validated();
        FeaturedVideo::create([
            'title' => $data['title'],
            'description' => $data['description'],
            'youtube_id' => $data['youtubeUrl'],
            'thumbnail' => $data['thumbnail'] ?? null,
            'duration' => $data['duration'] ?? null,
            'views' => $data['views'] ?? null,
            'is_active' => true,
            'is_featured' => false,
            'is_premium' => false
        ]);

        return $this->successResponse([], 'Video created successfully!', 200);
    }

    public function updateFeaturedVideo(Request $request){
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:featured_videos,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:225',
            'youtubeUrl' => 'required|string|max:225',
            'thumbnail' => 'nullable|string|max:255',
            'duration' => 'nullable|string|max:10',
            'views' => 'nullable|string|max:10'
        ]);
        if ($validator->fails()) {
            return $this->errorResponse([], $validator->errors(), 422);
        }

        $data = $validator->validated();
        $video = FeaturedVideo::findOrFail($data['id']);
        $video->update([
            'title' => $data['title'],
            'description' => $data['description'],
            'youtubeUrl' => $data['youtubeUrl'],
            'thumbnail' => $data['thumbnail'] ?? null,
            'duration' => $data['duration'] ?? null,
            'views' => $data['views'] ?? null,
            'is_active' => true,
            'is_featured' => true,
            'is_premium' => false
        ]);

        return $this->successResponse([], 'Video updated successfully!', 200);
    }

    public function deleteFeaturedVideo(Request $request){
        $validator = Validator::make($request->all(), [
            'video_id' => 'required|exists:featured_videos,id'
        ]);
        if ($validator->fails()) {
            return $this->errorResponse([], $validator->errors(), 422);
        }

        $data = $validator->validated();
        $video = FeaturedVideo::findOrFail($data['video_id']);
        $video->delete();

        return $this->successResponse([], 'Video deleted successfully!', 200);
    }

    public function updateHowVideos(Request $request){
        $validator = Validator::make($request->all(), [
            'video_id' => 'required|exists:how_videos,id',
            'title' => 'required|string|max:40',
            'description' => 'required|string|max:225',
            'youtube_id' => 'required|string|max:225'
        ]);
        if ($validator->fails()) {
            return $this->errorResponse([], $validator->errors(), 422);
        }
        $data = $validator->validated();

        $video = HowVideos::findOrFail($data['video_id']);
        $video->update([
            'title' => $data['title'],
            'description' => $data['description'],
            'youtube_id' => $data['youtube_id']
        ]);

        return $this->successResponse([], 'Video updated successfully!', 200);
    }

    public function listHowVideos(){
        $videos = HowVideos::all();
        return $this->successResponse($videos, 'videos has been fetched', 200);
    }
}
