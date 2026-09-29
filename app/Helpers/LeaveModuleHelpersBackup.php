<?php

namespace App\Helpers;

use App\Constants\StatusCodeConstants;
use App\Models\LeaveEntitlement;
use App\Models\LeaveEntitlementLog;
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
        $days_per_year = Carbon::now()->endOfYear()->dayOfYear ?? 365;
        
        $current_year = Carbon::now()->format('Y');
        $leave_policies = LeavePolicy::with(['leavePolicyTiers'])->active()->get() ?? [];
        
        $user = User::findByUuid($user_uuid);
        $user_years_of_service = $user->employment?->joined_date ? Carbon::parse($user->employment->joined_date)->diffInYears(Carbon::now()) : 0;

        $accrual_start = Carbon::create($current_year, 1, 1);

        DB::beginTransaction();

        try {
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
                    $leave_entitlement = LeaveEntitlement::create([
                        'uuid'                      => (string) Str::uuid(),
                        'user_id'                   => $user->id,
                        'leave_policy_id'           => $leave_policy->id,
                        'year'                      => $current_year,
                        'entitled_days'             => $entitled_days,
                        'used_days'                 => 0,
                        'balance_days'              => $leave_policy->is_prorated ? 0 : $entitled_days,
                        'carried_forward_days'      => 0,
                        'carry_forward_expiry_date' => $carry_forward_expiry_date,
                        'is_active'                 => StatusCodeConstants::ACTIVE,
                        'created_by'                => Auth::user()->uuid,
                        'created_at'                => Carbon::now(),
                        'updated_by'                => Auth::user()->uuid,
                        'updated_at'                => Carbon::now(),
                    ]);
                }

                $leave_entitlement_logs = LeaveEntitlementLog::where([
                    'leave_entitlement_id' => $leave_entitlement->id,
                    'is_active'            => StatusCodeConstants::ACTIVE,
                ]);

                $leave_entitlement_log_count = $leave_entitlement_logs->count();
                $missing_entitlement_logs = $leave_entitlement->entitled_days - $leave_entitlement_log_count;

                if ($missing_entitlement_logs > 0) {

                    $accrual_start = Carbon::parse($user->employment->joined_date);

                    if ($accrual_start->year < $current_year) {
                        $accrual_start = Carbon::create($current_year, 1, 1);
                    }

                    for ($i = 1; $i <= $missing_entitlement_logs; $i++) {
                        $assigned_at = Carbon::now();
                        $available_at = $assigned_at->toDateString();

                        if ($leave_policy->is_prorated && $leave_entitlement->entitled_days > 0) {

                            $prorated_days = $days_per_year / $leave_entitlement->entitled_days;
                            $sequence = $leave_entitlement_log_count + $i;

                            $available_at = $accrual_start
                                ->copy()
                                ->addDays(ceil($prorated_days * $sequence));

                            if ($available_at->year > $current_year) {
                                break;
                            }

                            $available_at = $available_at->toDateString();
                        }

                        LeaveEntitlementLog::create([
                            'uuid'                  => (string) Str::uuid(),
                            'leave_entitlement_id'  => $leave_entitlement->id,
                            'assigned_days'         => 1,
                            'used_days'             => 0,
                            'assigned_at'           => $assigned_at,
                            'available_at'          => $available_at,
                            'expired_at'            => null,
                            'is_carry_forward'      => 0,
                            'is_prorated'           => $leave_policy->is_prorated ? 1 : 0,
                            'is_active'             => StatusCodeConstants::ACTIVE,
                            'is_manual'             => 0,
                            'created_by'            => Auth::user()->uuid,
                            'created_at'            => Carbon::now(),
                            'updated_by'            => Auth::user()->uuid,
                            'updated_at'            => Carbon::now(),
                        ]);
                    }
                }
            }

            DB::commit();

        } catch (\Exception $exception) {
            DB::rollback();
            throw $exception;
        }
    }
}
