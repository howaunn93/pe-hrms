<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class LeaveEntitlementLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'uuid' => $this->uuid,
            'assigned_days' => $this->assigned_days,
            'used_days' => $this->used_days,
            'assigned_at' => $this->assigned_at,
            'available_at' => $this->available_at,
            'expired_at' => $this->expired_at,
            'is_carry_forward' => $this->is_carry_forward,
            'is_prorated' => $this->is_prorated,
            'is_manual' => $this->is_manual,
            'is_active' => $this->is_active,
            'created_by' => $this->created_by,
            'created_at' => Carbon::parse($this->created_at)->utc(),
            'updated_by' => $this->updated_by,
            'updated_at' => Carbon::parse($this->updated_at)->utc(),
            'leave_entitlement' => new LeaveEntitlementResource($this->whenLoaded('leaveEntitlement')),
        ];

        return $data;
    }
}
