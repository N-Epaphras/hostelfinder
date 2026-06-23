<?php
// PesaPal Configuration - SANDBOX MODE
// Replace with LIVE keys and base URL (https://pay.pesapal.com/v3) for production
define('PESAPAL_CONSUMER_KEY', '1SpIXn8beqUx1KVdLuqJTz3zmEWK8q+W');
define('PESAPAL_CONSUMER_SECRET', '+2X+wuBXUNs7QzA2MgGOVyPEcHQ=');
define('PESAPAL_BASE_URL', 'https://cybqa.pesapal.com/pesapalv3');
define('CURRENCY', 'UGX');

// Global variable to capture the last API error for easier debugging
$pesapal_last_error = '';

// Function to get PesaPal Auth Token (JWT)
function getPesapalToken() {
    $url = PESAPAL_BASE_URL . "/api/Auth/RequestToken";

    $data = [
        'consumer_key' => PESAPAL_CONSUMER_KEY,
        'consumer_secret' => PESAPAL_CONSUMER_SECRET
    ];

    $response = pesapalRequest($url, 'POST', $data);

    return $response['token'] ?? null;
}

// Generic function to make PesaPal API calls
function pesapalRequest($url, $method = 'POST', $data = null, $token = null) {
    global $pesapal_last_error;
    $ch = curl_init();
    
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // Bypasses SSL verification for local development (XAMPP). 
    // Remove this line when moving to a live/production server.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    if ($response === false) {
        $pesapal_last_error = curl_error($ch);
        error_log("Pesapal Curl Error: " . $pesapal_last_error);
        curl_close($ch);
        return null;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return json_decode($response, true);
    }
    $pesapal_last_error = "HTTP $httpCode: " . $response;
    error_log("Pesapal API Error: " . $pesapal_last_error);
    return null;
}
?>
