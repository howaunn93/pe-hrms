<?php

namespace App\Helpers;

use App\Constants\StatusCodeConstants;
use App\Exceptions\AppException;
use App\Models\LeaveEntitlement;
use App\Models\LeaveEntitlementLog;
use App\Models\LeavePolicy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LeaveModuleHelpers
{
    public function __construct ()
    {

    }

    public static function userLeaveEntitlementCheck (String $user_uuid): void
    {
        $user = User::findByUuid($user_uuid);

        DB::beginTransaction();

        try {

            self::assignNewLeave($user); // Assign new leave entitlement

            self::carryForwardLeave($user); // Carry forward leave

            self::updateProratedLeave($user); // Update prorated leave

            self::updateManualLeave($user); // Update leave balance

            self::updateExpiredLeave($user); // Update leave balance

            DB::commit();

        } catch (\Exception $exception) {
            DB::rollback();
            throw $exception;
        }
    }

    public static function updateManualLeave(User $user): void
    {
        $leave_entitlements = LeaveEntitlement::where([
            'user_id'   => $user->id,
            'is_active' => StatusCodeConstants::ACTIVE,
        ])->whereHas('leaveEntitlementLogs', function($query) {
            $query->where([
                'is_manual' => StatusCodeConstants::ACTIVE,
            ]);
        })
        ->lockForUpdate()
        ->get();

        foreach ($leave_entitlements as $leave_entitlement)
        {
            self::syncLeaveEntitlement($leave_entitlement);
        }
    }

    public static function syncLeaveEntitlement(LeaveEntitlement $leave_entitlement): void
    {
        $current_date = Carbon::now()->toDateString();

        $leave_entitlement_logs = LeaveEntitlementLog::where([
            'leave_entitlement_id' => $leave_entitlement->id,
            'is_active'            => StatusCodeConstants::ACTIVE,
        ])
        ->where('available_at', '<=', $current_date)
        ->where(function($query) use ($current_date) {
            $query->whereNull('expired_at')
                ->orWhere('expired_at', '>=', $current_date);
        })
        ->lockForUpdate()
        ->get();

        $used_days = $leave_entitlement_logs->sum('used_days');
        $balance_days = $leave_entitlement_logs->sum('assigned_days') - $used_days;
        $carried_forward_days = $leave_entitlement_logs
            ->where('is_carry_forward', StatusCodeConstants::ACTIVE)
            ->sum(function($leave_entitlement_log) {
                return (float) $leave_entitlement_log->assigned_days - (float) $leave_entitlement_log->used_days;
            });

        $leave_entitlement->update([
            'used_days'            => $used_days,
            'balance_days'         => $balance_days,
            'carried_forward_days' => $carried_forward_days,
            'updated_by'           => Auth::user()->uuid,
            'updated_at'           => Carbon::now(),
        ]);
    }

    public static function updateProratedLeave (User $user) :void
    {
        $current_year = Carbon::now()->format('Y');

        $leave_entitlements = LeaveEntitlement::with([
            'leavePolicy',
        ])->where([
            'user_id'   => $user->id,
            'year'      => $current_year,
            'is_active' => StatusCodeConstants::ACTIVE,
        ])
        ->whereHas('leavePolicy', function($query) {
            $query->where('is_prorated', StatusCodeConstants::ACTIVE);
        })
        ->lockForUpdate()
        ->get();

        foreach ($leave_entitlements as $leave_entitlement)
        {
            self::syncLeaveEntitlement($leave_entitlement);
        }

    }

    public static function updateExpiredLeave (User $user) :void
    {
        $current_date = Carbon::now()->toDateString();

        $leave_entitlement_logs = LeaveEntitlementLog::with([
            'leaveEntitlement',
        ])->where([
            'is_active' => StatusCodeConstants::ACTIVE,
        ])
        ->where('expired_at', '<=', $current_date)
        ->whereHas('leaveEntitlement', function($query) use ($user) {
            $query->where([
                'user_id'   => $user->id,
                'is_active' => StatusCodeConstants::ACTIVE,
            ]);
        })
        ->lockForUpdate()
        ->get();

        foreach ($leave_entitlement_logs as $leave_entitlement_log)
        {
            $leave_entitlement = $leave_entitlement_log->leaveEntitlement;

            $leave_entitlement_log->update([
                'is_active'  => StatusCodeConstants::INACTIVE,
                'updated_by' => Auth::user()->uuid,
                'updated_at' => Carbon::now(),
            ]);

            self::syncLeaveEntitlement($leave_entitlement);
        }
    }

    public static function carryForwardLeave (User $user): void
    {
        $current_year = Carbon::now()->format('Y');
        $previous_year = $current_year - 1;

        $user_past_leave_entitlements = LeaveEntitlement::with([
            'leavePolicy',
        ])->where([
            'user_id'   => $user->id,
            'year'      => $previous_year,
            'is_active' => StatusCodeConstants::ACTIVE,
        ])
        ->whereHas('leavePolicy', function($query) {
            $query->where('carry_forward_days', '>', 0);
        })
        ->get();

        foreach ($user_past_leave_entitlements as $user_past_leave_entitlement)
        {
            $leave_policy = $user_past_leave_entitlement->leavePolicy;

            if ($leave_policy) {

                $current_leave_entitlement = LeaveEntitlement::where([
                    'user_id'           => $user->id,
                    'leave_policy_id'   => $user_past_leave_entitlement->leave_policy_id,
                    'year'              => $current_year,
                    'is_active'         => StatusCodeConstants::ACTIVE,
                ])->first();

                if ($current_leave_entitlement) {

                    $previous_year_end_date = Carbon::create($previous_year, 12, 31)->toDateString();
                    $current_year_start_date = Carbon::create($current_year, 1, 1)->toDateString();
                    $user_past_leave_entitlement_logs = LeaveEntitlementLog::where([
                        'leave_entitlement_id' => $user_past_leave_entitlement->id,
                        'is_active'            => StatusCodeConstants::ACTIVE,
                    ])
                    ->where('available_at', '<=', $previous_year_end_date)
                    ->where(function($query) use ($current_year_start_date) {
                        $query->whereNull('expired_at')
                            ->orWhere('expired_at', '>=', $current_year_start_date);
                    })
                    ->get();

                    $total_carry_forward_days = min(
                        $user_past_leave_entitlement_logs->sum('assigned_days') - $user_past_leave_entitlement_logs->sum('used_days'),
                        $leave_policy->carry_forward_days
                    );

                    if ($total_carry_forward_days > 0) {

                        $carry_forward_expiry_date = null;

                        if ($leave_policy->carry_forward_expiry_month && $leave_policy->carry_forward_expiry_date) {

                            $carry_forward_expiry_month = Carbon::create(
                                $current_year,
                                $leave_policy->carry_forward_expiry_month,
                                1
                            );

                            $carry_forward_expiry_date = Carbon::create(
                                $current_year,
                                $leave_policy->carry_forward_expiry_month,
                                min(
                                    $leave_policy->carry_forward_expiry_date,
                                    $carry_forward_expiry_month->daysInMonth
                                )
                            )->toDateString();
                        }

                        $existing_carry_forward_days = LeaveEntitlementLog::where([
                            'leave_entitlement_id' => $current_leave_entitlement->id,
                            'is_carry_forward'     => StatusCodeConstants::ACTIVE,
                        ])->sum('assigned_days');

                        $new_carry_forward_days = $total_carry_forward_days - $existing_carry_forward_days;

                        if ($new_carry_forward_days > 0) {

                            $remaining_carry_forward_days = $new_carry_forward_days;

                            while ($remaining_carry_forward_days > 0) {

                                $assigned_days = min(
                                    1,
                                    $remaining_carry_forward_days
                                );

                                LeaveEntitlementLog::create([
                                    'uuid'                  => (string) Str::uuid(),
                                    'leave_entitlement_id'  => $current_leave_entitlement->id,
                                    'assigned_days'         => $assigned_days,
                                    'used_days'             => 0,
                                    'assigned_at'           => Carbon::now(),
                                    'available_at'          => Carbon::create($current_year, 1, 1)->toDateString(),
                                    'expired_at'            => $carry_forward_expiry_date,
                                    'is_carry_forward'      => StatusCodeConstants::ACTIVE,
                                    'is_prorated'           => StatusCodeConstants::INACTIVE,
                                    'is_active'             => StatusCodeConstants::ACTIVE,
                                    'is_manual'             => StatusCodeConstants::INACTIVE,
                                    'created_by'            => Auth::user()->uuid,
                                    'created_at'            => Carbon::now(),
                                    'updated_by'            => Auth::user()->uuid,
                                    'updated_at'            => Carbon::now(),
                                ]);

                                $remaining_carry_forward_days -= $assigned_days;
                            }

                            self::syncLeaveEntitlement($current_leave_entitlement);
                        }
                    }
                }
            }
        }
    }

    public static function assignNewLeave (User $user): void
    {
        User::where('id', $user->id)->lockForUpdate()->first();

        $user_years_of_service = $user->employment?->joined_date ? Carbon::parse($user->employment->joined_date)->diffInYears(Carbon::now()) : 0;
        $current_year = Carbon::now()->format('Y');
        $leave_policies = LeavePolicy::with(['leavePolicyTiers'])->active()->get() ?? [];
        $entitlement_end_date = Carbon::create($current_year, 12, 31)->endOfDay();

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

            $leave_entitlement = LeaveEntitlement::where([
                'user_id'           => $user->id,
                'leave_policy_id'   => $leave_policy->id,
                'year'              => $current_year,
                'is_active'         => StatusCodeConstants::ACTIVE,
            ])->first();

            // if user already entitled with the leave policy, then skip
            if ($leave_entitlement) {
                continue;
            }

            $annual_entitlement_days = (float) ($leave_policy_tier?->entitlement_days ?? 0);
            $leave_entitlement_logs = [];
            $accrual_start = $user->employment?->joined_date ? Carbon::parse($user->employment->joined_date)->startOfDay() : Carbon::create($current_year, 1, 1);

            if ($accrual_start->year < $current_year) {
                $accrual_start = Carbon::create($current_year, 1, 1);
            }

            if ($leave_policy->is_prorated && $annual_entitlement_days > 0) {
                $remaining_entitlement_days = $annual_entitlement_days;
                $sequence = 1;

                while ($remaining_entitlement_days > 0) {
                    $assigned_days = min(1, $remaining_entitlement_days);
                    $offset_days = (int) ceil(min($sequence, $annual_entitlement_days) * 360 / $annual_entitlement_days);
                    $available_at = $accrual_start->copy()->addDays($offset_days);

                    if ($available_at->gt($entitlement_end_date)) {
                        break;
                    }

                    $leave_entitlement_logs[] = [
                        'assigned_days' => $assigned_days,
                        'available_at' => $available_at->toDateString(),
                    ];

                    $remaining_entitlement_days -= $assigned_days;
                    $sequence++;
                }
            } else if (!$leave_policy->is_prorated && $annual_entitlement_days > 0) {
                $remaining_entitlement_days = $annual_entitlement_days;

                while ($remaining_entitlement_days > 0) {
                    $assigned_days = min(1, $remaining_entitlement_days);

                    $leave_entitlement_logs[] = [
                        'assigned_days' => $assigned_days,
                        'available_at' => Carbon::now()->toDateString(),
                    ];

                    $remaining_entitlement_days -= $assigned_days;
                }
            }

            $entitled_days = collect($leave_entitlement_logs)->sum('assigned_days');
            $carry_forward_expiry_date = null;

            if ($leave_policy->carry_forward_expiry_month && $leave_policy->carry_forward_expiry_date) {
                $carry_forward_expiry_month = Carbon::create($current_year + 1, $leave_policy->carry_forward_expiry_month, 1);
                $carry_forward_expiry_date = $carry_forward_expiry_month
                    ->copy()
                    ->day(min($leave_policy->carry_forward_expiry_date, $carry_forward_expiry_month->daysInMonth))
                    ->format('Y-m-d');
            }

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

            foreach ($leave_entitlement_logs as $leave_entitlement_log) {
                LeaveEntitlementLog::create([
                    'uuid'                  => (string) Str::uuid(),
                    'leave_entitlement_id'  => $leave_entitlement->id,
                    'assigned_days'         => $leave_entitlement_log['assigned_days'],
                    'used_days'             => 0,
                    'assigned_at'           => Carbon::now(),
                    'available_at'          => $leave_entitlement_log['available_at'],
                    'expired_at'            => null,
                    'is_carry_forward'      => StatusCodeConstants::INACTIVE,
                    'is_prorated'           => $leave_policy->is_prorated ? StatusCodeConstants::ACTIVE : StatusCodeConstants::INACTIVE,
                    'is_active'             => StatusCodeConstants::ACTIVE,
                    'is_manual'             => StatusCodeConstants::INACTIVE,
                    'created_by'            => Auth::user()->uuid,
                    'created_at'            => Carbon::now(),
                    'updated_by'            => Auth::user()->uuid,
                    'updated_at'            => Carbon::now(),
                ]);
            }

            self::syncLeaveEntitlement($leave_entitlement);
        }
    }

    public static function leaveRequestCheck (User $user, LeaveEntitlement $leave_entitlement, Array $request_dates, $resume_date, $handover_by_uuid, $total_days, $attachment): void
    {
        $leave_policy = $leave_entitlement->leavePolicy;
        $is_handover_required = $leave_policy?->is_handover_required == StatusCodeConstants::ACTIVE && $total_days >= ($leave_policy->handover_min_days ?? 0);
        $start_date = collect($request_dates)->min('date');
        $last_date = collect($request_dates)->max('date');
        $last_request_date = collect($request_dates)->sortBy('date')->last();
        $is_resume_same_day_first_half = Carbon::parse($resume_date)->startOfDay()->eq(Carbon::parse($last_date)->startOfDay())
            && isset($last_request_date['is_half_day']) && $last_request_date['is_half_day']
            && isset($last_request_date['is_first_half']) && $last_request_date['is_first_half'];
        $notice_days = Carbon::now()->startOfDay()->diffInDays(Carbon::parse($start_date)->startOfDay(), false);

        throw_if($leave_entitlement->user_id != $user->id, AppException::class, 'Invalid leave entitlement');
        throw_if(self::availableLeaveDays($leave_entitlement) < $total_days, AppException::class, 'Insufficient leave balance');
        throw_if($notice_days < ($leave_policy->min_notice_days ?? 0), AppException::class, 'Minimum notice days is not fulfilled');
        // throw_if(Carbon::parse($request->resume_date)->startOfDay()->lte(Carbon::parse($last_date)->startOfDay()) && !$is_resume_same_day_first_half, AppException::class, 'Resume date must be after the last leave date');
        throw_if($is_handover_required && !$handover_by_uuid, AppException::class, 'Handover is required');
        throw_if($leave_policy->requires_attachment == StatusCodeConstants::ACTIVE && !$attachment, AppException::class, 'Attachment is required');
    }

    public static function deductLeaveDays($leave_entitlement, $total_days, $updated_by)
    {
        $remaining_days = (float) $total_days;
        $deduct_carried_forward_days = 0;
        $current_date = Carbon::now()->toDateString();

        $leave_entitlement_logs = LeaveEntitlementLog::where('leave_entitlement_id', $leave_entitlement->id)
            ->where('is_active', StatusCodeConstants::ACTIVE)
            ->where('available_at', '<=', $current_date)
            ->where(function($query) use ($current_date) {
                $query->whereNull('expired_at')
                    ->orWhere('expired_at', '>=', $current_date);
            })
            ->whereRaw('assigned_days > used_days')
            ->orderByRaw('CASE WHEN used_days > 0 THEN 0 ELSE 1 END')
            ->orderBy('is_carry_forward', 'desc')
            ->orderBy('available_at', 'asc')
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get();

        foreach ($leave_entitlement_logs as $leave_entitlement_log)
        {
            if ($remaining_days <= 0) {
                break;
            }

            $available_days = (float) $leave_entitlement_log->assigned_days - (float) $leave_entitlement_log->used_days;
            $used_days = min($available_days, $remaining_days);

            $leave_entitlement_log->update([
                'used_days' => (float) $leave_entitlement_log->used_days + $used_days,
                'updated_by' => $updated_by,
                'updated_at' => Carbon::now(),
            ]);

            if ($leave_entitlement_log->is_carry_forward)
            {
                $deduct_carried_forward_days += $used_days;
            }

            $remaining_days -= $used_days;
        }

        $leave_entitlement->update([
            'used_days' => $leave_entitlement->used_days + $total_days,
            'carried_forward_days' => $leave_entitlement->carried_forward_days - $deduct_carried_forward_days,
            'balance_days' => $leave_entitlement->balance_days - $total_days,
            'updated_by' => $updated_by,
            'updated_at' => Carbon::now(),
        ]);
    }

    public static function addManualLeave(LeaveEntitlement $leave_entitlement, $used_days, $balance_days, $available_at = null, $expired_at = null)
    {
        $target_used_days = (float) $used_days;
        $target_assigned_days = (float) $used_days + (float) $balance_days;
        $current_date = Carbon::now()->toDateString();

        $leave_entitlement_logs = LeaveEntitlementLog::where([
            'leave_entitlement_id' => $leave_entitlement->id,
            'is_active'            => StatusCodeConstants::ACTIVE,
        ])
        ->where('available_at', '<=', $current_date)
        ->where(function($query) use ($current_date) {
            $query->whereNull('expired_at')
                ->orWhere('expired_at', '>=', $current_date);
        })
        ->lockForUpdate()
        ->get();

        $current_assigned_days = (float) $leave_entitlement_logs->sum('assigned_days');
        $current_used_days = (float) $leave_entitlement_logs->sum('used_days');

        if ($target_used_days < $current_used_days) {
            self::reduceLeaveEntitlementLogUsedDays($leave_entitlement, $current_used_days - $target_used_days);
        }

        $leave_entitlement_logs = LeaveEntitlementLog::where([
            'leave_entitlement_id' => $leave_entitlement->id,
            'is_active'            => StatusCodeConstants::ACTIVE,
        ])
        ->where('available_at', '<=', $current_date)
        ->where(function($query) use ($current_date) {
            $query->whereNull('expired_at')
                ->orWhere('expired_at', '>=', $current_date);
        })
        ->lockForUpdate()
        ->get();

        $current_assigned_days = (float) $leave_entitlement_logs->sum('assigned_days');

        if ($target_assigned_days > $current_assigned_days) {
            self::createManualLeaveEntitlementLogs($leave_entitlement, $target_assigned_days - $current_assigned_days, $available_at, $expired_at);
        }

        if ($target_assigned_days < $current_assigned_days) {
            self::reduceLeaveEntitlementLogAssignedDays($leave_entitlement, $current_assigned_days - $target_assigned_days);
        }

        $leave_entitlement_logs = LeaveEntitlementLog::where([
            'leave_entitlement_id' => $leave_entitlement->id,
            'is_active'            => StatusCodeConstants::ACTIVE,
        ])
        ->where('available_at', '<=', $current_date)
        ->where(function($query) use ($current_date) {
            $query->whereNull('expired_at')
                ->orWhere('expired_at', '>=', $current_date);
        })
        ->lockForUpdate()
        ->get();

        $current_used_days = (float) $leave_entitlement_logs->sum('used_days');

        if ($target_used_days > $current_used_days) {
            self::updateLeaveEntitlementLogUsedDays($leave_entitlement, $target_used_days - $current_used_days);
        }

        if ($target_used_days < $current_used_days) {
            self::reduceLeaveEntitlementLogUsedDays($leave_entitlement, $current_used_days - $target_used_days);
        }

        self::syncLeaveEntitlement($leave_entitlement);
    }

    public static function updateLeaveEntitlementLogUsedDays(LeaveEntitlement $leave_entitlement, $total_days): void
    {
        $remaining_days = (float) $total_days;
        $current_date = Carbon::now()->toDateString();

        $leave_entitlement_logs = LeaveEntitlementLog::where([
            'leave_entitlement_id' => $leave_entitlement->id,
            'is_active'            => StatusCodeConstants::ACTIVE,
        ])
        ->where('available_at', '<=', $current_date)
        ->where(function($query) use ($current_date) {
            $query->whereNull('expired_at')
                ->orWhere('expired_at', '>=', $current_date);
        })
        ->whereRaw('assigned_days > used_days')
        ->orderBy('available_at', 'asc')
        ->orderBy('id', 'asc')
        ->lockForUpdate()
        ->get();

        foreach ($leave_entitlement_logs as $leave_entitlement_log)
        {
            if ($remaining_days <= 0) {
                break;
            }

            $available_days = (float) $leave_entitlement_log->assigned_days - (float) $leave_entitlement_log->used_days;
            $used_days = min($available_days, $remaining_days);

            $leave_entitlement_log->update([
                'used_days'  => (float) $leave_entitlement_log->used_days + $used_days,
                'updated_by' => Auth::user()->uuid,
                'updated_at' => Carbon::now(),
            ]);

            $remaining_days -= $used_days;
        }

        throw_if($remaining_days > 0, AppException::class, 'Insufficient leave balance');
    }

    public static function reduceLeaveEntitlementLogUsedDays(LeaveEntitlement $leave_entitlement, $total_days): void
    {
        $remaining_days = (float) $total_days;
        $current_date = Carbon::now()->toDateString();

        $leave_entitlement_logs = LeaveEntitlementLog::where([
            'leave_entitlement_id' => $leave_entitlement->id,
            'is_active'            => StatusCodeConstants::ACTIVE,
        ])
        ->where('available_at', '<=', $current_date)
        ->where(function($query) use ($current_date) {
            $query->whereNull('expired_at')
                ->orWhere('expired_at', '>=', $current_date);
        })
        ->where('used_days', '>', 0)
        ->orderBy('available_at', 'desc')
        ->orderBy('id', 'desc')
        ->lockForUpdate()
        ->get();

        foreach ($leave_entitlement_logs as $leave_entitlement_log)
        {
            if ($remaining_days <= 0) {
                break;
            }

            $used_days = min((float) $leave_entitlement_log->used_days, $remaining_days);

            $leave_entitlement_log->update([
                'used_days'  => (float) $leave_entitlement_log->used_days - $used_days,
                'updated_by' => Auth::user()->uuid,
                'updated_at' => Carbon::now(),
            ]);

            $remaining_days -= $used_days;
        }

        throw_if($remaining_days > 0, AppException::class, 'Invalid used days');
    }

    public static function reduceLeaveEntitlementLogAssignedDays(LeaveEntitlement $leave_entitlement, $total_days): void
    {
        $remaining_days = (float) $total_days;
        $current_date = Carbon::now()->toDateString();

        $leave_entitlement_logs = LeaveEntitlementLog::where([
            'leave_entitlement_id' => $leave_entitlement->id,
            'is_active'            => StatusCodeConstants::ACTIVE,
        ])
        ->where('available_at', '<=', $current_date)
        ->where(function($query) use ($current_date) {
            $query->whereNull('expired_at')
                ->orWhere('expired_at', '>=', $current_date);
        })
        ->whereRaw('assigned_days > used_days')
        ->orderBy('is_manual', 'desc')
        ->orderBy('available_at', 'desc')
        ->orderBy('id', 'desc')
        ->lockForUpdate()
        ->get();

        foreach ($leave_entitlement_logs as $leave_entitlement_log)
        {
            if ($remaining_days <= 0) {
                break;
            }

            $available_days = (float) $leave_entitlement_log->assigned_days - (float) $leave_entitlement_log->used_days;
            $deduct_days = min($available_days, $remaining_days);
            $assigned_days = (float) $leave_entitlement_log->assigned_days - $deduct_days;

            $leave_entitlement_log->update([
                'assigned_days' => $assigned_days,
                'is_active'     => $assigned_days <= 0 ? StatusCodeConstants::INACTIVE : StatusCodeConstants::ACTIVE,
                'updated_by'    => Auth::user()->uuid,
                'updated_at'    => Carbon::now(),
            ]);

            $remaining_days -= $deduct_days;
        }

        throw_if($remaining_days > 0, AppException::class, 'Invalid leave balance');
    }

    public static function createManualLeaveEntitlementLogs(LeaveEntitlement $leave_entitlement, $total_days, $available_at = null, $expired_at = null): void
    {
        $remaining_days = (float) $total_days;

        while ($remaining_days > 0)
        {
            $assigned_days = min(1, $remaining_days);

            LeaveEntitlementLog::create([
                'uuid'                  => (string) Str::uuid(),
                'leave_entitlement_id'  => $leave_entitlement->id,
                'assigned_days'         => $assigned_days,
                'used_days'             => 0,
                'assigned_at'           => Carbon::now(),
                'available_at'          => $available_at ?? Carbon::now()->toDateString(),
                'expired_at'            => $expired_at,
                'is_carry_forward'      => StatusCodeConstants::INACTIVE,
                'is_prorated'           => StatusCodeConstants::INACTIVE,
                'is_manual'             => StatusCodeConstants::ACTIVE,
                'is_active'             => StatusCodeConstants::ACTIVE,
                'created_by'            => Auth::user()->uuid,
                'created_at'            => Carbon::now(),
                'updated_by'            => Auth::user()->uuid,
                'updated_at'            => Carbon::now(),
            ]);

            $remaining_days -= $assigned_days;
        }

    }

    public static function availableLeaveDays($leave_entitlement)
    {
        return $leave_entitlement->balance_days + ($leave_entitlement->leavePolicy?->allowed_negative_days ?? 0);
    }
}
