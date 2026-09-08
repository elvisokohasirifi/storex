<?php

namespace App\Http\Requests;

use App\Models\Shop;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ShopDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = backpack_user();
        if (! $user || $user->is_platform_admin) {
            return false;
        }
        $shop = Shop::whereKey($this->input('shop_id'))->first();

        return $shop && $user->manages($shop, true) && $shop->status !== 'frozen';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $shopId = (string) $this->input('shop_id');

        return [
            'shop_id' => ['required', 'uuid', 'exists:shops,id'],
            'name' => ['required', 'string', 'max:150'],
            'scope' => ['required', Rule::in(['all_products', 'category', 'brand', 'checkout'])],
            'type' => ['required', Rule::in(['percentage', 'fixed'])],
            'value' => ['required', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'],
            'is_active' => ['sometimes', 'boolean'],
            'category_id' => ['nullable', 'uuid', Rule::requiredIf($this->input('scope') === 'category'), Rule::exists('product_categories', 'id')->where('shop_id', $shopId)],
            'brand_id' => ['nullable', 'uuid', Rule::requiredIf($this->input('scope') === 'brand'), Rule::exists('brands', 'id')->where('shop_id', $shopId)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('type') === 'percentage' && (float) $this->input('value') > 100) {
                    $validator->errors()->add('value', 'Percentage discounts cannot be more than 100.');
                }
            },
        ];
    }
}
