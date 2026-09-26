<?php

namespace App\Helpers;

use App\Constants\StatusCodeConstants;
use App\Models\LeaveEntitlement;
use App\Models\LeavePolicy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LeaveModuleHelpers
{
    public function __construct ()
    {

    }

    public static function userLeaveEntitlementCheck (String $user_uuid): void
    {
        DB::beginTransaction();

        try {
            $current_year = Carbon::now()->format('Y');
            $leave_policies = LeavePolicy::with(['leavePolicyTiers'])->active()->get() ?? [];

            $user = User::findByUuid($user_uuid);
            $user_years_of_service = $user->employment?->joined_date ? Carbon::parse($user->employment->joined_date)->diffInYears(Carbon::now()) : 0;

            foreach ($leave_policies as $leave_policy)
            {
                $leave_policy_tier = $leave_policy->leavePolicyTiers
                    ->where('service_year_from', '<=', $user_years_of_service)
                    ->filter(function($tier) use ($user_years_of_service) {
                        return $tier->service_year_to === null || $tier->service_year_to > $user_years_of_service;
                    })->first();

                if (!$leave_policy_tier) {
                    $leave_policy_tier = $leave_policy->leavePolicyTiers->first();
                }

                $entitled_days = $leave_policy_tier?->entitlement_days ?? 0;
                $carry_forward_expiry_date = null;

                if ($leave_policy->carry_forward_expiry_month && $leave_policy->carry_forward_expiry_date) {
                        $carry_forward_expiry_month = Carbon::create($current_year + 1, $leave_policy->carry_forward_expiry_month, 1);
                        $carry_forward_expiry_date = $carry_forward_expiry_month
                            ->copy()
                            ->day(min($leave_policy->carry_forward_expiry_date, $carry_forward_expiry_month->daysInMonth))
                            ->format('Y-m-d');
                }

                $leave_entitlement = LeaveEntitlement::where([
                    'user_id'           => $user->id,
                    'leave_policy_id'   => $leave_policy->id,
                    'year'              => $current_year,
                    'is_active'         => StatusCodeConstants::ACTIVE,
                ])->first();

                // if user havent entitled with any of the leave policy, then create
                if (!$leave_entitlement) {
                    LeaveEntitlement::create([
                        'uuid'                      => (string) Str::uuid(),
                        'user_id'                   => $user->id,
                        'leave_policy_id'           => $leave_policy->id,
                        'year'                      => $current_year,
                        'entitled_days'             => $entitled_days,
                        'used_days'                 => 0,
                        'balance_days'              => $entitled_days,
                        'carried_forward_days'      => 0,
                        'carry_forward_expiry_date' => $carry_forward_expiry_date,
                        'is_active'                 => StatusCodeConstants::ACTIVE,
                        'created_by'                => Auth::user()->uuid,
                        'created_at'                => Carbon::now(),
                        'updated_by'                => Auth::user()->uuid,
                        'updated_at'                => Carbon::now(),
                    ]);
                }
            }

            DB::commit();

        } catch (\Exception $exception) {
            DB::rollback();
            throw $exception;
        }
    }
}
