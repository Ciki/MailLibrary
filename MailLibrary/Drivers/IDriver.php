<?php

/**
 * @author Tomáš Blatný
 */

declare(strict_types=1);

namespace greeny\MailLibrary\Drivers;

use greeny\MailLibrary\ContactList;
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
	public function connect(): void;


	/**
	 * Flushes changes to server
	 * @throws DriverException
	 */
	public function flush(): void;


	/**
	 * Gets all mailboxes
	 * @return string[]
	 * @throws DriverException
	 */
	public function getMailboxes(): array;


	/**
	 * Creates new mailbox
	 * @throws DriverException
	 */
	public function createMailbox(string $name): void;


	/**
	 * Renames mailbox
	 * @throws DriverException
	 */
	public function renameMailbox(string $from, string $to): void;


	/**
	 * Deletes mailbox
	 * @throws DriverException
	 */
	public function deleteMailbox(string $name): void;


	/**
	 * Switches current mailbox
	 * @throws DriverException
	 */
	public function switchMailbox(string $name): void;


	/**
	 * Finds UIDs of mails by filter
	 * @param array<int, array{key: string, value: string|int|\DateTimeInterface|bool|null}> $filters
	 * @return int[] of UIDs
	 */
	public function getMailIds(
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
	public function checkFilter(string $key, string|int|\DateTimeInterface|bool|null $value = null): void;


	/**
	 * Gets mail headers
	 * @return array<string, string|ContactList> of name => value
	 */
	public function getHeaders(int $mailId): array;


	/**
	 * Creates structure for mail
	 */
	public function getStructure(int $mailId, Mailbox $mailbox): IStructure;


	/**
	 * Gets part of body
	 * @param array<int, array<string, string|int>> $data
	 */
	public function getBody(int $mailId, array $data): string;


	/**
	 * Gets flags for mail
	 * @return array<string, bool>
	 */
	public function getFlags(int $mailId): array;


	/**
	 * Sets one flag for mail
	 * @throws DriverException
	 */
	public function setFlag(int $mailId, string $flag, bool $value): void;


	/**
	 * Copies mail to another mailbox
	 * @throws DriverException
	 */
	public function copyMail(int $mailId, string $toMailbox): void;


	/**
	 * Moves mail to another mailbox
	 * @throws DriverException
	 */
	public function moveMail(int $mailId, string $toMailbox): void;


	/**
	 * Deletes mail
	 * @throws DriverException
	 */
	public function deleteMail(int $mailId): void;
}
