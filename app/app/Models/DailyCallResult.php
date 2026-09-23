<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyCallResult extends Model
{
    use HasFactory;

    protected $table = 'opnavi_daily_call_results';

    protected $fillable = [
        'user_id',
        'result_date',
        'total_count',
        'status_counts',
        'confirmed_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'result_date' => 'date',
        'total_count' => 'integer',
        'status_counts' => 'array',
        'confirmed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
