<?php

namespace App\Listeners;

use App\Events\ConsentRecorded;
use App\Events\UserDataErased;
use App\Events\UserDataExported;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogComplianceAuditEvents implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'governance';

    public int $tries = 3;

    public function handleUserDataErased(UserDataErased $event): void
    {
        Log::channel('single')->info('[Compliance] User data erased', [
            'subject_id' => $event->subject->id,
            'requested_by' => $event->requesterId,
        ]);
    }

    public function handleConsentRecorded(ConsentRecorded $event): void
    {
        Log::channel('single')->info('[Compliance] Consent recorded', [
            'user_id' => $event->user->id,
            'purpose' => $event->consentPurpose,
            'granted' => $event->granted,
            'version' => $event->version,
        ]);
    }

    public function handleUserDataExported(UserDataExported $event): void
    {
        Log::channel('single')->info('[Compliance] User data exported', [
            'subject_id' => $event->subject->id,
            'requested_by' => $event->requesterId,
        ]);
    }
}
