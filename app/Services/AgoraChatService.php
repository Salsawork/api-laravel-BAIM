<?php

namespace App\Services;

use App\Services\Agora\RtcTokenBuilder;

class AgoraChatService
{
    public function buildToken($userId)
    {
        $appId = config('services.agora.app_id');
        $certificate = config('services.agora.app_certificate');

        $expire = time() + 3600; // 1 jam

        return RtcTokenBuilder::buildTokenWithUserAccount(
            $appId,
            $certificate,
            'chat',
            (string)$userId,
            RtcTokenBuilder::RolePublisher,
            $expire
        );
    }
}
