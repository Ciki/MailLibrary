<?php

/**
 * Test: greeny\MailLibrary\Drivers\ImapDriver::decodeBodyPart
 */

declare(strict_types=1);

namespace greeny\MailLibrary\Tests;

use greeny\MailLibrary\Drivers\ImapDriver;
use greeny\MailLibrary\Structures\ImapStructure;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$driver = new ImapDriver('user', 'pass', 'host');

// Plain text, no encoding, UTF-8 — returned as-is
Assert::same('Hello World', $driver->decodeBodyPart('Hello World', ImapStructure::ENCODING_7BIT));

// Base64 encoded UTF-8
Assert::same('Hello World', $driver->decodeBodyPart(base64_encode('Hello World'), ImapStructure::ENCODING_BASE64));

// Quoted-printable encoded
Assert::same('Héllo Wörld', $driver->decodeBodyPart('H=C3=A9llo W=C3=B6rld', ImapStructure::ENCODING_QUOTED_PRINTABLE));

// ISO-8859-2 charset conversion (Central European — Czech/Slovak characters)
$iso2text = iconv('UTF-8', 'ISO-8859-2', 'Príliš žluťoučký kůň');
Assert::true($iso2text !== false);
Assert::same('Príliš žluťoučký kůň', $driver->decodeBodyPart($iso2text, ImapStructure::ENCODING_7BIT, 'ISO-8859-2'));

// Windows-1250 charset conversion
$win1250text = iconv('UTF-8', 'WINDOWS-1250', 'český jazyk');
Assert::true($win1250text !== false);
Assert::same('český jazyk', $driver->decodeBodyPart($win1250text, ImapStructure::ENCODING_7BIT, 'WINDOWS-1250'));

// Base64 + charset conversion combined
$latin1text = iconv('UTF-8', 'ISO-8859-1', 'café résumé');
Assert::true($latin1text !== false);
$encoded = base64_encode($latin1text);
Assert::same('café résumé', $driver->decodeBodyPart($encoded, ImapStructure::ENCODING_BASE64, 'ISO-8859-1'));

// UTF-8 charset — no conversion needed
Assert::same('Už hotovo', $driver->decodeBodyPart('Už hotovo', ImapStructure::ENCODING_7BIT, 'UTF-8'));

// US-ASCII charset — no conversion needed
Assert::same('Hello', $driver->decodeBodyPart('Hello', ImapStructure::ENCODING_7BIT, 'US-ASCII'));

// Empty charset — no conversion
Assert::same('test', $driver->decodeBodyPart('test', ImapStructure::ENCODING_7BIT, ''));
