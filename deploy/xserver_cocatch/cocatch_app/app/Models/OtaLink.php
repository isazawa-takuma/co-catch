<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtaLink extends Model
{
    use HasFactory;

    protected $table = 'opnavi_ota_links';

    protected $fillable = [
        'customer_id',
        'ota_name',
        'listing_url',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
