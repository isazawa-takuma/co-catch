<?php

namespace App\Models;

use App\Support\PhoneSearch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = [
        '未対応',
        '担当不在',
        '受付ブロック',
        'メール',
        '連絡済み',
        'やり取り中',
        '見込み（アポイント時）',
        'アポイント',
        '見込み（アポイント後）',
        '追客',
        '現アナ',
        '商談中',
        '契約',
        'NG',
        '失注',
    ];

    public const STATUS_CLASSES = [
        '未対応' => 'not-started',
        '担当不在' => 'unavailable',
        '受付ブロック' => 'reception-block',
        'メール' => 'email',
        '連絡済み' => 'contacted',
        'やり取り中' => 'in-progress',
        '見込み（アポイント時）' => 'prospect-before-appointment',
        'アポイント' => 'appointment',
        '見込み（アポイント後）' => 'prospect-after-appointment',
        '追客' => 'follow-up',
        '現アナ' => 'current-analysis',
        '商談中' => 'negotiation',
        '契約' => 'contracted',
        'NG' => 'ng',
        '失注' => 'lost',
    ];

    private const PHONE_COLUMNS = [
        'head_office_phone',
        'public_phone',
        'contact_phone',
    ];

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
        'head_office_phone_normalized',
        'public_phone',
        'public_phone_normalized',
        'contact_phone',
        'contact_phone_normalized',
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

    protected static function booted(): void
    {
        static::saving(function (Customer $customer) {
            $customer->fillNormalizedPhoneColumns();
        });

        static::saved(function (Customer $customer) {
            $customer->syncPhoneSearchIndex();
        });

        static::forceDeleted(function (Customer $customer) {
            DB::table('opnavi_customer_phone_indexes')
                ->where('customer_id', $customer->id)
                ->delete();
        });
    }

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
        return $this->hasMany(Activity::class)->orderByDesc('action_at')->orderByDesc('id');
    }

    public static function statusClass(?string $status): string
    {
        return self::STATUS_CLASSES[$status] ?? 'default';
    }

    private function fillNormalizedPhoneColumns(): void
    {
        foreach (self::PHONE_COLUMNS as $column) {
            $normalized = PhoneSearch::normalize($this->{$column});
            $this->{$column.'_normalized'} = $normalized === '' ? null : $normalized;
        }
    }

    private function syncPhoneSearchIndex(): void
    {
        DB::table('opnavi_customer_phone_indexes')
            ->where('customer_id', $this->id)
            ->delete();

        $rows = [];
        foreach (self::PHONE_COLUMNS as $column) {
            foreach (PhoneSearch::tokens($this->{$column}) as $token) {
                $rows[] = [
                    'customer_id' => $this->id,
                    'phone_column' => $column,
                    'phone_token' => $token,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('opnavi_customer_phone_indexes')->insertOrIgnore($rows);
        }
    }
}
