<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Consultation;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Fee;
use Illuminate\Support\Facades\DB;

class AutoEndConsultation extends Command
{
    protected $signature = 'consultation:auto-end';
    protected $description = 'Auto end consultation when duration finished';

    public function handle()
    {
        $now = now();
    
        // ambil yang benar2 harus di end
        $consultations = Consultation::where('status','active')
            ->whereNotNull('started_at')
            ->whereRaw('DATE_ADD(started_at, INTERVAL duration_minutes MINUTE) <= ?', [$now])
            ->get();
    
        foreach ($consultations as $consult) {
    
            DB::beginTransaction();
    
            try {
    
                // lock row consultation
                $consult = Consultation::lockForUpdate()->find($consult->id);
    
                if ($consult->status != 'active') {
                    DB::rollBack();
                    continue;
                }
    
                // ================= END CONSULT
                $consult->update([
                    'status'=>'completed',
                    'ended_at'=>now()
                ]);
    
                // ================= FREE MENTOR SLOT
                \Mentor::where('id',$consult->mentor_id)
                ->lockForUpdate()
                ->update([
                    'current_consultation_id'=>null
                ]);

                // ================= HITUNG KOMISI
                $appFee = Fee::where('key_name','app_fee')->value('value') ?? 0;
                $mentorAmount = max($consult->price - $appFee, 0);
    
                // ================= WALLET MENTOR
                $wallet = Wallet::where('mentor_id',$consult->mentor_id)
                    ->lockForUpdate()
                    ->first();
    
                if ($wallet && $mentorAmount > 0) {
    
                    $wallet->increment('balance',$mentorAmount);
    
                    WalletTransaction::create([
                        'wallet_id'=>$wallet->id,
                        'consultation_id'=>$consult->id,
                        'transaction_amount'=>$mentorAmount,
                        'transaction_type'=>'credit',
                        'description'=>'Income from consultation'
                    ]);
                }
    
                DB::commit();
    
                $this->info("Consultation {$consult->id} completed");
    
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error($e->getMessage());
            }
        }
    
        return Command::SUCCESS;
    }
    
}
