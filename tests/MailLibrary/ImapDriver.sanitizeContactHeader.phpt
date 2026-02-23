<?php

/**
 * Test: greeny\MailLibrary\Drivers\ImapDriver::sanitizeContactHeader
 */

declare(strict_types=1);

namespace greeny\MailLibrary\Tests;

use greeny\MailLibrary\Drivers\ImapDriver;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

$driver = new ImapDriver('user', 'pass', 'host');

// Case 1: Display name with comma - should be quoted
Assert::same(
	'"Slovenská sporiteľňa, a.s. " <notifikacie@slsp.sk>',
	$driver->sanitizeContactHeader('Slovenská sporiteľňa, a.s. <notifikacie@slsp.sk>')
);

// Case 2: Already quoted display name with comma - should not be changed
Assert::same(
	'"Slovenská sporiteľňa, a.s." <notifikacie@slsp.sk>',
	$driver->sanitizeContactHeader('"Slovenská sporiteľňa, a.s." <notifikacie@slsp.sk>')
);

// Case 3: Display name without comma - should not be changed
Assert::same(
	'John Doe <john@doe.com>',
	$driver->sanitizeContactHeader('John Doe <john@doe.com>')
);

// Case 4: No display name - should not be changed
Assert::same(
	'<john@doe.com>',
	$driver->sanitizeContactHeader('<john@doe.com>')
);

// Case 5: No email part - should not be changed (preg_replace won't match)
Assert::same(
	'Slovenská sporiteľňa, a.s.',
	$driver->sanitizeContactHeader('Slovenská sporiteľňa, a.s.')
);
