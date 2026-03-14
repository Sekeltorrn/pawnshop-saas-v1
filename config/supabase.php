<?php

class Supabase {
    private $url;
    private $key;

    public function __construct() {
        // 1. CLOUD SMART CHECK: Try pulling from Render's Secure Vault first
        $this->url = getenv('SUPABASE_URL');
        $this->key = getenv('SUPABASE_KEY');

        // 2. LOCAL FALLBACK: If Render variables are empty, look for the .env file
        if (!$this->url || !$this->key) {
            $envPath = __DIR__ . '/../.env';
            
            if (!file_exists($envPath)) {
                die("Configuration Error: No Server Variables found, and .env file is missing at $envPath");
            }

            // --- THE BULLETPROOF .ENV READER ---
            $env = [];
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($name, $value) = explode('=', $line, 2);
                    $env[trim($name)] = trim($value, " \t\n\r\0\x0B\"");
                }
            }
            
            $this->url = $env['SUPABASE_URL'] ?? null;
            $this->key = $env['SUPABASE_KEY'] ?? null;
        }

        // Final sanity check
        if (!$this->url || !$this->key) {
            die("Configuration Error: SUPABASE_URL or SUPABASE_KEY is missing.");
        }
    }

    /**
     * Registers a new user in Supabase Auth
     * @param string $email
     * @param string $password
     * @param array $metaData (Contains full_name, business_name, country)
     * @return array ['code' => int, 'body' => array]
     */
    public function signUp($email, $password, $metaData = []) {
        $endpoint = rtrim($this->url, '/') . '/auth/v1/signup';

        // 2. Prepare the JSON Payload
        $data = [
            'email' => $email,
            'password' => $password,
            'data' => $metaData // This goes into 'raw_user_meta_data'
        ];

        $payload = json_encode($data);

        // 3. Initialize cURL (The messenger)
        $ch = curl_init($endpoint);

        // 4. Set Headers & Options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $this->key,           // The Anon Key
            'Content-Type: application/json',
            'Prefer: return=representation'    // Ask Supabase to return the user data immediately
        ]);

        // 5. Execute Request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        // 6. Network Error Handling
        if ($curlError) {
            return [
                'code' => 500,
                'body' => ['msg' => 'Network Error: ' . $curlError]
            ];
        }

        // 7. Return the result to register.php
        return [
            'code' => $httpCode,
            'body' => json_decode($response, true)
        ];
    } // End of signUp method

    public function signIn($email, $password) {
        $endpoint = rtrim($this->url, '/') . '/auth/v1/token?grant_type=password';

        $data = [
            'email' => $email,
            'password' => $password
        ];

        $payload = json_encode($data);

        $ch = curl_init($endpoint);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $this->key,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'code' => $httpCode,
            'body' => json_decode($response, true)
        ];
    } // End of signIn method

} // <--- THIS is the only closing bracket for the class, safely at the very bottom!

?>