<?php
include 'forms/db.php';
session_start();

 $client_id = '1000.15ZGHC5Q0G3B0QJTAVSXJ6J3QAQUBW';
    $client_secret = '4204be10a9b2475aacdef6a8a77e9e5688e619d730';
$redirect_uri = 'http://dev.smartsolutionsdigi.com/pm/zoho/authcode.php';
$grant_token = '1000.dea24de68c4c3e589a0bfaf8895f4057.0d874fdb633728b00901498af004c944'; // Updated auth code

$token_url = "https://accounts.zoho.com.au/oauth/v2/token";

$post_fields = http_build_query([
    'grant_type' => 'authorization_code',
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri' => $redirect_uri,
    'code' => $grant_token,
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$tokens = json_decode($response, true);

if (isset($tokens['error']) && $tokens['error'] === "invalid_code") {
    echo "Invalid authorization code. Please generate a new one.";
    die;
}

// Ensure we got the tokens
if (!isset($tokens['access_token']) || !isset($tokens['refresh_token'])) {
    echo "Failed to get tokens.";
    die;
}

// Save tokens securely in the database
if (isset($tokens['refresh_token'])) {
    $sql = "INSERT INTO oauthtoken (client_id, refresh_token, access_token, grant_token, expiry_time) VALUES 
    (:client_id, :refresh_token, :access_token, :grant_token, :expiry_time)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'client_id' => $client_id,
        'refresh_token' => $tokens['refresh_token'],
        'access_token' => $tokens['access_token'],
        'grant_token' => $grant_token,
        'expiry_time' => $tokens["expires_in"]
    ]);

    if ($stmt) {
        echo "Token saved successfully.";
    } else {
        echo "Failed to save token.";
    }
}
?>
