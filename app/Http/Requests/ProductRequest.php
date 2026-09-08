<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\Shop;
use Backpack\CRUD\app\Library\Validation\Rules\ValidUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = backpack_user();
        if (! $user || $user->is_platform_admin) {
            return false;
        }
        $shop = Shop::whereKey($this->input('shop_id'))->first();
        if (! $shop || ! $user->manages($shop) || $shop->status === 'frozen') {
            return false;
        }
        if ($this->route('id')) {
            $product = Product::whereKey($this->route('id'))->firstOrFail();

            return $product->shop_id === $shop->id && $product->status !== 'frozen' && $user->manages($product->shop);
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
    public static function productRules(string $shopId, ?string $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:10000'],
            'selling_price' => ['required', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'quantity' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('products')->where('shop_id', $shopId)->ignore($id)],
            'sku' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return self::productRules((string) $this->input('shop_id'), $this->route('id')) + [
            'shop_id' => ['required', 'uuid', 'exists:shops,id'],
            'image' => [ValidUpload::field('nullable')->file('image|max:5120')],
        ];
    }
}
