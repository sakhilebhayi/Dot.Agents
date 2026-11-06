<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Takes promises off plan pages that the product has nothing behind: no SAML, white labelling, custom model hosting
 * or written service level exists here. Prices and limits are not touched.
 */
return new class extends Migration
{
    private const UNBACKED = ['SAML', 'SSO', 'White Label', 'Custom Models', 'SLA', 'Digital Immune System'];

    public function up(): void
    {
        DB::table('subscription_plans')->orderBy('id')->each(function (object $plan): void {
            $features = json_decode((string) $plan->features, true);

            if (! is_array($features)) {
                return;
            }

            DB::table('subscription_plans')->where('id', $plan->id)->update([
                'features' => json_encode(array_values(array_diff($features, self::UNBACKED))),
            ]);
        });
    }

    public function down(): void {}
};
