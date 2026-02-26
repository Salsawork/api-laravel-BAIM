<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Consultation;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Fee;
use App\Models\Mentor;
use App\Models\Payments;
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

                // END CONSULTATION
                $consult->update([
                    'status'=>'completed'
                ]);

                // FREE MENTOR SLOT
                Mentor::where('id',$consult->mentor_id)
                    ->lockForUpdate()
                    ->update([
                        'current_consultation_id'=>null
                    ]);

                // AMBIL DATA PAYMENT
                $payment = Payments::where('consultation_id',$consult->id)
                    ->lockForUpdate()
                    ->first();

                if(!$payment){
                    DB::commit();
                    continue;
                }

                // skip jika free / belum paid
                if($consult->payment_status != 'paid'){
                    DB::commit();
                    continue;
                }

                if($payment->refund_status == 'refunded'){
                    DB::commit();
                    continue;
                }
                // AMBIL UANG MENTOR FIX
                $mentorAmount = $payment->mentor_receive ?? 0;

                if($mentorAmount <= 0){
                    DB::commit();
                    continue;
                }

                // WALLET MENTOR
                $wallet = Wallet::where('mentor_id',$consult->mentor_id)
                    ->lockForUpdate()
                    ->first();

                if ($wallet) {

                    $wallet->increment('balance',$mentorAmount);

                    WalletTransaction::create([
                        'wallet_id'=>$wallet->id,
                        'consultation_id'=>$consult->id,
                        'transaction_amount'=>$mentorAmount,
                        'transaction_type'=>'credit',
                        'description'=>'Income consultation '.$consult->order_number
                    ]);
                }

                DB::commit();

                $this->info("Consultation {$consult->id} completed & wallet added");

            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error($e->getMessage());
            }
        }

        return Command::SUCCESS;
    }

}
