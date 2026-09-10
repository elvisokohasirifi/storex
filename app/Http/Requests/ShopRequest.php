<?php

namespace App\Http\Requests;

use App\Models\Shop;
use Backpack\CRUD\app\Library\Validation\Rules\ValidUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = backpack_user();
        if (! $user || $user->is_platform_admin) {
            return false;
        }

        return ! $this->route('id') || $user->manages(Shop::whereKey($this->route('id'))->firstOrFail(), true);
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('id')) {
            $this->merge(['id' => $this->route('id')]);
        }
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('enable_inventory_management') || ! $this->boolean('enable_inventory_management') || ! $this->route('id')) {
                    return;
                }

                $hasIncompleteProducts = Shop::whereKey($this->route('id'))->whereHas('products', function ($query): void {
                    $query->whereNull('quantity')->orWhereNull('cost_price');
                })->exists();

                if ($hasIncompleteProducts) {
                    $validator->errors()->add('enable_inventory_management', 'Add quantity and cost price to every product before enabling inventory management.');
                }
            },
        ];
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'enable_inventory_management' => ['sometimes', 'boolean'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('shops')->ignore($this->route('id'))],
            'description' => ['nullable', 'string', 'max:10000'],
            'location' => ['required', 'string', 'max:255'], 'contacts' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'logo' => [ValidUpload::field('nullable')->file('image|max:2048')],
            'banner' => [ValidUpload::field('nullable')->file('image|max:5120')],
        ];
    }
}
