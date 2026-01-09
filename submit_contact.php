<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration (update with your actual database credentials)
$db_host = 'localhost';
$db_name = 'runwhisperers_db';
$db_user = 'root';
$db_pass = '';

// Email configuration
$admin_email = 'info@runwhispererscricket.com';
$site_name = 'Run Whisperers Cricket Academy';

// Response array
$response = array(
    'success' => false,
    'message' => '',
    'errors' => array()
);

// Check if form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Sanitize and validate input data
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Get form data
$full_name = isset($_POST['fullName']) ? sanitizeInput($_POST['fullName']) : '';
$email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
$phone = isset($_POST['phone']) ? sanitizeInput($_POST['phone']) : '';
$subject = isset($_POST['subject']) ? sanitizeInput($_POST['subject']) : '';
$inquiry_type = isset($_POST['inquiryType']) ? sanitizeInput($_POST['inquiryType']) : 'general';
$message = isset($_POST['message']) ? sanitizeInput($_POST['message']) : '';
$newsletter = isset($_POST['newsletter']) ? 1 : 0;
$privacy_policy = isset($_POST['privacyPolicy']) ? 1 : 0;
$timestamp = isset($_POST['timestamp']) ? $_POST['timestamp'] : date('Y-m-d H:i:s');
$ip_address = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'];

// Validation
$errors = array();

if (empty($full_name)) {
    $errors['fullName'] = 'Full name is required';
}

if (empty($email)) {
    $errors['email'] = 'Email address is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Invalid email address format';
}

if (empty($phone)) {
    $errors['phone'] = 'Phone number is required';
}

if (empty($subject)) {
    $errors['subject'] = 'Subject is required';
}

if (empty($message)) {
    $errors['message'] = 'Message is required';
}

if (!$privacy_policy) {
    $errors['privacyPolicy'] = 'You must agree to the privacy policy';
}

// If there are validation errors
if (!empty($errors)) {
    $response['errors'] = $errors;
    $response['message'] = 'Please fix the errors in the form';
    echo json_encode($response);
    exit;
}

try {
    // Create database connection
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create contacts table if it doesn't exist
    $create_table_sql = "CREATE TABLE IF NOT EXISTS contacts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        subject VARCHAR(200) NOT NULL,
        inquiry_type VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        newsletter TINYINT(1) DEFAULT 0,
        privacy_accepted TINYINT(1) DEFAULT 0,
        ip_address VARCHAR(45),
        user_agent TEXT,
        status VARCHAR(20) DEFAULT 'new',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        responded_at TIMESTAMP NULL,
        response_notes TEXT
    )";
    
    $pdo->exec($create_table_sql);
    
    // Insert contact into database
    $insert_sql = "INSERT INTO contacts (
        full_name, email, phone, subject, inquiry_type, message, 
        newsletter, privacy_accepted, ip_address, user_agent, created_at
    ) VALUES (
        :full_name, :email, :phone, :subject, :inquiry_type, :message,
        :newsletter, :privacy_accepted, :ip_address, :user_agent, :created_at
    )";
    
    $stmt = $pdo->prepare($insert_sql);
    $stmt->execute([
        ':full_name' => $full_name,
        ':email' => $email,
        ':phone' => $phone,
        ':subject' => $subject,
        ':inquiry_type' => $inquiry_type,
        ':message' => $message,
        ':newsletter' => $newsletter,
        ':privacy_accepted' => $privacy_policy,
        ':ip_address' => $ip_address,
        ':user_agent' => $user_agent,
        ':created_at' => $timestamp
    ]);
    
    $contact_id = $pdo->lastInsertId();
    
    // Send email notification to admin
    $email_subject = "New Contact Form Submission: $subject";
    $email_body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #1a472a; color: white; padding: 20px; text-align: center; }
            .content { background: #f9f9f9; padding: 20px; }
            .field { margin-bottom: 15px; }
            .label { font-weight: bold; color: #1a472a; }
            .footer { background: #eee; padding: 15px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>New Contact Form Submission</h2>
                <p>Run Whisperers Cricket Academy</p>
            </div>
            <div class='content'>
                <div class='field'>
                    <span class='label'>Contact ID:</span> $contact_id
                </div>
                <div class='field'>
                    <span class='label'>Full Name:</span> $full_name
                </div>
                <div class='field'>
                    <span class='label'>Email:</span> $email
                </div>
                <div class='field'>
                    <span class='label'>Phone:</span> $phone
                </div>
                <div class='field'>
                    <span class='label'>Subject:</span> $subject
                </div>
                <div class='field'>
                    <span class='label'>Inquiry Type:</span> $inquiry_type
                </div>
                <div class='field'>
                    <span class='label'>Message:</span><br>
                    " . nl2br($message) . "
                </div>
                <div class='field'>
                    <span class='label'>Newsletter Subscription:</span> " . ($newsletter ? 'Yes' : 'No') . "
                </div>
                <div class='field'>
                    <span class='label'>Submitted On:</span> " . date('F j, Y \a\t g:i A', strtotime($timestamp)) . "
                </div>
                <div class='field'>
                    <span class='label'>IP Address:</span> $ip_address
                </div>
            </div>
            <div class='footer'>
                <p>This email was sent from the contact form on Run Whisperers Cricket Academy website.</p>
                <p>Please respond to this inquiry within 24 hours.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Run Whisperers Contact Form <noreply@runwhispererscricket.com>" . "\r\n";
    $headers .= "Reply-To: $email" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Send email to admin
    $mail_sent = mail($admin_email, $email_subject, $email_body, $headers);
    
    // Send confirmation email to user
    if ($mail_sent) {
        $user_subject = "Thank you for contacting Run Whisperers Cricket Academy";
        $user_body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #1a472a; color: white; padding: 30px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; }
                .highlight { color: #d4af37; font-weight: bold; }
                .footer { background: #eee; padding: 20px; text-align: center; font-size: 12px; color: #666; }
                .contact-info { background: white; padding: 20px; border-left: 4px solid #d4af37; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Thank You for Contacting Us!</h2>
                    <p>Run Whisperers Cricket Academy</p>
                </div>
                <div class='content'>
                    <p>Dear <span class='highlight'>$full_name</span>,</p>
                    
                    <p>Thank you for getting in touch with Run Whisperers Cricket Academy. We have received your inquiry and our team will review it shortly.</p>
                    
                    <div class='contact-info'>
                        <p><strong>Your Inquiry Details:</strong></p>
                        <p><strong>Subject:</strong> $subject</p>
                        <p><strong>Reference ID:</strong> RW$contact_id</p>
                        <p><strong>Submitted:</strong> " . date('F j, Y \a\t g:i A', strtotime($timestamp)) . "</p>
                    </div>
                    
                    <p><strong>What happens next?</strong></p>
                    <ol>
                        <li>Our team will review your inquiry within 24 hours</li>
                        <li>We'll contact you using the details you provided</li>
                        <li>If needed, we'll schedule a call or meeting</li>
                    </ol>
                    
                    <p>In the meantime, you can:</p>
                    <ul>
                        <li>Visit our website: <a href='https://runwhispererscricket.com'>runwhispererscricket.com</a></li>
                        <li>Check our upcoming events</li>
                        <li>Browse our training programs</li>
                    </ul>
                    
                    <p>If you have any urgent matters, please call us at <strong>+264 81 123 4567</strong>.</p>
                    
                    <p>Best regards,<br>
                    <strong>The Run Whisperers Team</strong></p>
                </div>
                <div class='footer'>
                    <p>Run Whisperers Cricket Academy | Windhoek Sports Fields, Namibia</p>
                    <p>Phone: +264 81 123 4567 | Email: info@runwhispererscricket.com</p>
                    <p>© 2025 Run Whisperers Cricket Academy. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $user_headers = "MIME-Version: 1.0" . "\r\n";
        $user_headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $user_headers .= "From: Run Whisperers Cricket Academy <info@runwhispererscricket.com>" . "\r\n";
        $user_headers .= "Reply-To: info@runwhispererscricket.com" . "\r\n";
        
        // Send confirmation email to user
        mail($email, $user_subject, $user_body, $user_headers);
    }
    
    // Update newsletter subscription if requested
    if ($newsletter) {
        // Create newsletter subscribers table if it doesn't exist
        $newsletter_table_sql = "CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(100) NOT NULL UNIQUE,
            full_name VARCHAR(100),
            phone VARCHAR(20),
            subscription_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) DEFAULT 'active',
            source VARCHAR(50) DEFAULT 'contact_form'
        )";
        
        $pdo->exec($newsletter_table_sql);
        
        // Insert or update subscriber
        $newsletter_sql = "INSERT INTO newsletter_subscribers (email, full_name, phone, source) 
                          VALUES (:email, :full_name, :phone, 'contact_form')
                          ON DUPLICATE KEY UPDATE 
                          full_name = VALUES(full_name),
                          phone = VALUES(phone),
                          status = 'active'";
        
        $stmt = $pdo->prepare($newsletter_sql);
        $stmt->execute([
            ':email' => $email,
            ':full_name' => $full_name,
            ':phone' => $phone
        ]);
    }
    
    // Prepare success response
    $response['success'] = true;
    $response['message'] = 'Thank you for your message! We will get back to you soon.';
    $response['contact_id'] = $contact_id;
    
} catch (PDOException $e) {
    // Database error
    $response['message'] = 'Database error: ' . $e->getMessage();
    $response['debug'] = $e->getMessage();
    
    // Log error
    error_log("Contact form database error: " . $e->getMessage());
    
} catch (Exception $e) {
    // General error
    $response['message'] = 'An error occurred: ' . $e->getMessage();
    
    // Log error
    error_log("Contact form error: " . $e->getMessage());
}

// Return JSON response
echo json_encode($response);
exit;