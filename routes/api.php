<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\V1\AgentController;
use App\Http\Controllers\Api\V1\DeploymentController;
use App\Http\Controllers\Api\V1\DeploymentSkillController;
use App\Http\Controllers\Api\V1\SkillApprovalController;
use App\Http\Controllers\Api\V1\SkillCatalogController;
use App\Http\Controllers\Api\V1\SkillExecutionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public health probe — no authentication, used by load balancers and CI/CD pipelines
Route::get('/health', HealthController::class)->name('api.health');

// All API routes are versioned under /v1 with auth:sanctum + org.context; `writable` refuses
// every non-GET request from a read-only (viewer) role, whatever abilities its token carries
Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum', 'org.context', 'writable'])->group(function () {

    // Authenticated user — only safe, non-sensitive fields returned
    Route::get('/me', fn (Request $request) => response()->json(
        $request->user()->only([
            'id', 'name', 'email', 'profile_photo_url',
            'current_team_id', 'created_at',
        ])
    ))->name('me');

    // Agent marketplace catalog (read-only) — rate limited to prevent bulk enumeration
    Route::prefix('agents')->name('agents.')->middleware('throttle:120,1')->group(function () {
        Route::get('/', [AgentController::class, 'index'])->name('index');
        Route::get('/{agent}', [AgentController::class, 'show'])->name('show');
    });

    // Agent deployments (org-scoped, rate limited)
    Route::prefix('deployments')->name('deployments.')->middleware('throttle:60,1')->group(function () {
        Route::get('/', [DeploymentController::class, 'index'])->name('index');
        Route::post('/', [DeploymentController::class, 'store'])->name('store');
        Route::get('/{deployment}', [DeploymentController::class, 'show'])->name('show');
        Route::patch('/{deployment}', [DeploymentController::class, 'update'])->name('update');
        Route::post('/{deployment}/pause', [DeploymentController::class, 'pause'])->name('pause');
        Route::delete('/{deployment}', [DeploymentController::class, 'decommission'])->name('decommission');

        // Enterprise skill assignments per deployment
        Route::post('/{deployment}/skills', [DeploymentSkillController::class, 'assign'])->name('skills.assign');
        Route::patch('/{deployment}/skills/{skill}', [DeploymentSkillController::class, 'toggleSkill'])->name('skills.toggle');
        // AI execution endpoint — most restrictive limit (10 calls/min per user)
        Route::post('/{deployment}/skills/{skill}/execute', [SkillExecutionController::class, 'execute'])
            ->middleware('throttle:ai-execution')
            ->name('skills.execute');
        Route::get('/{deployment}/skills', [DeploymentSkillController::class, 'index'])->name('skills.index');
        Route::get('/{deployment}/skill-scores', [SkillExecutionController::class, 'scores'])->name('skills.scores');
    });

    // Enterprise skill catalog (read-only, filter by department)
    Route::prefix('skills')->name('skills.')->middleware('throttle:120,1')->group(function () {
        Route::get('/', [SkillCatalogController::class, 'index'])->name('index');
        Route::get('/{skill}', [SkillCatalogController::class, 'show'])->name('show');
    });

    // Skill approval workflow (org-scoped, rate limited)
    Route::prefix('skill-approvals')->name('skill-approvals.')->middleware('throttle:60,1')->group(function () {
        Route::get('/', [SkillApprovalController::class, 'index'])->name('index');
        Route::get('/{approval}', [SkillApprovalController::class, 'show'])->name('show');
        Route::post('/{approval}/approve', [SkillApprovalController::class, 'approve'])->name('approve');
        Route::post('/{approval}/reject', [SkillApprovalController::class, 'reject'])->name('reject');
    });
});
