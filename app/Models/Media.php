<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Media extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'mediable_type',
        'mediable_id',
        'disk',
        'path',
        'thumb_path',
        'mime',
        'size_bytes',
        'width',
        'height',
        'created_by',
        'expires_at',
        'is_pinned',
        'created_at',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'expires_at' => 'datetime',
        'is_pinned' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
