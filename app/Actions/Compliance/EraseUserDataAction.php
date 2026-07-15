<?php

namespace App\Actions\Compliance;

use App\Events\UserDataErased;
use App\Models\User;
use App\Services\Governance\AuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class EraseUserDataAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /**
     * Erase personal data for a user (GDPR Article 17 — Right to Erasure).
     *
     * This performs pseudonymisation rather than hard delete to preserve:
     *  - Audit trail integrity (legal requirement)
     *  - Referential integrity (foreign keys)
     *  - Financial records (7-year retention requirement)
     *
     * @param  User  $requester  Actor requesting the erasure (must be the subject or an admin).
     * @param  User  $subject  The user whose personal data will be erased.
     *
     * @throws AuthorizationException When requester is not authorized.
     */
    public function execute(User $requester, User $subject): void
    {
        Gate::authorize('erase-own-data', [$requester, $subject]);

        // Log before erasure while we still have the email
        $originalEmail = $subject->email;

        DB::transaction(function () use ($subject) {
            $anonymisedEmail = 'erased-'.$subject->id.'@deleted.dotagents.com';
            $anonymisedName = 'Erased User #'.$subject->id;

            // Pseudonymise the user record
            $subject->update([
                'name' => $anonymisedName,
                'email' => $anonymisedEmail,
                'password' => bcrypt(str()->random(64)), // invalidates password
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'remember_token' => null,
                'profile_photo_path' => null,
                'erased_at' => now(),
            ]);

            // Delete active sessions
            DB::table('sessions')->where('user_id', $subject->id)->delete();

            // Revoke all API tokens
            $subject->tokens()->delete();

            // Anonymise notifications (may contain PII in data payload)
            $subject->notifications()->delete();
        });

        $this->auditService->logUserAction(
            event: 'compliance.data_erased',
            description: "GDPR right-to-erasure executed for user {$subject->id} (original email: {$originalEmail}) by user {$requester->id}",
            subject: $subject,
        );

        event(new UserDataErased($subject, $requester->id));

        Log::info('EraseUserDataAction: user erased', [
            'subject_id' => $subject->id,
            'requested_by' => $requester->id,
        ]);
    }
}
