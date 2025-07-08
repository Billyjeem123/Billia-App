<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserActivityLog extends Model
{
    use HasFactory;


    protected $fillable = [
            'user_id',
            'activity',
            'description',
            'page_url',
            'properties',
            'ip_address',
            'user_agent',
            'created_at'
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = now();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

   # Scope for recent activities
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

   # Scope for specific activity types
    public function scopeOfType($query, $activity)
    {
        return $query->where('activity', $activity);
    }
}
