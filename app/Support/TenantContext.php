<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Context;
use RuntimeException;

/**
 * Which business this code is acting for. Set by middleware on a request, and carried into queued jobs through
 * Laravel's Context, so a job dispatched for a business runs for that business with no session.
 *
 * Reading tenant data with no context throws rather than returning everything. Scheduled work that really does span
 * every business says so with system().
 */
final class TenantContext
{
    private const ORGANIZATION = 'tenant.organization_id';

    private const SYSTEM = 'tenant.system';

    /** Test code may read across businesses. Requests made inside a test still start strict. */
    private static bool $lenient = false;

    public static function id(): ?int
    {
        $id = Context::getHidden(self::ORGANIZATION) ?? session('current_organization_id');

        return $id === null ? null : (int) $id;
    }

    public static function lenient(bool $lenient = true): bool
    {
        [$before, self::$lenient] = [self::$lenient, $lenient];

        return $before;
    }

    public static function isLenient(): bool
    {
        return self::$lenient;
    }

    public static function isSystem(): bool
    {
        return Context::getHidden(self::SYSTEM) === true;
    }

    public static function set(?int $organizationId): void
    {
        Context::forgetHidden([self::ORGANIZATION, self::SYSTEM]);

        if ($organizationId !== null) {
            Context::addHidden(self::ORGANIZATION, $organizationId);
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public static function forOrganization(int $organizationId, Closure $work): mixed
    {
        return self::during(fn () => self::set($organizationId), $work);
    }

    /**
     * Work that spans businesses: scheduled sweeps, platform scorecards, webhooks before they know whose they are.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public static function system(Closure $work): mixed
    {
        return self::during(fn () => self::enterSystem(), $work);
    }

    public static function enterSystem(): void
    {
        Context::forgetHidden(self::ORGANIZATION);
        Context::addHidden(self::SYSTEM, true);
    }

    /** @return array{0: ?int, 1: bool} */
    public static function snapshot(): array
    {
        return [Context::getHidden(self::ORGANIZATION), self::isSystem()];
    }

    /** @param  array{0: ?int, 1: bool}  $state */
    public static function restore(array $state): void
    {
        self::set($state[0]);

        if ($state[1]) {
            Context::addHidden(self::SYSTEM, true);
        }
    }

    private static function during(Closure $enter, Closure $work): mixed
    {
        $before = self::snapshot();
        $enter();

        try {
            return $work();
        } finally {
            self::restore($before);
        }
    }

    public static function missing(string $model): RuntimeException
    {
        return new RuntimeException("{$model} was read with no business in context. Use TenantContext::forOrganization() or, for work across businesses, TenantContext::system().");
    }
}
