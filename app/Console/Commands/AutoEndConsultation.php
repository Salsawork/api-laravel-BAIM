<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Consultation;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Fee;
use App\Models\Mentor;
use Illuminate\Support\Facades\DB;

class AutoEndConsultation extends Command
{
    protected $signature = 'consultation:auto-end';
    protected $description = 'Auto end consultation when duration finished';

    public function handle()
    {
        $now = now();
    
        $consultations = Consultation::where('status','active')
            ->whereNotNull('ended_at')
            ->where('ended_at','<=',$now)
            ->get();
    
        foreach ($consultations as $consult) {
    
            DB::beginTransaction();
    
            try {
    
                $consult = Consultation::lockForUpdate()->find($consult->id);
    
                if ($consult->status != 'active') {
                    DB::rollBack();
                    continue;
                }
    
                // END CONSULT
                $consult->update([
                    'status'=>'completed'
                ]);
    
                // FREE MENTOR SLOT
                Mentor::where('id',$consult->mentor_id)
                    ->lockForUpdate()
                    ->update([
                        'current_consultation_id'=>null
                    ]);
    
                // =====================
                // SKIP WALLET JIKA FREE
                // =====================
                if($consult->payment_status == 'free'){
                    DB::commit();
                    continue;
                }
    
                // KOMISI
                $appFee = Fee::where('key_name','app_fee')->value('value') ?? 0;
                $mentorAmount = max($consult->price - $appFee, 0);
    
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
    
                $this->info("Consultation {$consult->id} auto completed");
    
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error($e->getMessage());
            }
        }
    
        return Command::SUCCESS;
    }
    
    
}
