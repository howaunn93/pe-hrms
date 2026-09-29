<?php

namespace App\Http\Controllers\BE;

use App\Constants\StatusCodeConstants;
use App\Filters\UserFilter;
use App\Helpers\LeaveModuleHelpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\LeaveEntitlementShowRequest;
use App\Http\Requests\LeaveEntitlementUpdateRequest;
use App\Http\Requests\UserIndexRequest;
use App\Http\Resources\LeaveEntitlementResource;
use App\Http\Resources\UserResource;
use App\Models\LeaveEntitlement;
use App\Models\User;
use Carbon\Carbon;

class LeaveEntitlementController extends Controller
{
    public function __construct(private UserFilter $user_filter)
    {
    }

    public function index(UserIndexRequest $request)
    {
        $user = User::with([
            'personal',
            'contact',
            'employment.office',
            'employment.department',
            'employment.position',
            'emergency',
            'certificates',
            // 'leaveEntitlements' => function($query) {
            //     $query->where('is_active', StatusCodeConstants::ACTIVE);
            // },
            // 'leaveEntitlements.leavePolicy.leavePolicyTiers',
        ])->active();

        $user = $this->user_filter->apply($request, $request->size, $user);

        $user->each(function ($user) {
            LeaveModuleHelpers::userLeaveEntitlementCheck($user->uuid);

            $user->load([
                'leaveEntitlements' => function($query) {
                    $query->where('is_active', StatusCodeConstants::ACTIVE)
                        ->where('year', Carbon::now()->format('Y'));
                },
                'leaveEntitlements.leavePolicy.leavePolicyTiers',
                'leaveEntitlements.leaveEntitlementLogs',
            ]);
        });

        return self::responsePaginated(UserResource::collection($user), $user);
    }

    public function update(LeaveEntitlementUpdateRequest $request, string $uuid)
    {
        $leave_entitlement = LeaveEntitlement::findByUuid($uuid);

        LeaveModuleHelpers::addManualLeave($leave_entitlement, $request->used_days, $request->balance_days, $request->available_at, $request->expired_at);

        LeaveModuleHelpers::userLeaveEntitlementCheck($leave_entitlement->user->uuid);

        return self::response(new LeaveEntitlementResource($leave_entitlement));
    }

    public function show(LeaveEntitlementShowRequest $request, string $uuid)
    {
        $leave_entitlement = LeaveEntitlement::findByUuid($uuid);

        LeaveModuleHelpers::userLeaveEntitlementCheck($leave_entitlement->user->uuid);

        $leave_entitlement->refresh();

        return self::response(new LeaveEntitlementResource($leave_entitlement));
    }
}
