<?php

namespace App\Services;

use Carbon\Carbon;
use App\Services\Agora\RtcTokenBuilder;

class AgoraService
{
    public function buildToken($channelName, $uid, $expireSeconds = 3600)
    {
        $appId = config('services.agora.app_id');
        $appCertificate = config('services.agora.app_certificate');

        $currentTimestamp = Carbon::now()->timestamp;
        $privilegeExpiredTs = $currentTimestamp + $expireSeconds;

        return RtcTokenBuilder::buildTokenWithUid(
            $appId,
            $appCertificate,
            $channelName,
            $uid,
            RtcTokenBuilder::RolePublisher,
            $privilegeExpiredTs
        );
    }
}
