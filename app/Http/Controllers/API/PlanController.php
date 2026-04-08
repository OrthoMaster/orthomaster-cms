<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TrkPlanUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlanController extends Controller
{
    public function getPlans()
    {
        try {
            $plans = Plan::with([
                'prices'   => fn($q) => $q->where('active_flg', true)->orderBy('billing_cycle'),
                'features' => fn($q) => $q->orderBy('order_dsp'),
            ])
                ->where('active_flg', true)
                ->orderBy('order_dsp')
                ->get()
                ->map(fn(Plan $plan) => $this->formatPlan($plan));

            return response()->json([
                'success' => true,
                'data' => $plans
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['plans', 'get-all']);
            return response()->json([
                'success' => false,
            ]);
        }
    }

    public function currentSubscription(Request $request)
    {
        $user = $request->user();

        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'trialing'])
            ->with(['plan', 'planPrice'])
            ->latest('starts_at')
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatSubscription($subscription),
        ]);
    }

    public function currentUsage(Request $request)
    {
        $user = $request->user();
 
        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'trialing'])
            ->latest('starts_at')
            ->first();
 
        if (! $subscription) {
            return response()->json(['data' => null]);
        }
 
        $usage = TrkPlanUsage::query()
            ->where('user_id', $user->id)
            ->where('subscription_id', $subscription->id)
            ->where('period_date', now()->toDateString())
            ->get()
            ->map(fn (TrkPlanUsage $r) => [
                'metric'            => $r->metric,
                'used'              => $r->used,
                'limit'             => $r->limit_value === 0 ? 'unlimited' : $r->limit_value,
                'remaining_percent' => $r->remainingPercent(),
                'has_remaining'     => $r->hasRemaining(),
            ]);
 
        return response()->json([
            'data' => [
                'subscription_id' => $subscription->id,
                'period_date'     => now()->toDateString(),
                'usage'           => $usage,
            ],
        ]);
    }

    public function checkFeature(Request $request, string $featureKey)
    {
        $user = $request->user();

        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'trialing'])
            ->with('plan.features')
            ->latest('starts_at')
            ->first();

        if (!$subscription) {
            return response()->json([
                'allowed' => false,
                'reason'  => 'no_active_subscription',
            ]);
        }

        $feature = $subscription->plan->features
            ->firstWhere('feature_key', $featureKey);

        $allowed = $feature && ! in_array($feature->value, ['false', '0', '']);

        return response()->json([
            'allowed' => $allowed,
            'value'   => $feature?->value,
            'plan'    => $subscription->plan->slug,
        ]);
    }


    private function formatPlan(Plan $plan): array
    {
        return [
            'id'                   => $plan->id,
            'name'                 => $plan->name,
            'description'          => $plan->description,
            'yearly_saving_percent' => $plan->yearlySavingPercent(),
            'prices'               => $plan->prices->map(fn($p) => [
                'billing_cycle'   => $p->billing_cycle,
                'price'           => $p->price,
                'original_price'  => $p->original_price,
            ]),
            'features' => $plan->features->map(fn($f) => [
                'key'            => $f->feature_key,
                'label'          => $f->feature_label,
                'value'          => $f->value,
                'highlight_flg' => $f->highlight_flg,
            ]),
        ];
    }

    private function formatSubscription(Subscription $subscription): array
    {
        return [
            'id'             => $subscription->id,
            'status'         => $subscription->status,
            'billing_cycle'  => $subscription->planPrice->billing_cycle,
            'price'          => $subscription->price_snapshot,
            'currency'       => $subscription->currency_snapshot,
            'starts_at'      => $subscription->starts_at?->toDateString(),
            'ends_at'        => $subscription->ends_at?->toDateString(),
            'trial_ends_at'  => $subscription->trial_ends_at?->toDateString(),
            'canceled_at'    => $subscription->canceled_at?->toDateString(),
            'days_remaining' => $subscription->daysRemaining(),
            'auto_renew'     => $subscription->auto_renew,
            'plan'           => [
                'id'   => $subscription->plan->id,
                'name' => $subscription->plan->name,
            ],
        ];
    }
}
