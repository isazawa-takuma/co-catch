<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['未対応', '連絡済み', 'やり取り中', 'アポイント', '商談中', '契約', '失注'];

    protected $table = 'opnavi_customers';

    protected $fillable = [
        'registered_at',
        'business_name',
        'prefecture',
        'region',
        'area_name',
        'address',
        'website_url',
        'head_office_phone',
        'public_phone',
        'contact_phone',
        'experience_title',
        'domestic_otas',
        'ota_count',
        'other_ota_names',
        'business_scale',
        'store_count',
        'monthly_open_days',
        'request_booking_status',
        'research_notes',
        'status',
        'priority',
        'owner_id',
        'last_action_at',
        'last_action_summary',
        'next_action_at',
        'next_action_summary',
        'sales_memo',
    ];

    protected $casts = [
        'registered_at' => 'date',
        'last_action_at' => 'date',
        'next_action_at' => 'date',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function otaLinks()
    {
        return $this->hasMany(OtaLink::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }
}
