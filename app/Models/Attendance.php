<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Models\Traits\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory, BranchScoped;

    protected $fillable = [
        'user_id',
        'branch_id',
        'work_date',
        'check_in_at',
        'check_in_lat',
        'check_in_lng',
        'check_in_accuracy',
        'check_in_distance_m',
        'check_out_at',
        'check_out_lat',
        'check_out_lng',
        'check_out_distance_m',
        'late_minutes',
        'status',
        'note',
        'manual_reason',
        'edited_by',
    ];

    protected $casts = [
        'work_date' => 'date:Y-m-d',
        'check_in_at' => 'datetime',
        'check_in_lat' => 'float',
        'check_in_lng' => 'float',
        'check_in_accuracy' => 'float',
        'check_in_distance_m' => 'float',
        'check_out_at' => 'datetime',
        'check_out_lat' => 'float',
        'check_out_lng' => 'float',
        'check_out_distance_m' => 'float',
        'late_minutes' => 'integer',
        'status' => AttendanceStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
