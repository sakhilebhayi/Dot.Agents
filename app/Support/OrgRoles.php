<?php

namespace App\Support;

/**
 * The seven roles a person can hold in a business, and what each may do. The role lives on the organisation
 * membership. This table is the only place that decides what a role means.
 */
final class OrgRoles
{
    public const OWNER = 'owner';

    public const ADMIN = 'admin';

    public const AGENT_MANAGER = 'agent_manager';

    public const DEVELOPER = 'developer';

    public const APPROVER = 'approver';

    public const OPERATOR = 'operator';

    public const VIEWER = 'viewer';

    public const ALL = [self::OWNER, self::ADMIN, self::AGENT_MANAGER, self::DEVELOPER, self::APPROVER, self::OPERATOR, self::VIEWER];

    public const LABELS = [
        self::OWNER => 'Business owner',
        self::ADMIN => 'Administrator',
        self::AGENT_MANAGER => 'Agent manager',
        self::DEVELOPER => 'Developer',
        self::APPROVER => 'Approver',
        self::OPERATOR => 'Operator',
        self::VIEWER => 'Viewer',
    ];

    public const DESCRIPTIONS = [
        self::OWNER => 'Everything, including closing the business and handing it over.',
        self::ADMIN => 'Everything except ownership: people, billing, security and every agent.',
        self::AGENT_MANAGER => 'Creates, configures, deploys and retires agents, and looks after their knowledge and memory.',
        self::DEVELOPER => 'Builds tools, workflows and API access, and configures agents.',
        self::APPROVER => 'Reviews what agents ask to do. Sees agents and their runs.',
        self::OPERATOR => 'Starts agents and works with them. Cannot change how they are set up.',
        self::VIEWER => 'Reads everything their role allows. Changes nothing.',
    ];

    /** Names used before the seven roles existed. */
    private const ALIASES = ['member' => self::OPERATOR, 'manager' => self::AGENT_MANAGER, 'editor' => self::OPERATOR];

    private const SEES_WORK = ['agents.view', 'runs.view'];

    private const BUILDS = ['agents.manage', 'workflows.manage', 'knowledge.contribute', 'tools.view', 'usage.view'];

    private const CAPABILITIES = [
        self::OWNER => ['*'],
        self::ADMIN => ['*', '!organization.own'],
        self::AGENT_MANAGER => [...self::SEES_WORK, ...self::BUILDS, 'agents.operate', 'agents.retire', 'knowledge.manage', 'memory.govern', 'audit.view'],
        self::DEVELOPER => [...self::SEES_WORK, ...self::BUILDS, 'agents.operate', 'tools.manage', 'api.manage', 'audit.view'],
        self::APPROVER => [...self::SEES_WORK, 'approvals.review'],
        self::OPERATOR => [...self::SEES_WORK, 'agents.operate', 'knowledge.contribute'],
        self::VIEWER => self::SEES_WORK,
    ];

    public static function normalise(?string $role): ?string
    {
        return $role === null ? null : (self::ALIASES[$role] ?? (in_array($role, self::ALL, true) ? $role : null));
    }

    public static function allows(?string $role, string $capability): bool
    {
        $granted = self::CAPABILITIES[self::normalise($role)] ?? [];

        if (in_array('!'.$capability, $granted, true)) {
            return false;
        }

        return in_array('*', $granted, true) || in_array($capability, $granted, true);
    }

    /** @return list<string> Capabilities shown on the members page, in plain order. */
    public static function known(): array
    {
        return ['organization.own', 'members.manage', 'billing.manage', 'security.view', 'audit.view', 'agents.view', 'agents.operate', 'agents.manage', 'agents.retire', 'workflows.manage', 'knowledge.manage', 'knowledge.contribute', 'memory.govern', 'tools.view', 'tools.manage', 'api.manage', 'approvals.review', 'runs.view', 'usage.view'];
    }
}
