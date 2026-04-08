<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\PlanPrice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use TCG\Voyager\Models\Role;
use TCG\Voyager\Models\User;

class PlanSeeder extends Seeder
{
    /**
     * Auto generated seed file.
     *
     * @return void
     */
    public function run()
    {
        $plans = [
            // Free
            [
                'category'    => 'chat',
                'name'        => 'Free',
                'description' => "Bénéficiez d'une assistance IA de base, gratuitement et pour toujours.",
                'order_dsp'  => 0,
                'prices' => [
                    ['billing_cycle' => 'monthly', 'price' => 0.00],
                    ['billing_cycle' => 'yearly',  'price' => 0.00],
                ],
                'features' => [
                    ['feature_key' => 'token_limit', 'feature_label' => 'Tokens / jour', 'value' => '1000'],
                ],
            ],

            // Pro
            [
                'category'    => 'chat',
                'name'        => 'Pro',
                'description' => "Pour les personnes ayant besoin d'un soutien pour des recherches et des études approfondies.",
                'order_dsp'  => 1,
                'prices' => [
                    ['billing_cycle' => 'monthly', 'price' => 20.00, 'original_price' => 20.00],
                    ['billing_cycle' => 'yearly',  'price' => 16.00, 'original_price' => 20.00], // -20%
                ],
                'features' => [
                    ['feature_key' => 'token_limit', 'feature_label' => 'Tokens / jour', 'value' => '5000'],
                ],
            ],

            // Expert
            [
                'category'    => 'chat',
                'name'        => 'Exprt',
                'description' => "Pour les personnes ayant besoin d'un soutien complet en IA.",
                'order_dsp'  => 2,
                'prices' => [
                    ['billing_cycle' => 'monthly', 'price' => 30.00, 'original_price' => 30.00],
                    ['billing_cycle' => 'yearly',  'price' => 25.00, 'original_price' => 30.00],
                ],
                'features' => [
                    ['feature_key' => 'token_limit', 'feature_label' => 'Tokens / jour', 'value' => 'unlimited'],
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $plan = Plan::create([
                'name'        => $planData['name'],
                'category'    => $planData['category'],
                'description' => $planData['description'],
                'order_dsp'   => $planData['order_dsp'],
            ]);
 
            foreach ($planData['prices'] as $priceData) {
                PlanPrice::create(array_merge(['plan_id' => $plan->id], $priceData));
            }
 
            foreach ($planData['features'] as $i => $featureData) {
                PlanFeature::create(array_merge([
                    'plan_id'        => $plan->id,
                    'order_dsp'     => $i,
                    'highlight_flg' => false,
                ], $featureData));
            }
        }
    }
}
