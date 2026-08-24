<?php
// Handles the staff/business registration forms on register.html.
// Runs on the hosting account's own PHP + mail() — no third-party form API.

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

// Honeypot field: bots fill it in, real users never see it. Pretend success.
if (!empty($data['_honey'])) {
    echo json_encode(['success' => true]);
    exit;
}

$recipients = [
    'staff'    => 'recruitment@callatemp.com.au',
    'business' => 'bookatemp@callatemp.com.au',
    'enquiry'  => 'bookatemp@callatemp.com.au',
];

$formType = $data['_form_type'] ?? '';
if (!isset($recipients[$formType])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown form type.']);
    exit;
}

$to = $recipients[$formType];
$subject = isset($data['_subject'])
    ? trim(str_replace(["\r", "\n"], '', $data['_subject']))
    : 'New submission — Call A Temp';

$rows = '';
foreach ($data as $key => $value) {
    if ($key === '' || $key[0] === '_') {
        continue;
    }
    $label = ucwords(str_replace(['-', '_'], ' ', $key));
    $printable = is_array($value) ? implode(', ', $value) : $value;
    $rows .= str_pad($label . ':', 20) . $printable . "\n";
}

$labels = [
    'staff'    => 'staff registration',
    'business' => 'business registration',
    'enquiry'  => 'contact form enquiry',
];

$body = "New " . $labels[$formType] . " from callatemp.com.au:\n\n" . $rows;

$headers = "From: Call A Temp Website <noreply@callatemp.com.au>\r\n";

if (!empty($data['email'])) {
    $replyTo = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
    if (filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers .= "Reply-To: {$replyTo}\r\n";
    }
}

$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$sent = mail($to, $subject, $body, $headers);

if ($sent) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Mail could not be sent.']);
}
