<?php

namespace App\Models;

use App\Constants\StatusCodeConstants;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LeaveEntitlementLog extends Model
{
    use HasActivityLog;

    protected $table = 'leave_entitlement_logs';
    public $timestamps = false;
    protected $casts = [
        'is_carry_forward' => 'boolean',
        'is_prorated' => 'boolean',
        'is_manual' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime:Y-m-d H:i:s.u',
        'updated_at' => 'datetime:Y-m-d H:i:s.u',
    ];

    protected $fillable = [
        'uuid',
        'leave_entitlement_id',
        'assigned_days',
        'used_days',
        'assigned_at',
        'available_at',
        'expired_at',
        'is_carry_forward',
        'is_prorated',
        'is_manual',
        'is_active',
        'created_by',
        'created_at',
        'updated_by',
        'updated_at',
    ];

    /**
     * Scopes
     */
    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', StatusCodeConstants::ACTIVE);
    }

    /**
     * Data Retrieval Methods
     */
    public static function findByUuid(string $uuid, bool $fail = true)
    {
        $query = self::with([
            'leaveEntitlement',
        ])->where('uuid', $uuid)
            ->where('is_active', StatusCodeConstants::ACTIVE);

        if ($fail)
        {
            return $query->firstOrFail();
        }

        return $query->first();
    }

    /**
     * Relationships
     */
    public function leaveEntitlement()
    {
        return $this->belongsTo(LeaveEntitlement::class, 'leave_entitlement_id', 'id')->active();
    }
}
