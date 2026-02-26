<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Review;
use App\Models\Consultation;
use App\Models\Mentor;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    // CREATE REVIEW
    public function store(Request $request)
    {
        $request->validate([
            'consultation_id'=>'required|exists:consultations,id',
            'rating'=>'required|integer|min:1|max:5',
            'comment'=>'nullable|string|max:1000'
        ]);

        $user = auth()->user();

        DB::beginTransaction();
        try {

            $consult = Consultation::where('id',$request->consultation_id)
                ->where('customer_user_id',$user->id)
                ->lockForUpdate()
                ->first();

            if(!$consult){
                return response()->json([
                    'status'=>false,
                    'message'=>'Consultation tidak ditemukan'
                ],404);
            }

            if($consult->status !== 'completed'){
                return response()->json([
                    'status'=>false,
                    'message'=>'Review hanya bisa setelah konsultasi selesai'
                ],422);
            }

            // 1 consultation = 1 review
            $already = Review::where('consultation_id',$consult->id)
                ->where('customer_user_id',$user->id)
                ->exists();

            if($already){
                return response()->json([
                    'status'=>false,
                    'message'=>'Review sudah pernah dibuat'
                ],409);
            }

            $review = Review::create([
                'customer_user_id'=>$user->id,
                'consultation_id'=>$consult->id,
                'rating'=>$request->rating,
                'comment'=>$request->comment
            ]);

            // update rating mentor
            $this->updateMentorRating($consult->mentor_id);

            DB::commit();

            return response()->json([
                'status'=>true,
                'message'=>'Review berhasil dibuat',
                'data'=>$review
            ]);

        } catch (\Exception $e){
            DB::rollBack();
            return response()->json([
                'status'=>false,
                'message'=>$e->getMessage()
            ],500);
        }
    }

    // UPDATE REVIEW
    public function update(Request $request,$id)
    {
        $request->validate([
            'rating'=>'required|integer|min:1|max:5',
            'comment'=>'nullable|string|max:1000'
        ]);

        $user = auth()->user();

        DB::beginTransaction();
        try {

            $review = Review::where('id',$id)
                ->where('customer_user_id',$user->id)
                ->lockForUpdate()
                ->first();

            if(!$review){
                return response()->json([
                    'status'=>false,
                    'message'=>'Review tidak ditemukan'
                ],404);
            }

            $review->update([
                'rating'=>$request->rating,
                'comment'=>$request->comment
            ]);

            $mentorId = $review->consultation->mentor_id;
            $this->updateMentorRating($mentorId);

            DB::commit();

            return response()->json([
                'status'=>true,
                'message'=>'Review berhasil diupdate',
                'data'=>$review
            ]);

        } catch (\Exception $e){
            DB::rollBack();
            return response()->json([
                'status'=>false,
                'message'=>$e->getMessage()
            ],500);
        }
    }

    // DELETE REVIEW
    public function destroy($id)
    {
        $user = auth()->user();

        DB::beginTransaction();
        try {

            $review = Review::where('id',$id)
                ->where('customer_user_id',$user->id)
                ->lockForUpdate()
                ->first();

            if(!$review){
                return response()->json([
                    'status'=>false,
                    'message'=>'Review tidak ditemukan'
                ],404);
            }

            $mentorId = $review->consultation->mentor_id;

            $review->delete();

            $this->updateMentorRating($mentorId);

            DB::commit();

            return response()->json([
                'status'=>true,
                'message'=>'Review berhasil dihapus'
            ]);

        } catch (\Exception $e){
            DB::rollBack();
            return response()->json([
                'status'=>false,
                'message'=>$e->getMessage()
            ],500);
        }
    }

    // LIST REVIEW PER MENTOR (PUBLIC)
    public function mentorReviews($mentorId)
    {
        $reviews = Review::with([
            'customer:id,name,email,phone,profile_photo_path',
            'consultation:id,mentor_id'
        ])
        ->whereHas('consultation', function($q) use ($mentorId){
            $q->where('mentor_id',$mentorId)
              ->where('status','completed');
        })
        ->latest()
        ->paginate(10);

        return response()->json([
            'status'=>true,
            'data'=>$reviews
        ]);
    }

    // RATING SUMMARY (AVG + BREAKDOWN)
    public function mentorRating($mentorId)
    {
        $reviews = Review::whereHas('consultation', function($q) use ($mentorId){
            $q->where('mentor_id',$mentorId);
        });

        $avg = round($reviews->avg('rating'),1);
        $total = $reviews->count();

        $breakdown = [
            5 => (clone $reviews)->where('rating',5)->count(),
            4 => (clone $reviews)->where('rating',4)->count(),
            3 => (clone $reviews)->where('rating',3)->count(),
            2 => (clone $reviews)->where('rating',2)->count(),
            1 => (clone $reviews)->where('rating',1)->count(),
        ];

        return response()->json([
            'status'=>true,
            'data'=>[
                'avg_rating'=>$avg,
                'total_review'=>$total,
                'breakdown'=>$breakdown
            ]
        ]);
    }

    // UPDATE RATING MENTOR (CORE LOGIC)
    private function updateMentorRating($mentorId)
    {
        $reviews = Review::whereHas('consultation', function($q) use ($mentorId){
            $q->where('mentor_id',$mentorId);
        });

        $avg = $reviews->avg('rating') ?? 0;
        $count = $reviews->count();

        Mentor::where('id',$mentorId)->update([
            'rating_avg'=>round($avg,1),
            'rating_count'=>$count
        ]);
    }
}