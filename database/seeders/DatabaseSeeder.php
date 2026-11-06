<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // These accounts have passwords in a public repository.
        abort_if(app()->isProduction(), 500, 'Demo accounts are never seeded in production. Seed the catalogue with AgentPlatformSeeder.');

        // User::factory(10)->withPersonalTeam()->create();

        User::factory()->withPersonalTeam()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@dotagents.com',
            'password' => Hash::make('DotAgents2024!'),
        ]);

        $this->call([
            AgentPlatformSeeder::class,
            EnterpriseSkillsSeeder::class,
            ProductionDemoSeeder::class,
        ]);
    }
}
