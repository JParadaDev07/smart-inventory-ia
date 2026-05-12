<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use BelongsToBusiness;

    protected $fillable = [
        'business_id',
        'plan',
        'amount',
        'amount_in_cents',
        'currency',
        'reference',
        'status',
        'wompi_transaction_id',
        'wompi_status',
        'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'amount_in_cents' => 'integer',
            'raw_response' => 'array',
        ];
    }

    public function isApproved(): bool
    {
        return $this->status === 'paid'
            || strtoupper((string) $this->wompi_status) === 'APPROVED';
    }
}
