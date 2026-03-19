<?php

/**
 * @author Tomáš Blatný
 */

declare(strict_types=1);

namespace greeny\MailLibrary;

use greeny\MailLibrary\Structures\IStructure;
use Nette\Utils\Strings;

class Mail
{
	public const string ANSWERED = 'ANSWERED';
	public const string BCC = 'BCC';
	public const string BEFORE = 'BEFORE';
	public const string BODY = 'BODY';
	public const string CC = 'CC';
	public const string DELETED = 'DELETED';
	public const string FLAGGED = 'FLAGGED';
	public const string FROM = 'FROM';
	public const string KEYWORD = 'KEYWORD';
	public const string NEW_MESSAGES = 'NEW';
	public const string NOT_KEYWORD = 'UNKEYWORD';
	public const string OLD_MESSAGES = 'OLD';
	public const string ON = 'ON';
	public const string RECENT = 'RECENT';
	public const string SEEN = 'SEEN';
	public const string SINCE = 'SINCE';
	public const string SUBJECT = 'SUBJECT';
	public const string TEXT = 'TEXT';
	public const string TO = 'TO';

	// flags
	public const string FLAG_ANSWERED = '\\ANSWERED';
	public const string FLAG_DELETED = '\\DELETED';
	public const string FLAG_DRAFT = '\\DRAFT';
	public const string FLAG_FLAGGED = '\\FLAGGED';
	public const string FLAG_SEEN = '\\SEEN';

	// orders
	public const int ORDER_DATE = SORTDATE;
	public const int ORDER_ARRIVAL = SORTARRIVAL;
	public const int ORDER_FROM = SORTFROM;
	public const int ORDER_SUBJECT = SORTSUBJECT;
	public const int ORDER_TO = SORTTO;
	public const int ORDER_CC = SORTCC;
	public const int ORDER_SIZE = SORTSIZE;

	/**
	 * @var array<string, string|ContactList>|null
	 */
	protected ?array $headers = null;

	protected ?IStructure $structure = null;

	/**
	 * @var array<string, bool>|null
	 */
	protected ?array $flags = null;


	public function __construct(
		protected Connection $connection,
		protected Mailbox $mailbox,
		protected int $id
	) {
	}


	/**
	 * Header checker
	 */
	public function __isset(string $name): bool
	{
		if ($this->headers === null) {
			$this->initializeHeaders();
		}
		
		$key = $this->normalizeHeaderName($this->lowerCamelCaseToHeaderName($name));
		return isset($this->headers[$key]);
	}


	/**
	 * Header getter
	 */
	public function __get(string $name): mixed
	{
		return $this->getHeader(
			$this->normalizeHeaderName($this->lowerCamelCaseToHeaderName($name))
		);
	}


	public function getId(): int
	{
		return $this->id;
	}


	public function getMailbox(): Mailbox
	{
		return $this->mailbox;
	}


	/**
	 * @return array<string, string|ContactList>
	 */
	public function getHeaders(): array
	{
		if ($this->headers === null) {
			$this->initializeHeaders();
		}
		
		return $this->headers;
	}


	public function getHeader(string $name): null|string|ContactList
	{
		if ($this->headers === null) {
			$this->initializeHeaders();
		}
		
		$index = $this->normalizeHeaderName($name);
		return $this->headers[$index] ?? null;
	}


	public function getSender(): ?Contact
	{
		/** @var ContactList|null $from */
		$from = $this->getHeader('from');
		if ($from) {
			$contacts = $from->getContactsObjects();
			return count($contacts) ? $contacts[0] : null;
		}
		
		return null;
	}


	/**
	 * @param string[] $headers
	 * @return Contact[]|null
	 */
	public function getRecipients(array $headers = ['to']): ?array
	{
		$ret = [];
		foreach ($headers as $hName) {
			/** @var ContactList|null $header */
			$header = $this->getHeader($hName);
			if ($header) {
				$ret = array_merge($ret, $header->getContactsObjects());
			}
		}

		return count($ret) ? $ret : null;
	}


	public function getSubject(): ?string
	{
		return $this->getHeader('subject');
	}


	public function getBody(): string
	{
		if (!$this->structure instanceof \greeny\MailLibrary\Structures\IStructure) {
			$this->initializeStructure();
		}
		
		return $this->structure->getBody();
	}


	public function getHtmlBody(): string
	{
		if (!$this->structure instanceof \greeny\MailLibrary\Structures\IStructure) {
			$this->initializeStructure();
		}
		
		return $this->structure->getHtmlBody();
	}


	public function getTextBody(): string
	{
		if (!$this->structure instanceof \greeny\MailLibrary\Structures\IStructure) {
			$this->initializeStructure();
		}
		
		return $this->structure->getTextBody();
	}


	/**
	 * @return Attachment[]
	 */
	public function getAttachments(): array
	{
		if (!$this->structure instanceof \greeny\MailLibrary\Structures\IStructure) {
			$this->initializeStructure();
		}
		
		return $this->structure->getAttachments();
	}


	/**
	 * @return array<string, bool>
	 */
	public function getFlags(): array
	{
		if ($this->flags === null) {
			$this->initializeFlags();
		}
		
		return $this->flags;
	}


	/**
	 * @param array<string, bool> $flags
	 */
	public function setFlags(array $flags, bool $autoFlush = false): void
	{
		$this->connection->getDriver()->switchMailbox($this->mailbox->getName());
		foreach (
			[
				self::FLAG_ANSWERED,
				self::FLAG_DELETED,
				self::FLAG_DRAFT,
				self::FLAG_FLAGGED,
				self::FLAG_SEEN,
			] as $flag
		) {
			if (isset($flags[$flag])) {
				$this->connection->getDriver()->setFlag($this->id, $flag, $flags[$flag]);
			}
		}

		if ($autoFlush) {
			$this->connection->getDriver()->flush();
		}
	}


	public function move(string $toMailbox): void
	{
		$this->connection->getDriver()->switchMailbox($this->mailbox->getName());
		$this->connection->getDriver()->moveMail($this->id, $toMailbox);
	}


	public function copy(string $toMailbox): void
	{
		$this->connection->getDriver()->switchMailbox($this->mailbox->getName());
		$this->connection->getDriver()->copyMail($this->id, $toMailbox);
	}


	public function delete(): void
	{
		$this->connection->getDriver()->switchMailbox($this->mailbox->getName());
		$this->connection->getDriver()->deleteMail($this->id);
	}


	protected function initializeHeaders(): void
	{
		$this->headers = [];
		$this->connection->getDriver()->switchMailbox($this->mailbox->getName());
		foreach ($this->connection->getDriver()->getHeaders($this->id) as $key => $value) {
			$this->headers[$this->normalizeHeaderName($key)] = $value;
		}
	}


	protected function initializeStructure(): void
	{
		$this->connection->getDriver()->switchMailbox($this->mailbox->getName());
		$this->structure = $this->connection->getDriver()->getStructure($this->id, $this->mailbox);
	}


	protected function initializeFlags(): void
	{
		$this->connection->getDriver()->switchMailbox($this->mailbox->getName());
		$this->flags = $this->connection->getDriver()->getFlags($this->id);
	}


	/**
	 * Formats header name (X-Received-From => x-recieved-from)
	 *
	 * @param string $name Header name (with dashes, valid UTF-8 string)
	 */
	protected function normalizeHeaderName(string $name): string
	{
		return Strings::normalize(Strings::lower($name));
	}


	/**
	 * Converts camel cased name to normalized header name (xReceivedFrom => x-recieved-from)
	 *
	 * @return string name with dashes
	 */
	protected function lowerCamelCaseToHeaderName(string $camelCasedName): string
	{
		// todo: test this
		// todo: use something like this instead http://stackoverflow.com/a/1993772
		$dashedName = lcfirst((string) preg_replace_callback(
			'~-.~',
			fn (array $matches): string => ucfirst(substr((string) $matches[0], 1)),
			$camelCasedName
		));

		return $dashedName;
	}
}
