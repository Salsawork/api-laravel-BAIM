<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\Mentor;
use App\Models\MentorTopic;
use App\Models\MentorService;
use App\Models\Wallet;
use App\Models\Schedule;
use App\Models\Consultation;

class MentorController extends Controller
{
    public function registerMentor(Request $request)
    {
        $request->validate([
            'user_type_id' => 'required|exists:user_types,id',
            'full_name' => 'required|string|max:150',
            'age' => 'required|integer|min:18',
            'experience_years' => 'required|integer|min:0',
            'description' => 'required|string',

            'bank_id' => 'required|exists:mst_bank,id_bank',
            'bank_account' => 'required|string|max:50',
            'bank_holder_name' => 'required|string|max:150',

            'topics' => 'required|array|min:1',
            'topics.*' => 'exists:topic_categories,id',

            'services' => 'required|array|min:1',
            'services.*.service_type_id' => 'required|exists:service_types,id',
            'services.*.price' => 'required|numeric|min:1000',
            'services.*.duration_minutes' => 'required|integer|min:30'
        ]);

        $user = auth()->user();

        // CEK SUDAH JADI MENTOR ATAU BELUM
        $exists = Mentor::where('user_id', $user->id)->first();

        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'User already registered as mentor'
            ], 409);
        }

        DB::beginTransaction();

        try {

            // CREATE MENTOR
            $mentor = Mentor::create([
                'user_id' => $user->id,
                'user_type_id' => $request->user_type_id,
                'full_name' => $request->full_name,
                'age' => $request->age,
                'experience_years' => $request->experience_years,
                'description' => $request->description,
                'bank_id' => $request->bank_id,
                'bank_account' => $request->bank_account,
                'bank_holder_name' => $request->bank_holder_name,
                'is_verified' => 0,
                'is_online' => 0
            ]);

            // INSERT TOPICS
            foreach ($request->topics as $topicId) {
                MentorTopic::create([
                    'mentor_id' => $mentor->id,
                    'topic_category_id' => $topicId
                ]);
            }

            // INSERT SERVICES
            foreach ($request->services as $service) {
                MentorService::create([
                    'mentor_id' => $mentor->id,
                    'service_type_id' => $service['service_type_id'],
                    'price' => $service['price'],
                    'duration_minutes' => $service['duration_minutes']
                ]);
            }

            // CREATE WALLET
            Wallet::create([
                'mentor_id' => $mentor->id,
                'balance' => 0
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Mentor registration successful',
                'mentor_id' => $mentor->id
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Failed to register mentor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function listMentors(Request $request)
    {
        $query = Mentor::query()
        ->where('is_verified', 1)
        ->where('is_online', 1)
        ->whereNull('current_consultation_id');
    
        if ($request->filled('search')) {
            $search = strtolower($request->search);
    
            $query->whereRaw('LOWER(full_name) LIKE ?', ["%{$search}%"]);
        }
    
        if ($request->filled('topic_id')) {
            $query->whereHas('topics', function ($q) use ($request) {
                $q->where('topic_category_id', $request->topic_id);
            });
        }
    
        if ($request->filled('service_type_id')) {
            $query->whereHas('services', function ($q) use ($request) {
                $q->where('service_type_id', $request->service_type_id);
            });
        }
    
        $mentors = $query
            ->with([
                'services.serviceType',
                'topics.topic'
            ])
            ->orderByDesc('is_online') // online paling atas
            ->orderByDesc('rating_avg') // rating
            ->orderByDesc('total_sessions') // populer
            ->get();
    
        return response()->json([
            'status' => true,
            'total' => $mentors->count(),
            'data' => $mentors
        ]);
    }
    
    public function detail($id)
    {
        $mentor = Mentor::with([
            'services.serviceType',
            'topics.topic'
        ])
        ->where('id',$id)
        ->where('is_verified',1)
        ->first();
    
        if(!$mentor){
            return response()->json([
                'status'=>false,
                'message'=>'Mentor not found'
            ],404);
        }
    
        return response()->json([
            'status'=>true,
            'data'=>[
                'id'=>$mentor->id,
                'name'=>$mentor->full_name,
                'age'=>$mentor->age,
                'experience_years'=>$mentor->experience_years,
                'description'=>$mentor->description,
                'rating'=>$mentor->rating_avg,
                'total_sessions'=>$mentor->total_sessions,
                'is_online'=>$mentor->is_online,
    
                'services'=>$mentor->services->map(function($s){
                    return [
                        'service_type_id'=>$s->service_type_id,
                        'service_name'=>$s->serviceType->name ?? null,
                        'price'=>$s->price,
                        'duration'=>$s->duration_minutes
                    ];
                }),
    
                'topics'=>$mentor->topics->map(function($t){
                    return [
                        'topic_id'=>$t->topic_category_id,
                        'topic_name'=>$t->topic->name ?? null
                    ];
                })
            ]
        ]);
    }

    // Untuk Toggle on/off
    public function toggleOnline(Request $request)
    {
        $request->validate([
            'is_online' => 'required|boolean'
        ]);
    
        $mentor = Mentor::where('user_id', auth()->id())->first();
    
        // BUKAN MENTOR
        if(!$mentor){
            return response()->json([
                'success'=>false,
                'message'=>'Akun ini bukan ustad/mentor'
            ],403);
        }
    
        // BELUM VERIFIED ADMIN
        if(!$mentor->is_verified){
            return response()->json([
                'success'=>false,
                'message'=>'Akun ustad belum diverifikasi admin'
            ],403);
        }
    
        // UPDATE STATUS
        $mentor->update([
            'is_online' => $request->is_online,
            'last_seen' => $request->is_online ? now() : $mentor->last_seen
        ]);
    
        return response()->json([
            'success'=>true,
            'is_online'=>$mentor->is_online,
            'message'=>$mentor->is_online ? 'Ustad online' : 'Ustad offline'
        ]);
    }
    
    // set di UI masih aktif atau off
    public function mentorPresence()
    {
        $mentor = Mentor::where('user_id', auth()->id())->first();

        if(!$mentor){
            return response()->json([
                'success'=>false,
                'message'=>'Akun ini bukan ustad'
            ],403);
        }

        // ❌ toggle off manual
        if(!$mentor->is_online){
            return response()->json([
                'success'=>false,
                'message'=>'Ustad sedang offline'
            ],403);
        }

        // ❌ auto offline karena last_seen lama
        if($mentor->last_seen && $mentor->last_seen < now()->subSeconds(60)){
            
            $mentor->update([
                'is_online'=>0
            ]);

            return response()->json([
                'success'=>false,
                'message'=>'Session expired, ustad dianggap offline'
            ],403);
        }

        // update heartbeat
        $mentor->update([
            'last_seen'=>now()
        ]);

        return response()->json([
            'success'=>true,
            'message'=>'presence updated'
        ]);
    }

    // Consultation filter berdasarkan status
    public function mentorConsultations(Request $request)
    {
        $userId = auth()->id();
    
        $mentor = Mentor::where('user_id',$userId)->first();
    
        if (!$mentor) {
            return response()->json([
                'success'=>false,
                'message'=>'Mentor not found'
            ],404);
        }
    
        $query = Consultation::with([
                'customer:id,name,email,profile_photo_path',
                'payment:id,consultation_id,status,paid_at'
            ])
            ->where('mentor_id', $mentor->id);
    
        // FILTER BY STATUS (opsional dari frontend)
        if($request->status){
    
            if($request->status == 'active'){
                $query->where('status','active');
            }
    
            elseif($request->status == 'completed'){
                $query->where('status','completed');
            }
    
            elseif($request->status == 'waiting'){
                $query->where('payment_status','waiting');
            }
    
            elseif($request->status == 'paid'){
                $query->where('payment_status','paid');
            }
        }
    
        // default urutan
        $data = $query
            ->orderByRaw("
                CASE 
                    WHEN status='active' THEN 1
                    WHEN payment_status='paid' AND status!='completed' THEN 2
                    WHEN status='pending' THEN 3
                    WHEN status='completed' THEN 4
                    ELSE 5
                END
            ")
            ->orderByDesc('id')
            ->get();
    
        return response()->json([
            'success' => true,
            'total' => $data->count(),
            'data' => $data
        ]);
    }
    
    public function detailConsultation($id)
    {
        $userId = auth()->id();
    
        // cek mentor login
        $mentor = Mentor::where('user_id',$userId)->first();
    
        if(!$mentor){
            return response()->json([
                'success'=>false,
                'message'=>'Akun ini bukan mentor'
            ],403);
        }
    
        // ambil consultation
        $consult = Consultation::with([
            'customer:id,name,email,profile_photo_path',
            'payment:id,consultation_id,status,paid_at',
            'service:id,name',
            'topic:id,name'
        ])->find($id);
    
        // kalau id tidak ada
        if(!$consult){
            return response()->json([
                'success'=>false,
                'message'=>'Consultation tidak ditemukan'
            ],404);
        }
    
        // kalau bukan milik mentor ini
        if($consult->mentor_id != $mentor->id){
            return response()->json([
                'success'=>false,
                'message'=>'Anda tidak memiliki akses ke consultation ini'
            ],403);
        }
    
        return response()->json([
            'success'=>true,
            'data'=>$consult
        ]);
    }
    

}
