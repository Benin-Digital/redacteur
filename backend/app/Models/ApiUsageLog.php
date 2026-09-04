<?php
// app/Models/ApiUsageLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiUsageLog extends Model
{
    use HasFactory;

    protected $table = 'api_usage_logs';

    protected $fillable = [
        'user_id',
        'site_id',
        'action',
        'provider',
        'model',
        'tokens_used',
        'response_time_ms',
        'status',
        'error_message',
        'ip_address',
        'metadata'
    ];

    protected $casts = [
        'tokens_used' => 'integer',
        'response_time_ms' => 'integer',
        'metadata' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeErrors($query)
    {
        return $query->where('status', 'error');
    }

    public static function recordSuccess($data): self
    {
        return self::create(array_merge($data, ['status' => 'success']));
    }

    public static function recordError($data, $errorMessage): self
    {
        return self::create(array_merge($data, [
            'status' => 'error',
            'error_message' => $errorMessage
        ]));
    }
}