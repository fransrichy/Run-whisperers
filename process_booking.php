<?php
// process_booking.php
// Run Whisperers Cricket Academy - Booking Processing Script

// Database Configuration (Uncomment and configure when database is ready)
/*
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'runwhisperers_db');
*/

// Email Configuration
define('ADMIN_EMAIL', 'bookings@runwhispererscricket.com');
define('SITE_NAME', 'Run Whisperers Cricket Academy');
define('SITE_URL', 'https://runwhispererscricket.com');

// WhatsApp Configuration
define('WHATSAPP_NUMBER', '264811234567');
define('WHATSAPP_API_KEY', 'your_whatsapp_api_key'); // For WhatsApp Business API

// SMS Configuration (using Twilio or similar service)
define('SMS_API_KEY', 'your_sms_api_key');
define('SMS_SENDER_ID', 'RunWhisperers');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize form data
    $bookingData = [
        'booking_id' => generateBookingID(),
        'booking_type' => sanitizeInput($_POST['booking_type'] ?? ''),
        'booking_date' => date('Y-m-d H:i:s'),
        'full_name' => sanitizeInput($_POST['full_name'] ?? ''),
        'email' => sanitizeInput($_POST['email'] ?? ''),
        'phone' => sanitizeInput($_POST['phone'] ?? ''),
        'dob' => sanitizeInput($_POST['dob'] ?? ''),
        'address' => sanitizeInput($_POST['address'] ?? ''),
        'emergency_contact_name' => sanitizeInput($_POST['emergency_contact_name'] ?? ''),
        'emergency_contact_phone' => sanitizeInput($_POST['emergency_contact_phone'] ?? ''),
        'medical_conditions' => sanitizeInput($_POST['medical_conditions'] ?? ''),
        'experience_level' => sanitizeInput($_POST['experience_level'] ?? ''),
        'training_goals' => sanitizeInput($_POST['training_goals'] ?? ''),
        'notify_email' => isset($_POST['notify_email']) ? 1 : 0,
        'notify_whatsapp' => isset($_POST['notify_whatsapp']) ? 1 : 0,
        'notify_sms' => isset($_POST['notify_sms']) ? 1 : 0,
        'payment_method' => sanitizeInput($_POST['payment_method'] ?? ''),
        'agree_terms' => isset($_POST['agree_terms']) ? 1 : 0
    ];

    // Handle different booking types
    switch ($bookingData['booking_type']) {
        case 'training':
            $bookingData['program'] = sanitizeInput($_POST['program'] ?? '');
            $bookingData['program_price'] = floatval($_POST['program_price'] ?? 0);
            $bookingData['training_date'] = sanitizeInput($_POST['training_date'] ?? '');
            $bookingData['training_time'] = sanitizeInput($_POST['training_time'] ?? '');
            $bookingData['coach'] = sanitizeInput($_POST['coach'] ?? '');
            
            // Payment details for card payments
            if ($bookingData['payment_method'] === 'card') {
                $bookingData['card_number'] = maskCardNumber($_POST['card_number'] ?? '');
                $bookingData['card_holder'] = sanitizeInput($_POST['card_holder'] ?? '');
                $bookingData['card_expiry'] = sanitizeInput($_POST['card_expiry'] ?? '');
            }
            break;
            
        case 'camping':
            $bookingData['camping_package'] = sanitizeInput($_POST['camping_package'] ?? '');
            $bookingData['camping_price'] = floatval($_POST['camping_price'] ?? 0);
            $bookingData['participants'] = intval($_POST['participants'] ?? 1);
            $bookingData['preferred_dates'] = sanitizeInput($_POST['preferred_dates'] ?? '');
            break;
            
        case 'package':
            $bookingData['academy_package'] = sanitizeInput($_POST['academy_package'] ?? '');
            $bookingData['package_price'] = floatval($_POST['package_price'] ?? 0);
            $bookingData['start_date'] = sanitizeInput($_POST['start_date'] ?? '');
            break;
    }

    // Calculate total amount
    $bookingData['total_amount'] = calculateTotalAmount($bookingData);
    
    // Generate payment reference
    $bookingData['payment_reference'] = generatePaymentReference();

    // Validate required fields
    $validationErrors = validateBookingData($bookingData);
    
    if (!empty($validationErrors)) {
        // Return validation errors
        sendErrorResponse($validationErrors);
    }

    // Save to database (Uncomment when database is ready)
    /*
    $saved = saveToDatabase($bookingData);
    if (!$saved) {
        sendErrorResponse(['database' => 'Failed to save booking to database']);
    }
    */

    // Send notifications
    $notificationsSent = sendNotifications($bookingData);
    
    // Send email confirmation to admin
    sendAdminNotification($bookingData);
    
    // Send email confirmation to customer
    if ($bookingData['notify_email']) {
        sendCustomerEmail($bookingData);
    }
    
    // Send WhatsApp message
    if ($bookingData['notify_whatsapp']) {
        sendWhatsAppMessage($bookingData);
    }
    
    // Send SMS
    if ($bookingData['notify_sms']) {
        sendSMS($bookingData);
    }

    // Return success response
    sendSuccessResponse($bookingData);
}

// Helper Functions

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function generateBookingID() {
    return 'RW' . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

function generatePaymentReference() {
    return 'PAY' . date('Ymd') . rand(1000, 9999);
}

function maskCardNumber($cardNumber) {
    $cleaned = preg_replace('/[^0-9]/', '', $cardNumber);
    if (strlen($cleaned) >= 4) {
        return '**** **** **** ' . substr($cleaned, -4);
    }
    return '**** **** **** ****';
}

function calculateTotalAmount($data) {
    $basePrice = 0;
    $registrationFee = 200;
    
    switch ($data['booking_type']) {
        case 'training':
            $basePrice = $data['program_price'] ?? 0;
            break;
        case 'camping':
            $basePrice = $data['camping_price'] ?? 0;
            $participants = $data['participants'] ?? 1;
            $basePrice *= $participants;
            break;
        case 'package':
            $basePrice = $data['package_price'] ?? 0;
            break;
    }
    
    return $basePrice + $registrationFee;
}

function validateBookingData($data) {
    $errors = [];
    
    // Common validations
    if (empty($data['full_name'])) {
        $errors['full_name'] = 'Full name is required';
    }
    
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Valid email is required';
    }
    
    if (empty($data['phone'])) {
        $errors['phone'] = 'Phone number is required';
    }
    
    if (empty($data['emergency_contact_name'])) {
        $errors['emergency_contact_name'] = 'Emergency contact name is required';
    }
    
    if (empty($data['emergency_contact_phone'])) {
        $errors['emergency_contact_phone'] = 'Emergency contact phone is required';
    }
    
    if (!$data['agree_terms']) {
        $errors['terms'] = 'You must agree to the terms and conditions';
    }
    
    // Booking type specific validations
    switch ($data['booking_type']) {
        case 'training':
            if (empty($data['program'])) {
                $errors['program'] = 'Please select a training program';
            }
            if (empty($data['training_date'])) {
                $errors['training_date'] = 'Please select a training date';
            }
            if (empty($data['training_time'])) {
                $errors['training_time'] = 'Please select a training time';
            }
            if (empty($data['coach'])) {
                $errors['coach'] = 'Please select a coach';
            }
            if (empty($data['payment_method'])) {
                $errors['payment_method'] = 'Please select a payment method';
            }
            break;
            
        case 'camping':
            if (empty($data['camping_package'])) {
                $errors['camping_package'] = 'Please select a camping package';
            }
            break;
            
        case 'package':
            if (empty($data['academy_package'])) {
                $errors['academy_package'] = 'Please select an academy package';
            }
            break;
    }
    
    return $errors;
}

function sendErrorResponse($errors) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please fix the errors below',
        'errors' => $errors
    ]);
    exit;
}

function sendSuccessResponse($data) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Booking submitted successfully!',
        'booking_id' => $data['booking_id'],
        'payment_reference' => $data['payment_reference'],
        'total_amount' => $data['total_amount'],
        'notification_methods' => [
            'email' => $data['notify_email'],
            'whatsapp' => $data['notify_whatsapp'],
            'sms' => $data['notify_sms']
        ]
    ]);
    exit;
}

function saveToDatabase($data) {
    // Database connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        return false;
    }
    
    // Prepare SQL statement based on booking type
    switch ($data['booking_type']) {
        case 'training':
            $sql = "INSERT INTO training_bookings (booking_id, full_name, email, phone, program, program_price, training_date, training_time, coach, payment_method, total_amount, payment_reference, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssdssssds", 
                $data['booking_id'],
                $data['full_name'],
                $data['email'],
                $data['phone'],
                $data['program'],
                $data['program_price'],
                $data['training_date'],
                $data['training_time'],
                $data['coach'],
                $data['payment_method'],
                $data['total_amount'],
                $data['payment_reference']
            );
            break;
            
        case 'camping':
            $sql = "INSERT INTO camping_bookings (booking_id, full_name, email, phone, camping_package, camping_price, participants, preferred_dates, payment_method, total_amount, payment_reference, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssdissds", 
                $data['booking_id'],
                $data['full_name'],
                $data['email'],
                $data['phone'],
                $data['camping_package'],
                $data['camping_price'],
                $data['participants'],
                $data['preferred_dates'],
                $data['payment_method'],
                $data['total_amount'],
                $data['payment_reference']
            );
            break;
            
        case 'package':
            $sql = "INSERT INTO package_bookings (booking_id, full_name, email, phone, academy_package, package_price, start_date, payment_method, total_amount, payment_reference, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssdssds", 
                $data['booking_id'],
                $data['full_name'],
                $data['email'],
                $data['phone'],
                $data['academy_package'],
                $data['package_price'],
                $data['start_date'],
                $data['payment_method'],
                $data['total_amount'],
                $data['payment_reference']
            );
            break;
    }
    
    $success = $stmt->execute();
    
    if (!$success) {
        error_log("Database error: " . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
    
    return $success;
}

function sendCustomerEmail($data) {
    $to = $data['email'];
    $subject = "Booking Confirmation - " . SITE_NAME;
    
    // Prepare email body based on booking type
    $body = prepareEmailBody($data);
    
    $headers = "From: " . SITE_NAME . " <" . ADMIN_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $body, $headers);
}

function sendAdminNotification($data) {
    $to = ADMIN_EMAIL;
    $subject = "New Booking Received - " . $data['booking_id'];
    
    $body = "<h2>New Booking Received</h2>";
    $body .= "<p><strong>Booking ID:</strong> " . $data['booking_id'] . "</p>";
    $body .= "<p><strong>Booking Type:</strong> " . ucfirst($data['booking_type']) . "</p>";
    $body .= "<p><strong>Customer:</strong> " . $data['full_name'] . "</p>";
    $body .= "<p><strong>Email:</strong> " . $data['email'] . "</p>";
    $body .= "<p><strong>Phone:</strong> " . $data['phone'] . "</p>";
    $body .= "<p><strong>Total Amount:</strong> N$ " . number_format($data['total_amount'], 2) . "</p>";
    $body .= "<p><strong>Payment Reference:</strong> " . $data['payment_reference'] . "</p>";
    $body .= "<p><strong>Date:</strong> " . $data['booking_date'] . "</p>";
    
    $headers = "From: " . SITE_NAME . " <" . ADMIN_EMAIL . ">\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $body, $headers);
}

function prepareEmailBody($data) {
    $body = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Booking Confirmation</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #1a472a; color: white; padding: 20px; text-align: center; }
            .content { background-color: #f9f7f3; padding: 30px; }
            .footer { background-color: #f5f1e8; padding: 20px; text-align: center; font-size: 12px; }
            .booking-details { background-color: white; padding: 20px; border-radius: 5px; margin: 20px 0; }
            .total-amount { font-size: 18px; font-weight: bold; color: #1a472a; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . SITE_NAME . '</h1>
                <h2>Booking Confirmation</h2>
            </div>
            <div class="content">
                <p>Dear ' . $data['full_name'] . ',</p>
                <p>Thank you for booking with Run Whisperers Cricket Academy! Your booking has been confirmed.</p>
                
                <div class="booking-details">
                    <h3>Booking Details</h3>
                    <p><strong>Booking ID:</strong> ' . $data['booking_id'] . '</p>
                    <p><strong>Booking Type:</strong> ' . ucfirst($data['booking_type']) . '</p>';
    
    // Add type-specific details
    switch ($data['booking_type']) {
        case 'training':
            $coachNames = [
                'david' => 'David Muller',
                'sarah' => 'Sarah van der Merwe',
                'james' => 'James Petersen'
            ];
            $coachName = $coachNames[$data['coach']] ?? $data['coach'];
            
            $body .= '<p><strong>Program:</strong> ' . ucfirst($data['program']) . '</p>';
            $body .= '<p><strong>Training Date:</strong> ' . $data['training_date'] . '</p>';
            $body .= '<p><strong>Training Time:</strong> ' . $data['training_time'] . '</p>';
            $body .= '<p><strong>Coach:</strong> ' . $coachName . '</p>';
            break;
            
        case 'camping':
            $packageNames = [
                'weekend' => 'Weekend Cricket Camp',
                'weekly' => 'Weekly Intensive Camp',
                'family' => 'Family Cricket Camp'
            ];
            $packageName = $packageNames[$data['camping_package']] ?? $data['camping_package'];
            
            $body .= '<p><strong>Package:</strong> ' . $packageName . '</p>';
            $body .= '<p><strong>Participants:</strong> ' . $data['participants'] . '</p>';
            if (!empty($data['preferred_dates'])) {
                $body .= '<p><strong>Preferred Dates:</strong> ' . $data['preferred_dates'] . '</p>';
            }
            break;
            
        case 'package':
            $packageNames = [
                'starter' => 'Starter Package',
                'pro' => 'Pro Package',
                'elite' => 'Elite Package'
            ];
            $packageName = $packageNames[$data['academy_package']] ?? $data['academy_package'];
            
            $body .= '<p><strong>Package:</strong> ' . $packageName . '</p>';
            if (!empty($data['start_date'])) {
                $body .= '<p><strong>Start Date:</strong> ' . $data['start_date'] . '</p>';
            }
            break;
    }
    
    $body .= '<p><strong>Payment Method:</strong> ' . ucfirst($data['payment_method']) . '</p>';
    $body .= '<p><strong>Payment Reference:</strong> ' . $data['payment_reference'] . '</p>';
    $body .= '<p class="total-amount">Total Amount: N$ ' . number_format($data['total_amount'], 2) . '</p>';
    
    $body .= '</div>
                
                <p><strong>Next Steps:</strong></p>';
    
    // Add payment instructions based on payment method
    switch ($data['payment_method']) {
        case 'bank':
        case 'eft':
            $body .= '<p>Please complete your payment using the following bank details:</p>
                    <p><strong>Bank:</strong> Standard Bank Namibia<br>
                    <strong>Account Name:</strong> Run Whisperers Cricket Academy<br>
                    <strong>Account Number:</strong> 1234567890<br>
                    <strong>Reference:</strong> ' . $data['payment_reference'] . '</p>';
            break;
            
        case 'cash':
            $body .= '<p>Please visit our office to complete your cash payment:<br>
                    <strong>Address:</strong> Windhoek Sports Fields, Namibia<br>
                    <strong>Hours:</strong> Mon-Fri 8am-6pm, Sat 9am-4pm</p>';
            break;
    }
    
    $body .= '<p>If you have any questions, please contact us at ' . ADMIN_EMAIL . ' or call +264 81 123 4567.</p>
                <p>We look forward to seeing you at the academy!</p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>
                <p>Windhoek Sports Fields, Namibia | ' . ADMIN_EMAIL . ' | +264 81 123 4567</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $body;
}

function sendWhatsAppMessage($data) {
    // This function requires WhatsApp Business API integration
    // For demo purposes, we'll log the message that would be sent
    
    $message = "Hello " . $data['full_name'] . "!\n\n";
    $message .= "Your booking with Run Whisperers Cricket Academy has been confirmed.\n\n";
    $message .= "Booking ID: " . $data['booking_id'] . "\n";
    $message .= "Booking Type: " . ucfirst($data['booking_type']) . "\n";
    $message .= "Total Amount: N$ " . number_format($data['total_amount'], 2) . "\n";
    $message .= "Payment Reference: " . $data['payment_reference'] . "\n\n";
    $message .= "Thank you for choosing us!\n";
    $message .= "For inquiries: +264 81 123 4567";
    
    // In production, you would use WhatsApp Business API here
    // For example, using Twilio WhatsApp API:
    /*
    $client = new Twilio\Rest\Client(WHATSAPP_API_KEY);
    $message = $client->messages->create(
        "whatsapp:+" . $data['phone'],
        [
            "from" => "whatsapp:+" . WHATSAPP_NUMBER,
            "body" => $message
        ]
    );
    */
    
    // Log the message for demo
    error_log("WhatsApp message prepared for: " . $data['phone']);
    error_log("Message: " . $message);
    
    return true;
}

function sendSMS($data) {
    // This function requires SMS API integration (Twilio, etc.)
    // For demo purposes, we'll log the message that would be sent
    
    $message = "RunWhisperers: Booking confirmed! ID: " . $data['booking_id'] . 
               " Amt: N$" . number_format($data['total_amount'], 2) . 
               " Ref: " . $data['payment_reference'];
    
    // In production, you would use an SMS API here
    // For example, using Twilio:
    /*
    $client = new Twilio\Rest\Client(SMS_API_KEY);
    $message = $client->messages->create(
        $data['phone'],
        [
            "from" => SMS_SENDER_ID,
            "body" => $message
        ]
    );
    */
    
    // Log the message for demo
    error_log("SMS prepared for: " . $data['phone']);
    error_log("Message: " . $message);
    
    return true;
}

function sendNotifications($data) {
    $results = [
        'email' => false,
        'whatsapp' => false,
        'sms' => false
    ];
    
    if ($data['notify_email']) {
        $results['email'] = sendCustomerEmail($data);
    }
    
    if ($data['notify_whatsapp']) {
        $results['whatsapp'] = sendWhatsAppMessage($data);
    }
    
    if ($data['notify_sms']) {
        $results['sms'] = sendSMS($data);
    }
    
    return $results;
}

// Handle AJAX request
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // This is an AJAX request
    // The form submission handler above will return JSON
} else {
    // This is a regular form submission
    // You might want to redirect to a thank you page
    header('Location: thank-you.html');
    exit;
}
?>