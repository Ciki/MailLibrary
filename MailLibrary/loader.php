<?php

/**
 * @author Tomáš Blatný
 */

declare(strict_types=1);

require_once __DIR__ . '/exceptions.php';

spl_autoload_register(function (string $type): void {
	static $paths = [
		'greeny\maillibrary\connection' => 'Connection.php',
		'greeny\maillibrary\mailbox' => 'Mailbox.php',
		'greeny\maillibrary\selection' => 'Selection.php',
		'greeny\maillibrary\mail' => 'Mail.php',
		'greeny\maillibrary\contactlist' => 'ContactList.php',
		'greeny\maillibrary\contact' => 'Contact.php',
		'greeny\maillibrary\attachment' => 'Attachment.php',
		'greeny\maillibrary\structures\istructure' => 'Structures/IStructure.php',
		'greeny\maillibrary\structures\imapstructure' => 'Structures/ImapStructure.php',
		'greeny\maillibrary\drivers\idriver' => 'Drivers/IDriver.php',
		'greeny\maillibrary\drivers\imapdriver' => 'Drivers/ImapDriver.php',
		'greeny\maillibrary\extensions\maillibraryextension' => 'Extensions/MailLibraryExtension.php',
	];

	$type = ltrim(strtolower($type), '\\'); // PHP namespace bug #49143

	if (isset($paths[$type])) {
		require_once __DIR__ . '/' . $paths[$type];
	}
});
