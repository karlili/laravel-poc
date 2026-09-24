<?php

namespace App\Livewire\Forms;

use App\Models\Company;
use Illuminate\Validation\Rule;
use Livewire\Form;

class CompanyForm extends Form
{
    public ?Company $company = null;

    public string $name = '';

    public string $domain = '';

    public string $industry = '';

    public string $email = '';

    public string $phone = '';

    public string $address_line1 = '';

    public string $address_line2 = '';

    public string $city = '';

    public string $state = '';

    public string $postcode = '';

    public string $country = '';

    public ?int $owner_id = null;

    public function setCompany(Company $company): void
    {
        $this->company = $company;

        $this->fill([
            ...array_map(fn ($value) => $value ?? '', $company->only([
                'name', 'domain', 'industry', 'email', 'phone',
                'address_line1', 'address_line2', 'city', 'state', 'postcode', 'country',
            ])),
            'owner_id' => $company->owner_id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
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
     * Validate and save the company. Only users who may reassign records can
     * change the owner; new records default to the current user.
     */
    public function save(bool $canAssign): Company
    {
        $validated = array_map(fn ($value) => $value === '' ? null : $value, $this->validate());

        if (! $canAssign) {
            unset($validated['owner_id']);
        }

        $company = $this->company ?? new Company(['owner_id' => auth()->id()]);
        $company->fill($validated);

        if ($company->country !== null) {
            $company->country = strtoupper($company->country);
        }

        $company->save();

        return $company;
    }
}
