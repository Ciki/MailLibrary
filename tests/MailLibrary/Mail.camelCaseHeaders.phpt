<?php
/** @var \greeny\MailLibrary\Connection $connection */
$connection = require "../bootstrap.php";

use Tester\Assert;

$mail = $connection->getMailbox('x')->getMails()->fetchAll()[1];

// single-word headers — no conversion needed
Assert::equal(md5(1), $mail->name);
Assert::equal('1', $mail->id);

// camelCase → dash-separated header lookup
Assert::equal('text/plain', $mail->contentType);
Assert::equal('TestMailer', $mail->xMailer);
Assert::equal('custom-value', $mail->xCustomHeader);

// __isset works too
Assert::true(isset($mail->contentType));
Assert::true(isset($mail->xMailer));
Assert::false(isset($mail->nonExistentHeader));

// getHeader accepts all formats: dash-separated, PascalCase, camelCase
Assert::equal('text/plain', $mail->getHeader('content-type'));
Assert::equal('text/plain', $mail->getHeader('Content-Type'));
Assert::equal('text/plain', $mail->getHeader('contentType'));
Assert::equal('TestMailer', $mail->getHeader('x-mailer'));
Assert::equal('TestMailer', $mail->getHeader('X-Mailer'));
Assert::equal('TestMailer', $mail->getHeader('xMailer'));
Assert::null($mail->getHeader('non-existent'));
