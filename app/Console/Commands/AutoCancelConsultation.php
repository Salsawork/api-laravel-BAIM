<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Consultation;
use App\Models\Mentor;
use DB;

class AutoCancelConsultation extends Command
{
    protected $signature = 'consult:auto-cancel';
    protected $description = 'Auto cancel consultation if not paid after 10 minutes';

    public function handle()
    {
        $now = now();
    
        $consultations = Consultation::where('payment_status','waiting')
            ->where('expired_at','<',$now)
            ->get();
    
        foreach($consultations as $consult){
    
            DB::beginTransaction();
    
            try{
    
                $consult = Consultation::lockForUpdate()->find($consult->id);
    
                if($consult->payment_status != 'waiting'){
                    DB::rollBack();
                    continue;
                }
    
                // cancel consultation
                $consult->update([
                    'status'=>'cancelled',
                    'payment_status'=>'failed'
                ]);
    
                // free mentor slot
                Mentor::where('current_consultation_id',$consult->id)
                    ->lockForUpdate()
                    ->update([
                        'current_consultation_id'=>null
                    ]);
    
                DB::commit();
    
                $this->info("Cancelled consultation ID ".$consult->id);
    
            } catch(\Exception $e){
                DB::rollBack();
                $this->error($e->getMessage());
            }
        }
    
        return Command::SUCCESS;
    }
    
}
