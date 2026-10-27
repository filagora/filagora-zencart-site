<?php

return [
    'flash' => [
        'membership_started' => 'Membership activated.',
        'subscription_start_unavailable' => 'Unable to start that membership.',
        'checkout_completed' => 'Checkout completed. We will activate your membership as soon as the payment provider confirms the subscription.',
        'checkout_canceled' => 'Checkout canceled.',
    ],
    'messages' => [
        'membership_activated' => 'Membership activated.',
        'membership_canceling' => 'Membership will cancel at the end of the current billing period.',
        'membership_canceled' => 'Membership canceled.',
    ],
    'errors' => [
        'not_configured' => 'This membership is not yet configured for billing.',
        'unsupported_provider' => 'This membership uses a billing provider that is not supported yet.',
        'invalid_product_id' => 'Stripe billing expects a Price ID like price_... but this plan is using a Product ID like prod_.... Update the membership plan to use the Stripe price ID.',
        'price_not_found' => 'Stripe could not find that price. Make sure the membership plan uses the Stripe Price ID like price_..., not the Product ID like prod_....',
        'paypal_not_configured' => 'PayPal billing is not fully configured. Add the PayPal API credentials and webhook id before enabling PayPal plans.',
        'paypal_create_failed' => 'PayPal could not start that subscription. Please try again later.',
        'paypal_webhook_invalid' => 'PayPal webhook signature verification failed.',
    ],
];
