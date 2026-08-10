<?php

namespace App\Services\AI;

use App\Models\AgentDeployment;
use App\Models\AgentSkill;
use App\Skills\Contracts\SkillContract;
use App\Skills\WebhookSkill;
use Illuminate\Support\Collection;

/**
 * Skill Registry Service
 *
 * The single source of truth for all agentic skill implementations.
 *
 * Responsibilities:
 *   – Auto-register all built-in PHP skill implementations on boot
 *   – Resolve a skill instance by key (checking registry first, then DB)
 *   – Enumerate skills available to or enabled on a given deployment
 */
class SkillRegistryService
{
    /** @var array<string, string>  key → fully-qualified class name */
    private array $registry = [];

    public function __construct()
    {
        SkillRegistryBootstrapper::register($this);
    }

    // ── Registration ─────────────────────────────────────

    /** Register a skill implementation for a key. Idempotent. */
    public function register(string $key, string $class): void
    {
        $this->registry[$key] = $class;
    }

    // ── Resolution ───────────────────────────────────────

    /**
     * Resolve a live skill instance by key.
     * Checks the in-memory registry first, then the DB agent_skills table.
     *
     * @throws \RuntimeException when no implementation is found
     */
    public function resolve(string $key): SkillContract
    {
        if (isset($this->registry[$key])) {
            return app($this->registry[$key]);
        }

        $skill = AgentSkill::where('key', $key)->where('is_active', true)->first();

        if ($skill && $skill->class && class_exists($skill->class)) {
            return app($skill->class);
        }

        // Org-defined custom skill -- no PHP class, executed via its own
        // webhook instead. See App\Skills\WebhookSkill.
        if ($skill && $skill->isWebhookSkill()) {
            return new WebhookSkill($skill);
        }

        throw new \RuntimeException("Skill [{$key}] not found or has no PHP implementation.");
    }

    /** Return true when a skill key has a resolvable implementation. */
    public function hasImplementation(string $key): bool
    {
        if (isset($this->registry[$key])) {
            return true;
        }

        $skill = AgentSkill::where('key', $key)->where('is_active', true)->first();

        return (bool) $skill?->hasImplementation();
    }

    // ── Deployment-scoped queries ─────────────────────────

    /**
     * Return all AgentSkill models assigned and enabled for a deployment.
     */
    public function getEnabledSkills(AgentDeployment $deployment): Collection
    {
        return $deployment->skillAssignments()
            ->where('is_enabled', true)
            ->with('skill')
            ->get()
            ->pluck('skill')
            ->filter();
    }

    /**
     * Return true when a specific skill is enabled on a deployment.
     */
    public function deploymentHasSkill(AgentDeployment $deployment, string $key): bool
    {
        return $deployment->skillAssignments()
            ->where('is_enabled', true)
            ->whereHas('skill', fn ($q) => $q->where('key', $key))
            ->exists();
    }

    /**
     * Return all registered skill keys (built-in only).
     */
    public function registeredKeys(): array
    {
        return array_keys($this->registry);
    }
}
