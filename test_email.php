<?php
require_once 'mailer_config.php';

echo "Testing SMTP Connection...\n";

$result = sendNotificationEmail('requestsystem@bscb.co.th', 'Test Email', 'This is a test email from the portal system.');

print_r($result);
?>
