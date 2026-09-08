<?php

namespace App\Http\Requests;

use App\Models\Brand;
use App\Models\Shop;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = backpack_user();
        $shop = Shop::whereKey($this->input('shop_id'))->first();
        if (! $user || ! $shop || ! $user->manages($shop) || $shop->status === 'frozen') {
            return false;
        }
        if ($this->route('id')) {
            $entry = Brand::whereKey($this->route('id'))->firstOrFail();

            return $entry->shop_id === $shop->id;
        }

        return true;
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
        $entry = $this->route('id') ? Brand::whereKey($this->route('id'))->firstOrFail() : null;

        return [
            'shop_id' => ['required', 'uuid', 'exists:shops,id'],
            'name' => ['required', 'string', 'max:150', Rule::unique('brands')->where('shop_id', $this->input('shop_id'))->ignore($entry)],
            'description' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
