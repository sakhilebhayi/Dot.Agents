<?php

namespace App\Services\Governance;

use App\Models\AgentDeployment;
use App\Models\AuditLog;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuditService
{
    public function logAgentAction(AgentDeployment $deployment, string $event, array $data = []): AuditLog
    {
        return AuditLog::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $deployment->organization_id,
            'agent_deployment_id' => $deployment->id,
            'auditable_type' => AgentDeployment::class,
            'auditable_id' => $deployment->id,
            'event' => $event,
            'event_category' => 'agent_action',
            'description' => "Agent '{$deployment->display_name}' performed: {$event}",
            'new_values' => $data,
            'ip_address' => app('request')->ip(),
            'user_agent' => app('request')->userAgent(),
            'session_id' => session()->getId(),
            'risk_level' => $this->assessRiskLevel($event, $data),
        ]);
    }

    public function logUserAction(
        string $event,
        string $description,
        array $data = [],
        mixed $subject = null,
        array $metadata = []
    ): AuditLog {
        $mergedData = array_merge($data, $metadata);

        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        $orgId = session('current_organization_id')
            ?? $currentUser?->currentOrganization()->id
            ?? (($subject && isset($subject->organization_id)) ? (int) $subject->organization_id : null);

        return AuditLog::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $orgId,
            'user_id' => Auth::id(),
            'auditable_type' => $subject ? get_class($subject) : null,
            'auditable_id' => $subject?->getKey(),
            'event' => $event,
            'event_category' => 'user_action',
            'description' => $description,
            'new_values' => $mergedData,
            'ip_address' => app('request')->ip(),
            'user_agent' => app('request')->userAgent(),
            'session_id' => session()->getId(),
            'risk_level' => $this->assessRiskLevel($event, $mergedData),
        ]);
    }

    public function logSecurityEvent(
        int $organizationId,
        string $eventType,
        string $severity,
        string $title,
        string $description,
        array $data = [],
        ?int $deploymentId = null
    ): SecurityEvent {
        $event = SecurityEvent::create([
            'organization_id' => $organizationId,
            'agent_deployment_id' => $deploymentId,
            'user_id' => Auth::id(),
            'event_type' => $eventType,
            'severity' => $severity,
            'title' => $title,
            'description' => $description,
            'event_data' => $data,
            'source_ip' => app('request')->ip(),
            'status' => 'open',
            'auto_remediated' => false,
        ]);

        // Log to application log for SIEM integration
        Log::channel('security')->{$severity === 'critical' ? 'critical' : 'warning'}(
            "[SECURITY] [{$eventType}] {$title}",
            ['event_id' => $event->id, 'data' => $data]
        );

        return $event;
    }

    public function detectPromptInjection(string $input, ?AgentDeployment $deployment = null): bool
    {
        $injectionPatterns = [
            '/ignore.{0,20}(previous|above|all|prior)\s+(instructions|rules|context)/i',
            '/you are now/i',
            '/disregard.{0,20}(training|instructions|guidelines)/i',
            '/act as.{0,30}(without|ignore|bypass)/i',
            '/\[system\]/i',
            '/\<system\>/i',
            '/###\s*system/i',
            '/roleplay as/i',
            '/pretend (you are|to be)/i',
            '/jailbreak/i',
            '/DAN mode/i',
            '/\]\]>.*<!--/i',
            '/^SYSTEM:\s/i',
        ];

        foreach ($injectionPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                if ($deployment) {
                    $this->logSecurityEvent(
                        $deployment->organization_id,
                        'prompt_injection',
                        'warning',
                        'Potential Prompt Injection Detected',
                        "Suspicious pattern detected in agent input for deployment '{$deployment->display_name}'",
                        ['pattern_matched' => $pattern, 'input_excerpt' => substr($input, 0, 200)],
                        $deployment->id
                    );
                }

                return true;
            }
        }

        return false;
    }

    public function detectPermissionAbuse(AgentDeployment $deployment, string $action): bool
    {
        $restrictedActions = $deployment->restricted_actions ?? [];

        if (in_array($action, $restrictedActions)) {
            $this->logSecurityEvent(
                $deployment->organization_id,
                'permission_abuse',
                'error',
                'Permission Violation Attempted',
                "Agent attempted to perform restricted action: {$action}",
                ['action' => $action, 'deployment_id' => $deployment->id],
                $deployment->id
            );

            return true;
        }

        return false;
    }

    private function assessRiskLevel(string $event, array $data): string
    {
        $highRiskEvents = [
            'task_failed', 'approval_rejected', 'security_violation',
            'data_access_violation', 'permission_abuse',
        ];

        $criticalEvents = [
            'prompt_injection_detected', 'data_breach_attempt',
            'unauthorized_access', 'agent_quarantined',
            'security.kill_switch', 'security.kill_switch.deployment',
        ];

        if (in_array($event, $criticalEvents)) {
            return 'critical';
        }

        if (in_array($event, $highRiskEvents)) {
            return 'high';
        }

        return 'low';
    }
}
