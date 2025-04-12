<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Traits\JsonResponseTrait;
use App\Models\HomeBanner;
use App\Models\HowVideos;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            return response()->json(['errors' => $validator->errors()], 422);
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
            'banner_image' => $data['banner_image'],
        ]);

        return response()->json([
            'message' => 'Banner updated successfully',
            'banner' => $homeBanner
        ]);
    }

    public function listBanner(){
        $banners = HomeBanner::all();
        return $this->successResponse($banners, "banners has been fetched!", 200);
    }

    public function updateHowVideos(Request $request){
        $validator = Validator::make($request->all(), [
            'video_id' => 'required|exists:how_videos,id',
            'title' => 'required|string|max:40',
            'description' => 'required|string|max:225',
            'youtube_id' => 'required|string|max:225'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();

        $video = HowVideos::findOrFail($data['video_id']);
        $video->update([
            'title' => data['title'],
            'description' => data['description'],
            'youtube_id' => data['youtube_id']
        ]);

        return response()->json([
            'message' => 'Video updated successfully',
            'banner' => $video
        ]);
    }

    public function listHowVideos(){
        $videos = HowVideos::all();
        return $this->successResponse($videos, 'videos has been fetched', 200);
    }

}
