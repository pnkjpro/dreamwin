<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Traits\JsonResponseTrait;
use App\Models\HomeBanner;
use App\Models\FundTransaction;
use App\Models\User;
use App\Models\HowVideos;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
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
        $banners = HomeBanner::all();
        return $this->successResponse([
            'banners' => $banners,
            'official_notice' => Config::get('himpri.constant.official_notice'),
            'official_notice_status' => Config::get('himpri.constant.official_notice_status'),
        ], "banners has been fetched!", 200);
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

    public function runQuery(){
        $skipUsers = [1,3,54];
        $users = User::where('email', 'not like', '%@himpri.com')
                        ->whereNotIn('id',$skipUsers)->get();
        $result = [];
        foreach($users as $user){
            $funds = (int) FundTransaction::where('user_id', $user->id)
                                            ->where('approved_status', 'approved')
                                            ->sum('amount');
            if($user->funds == $funds){
                $result['verified_funds'][] = $user ->id;
            } else {
                $adjusted_amt = $funds - $user->funds;
                // User::find($user->id)->increment('funds', $adjusted_amt);
                if($user->funds > $funds){
                    $result['mismatched_funds']['user']['over'][$user->id]['net_diff'] = $funds - $user->funds; 
                } else if($funds > $user->funds){
                    $result['mismatched_funds']['user']['under'][$user->id]['net_diff'] = $funds - $user->funds; 
                }
            }
        }
        $result['metainfo']['counts']['verified_funds'] = count($result['verified_funds']);
        $result['metainfo']['counts']['mismatched_funds']['over'] = isset($result['mismatched_funds']) ? count($result['mismatched_funds']['user']['over']) : 0;
        $result['metainfo']['counts']['mismatched_funds']['under'] = isset($result['mismatched_funds']) ? count($result['mismatched_funds']['user']['under']) : 0;  
        
        return response()->json($result);
    }
}
