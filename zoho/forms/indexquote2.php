<?php

include("db.php");
include("../access-token.php");



$secretKey = "6LeD8O8qAAAAAPfdzzToQ7wQM6RzzYJ7HWEj4zfv";  // Your Secret Key
$responseKey = $_POST['g-recaptcha-response'];  // The response from the reCAPTCHA widget
$userIP = $_SERVER['REMOTE_ADDR'];  // Get the user's IP address

// Send a POST request to Google's reCAPTCHA server for verification
$verifyURL = "https://www.google.com/recaptcha/api/siteverify";
$response = file_get_contents($verifyURL . "?secret=" . $secretKey . "&response=" . $responseKey . "&remoteip=" . $userIP);

// Decode the JSON response from Google
$responseData = json_decode($response);

// Check if the reCAPTCHA was successfully verified
if ($responseData->success) {
    // Proceed with form processing, saving to the database, sending to Zoho CRM, etc.
    echo "reCAPTCHA verified successfully!";
} else {
    // If verification fails, handle the error
    echo "reCAPTCHA verification failed!";
    
}









// Function to send an email
function sendMail($to, $subject, $message) {
    $headers = "From: no-reply@example.com\r\n";
    $headers .= "Reply-To: no-reply@example.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Function to map form fields to Zoho CRM fields
function mapFormFields($formData) {
    return [
        'Last_Name' => $formData['name'] ?? '',
        'Email' => $formData['email'] ?? '',
        'Phone' => $formData['phone'] ?? '',
        'Suburb' => $formData['state'] ?? '',
        'Zip_Code' => $formData['zip'] ?? ''
    ];
}

// Function to insert data into the database
function insertDataIntoDatabase($mappedData, $pdo) {
    try {
        $sql = "INSERT INTO homepage2 (name, email, phone, zip, state)
                VALUES (:name, :email, :phone, :zip, :state)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name'  => $mappedData['Last_Name'],
            ':email' => $mappedData['Email'],
            ':phone' => $mappedData['Phone'],
            ':zip'   => $mappedData['Zip_Code'],
            ':state' => $mappedData['Suburb']
        ]);

        return true;
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        return false;
    }
}

// Function to send data to Zoho CRM
function addRecordToZoho($mappedData, $pdo) {
    getAccessToken($pdo);
    $accessToken = $_SESSION['access_token'] ?? null;

    if (!$accessToken) {
        error_log("Error: Missing Zoho access token.");
        return false;
    }

    $module = 'Leads';
    $apiUrl = "https://www.zohoapis.com.au/crm/v2/$module";
    $data = ['data' => [$mappedData]];
    $jsonData = json_encode($data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Zoho-oauthtoken $accessToken",
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 201) {
        return true;
    } else {
        error_log("Zoho API Error ({$httpCode}): " . $response);
        return false;
    }
}

// Processing form data
$formData = $_POST;
$mappedData = mapFormFields($formData);
$userMessage = $_POST['message'] ?? ''; // Ensure the message field is defined

// Email Details
$to = "madhkunchala@gmail.com";
$subject = "New Form Submission";
$message = "
<html>
<head>
  <title>Insurance Inquiry</title>
  <style>
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
    th { background-color: #f2f2f2; }
  </style>
</head>
<body>
  <h2>New Insurance Inquiry</h2>
  <table>
    <tr><th>Full Name</th><td>{$mappedData['Last_Name']}</td></tr>
    <tr><th>Email</th><td>{$mappedData['Email']}</td></tr>
    <tr><th>Phone Number</th><td>{$mappedData['Phone']}</td></tr>
    <tr><th>City</th><td>{$mappedData['Suburb']}</td></tr>
    <tr><th>Zip Code</th><td>{$mappedData['Zip_Code']}</td></tr>
    <tr><th>Message</th><td>{$userMessage}</td></tr>
  </table>
</body>
</html>";

// Insert into the database
if (insertDataIntoDatabase($mappedData, $pdo)) {
    // Send data to Zoho CRM
    addRecordToZoho($mappedData, $pdo);

    // Send email
    if (sendMail($to, $subject, $message)) {
        // Redirect to the thank-you page after successful submission
        header("Location: ../../thankyou.html");
        exit();
    } else {
        echo "Data saved but email sending failed!";
    }
} else {
    echo "Database insertion failed!";
}

?>
