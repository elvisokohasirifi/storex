<?php

namespace App\Http\Requests;

use App\Models\LedgerEntry;
use App\Models\Shop;
use Backpack\CRUD\app\Library\Validation\Rules\ValidUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LedgerEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = backpack_user();
        $shop = Shop::whereKey($this->input('shop_id'))->first();
        if (! $user || ! $shop || ! $user->manages($shop)) {
            return false;
        }

        return ! $this->route('id') || LedgerEntry::whereKey($this->route('id'))->firstOrFail()->shop_id === $shop->id;
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('id')) {
            $this->merge(['id' => $this->route('id')]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'uuid', 'exists:shops,id'], 'type' => ['required', Rule::in(['income', 'expense'])],
            'category' => ['required', 'string', 'max:100'], 'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'],
            'occurred_on' => ['required', 'date', 'before_or_equal:today'], 'notes' => ['nullable', 'string', 'max:10000'],
            'receipt' => [ValidUpload::field('nullable')->file('mimes:jpg,jpeg,png,webp,pdf|max:10240')],
        ];
    }
}
