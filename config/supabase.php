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


    /**
     * Verify an OTP (One Time Password) sent to an email.
     * @param string $email The user's email
     * @param string $token The 6-digit code
     * @param string $type The verification type (e.g., 'signup', 'recovery')
     * @return array Contains HTTP 'code' and decoded 'body'
     */
    public function verifyOtp($email, $token, $type = 'signup') {
        
        // Removed the ?type= from the URL endpoint
        $endpoint = rtrim($this->url, '/') . '/auth/v1/verify';

        // Added the 'type' directly into the JSON data payload
        $data = [
            'type'  => $type,
            'email' => $email,
            'token' => $token
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
        $curlError = curl_error($ch);
        
        curl_close($ch);

        if ($curlError) {
            return [
                'code' => 500,
                'body' => ['msg' => 'Network Error: ' . $curlError]
            ];
        }

        return [
            'code' => $httpCode,
            'body' => json_decode($response, true)
        ];
    } // End of verifyOtp method


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


    /**
     * Sends a 6-digit OTP for returning user verification.
     * @param string $email The user's email
     * @return array Contains HTTP 'code' and decoded 'body'
     */
    public function sendLoginOtp($email) {
        $endpoint = rtrim($this->url, '/') . '/auth/v1/otp';

        // create_user=false ensures it only sends to existing emails
        $payload = json_encode([
            'email' => $email,
            'create_user' => false 
        ]);

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
    } // End of sendLoginOtp method

    /**
     * Triggers the Password Recovery Email with a 6-digit OTP
     * @param string $email The user's email
     * @return array Contains HTTP 'code' and decoded 'body'
     */
    public function resetPassword($email) {
        $endpoint = rtrim($this->url, '/') . '/auth/v1/recover';

        $payload = json_encode([
            'email' => $email
        ]);

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
    } // End of resetPassword method

    /**
     * Updates a user's password using an active Access Token.
     * @param string $accessToken The temporary token from OTP verification
     * @param string $newPassword The new password
     * @return array Contains HTTP 'code' and decoded 'body'
     */
    public function updateUserPassword($accessToken, $newPassword) {
        $endpoint = rtrim($this->url, '/') . '/auth/v1/user';

        $payload = json_encode([
            'password' => $newPassword
        ]);

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT"); // Supabase uses PUT for updates
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        
        // NOTICE: We must pass the Bearer Access Token here to prove identity!
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $this->key, 
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'code' => $httpCode,
            'body' => json_decode($response, true)
        ];
    } // End of updateUserPassword method

    /**
     * Uploads a file to a Supabase Storage Bucket.
     * @param string $bucket Name of the bucket (e.g., 'compliance_documents')
     * @param string $fileTmpPath Path of the uploaded file on the server
     * @param string $destinationPath The filename to save as in the bucket
     * @param string $mimeType The file's MIME type (e.g., 'image/jpeg')
     * @return array Contains HTTP 'code' and decoded 'body'
     */
    public function uploadFile($bucket, $fileTmpPath, $destinationPath, $mimeType) {
        $endpoint = rtrim($this->url, '/') . '/storage/v1/object/' . $bucket . '/' . ltrim($destinationPath, '/');
        $fileData = file_get_contents($fileTmpPath);

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); // Supabase Storage uses POST for inserts
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $this->key, // Using Anon key for public bucket upload
            'Content-Type: ' . $mimeType
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'code' => $httpCode,
            'body' => json_decode($response, true)
        ];
    }

    /**
     * Updates a user's JSON compliance data in the public.profiles table.
     * Securely hunts for the Service Role Key to bypass RLS.
     * * @param string $userId The UUID of the user
     * @param array $jsonArray The full PHP array to be saved as JSONB
     * @return array Contains HTTP 'code' and decoded 'body'
     */
    public function updateComplianceData($userId, $jsonArray) {
        $endpoint = rtrim($this->url, '/') . '/rest/v1/profiles?id=eq.' . $userId;
        $payload = json_encode(['compliance_data' => $jsonArray]);

        // 1. SAFELY GRAB THE MASTER KEY (Bulletproof .env locator)
        $serviceKey = getenv('SUPABASE_SERVICE_KEY');
        
        if (!$serviceKey) {
            // Hunt for the .env file in the current, parent, or grandparent directory
            $possiblePaths = [
                __DIR__ . '/.env',
                __DIR__ . '/../.env',
                __DIR__ . '/../../.env'
            ];
            
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($lines as $line) {
                        if (strpos(trim($line), '#') === 0) continue;
                        if (strpos(trim($line), 'SUPABASE_SERVICE_KEY=') === 0) {
                            // Safely extract the key and strip any rogue quotes or spaces
                            $serviceKey = trim(substr(trim($line), strlen('SUPABASE_SERVICE_KEY=')), " \t\n\r\0\x0B\"'");
                            break 2; // Found it, break out of both loops
                        }
                    }
                }
            }
        }

        // Failsafe fallback to the public key (RLS will block it, but PHP won't crash)
        if (!$serviceKey) {
            $serviceKey = $this->key;
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $serviceKey,
            'Authorization: Bearer ' . $serviceKey,
            'Content-Type: application/json',
            'Prefer: return=representation' // Force Supabase to hand back the data
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $body = json_decode($response, true);

        // Anti-Trap Check: If RLS still blocks us, scream loudly
        if (($httpCode == 200 || $httpCode == 204) && empty($body)) {
            return [
                'code' => 403, 
                'body' => 'RLS silently blocked the update. Check your .env file path and SUPABASE_SERVICE_KEY.'
            ];
        }

        return [
            'code' => $httpCode,
            'body' => $body
        ];
    }

    /**
     * Fetches all unpaid tenants to check their compliance data for pending documents.
     * Securely hunts for the Service Role Key to bypass RLS.
     * @return array Contains HTTP 'code' and decoded 'body'
     */
    public function getPendingTenants() {
        // Query the profiles table for anyone who is 'unpaid'
        $endpoint = rtrim($this->url, '/') . '/rest/v1/profiles?payment_status=eq.unpaid&select=id,business_name,email,created_at,compliance_data';

        // 1. SAFELY GRAB THE MASTER KEY (Bulletproof .env locator)
        $serviceKey = getenv('SUPABASE_SERVICE_KEY');
        
        if (!$serviceKey) {
            // Hunt for the .env file in the current, parent, or grandparent directory
            $possiblePaths = [
                __DIR__ . '/.env',
                __DIR__ . '/../.env',
                __DIR__ . '/../../.env'
            ];
            
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($lines as $line) {
                        if (strpos(trim($line), '#') === 0) continue;
                        if (strpos(trim($line), 'SUPABASE_SERVICE_KEY=') === 0) {
                            // Safely extract the key and strip any rogue quotes or spaces
                            $serviceKey = trim(substr(trim($line), strlen('SUPABASE_SERVICE_KEY=')), " \t\n\r\0\x0B\"'");
                            break 2; // Found it, break out of both loops
                        }
                    }
                }
            }
        }

        // Failsafe fallback to the public key (RLS will block it, but PHP won't crash)
        if (!$serviceKey) {
            $serviceKey = $this->key;
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET"); 
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $serviceKey,
            'Authorization: Bearer ' . $serviceKey,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'code' => $httpCode,
            'body' => json_decode($response, true)
        ];
    }

    /**
     * Fetches the compliance data for a specific single tenant.
     * Securely hunts for the Service Role Key to bypass RLS.
     * @param string $tenantId The UUID of the tenant
     * @return array Contains HTTP 'code' and decoded 'body'
     */
    public function getComplianceData($tenantId) {
        $endpoint = rtrim($this->url, '/') . '/rest/v1/profiles?id=eq.' . $tenantId . '&select=compliance_data';

        // 1. SAFELY GRAB THE MASTER KEY (Bulletproof .env locator)
        $serviceKey = getenv('SUPABASE_SERVICE_KEY');
        
        if (!$serviceKey) {
            // Hunt for the .env file in the current, parent, or grandparent directory
            $possiblePaths = [
                __DIR__ . '/.env',
                __DIR__ . '/../.env',
                __DIR__ . '/../../.env'
            ];
            
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($lines as $line) {
                        if (strpos(trim($line), '#') === 0) continue;
                        if (strpos(trim($line), 'SUPABASE_SERVICE_KEY=') === 0) {
                            // Safely extract the key and strip any rogue quotes or spaces
                            $serviceKey = trim(substr(trim($line), strlen('SUPABASE_SERVICE_KEY=')), " \t\n\r\0\x0B\"'");
                            break 2; // Found it, break out of both loops
                        }
                    }
                }
            }
        }

        // Failsafe fallback to the public key
        if (!$serviceKey) {
            $serviceKey = $this->key;
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET"); 
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $serviceKey,
            'Authorization: Bearer ' . $serviceKey,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'code' => $httpCode,
            'body' => json_decode($response, true)
        ];
    }

    /**
     * Upgrades a tenant's payment_status to 'paid' to unlock their dashboard.
     * @param string $tenantId The UUID of the tenant
     * @param string $newStatus The new status (e.g., 'paid')
     */
    public function updatePaymentStatus($tenantId, $newStatus = 'paid') {
        $endpoint = rtrim($this->url, '/') . '/rest/v1/profiles?id=eq.' . $tenantId;
        $payload = json_encode(['payment_status' => $newStatus]);

        // Grab the Master Key to bypass RLS
        $serviceKey = $this->key;
        $envPath = __DIR__ . '/../.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($name, $value) = explode('=', $line, 2);
                    if (trim($name) === 'SUPABASE_SERVICE_KEY') {
                        $serviceKey = trim($value, " \t\n\r\0\x0B\"");
                        break;
                    }
                }
            }
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH"); 
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $serviceKey,
            'Authorization: Bearer ' . $serviceKey,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'code' => $httpCode,
            'body' => json_decode($response, true)
        ];
    }

} // <--- THIS is the only closing bracket for the class, safely at the very bottom!

?>