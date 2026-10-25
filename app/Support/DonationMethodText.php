<?php

namespace App\Support;

class DonationMethodText
{
    /**
     * Editable wording on a donation method block. Blocks saved before a field
     * existed have no key and keep the original wording; a key saved as an
     * empty string means the editor chose to hide it (except the PayPal payment
     * description, which PayPal requires, so empty falls back to the default).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function for(array $payload, string $key): string
    {
        $provider = trim((string) ($payload['provider'] ?? 'paypal'));
        $default = match ($key) {
            'amounts_heading' => 'Recurring Support',
            'amount_suffix' => 'a month',
            'tag_label' => 'Zelle Tag',
            'item_name' => 'Buy the Zen Cart Team some coffee!',
            default => match ($provider) {
                'zelle' => 'Send To This Tag',
                'github' => 'Sponsor Directly',
                default => 'One-Time Donation',
            },
        };

        if (! array_key_exists($key, $payload)) {
            return $default;
        }

        $value = trim((string) $payload[$key]);

        return $value === '' && $key === 'item_name' ? $default : $value;
    }
}
