<?php

namespace App\Livewire\Forms;

use App\Models\Contact;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ContactForm extends Form
{
    public ?Contact $contact = null;

    public ?int $company_id = null;

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $phone = '';

    public string $job_title = '';

    public ?int $owner_id = null;

    public function setContact(Contact $contact): void
    {
        $this->contact = $contact;

        $this->fill([
            ...array_map(fn ($value) => $value ?? '', $contact->only([
                'first_name', 'last_name', 'email', 'phone', 'job_title',
            ])),
            'company_id' => $contact->company_id,
            'owner_id' => $contact->owner_id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'company_id' => ['nullable', Rule::exists('companies', 'id')->withoutTrashed()],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')],
        ];
    }

    /**
     * Validate and save the contact. Only users who may reassign records can
     * change the owner; new records default to the current user.
     */
    public function save(bool $canAssign): Contact
    {
        $validated = array_map(fn ($value) => $value === '' ? null : $value, $this->validate());

        if (! $canAssign) {
            unset($validated['owner_id']);
        }

        $contact = $this->contact ?? new Contact(['owner_id' => auth()->id()]);
        $contact->fill($validated)->save();

        return $contact;
    }
}
