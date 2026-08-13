<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use HasFactory;

    public const CONTACT_STATUSES = ['受付', '担当者', '代表', 'その他'];

    protected $table = 'opnavi_activities';

    protected $fillable = [
        'customer_id',
        'user_id',
        'action_at',
        'contact_person',
        'contact_status',
        'contact_phone',
        'status',
        'memo',
    ];

    protected $casts = [
        'action_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
