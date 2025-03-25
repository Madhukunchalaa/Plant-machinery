<?php
// Start output buffering
ob_start(); 

// Include necessary files
include 'db.php';
include '../access-token.php';


$secretKey = "6Lc_e-0qAAAAAKW_gmvj9B-pbNHOdmxE63JF8qX2";  // Your Secret Key
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

// Function to format phone number for Zoho
function formatPhoneNumber($phone) {
    // Remove all non-numeric characters
    $cleanedPhone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check if it's an Australian number
    if (strlen($cleanedPhone) >= 10) {
        // Format to expected Zoho format (e.g., +61 4XX XXX XXX)
        if (substr($cleanedPhone, 0, 2) == '61') {
            return '+' . $cleanedPhone;
        } else if (substr($cleanedPhone, 0, 1) == '0') {
            return '+61' . substr($cleanedPhone, 1);
        } else {
            return '+61' . $cleanedPhone;
        }
    }
    
    // If not Australian or format unknown, return as is
    return $phone;
}

// Step 1: Collect form data and map to Zoho CRM fields
function mapFormFields($formData) {
    return [
        'Last_Name' => $formData['name'] ?? '',
        'Phone' => formatPhoneNumber($formData['phone'] ?? ''),
        'Email' => $formData['email'] ?? '',
        'Suburb' => $formData['state'] ?? '',
        'Layout'=> [
            'name'=> 'Standard',
            'id' => '58760000004939683'
        ]
    ];
}

// Function to insert data into the database
function insertDataIntoDatabase($mappedData, $pdo) {
    try {
        $sql = "INSERT INTO homepage (name, email, phone, state) 
                VALUES (:name, :email, :phone, :state)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $mappedData['Last_Name'],
            ':email' => $mappedData['Email'],
            ':phone' => $mappedData['Phone'],
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
    
    // Log the data being sent for debugging
    error_log("Sending to Zoho: " . $jsonData);

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

    // Log the response for debugging
    error_log("Zoho Response: " . $response);

    if ($httpCode === 201 || $httpCode === 200) {
        return true;
    } else {
        error_log("Zoho API Error ({$httpCode}): " . $response);
        return false;
    }
}

// Main logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mappedData = mapFormFields($_POST);

    if (empty($mappedData['Last_Name']) || empty($mappedData['Email']) || empty($mappedData['Phone'])) {
        header("Location: /error.html");
        exit();
    }

    // Insert data into the database
    if (insertDataIntoDatabase($mappedData, $pdo)) {
        if (addRecordToZoho($mappedData, $pdo)) {
            // Send Email
            $fullName = htmlspecialchars($_POST['name']);
            $userEmail = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ? $_POST['email'] : null;
            $phoneNumber = htmlspecialchars($_POST['phone']);
            $userState = htmlspecialchars($_POST['state']);

            if (!$userEmail) {
                echo "Invalid email address.";
                exit();
            }

            $to = "info@ilinkinsurance.com.au, smartsolutions.designstudio@gmail.com, madhkunchala@gmail.com, quotes@ilinkinsurance.com.au";
            $subject = "New Homepage Inquiry from " . $fullName;

            $message = "
            <html>
            <head>
              <title>Homepage Inquiry</title>
              <style>
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                th { background-color: #f2f2f2; }
              </style>
            </head>
            <body>
              <h2>New Homepage Inquiry</h2>
              <table>
                <tr><th>Full Name</th><td>$fullName</td></tr>
                <tr><th>Email</th><td>$userEmail</td></tr>
                <tr><th>Phone Number</th><td>$phoneNumber</td></tr>
                <tr><th>State</th><td>$userState</td></tr>
              </table>
            </body>
            </html>
            ";

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: no-reply@yourdomain.com\r\n";
            $headers .= "Reply-To: $userEmail\r\n";

            if (mail($to, $subject, $message, $headers)) {
                header("Location: ../../thankyou.html");
                exit();
            } else {
                error_log("Failed to send email.");
            }
        } else {
            // Log this specific error
            error_log("Failed to add record to Zoho CRM");
        }
    } else {
        error_log("Failed to insert data into database");
    }
}

header("Location: /error.html");
exit();
?>