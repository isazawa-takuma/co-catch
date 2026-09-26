<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use HasFactory;

    public const CONTACT_STATUSES = ['担当外（男）', '担当外（女）', '担当（男）', '担当（女）', '社長'];
    public const STATUSES = [
        'コール',
        'コールのみ',
        '担不在',
        '受付ブロック',
        'NG',
        'メール',
        'メモ',
        '情報共有',
        '逆電',
        '逆メール',
        'APO',
        '逆連絡APO',
        'APO変更',
        'APOキャンセル',
        '詰め直し',
        'HP問合せ',
        '現アナ',
        '初訪',
        '再訪',
        '対応',
    ];
    public const RANKS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

    protected $table = 'opnavi_activities';

    protected $fillable = [
        'customer_id',
        'user_id',
        'rank',
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
