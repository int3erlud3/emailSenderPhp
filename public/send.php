<?php

declare(strict_types=1);

use EmailSender\App;
use EmailSender\ConfigException;
use EmailSender\ContactResult;
use EmailSender\Http;

require dirname(__DIR__) . '/vendor/autoload.php';

Http::sendSecurityHeaders();

$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
$wantsJson = is_string($accept) && str_contains($accept, 'application/json');

$respond = static function (ContactResult $result) use ($wantsJson): never {
    http_response_code($result->status);
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        $msg = Http::escape($result->message);
        echo "<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"utf-8\"><title>Contact</title>"
            . "<link rel=\"stylesheet\" href=\"assets/style.css\"></head><body><main><p class=\"status\">$msg</p>"
            . '<p><a href="./">Back to the form</a></p></main></body></html>';
    }
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $respond(new ContactResult(405, false, 'Method not allowed.'));
}
$length = $_SERVER['CONTENT_LENGTH'] ?? '0';
if (!is_string($length) || !ctype_digit($length) || (int) $length > 32768) {
    $respond(new ContactResult(413, false, 'Request too large.'));
}

Http::startSession();

try {
    $config = App::config();
} catch (ConfigException $e) {
    error_log('email-sender: configuration error: ' . $e->getMessage());
    $respond(new ContactResult(500, false, 'The contact form is not configured.'));
}

/** @var array<string, mixed> $_SESSION */
$result = App::contactHandler($config)->handle($_POST, Http::clientIp(), $_SESSION);
$respond($result);
