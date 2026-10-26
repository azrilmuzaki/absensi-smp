<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_data',
        'new_data',
        'reason',
        'ip_address',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Polymorphic relation ke model yang diubah.
     */
    public function auditable()
    {
        return $this->morphTo();
    }

    /**
     * Helper untuk mencatat kejadian audit dengan mudah.
     */
    public static function record(
        string $action,
        ?string $auditableType = null,
        ?int $auditableId = null,
        array $oldData = [],
        array $newData = [],
        ?string $reason = null
    ): self {
        return self::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_data' => empty($oldData) ? null : $oldData,
            'new_data' => empty($newData) ? null : $newData,
            'reason' => $reason,
            'ip_address' => request()->ip(),
        ]);
    }
}
