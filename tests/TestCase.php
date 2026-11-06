<?php

namespace Tests;

use App\Support\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Test code reads across businesses to check what happened. HTTP and Livewire requests in a test still start with
     * no business, through ResetTenantContext, exactly as they do in production.
     */
    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::lenient();
    }
}
