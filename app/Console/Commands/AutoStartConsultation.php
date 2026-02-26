<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Consultation;
use App\Models\Mentor;
use Illuminate\Support\Facades\DB;

class AutoStartConsultation extends Command
{
    protected $signature = 'consultation:auto-start';
    protected $description = 'Auto start scheduled consultation when time arrives';

    public function handle()
    {
        $now = now();

        $consults = Consultation::where('status','pending')
            ->where('payment_status','paid')
            ->whereNotNull('scheduled_start')
            ->where('scheduled_start','<=',$now)
            ->get();

        foreach($consults as $row){

            DB::beginTransaction();

            try{

                $consult = Consultation::lockForUpdate()->find($row->id);

                if(!$consult){
                    DB::rollBack();
                    continue;
                }

                // safety recheck
                if($consult->status != 'pending' || $consult->payment_status != 'paid'){
                    DB::rollBack();
                    continue;
                }

                $mentor = Mentor::lockForUpdate()->find($consult->mentor_id);
                if(!$mentor){
                    DB::rollBack();
                    continue;
                }

                // HANDLE SUDAH LEWAT TOTAL
                if($consult->scheduled_end && now()->gt($consult->scheduled_end)){
                    
                    // langsung completed
                    $consult->update([
                        'status'=>'completed',
                        'started_at'=>$consult->scheduled_start,
                        'ended_at'=>$consult->scheduled_end
                    ]);

                    DB::commit();
                    $this->info("Consultation {$consult->id} auto completed (missed)");
                    continue;
                }

                // MENTOR MASIH DIPAKAI
                if($mentor->current_consultation_id 
                    && $mentor->current_consultation_id != $consult->id){

                    // tunggu mentor free
                    DB::rollBack();
                    continue;
                }

                // START SESSION
                $consult->update([
                    'status'=>'active',
                    'started_at'=>$consult->scheduled_start,
                    'ended_at'=>$consult->scheduled_end
                ]);

                $mentor->update([
                    'current_consultation_id'=>$consult->id
                ]);

                DB::commit();

                $this->info("Consultation {$consult->id} started");

            }catch(\Throwable $e){
                DB::rollBack();
                $this->error($e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}