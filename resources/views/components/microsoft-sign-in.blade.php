@if (config('services.azure.enabled'))
    <div class="flex flex-col gap-6">
        <flux:button :href="route('auth.microsoft.redirect')" class="w-full" data-test="microsoft-sign-in-button">
            <svg class="size-4" viewBox="0 0 21 21" aria-hidden="true">
                <rect x="1" y="1" width="9" height="9" fill="#f25022" />
                <rect x="11" y="1" width="9" height="9" fill="#7fba00" />
                <rect x="1" y="11" width="9" height="9" fill="#00a4ef" />
                <rect x="11" y="11" width="9" height="9" fill="#ffb900" />
            </svg>
            {{ __('Sign in with Microsoft') }}
        </flux:button>

        <flux:separator :text="__('or')" />
    </div>
@endif
