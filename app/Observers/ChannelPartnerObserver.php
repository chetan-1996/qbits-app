<?php

namespace App\Observers;

use App\Models\ChannelPartner;
use Cache;

class ChannelPartnerObserver
{
    public function saved(ChannelPartner $model)
    {
        Cache::tags(['channel_partners'])->flush();
    }

    public function deleted(ChannelPartner $model)
    {
        Cache::tags(['channel_partners'])->flush();
    }
}
