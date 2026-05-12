<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity_change' => ['required', 'integer'], // positive = restock, negative = write-off
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'type' => ['nullable', 'string', 'in:restock,adjustment,correction'],
            // Perishable batch receipt fields (used when type=restock and product is perecedero).
            'expiration_date' => ['nullable', 'date'],
            'cost_unit' => ['nullable', 'numeric', 'min:0'],
            // Optional for merma/adjustment: consume from a specific batch.
            'batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
