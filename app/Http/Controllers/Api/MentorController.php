<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
            'ktp_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',


            'upload_resume' => 'nullable|file|mimes:pdf,doc,docx|max:2048',
    
            'bank_id' => 'required|exists:mst_bank,id_bank',
            'bank_account' => 'required|string|max:50',
            'bank_holder_name' => 'required|string|max:150',
    
            'topics' => 'required|array|min:1',
            'topics.*' => 'exists:topic_categories,id',
    
            'services' => 'required|array|min:1',
            'services.*.service_type_id' => 'required|exists:service_types,id',
            'services.*.duration_minutes' => 'required|integer|min:30'
        ]);
    
        $user = auth()->user();
    
        // CEK SUDAH JADI MENTOR
        $exists = Mentor::where('user_id', $user->id)->first();
        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'User already registered as mentor'
            ], 409);
        }
    
        // VALIDASI KHUSUS USER TYPE
        // jika MUTHOWIF → hanya chat service
        if($request->user_type_id == 1){
            foreach($request->services as $srv){
                if($srv['service_type_id'] != 1){
                    return response()->json([
                        'status'=>false,
                        'message'=>'Muthowif hanya boleh layanan chat'
                    ],422);
                }
            }
        }
    
        // jika KONSULTAN price wajib diisi
        if($request->user_type_id == 2){
            foreach($request->services as $srv){
    
                if(!isset($srv['price'])){
                    return response()->json([
                        'status'=>false,
                        'message'=>'Harga wajib diisi untuk konsultan'
                    ],422);
                }
    
                if($srv['price'] < 1000){
                    return response()->json([
                        'status'=>false,
                        'message'=>'Harga minimal 1000'
                    ],422);
                }
            }
        }
    
        DB::beginTransaction();
    
        try {
    
            $baseUploadPath = dirname(base_path()) . '/public_html/api-baim.baitullah.co.id/uploads';
            
            // UPLOAD KTP
            $ktpPath = null;
            
            if ($request->hasFile('ktp_photo')) {
            
                $ktpFolder = $baseUploadPath . '/ktp';
            
                if (!file_exists($ktpFolder)) {
                    mkdir($ktpFolder, 0775, true);
                }
            
                $file = $request->file('ktp_photo');
                $filename = 'ktp_'.$user->id.'_'.time().'.'.$file->getClientOriginalExtension();
            
                $file->move($ktpFolder, $filename);
            
                $ktpPath = $filename;
            }
            
            // UPLOAD RESUME
            $resumePath = null;
            
            if ($request->hasFile('upload_resume')) {
            
                $resumeFolder = $baseUploadPath . '/resume';
            
                if (!file_exists($resumeFolder)) {
                    mkdir($resumeFolder, 0775, true);
                }
            
                $resumeFile = $request->file('upload_resume');
                $resumeFilename = 'resume_'.$user->id.'_'.time().'.'.$resumeFile->getClientOriginalExtension();
            
                $resumeFile->move($resumeFolder, $resumeFilename);
            
                $resumePath = $resumeFilename;
            }
                
            // CREATE MENTOR
            $mentor = Mentor::create([
                'user_id' => $user->id,
                'user_type_id' => $request->user_type_id,
                'full_name' => $request->full_name,
                'age' => $request->age,
                'experience_years' => $request->experience_years,
                'description' => $request->description,
                'ktp_photo' => $ktpPath,

                'upload_resume' => $resumePath,

                'bank_id' => $request->bank_id,
                'bank_account' => $request->bank_account,
                'bank_holder_name' => $request->bank_holder_name,
                'is_verified' => 0,
                'is_online' => 0,
                'rating_avg' => 0,
                'total_sessions' => 0
            ]);
    
            // INSERT TOPICS
            foreach ($request->topics as $topicId) {
                MentorTopic::create([
                    'mentor_id' => $mentor->id,
                    'topic_category_id' => $topicId
                ]);
            }
    
            foreach ($request->services as $service) {
    
                // prevent duplicate service
                $existsService = MentorService::where('mentor_id',$mentor->id)
                    ->where('service_type_id',$service['service_type_id'])
                    ->exists();
    
                if($existsService){
                    continue;
                }
    
                MentorService::create([
                    'mentor_id' => $mentor->id,
                    'service_type_id' => $service['service_type_id'],
                    'price' => $service['price'] ?? 0, // muthowif boleh 0
                    'duration_minutes' => $service['duration_minutes']
                ]);
            }
    
            Wallet::create([
                'mentor_id' => $mentor->id,
                'balance' => 0
            ]);
    
            DB::commit();
    
            return response()->json([
                'status' => true,
                'message' => 'Mentor registration successful',
                'data' => [
                    'mentor_id' => $mentor->id,
                    'user_type_id' => $mentor->user_type_id,
                    'is_verified' => 0
                ]
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
    
            // Mentor tidak boleh sedang sesi aktif
            ->whereDoesntHave('consultations', function ($q) {
                $q->where(function ($q2) {
                    $q2->where('status', 'active')
                    ->orWhere(function ($q3) {
                        $q3->where('status', 'pending')
                           ->where('payment_status', 'paid');
                    });
                });
            });
    
        // SEARCH
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->whereRaw('LOWER(full_name) LIKE ?', ["%{$search}%"]);
        }
    
        // FILTER TOPIC
        if ($request->filled('topic_id')) {
            $query->whereHas('topics', function ($q) use ($request) {
                $q->where('topic_category_id', $request->topic_id);
            });
        }
    
        // FILTER SERVICE
        if ($request->filled('service_type_id')) {
            $query->whereHas('services', function ($q) use ($request) {
                $q->where('service_type_id', $request->service_type_id);
            });
        }
    
        $mentors = $query
            ->with([
                'user:id,name,email,phone,profile_photo_path',
                'services.serviceType',
                'topics.topic'
            ])
            ->orderByDesc('rating_avg')
            ->orderByDesc('total_sessions')
            ->get();
    
        return response()->json([
            'status' => true,
            'total' => $mentors->count(),
            'data' => $mentors
        ]);
    }
    
    public function getByUserType(Request $request, $userTypeId)
    {
        $query = Mentor::query()
            ->where('user_type_id', $userTypeId)
            ->where('is_verified', 1);

        // optional filter online only
        if ($request->online_only) {
            $query->where('is_online', 1);
        }

        $mentors = $query
            ->with([
                'user:id,name,email,phone,profile_photo_path',
                'services.serviceType',
                'topics.topic'
            ])
            ->orderByDesc('rating_avg')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $mentors
        ]);
    }
    
    public function detail($id)
    {
        $mentor = Mentor::with([
            'user:id,name,email,phone,profile_photo_path',
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

        // toggle off manual
        if(!$mentor->is_online){
            return response()->json([
                'success'=>false,
                'message'=>'Ustad sedang offline'
            ],403);
        }

        // auto offline karena last_seen lama
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
            'customer:id,name,email,phone,profile_photo_path',
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
    
     // get is_verified
    public function isVerified()
    {
        $userId = auth()->id();

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $mentor = Mentor::where('user_id', $userId)->first();

        // jika mentor belum dibuat
        if (!$mentor) {
            return response()->json([
                'success' => true,
                'is_verified' => false,
                'message' => 'Mentor profile not found'
            ]);
        }

        return response()->json([
            'success' => true,
            'is_verified' => (bool) $mentor->is_verified
        ]);
    }
    
    public function getAvailableSlots($mentorId, Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'duration_hours' => 'nullable|integer|min:1|max:12'
        ]);
    
        $mentor = Mentor::find($mentorId);
    
        if(!$mentor){
            return response()->json([
                'status'=>false,
                'message'=>'Mentor not found'
            ],404);
        }
    
        $schedule = Schedule::where('mentor_id',$mentor->id)
            ->whereDate('date',$request->date)
            ->where('is_active',1)
            ->first();
    
        if(!$schedule){
            return response()->json([
                'status'=>false,
                'message'=>'Tidak ada schedule di tanggal ini'
            ],404);
        }
    
        $duration = $request->duration_hours ?? 1;
    
        $scheduleStart = Carbon::parse($schedule->date.' '.$schedule->start_time);
        $scheduleEnd   = Carbon::parse($schedule->date.' '.$schedule->end_time);
    
        $slots = [];
    
        $current = clone $scheduleStart;
    
        while($current < $scheduleEnd){
    
            $slotEnd = (clone $current)->addHours($duration);
    
            // jika durasi melebihi jam schedule, stop
            if($slotEnd > $scheduleEnd){
                break;
            }
    
            // cek bentrok booking
            $conflict = Consultation::where('mentor_id',$mentor->id)
                ->where('status','active')
                ->where(function($q) use ($current,$slotEnd){
                    $q->whereBetween('scheduled_start',[$current,$slotEnd])
                    ->orWhereBetween('scheduled_end',[$current,$slotEnd])
                    ->orWhere(function($q2) use ($current,$slotEnd){
                        $q2->where('scheduled_start','<=',$current)
                           ->where('scheduled_end','>=',$slotEnd);
                    });
                })
                ->exists();
    
            if(!$conflict){
                $slots[] = $current->format('H:i');
            }
    
            $current->addHour();
        }
    
        return response()->json([
            'status'=>true,
            'mentor'=>$mentor->full_name,
            'date'=>$request->date,
            'price_per_hour'=>$schedule->price,
            'duration_requested'=>$duration,
            'slots'=>$slots
        ]);
    }
}