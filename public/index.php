<?php

declare(strict_types=1);

use EmailSender\ContactHandler;
use EmailSender\Csrf;
use EmailSender\Http;
use EmailSender\Validator;

require dirname(__DIR__) . '/vendor/autoload.php';

Http::sendSecurityHeaders();
Http::startSession();
/** @var array<string, mixed> $_SESSION */
$token = Csrf::token($_SESSION);
$e = static fn (string $v): string => Http::escape($v);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="referrer" content="no-referrer">
  <title>Contact</title>
  <link rel="stylesheet" href="assets/style.css">
  <script src="assets/form.js" defer></script>
</head>
<body>
<main>
  <h1>Contact</h1>
  <p class="hint">All fields are required. Your address is only used to reply to you.</p>
  <form id="contact-form" method="post" action="send.php" novalidate>
    <input type="hidden" name="csrf_token" value="<?= $e($token) ?>">
    <div class="field">
      <label for="name">Name</label>
      <input id="name" name="name" type="text" required maxlength="<?= Validator::MAX_NAME ?>" autocomplete="name">
      <p class="error" id="name-error" aria-live="polite"></p>
    </div>
    <div class="field">
      <label for="email">E-mail</label>
      <input id="email" name="email" type="email" required maxlength="254" autocomplete="email">
      <p class="error" id="email-error" aria-live="polite"></p>
    </div>
    <div class="field">
      <label for="subject">Subject</label>
      <input id="subject" name="subject" type="text" required maxlength="<?= Validator::MAX_SUBJECT ?>">
      <p class="error" id="subject-error" aria-live="polite"></p>
    </div>
    <div class="field">
      <label for="message">Message</label>
      <textarea id="message" name="message" rows="7" required
        minlength="<?= Validator::MIN_MESSAGE ?>" maxlength="<?= Validator::MAX_MESSAGE ?>"></textarea>
      <p class="counter" id="message-counter">0 / <?= Validator::MAX_MESSAGE ?></p>
      <p class="error" id="message-error" aria-live="polite"></p>
    </div>
    <!-- Honeypot: hidden from people (CSS + aria-hidden), filled in by naive bots -->
    <div class="hp" aria-hidden="true">
      <label for="<?= ContactHandler::HONEYPOT_FIELD ?>">Website</label>
      <input id="<?= ContactHandler::HONEYPOT_FIELD ?>" name="<?= ContactHandler::HONEYPOT_FIELD ?>"
        type="text" tabindex="-1" autocomplete="off">
    </div>
    <button type="submit">Send message</button>
    <p id="form-status" class="status" role="status" aria-live="polite"></p>
  </form>
</main>
</body>
</html>
