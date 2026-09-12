<?php

namespace App\Models\Traits;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait LogsSafeActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('moderation')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept([
                'password',
                'remember_token',
                'pin',
                'paystack_secret_key',
                'paystack_public_key',
                'momo_number',
                'momo_account_name',
                'receipt',
                'receipt_path',
            ]);
    }
}
