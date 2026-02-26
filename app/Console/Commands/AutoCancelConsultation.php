<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Consultation;
use Illuminate\Support\Facades\DB;

class AutoCancelConsultation extends Command
{
    protected $signature = 'consult:auto-cancel';
    protected $description = 'Auto cancel consultation if not paid after expiry';

    public function handle()
    {
        $now = now();

        $consultations = Consultation::where('status','pending')
            ->where('payment_status','waiting')
            ->whereNotNull('expired_at')
            ->where('expired_at','<=',$now)
            ->get();

        foreach($consultations as $row){

            DB::beginTransaction();

            try{

                $consult = Consultation::lockForUpdate()->find($row->id);

                if(!$consult){
                    DB::rollBack();
                    continue;
                }

                // double safety check (race condition payment)
                if(
                    $consult->status !== 'pending' ||
                    $consult->payment_status !== 'waiting'
                ){
                    DB::rollBack();
                    continue;
                }

                // cancel
                $consult->update([
                    'status'=>'cancelled',
                    'payment_status'=>'expired'
                ]);

                DB::commit();

                $this->info("Auto cancelled consultation ID {$consult->id}");

            }catch(\Throwable $e){
                DB::rollBack();
                $this->error($e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}