<?php
// config/paymongo.php

/**
 * Generates a PayMongo Checkout Session URL
 * * @param float $amount The amount in PHP (e.g. 1280.50)
 * @param string $description What the user sees on the checkout page
 * @param string $reference_number Your internal tracking ID (e.g. PT-00123-RENEW)
 * @param array $customer Optional: ['name' => 'John Doe', 'email' => 'juan@example.com', 'phone' => '09123456789']
 * @return array ['success' => true, 'checkout_url' => 'https://...', 'checkout_id' => 'cs_...'] OR ['success' => false, 'error' => '...']
 */
function createPaymongoCheckout($amount, $description, $reference_number, $customer = [], $custom_success_url = null) {
    // 1. LOAD SECRET KEY (From Render Env Vars or local fallback)
    $secret_key = getenv('PAYMONGO_SECRET_KEY');
    
    if (!$secret_key) {
        // Fallback for local development if getenv() isn't loading the .env file directly
        $env_path = __DIR__ . '/../.env';
        if (file_exists($env_path)) {
            $env = [];
            $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($name, $value) = explode('=', $line, 2);
                    $env[trim($name)] = trim($value, " \t\n\r\0\x0B\"");
                }
            }
            $secret_key = $env['PAYMONGO_SECRET_KEY'] ?? null;
        }
    }

    if (!$secret_key) {
        return ['success' => false, 'error' => 'System Error: PayMongo Secret Key not configured.'];
    }

    // 2. FORMAT AMOUNT (PayMongo strictly requires integers in Centavos. ₱100.50 = 10050)
    $amount_in_centavos = intval(round($amount * 100));

    // 3. BUILD THE PAYLOAD
    // Determine the base URL for redirects (production domain or local .test)
    $base_url = getenv('APP_URL') ?: 'http://pawnshop-saas-v1.test';
    
    $payload = [
        'data' => [
            'attributes' => [
                'send_email_receipt' => true,
                'show_description' => true,
                'show_line_items' => true,
                'description' => $description,
                'line_items' => [
                    [
                        'currency' => 'PHP',
                        'amount' => $amount_in_centavos,
                        'description' => 'Pawnshop SaaS Transaction',
                        'name' => $description,
                        'quantity' => 1
                    ]
                ],
                'payment_method_types' => [
                    'gcash',
                    'paymaya',
                    'card', 
                    'qrph'
                ],
                'reference_number' => $reference_number,
                'success_url' => $custom_success_url ? $custom_success_url : $base_url . '/api/payment_success.php?reference=' . urlencode($reference_number),
                'cancel_url' => $base_url . '/api/payment_cancel.php?reference=' . urlencode($reference_number)
            ]
        ]
    ];

    // Add customer billing info if provided (Makes checkout faster for them)
    if (!empty($customer)) {
        $payload['data']['attributes']['billing'] = [
            'name' => $customer['name'] ?? 'Pawnereno Customer',
            'email' => $customer['email'] ?? 'no-reply@pawnereno.com',
            'phone' => $customer['phone'] ?? ''
        ];
    }

    // 4. EXECUTE CURL REQUEST TO PAYMONGO
    $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    
    // PayMongo uses Basic Auth with the Secret Key as the username (no password)
    $auth_string = base64_encode($secret_key . ':');
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . $auth_string
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    // 5. HANDLE RESPONSE
    if ($http_code === 200 && isset($result['data']['attributes']['checkout_url'])) {
        return [
            'success' => true,
            'checkout_url' => $result['data']['attributes']['checkout_url'],
            'checkout_id' => $result['data']['id']
        ];
    } else {
        $error_msg = $result['errors'][0]['detail'] ?? 'Unknown PayMongo API Error';
        return [
            'success' => false,
            'error' => "Gateway Error ($http_code): " . $error_msg
        ];
    }
}
?>