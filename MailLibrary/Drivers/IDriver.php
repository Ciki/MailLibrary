<?php

/**
 * @author Tomáš Blatný
 */

declare(strict_types=1);

namespace greeny\MailLibrary\Drivers;

use greeny\MailLibrary\DriverException;
use greeny\MailLibrary\Mail;
use greeny\MailLibrary\Mailbox;
use greeny\MailLibrary\Structures\IStructure;

interface IDriver
{

	/**
	 * Connects to server
	 * @throws DriverException
	 */
	function connect(): void;

	/**
	 * Flushes changes to server
	 * @throws DriverException
	 */
	function flush(): void;

	/**
	 * Gets all mailboxes
	 * @return string[]
	 * @throws DriverException
	 */
	function getMailboxes(): array;

	/**
	 * Creates new mailbox
	 * @throws DriverException
	 */
	function createMailbox(string $name): void;

	/**
	 * Renames mailbox
	 * @throws DriverException
	 */
	function renameMailbox(string $from, string $to): void;

	/**
	 * Deletes mailbox
	 * @throws DriverException
	 */
	function deleteMailbox(string $name): void;

	/**
	 * Switches current mailbox
	 * @throws DriverException
	 */
	function switchMailbox(string $name): void;

	/**
	 * Finds UIDs of mails by filter
	 * @return array of UIDs
	 */
	function getMailIds(
		array $filters,
		int $limit = 0,
		int $offset = 0,
		int $orderBy = Mail::ORDER_DATE,
		string $orderType = 'ASC'
	): array;

	/**
	 * Checks if filter is applicable for this driver
	 * @throws DriverException
	 */
	function checkFilter(string $key, mixed $value = null): void;

	/**
	 * Gets mail headers
	 * @return array of name => value
	 */
	function getHeaders(int $mailId): array;

	/**
	 * Creates structure for mail
	 */
	function getStructure(int $mailId, Mailbox $mailbox): IStructure;

	/**
	 * Gets part of body
	 */
	function getBody(int $mailId, array $data): string;

	/**
	 * Gets flags for mail
	 */
	function getFlags(int $mailId): array;

	/**
	 * Sets one flag for mail
	 * @throws DriverException
	 */
	function setFlag(int $mailId, string $flag, bool $value): void;

	/**
	 * Copies mail to another mailbox
	 * @throws DriverException
	 */
	function copyMail(int $mailId, string $toMailbox): void;

	/**
	 * Moves mail to another mailbox
	 * @throws DriverException
	 */
	function moveMail(int $mailId, string $toMailbox): void;

	/**
	 * Deletes mail
	 * @throws DriverException
	 */
	function deleteMail(int $mailId): void;
}
