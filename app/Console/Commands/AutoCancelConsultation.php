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
            ->lockForUpdate()
            ->get();

        foreach($consultations as $consult){

            DB::transaction(function() use ($consult){

                // update consultation
                $consult->update([
                    'status'=>'cancelled',
                    'payment_status'=>'failed'
                ]);

                // update mentor
                Mentor::where('current_consultation_id',$consult->id)
                    ->update([
                        'current_consultation_id'=>null
                    ]);
            });

            $this->info("Cancelled consultation ID ".$consult->id);
        }

        return 0;
    }
}
