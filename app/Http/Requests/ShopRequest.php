<?php

namespace App\Http\Requests;

use App\Models\Shop;
use Backpack\CRUD\app\Library\Validation\Rules\ValidUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
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
