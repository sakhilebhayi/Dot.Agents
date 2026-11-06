<?php

namespace App\Services\Tools\BuiltIn;

use App\Contracts\AgentTool;
use App\Models\Organization;
use App\Notifications\AgentMessageNotification;
use App\Services\Tools\ToolContext;
use App\Services\Tools\ToolDefinition;
use App\Services\Tools\ToolFailure;
use App\Services\Tools\ToolOutput;
use Ramsey\Uuid\Uuid;

/** Sends a person in this business a message on the platform. The recipient is checked against the run's business. */
class NotifyMember implements AgentTool
{
    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            key: 'notify_member',
            label: 'Message a team member',
            category: 'Communication',
            description: 'Sends a short message on this platform to one person in this business. Get their id from team_directory.',
            accesses: 'People in this business.',
            schema: ['type' => 'object', 'properties' => [
                'user_id' => ['type' => 'integer'],
                'subject' => ['type' => 'string', 'maxLength' => 120],
                'message' => ['type' => 'string', 'maxLength' => 2000],
            ], 'required' => ['user_id', 'subject', 'message']],
            writesState: true,
            safeToRepeat: false,
        );
    }

    public function run(ToolContext $context, array $arguments): ToolOutput
    {
        $member = Organization::findOrFail($context->organizationId())->users()->whereKey($arguments['user_id'])->first();

        if ($member === null) {
            throw new ToolFailure('That person is not in this business.');
        }

        $notification = new AgentMessageNotification($context->run->deployment->name, $arguments['subject'], $arguments['message'], $context->run->uuid);
        // Derived from the effect, so sending it twice can only ever store it once.
        $notification->id = Uuid::uuid5(Uuid::NAMESPACE_URL, $context->idempotencyKey)->toString();
        $member->notify($notification);

        return new ToolOutput("Sent to {$member->name}.");
    }
}
