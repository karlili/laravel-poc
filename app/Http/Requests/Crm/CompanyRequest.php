<?php

namespace App\Http\Requests\Crm;

use App\Models\Company;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates creating (POST /companies) and updating (PUT /companies/{company}) a company.
 */
class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');

        return $company instanceof Company
            ? (bool) $this->user()?->can('update', $company)
            : (bool) $this->user()?->can('create', Company::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')],
        ];
    }

    /**
     * Validated attributes ready to fill the model. Only users who may reassign
     * records can change the owner.
     *
     * @return array<string, mixed>
     */
    public function attributesForSave(): array
    {
        $attributes = $this->validated();

        if (! $this->user()?->can('assign', Company::class)) {
            unset($attributes['owner_id']);
        }

        if (isset($attributes['country'])) {
            $attributes['country'] = strtoupper($attributes['country']);
        }

        return $attributes;
    }
}
