<?php
// Including DB Connection & Access Token File
ob_start();
include 'db.php';
include '../access-token.php';

/**
 * Step 1: Collecting the Form Fields 
 * Step 2: Inserting into DB (ctpquote)
 * Step 3: Sending data to Zoho CRM
 * Step 4: Sending Email Notification
 */

// Mapping form fields to match Zoho CRM
function mapFormFields($formData) {
    return [
        'Last_Name' => $formData['full-name'] ?? '',
        'Address' => $formData['address'] ?? '',
        'Phone' => $formData['contact-number'] ?? '',
        'Email' => $formData['email'] ?? '',
        'Occupation' => $formData['occupation'] ?? '',
        'Billing_Number' => $formData['billing-number'] ?? '',
        'Plate_Number' => $formData['plate-number'] ?? '',
        'VIN_Number' => $formData['vin-number'] ?? '',
        'Customer_Number' => $formData['customer-number'] ?? '',
        'Vehicle_Status' => $formData['vehicle-status'] ?? '',
        'Year_Manufacture' => $formData['year-manufacture'] ?? '',
        'Make_Vehicle' => $formData['make-vehicle'] ?? '',
        'Vehicle_Shape' => $formData['vehicle-shape'] ?? '',
        'Vehicle_GVM' => $formData['vehicle-gvm'] ?? '',
        'Vehicle_Usage' => $formData['vehicle-usage'] ?? '',
        'Postcode' => $formData['postcode'] ?? '',
        'Suburb' => $formData['suburb'] ?? '',
        'Customer_Type' => $formData['customer-type'] ?? '',
        'Insurance_Duration' => $formData['insurance-duration'] ?? '',
        'Input_Tax_Credit' => $formData['input-tax-credit'] ?? '',
       'Layout'=> [
                  'name'=> 'Standard',
            'id' => '58760000004939683'
        ]
         

    ];
}

// Function to insert data into the ctpquote database
function insertDataIntoDatabase($mappedData, $pdo) {
    try {
        $sql = "INSERT INTO excavator(
                    full_name, address, contact_number, email, occupation, billing_number, 
                    plate_number, vin_number, customer_number, vehicle_status, year_manufacture, 
                    make_vehicle, vehicle_shape, vehicle_gvm, vehicle_usage, postcode, suburb, 
                    customer_type, insurance_duration, input_tax_credit
                ) VALUES (
                    :full_name, :address, :contact_number, :email, :occupation, :billing_number,
                    :plate_number, :vin_number, :customer_number, :vehicle_status, :year_manufacture,
                    :make_vehicle, :vehicle_shape, :vehicle_gvm, :vehicle_usage, :postcode, :suburb,
                    :customer_type, :insurance_duration, :input_tax_credit
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':full_name' => $mappedData['Last_Name'],
            ':address' => $mappedData['Address'],
            ':contact_number' => $mappedData['Phone'],
            ':email' => $mappedData['Email'],
            ':occupation' => $mappedData['Occupation'],
            ':billing_number' => $mappedData['Billing_Number'],
            ':plate_number' => $mappedData['Plate_Number'],
            ':vin_number' => $mappedData['VIN_Number'],
            ':customer_number' => $mappedData['Customer_Number'],
            ':vehicle_status' => $mappedData['Vehicle_Status'],
            ':year_manufacture' => $mappedData['Year_Manufacture'],
            ':make_vehicle' => $mappedData['Make_Vehicle'],
            ':vehicle_shape' => $mappedData['Vehicle_Shape'],
            ':vehicle_gvm' => $mappedData['Vehicle_GVM'],
            ':vehicle_usage' => $mappedData['Vehicle_Usage'],
            ':postcode' => $mappedData['Postcode'],
            ':suburb' => $mappedData['Suburb'],
            ':customer_type' => $mappedData['Customer_Type'],
            ':insurance_duration' => $mappedData['Insurance_Duration'],
            ':input_tax_credit' => $mappedData['Input_Tax_Credit']
        ]);

        return true;
    } catch (PDOException $e) {
        echo "Database Error: " . $e->getMessage();
        return false;
    }
}

// Function to send data to Zoho CRM
function addRecordToZoho($mappedData, $pdo) {
    getAccessToken($pdo);
    $accessToken = $_SESSION['access_token'] ?? null;
    if (!$accessToken) {
        echo "Error: Missing Zoho access token.";
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
        $errorData = json_decode($response, true);
        echo "Zoho API Error ({$httpCode}): " . ($errorData['message'] ?? $response);
        return false;
    }
}

// Function to send email notification
// Function to send email notification in table format
function sendEmailNotification($mappedData) {
    $to = "madhkunchala@gmail.com"; // Change to admin email
    $subject = "New CTP Inquiry Insurance Inquiry";
     $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: no-reply@ilinkinsurance.com.au\r\n";
            $headers .= "Reply-To: $userEmail\r\n";
    $message = "
    <html>
    <head>
        <title>New CTP Inquiry</title>
        <style>
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 10px;
                text-align: left;
            }
            th {
                background-color: #f2f2f2;
            }
            body {
                font-family: Arial, sans-serif;
            }
        </style>
    </head>
    <body>
        <h2>New CTP Inquiry Received</h2>
        <table>
            <tr><th>Field</th><th>Details</th></tr>
            <tr><td><strong>Full Name</strong></td><td>{$mappedData['Last_Name']}</td></tr>
            <tr><td><strong>Email</strong></td><td>{$mappedData['Email']}</td></tr>
            <tr><td><strong>Phone</strong></td><td>{$mappedData['Phone']}</td></tr>
            <tr><td><strong>Address</strong></td><td>{$mappedData['Address']}</td></tr>
            <tr><td><strong>Vehicle Make</strong></td><td>{$mappedData['Make_Vehicle']}</td></tr>
            <tr><td><strong>Vehicle Year</strong></td><td>{$mappedData['Year_Manufacture']}</td></tr>
            <tr><td><strong>Customer Type</strong></td><td>{$mappedData['Customer_Type']}</td></tr>
            <tr><td><strong>Billing Number</strong></td><td>{$mappedData['Billing_Number']}</td></tr>
            <tr><td><strong>Plate Number</strong></td><td>{$mappedData['Plate_Number']}</td></tr>
            <tr><td><strong>VIN Number</strong></td><td>{$mappedData['VIN_Number']}</td></tr>
            <tr><td><strong>Customer Number</strong></td><td>{$mappedData['Customer_Number']}</td></tr>
            <tr><td><strong>Vehicle Status</strong></td><td>{$mappedData['Vehicle_Status']}</td></tr>
            <tr><td><strong>Vehicle Shape</strong></td><td>{$mappedData['Vehicle_Shape']}</td></tr>
            <tr><td><strong>Vehicle GVM</strong></td><td>{$mappedData['Vehicle_GVM']}</td></tr>
            <tr><td><strong>Vehicle Usage</strong></td><td>{$mappedData['Vehicle_Usage']}</td></tr>
            <tr><td><strong>Postcode</strong></td><td>{$mappedData['Postcode']}</td></tr>
            <tr><td><strong>Suburb</strong></td><td>{$mappedData['Suburb']}</td></tr>
            <tr><td><strong>Insurance Duration</strong></td><td>{$mappedData['Insurance_Duration']}</td></tr>
            <tr><td><strong>Input Tax Credit</strong></td><td>{$mappedData['Input_Tax_Credit']}</td></tr>
        </table>
    </body>
    </html>";

    return mail($to, $subject, $message, $headers);
}


// Main logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mappedData = mapFormFields($_POST);

    if (empty($mappedData['Last_Name']) || empty($mappedData['Email']) || empty($mappedData['Phone'])) {
        header("Location: error.html");
        exit;
    }

    if (insertDataIntoDatabase($mappedData, $pdo)) {
        if (addRecordToZoho($mappedData, $pdo)) {
            sendEmailNotification($mappedData); // Send email after success
            header("Location: ../../thankyou.html");
            
        } else {
            header("Location: /error.html");
        
        }
    } else {
        header("Location: /error.html");
        exit;
    }
} else {
    header("Location: /error.html");
    exit;
}
