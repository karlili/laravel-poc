<?php

namespace App\Http\Requests\Crm;

use App\Models\Contact;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates creating (POST /contacts) and updating (PUT /contacts/{contact}) a contact.
 */
class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contact = $this->route('contact');

        return $contact instanceof Contact
            ? (bool) $this->user()?->can('update', $contact)
            : (bool) $this->user()?->can('create', Contact::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
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
     * Validated attributes ready to fill the model. Only users who may reassign
     * records can change the owner.
     *
     * @return array<string, mixed>
     */
    public function attributesForSave(): array
    {
        $attributes = $this->validated();

        if (! $this->user()?->can('assign', Contact::class)) {
            unset($attributes['owner_id']);
        }

        return $attributes;
    }
}
