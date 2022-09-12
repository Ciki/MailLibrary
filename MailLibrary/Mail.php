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
	const ANSWERED = 'ANSWERED';
	const BCC = 'BCC';
	const BEFORE = 'BEFORE';
	const BODY = 'BODY';
	const CC = 'CC';
	const DELETED = 'DELETED';
	const FLAGGED = 'FLAGGED';
	const FROM = 'FROM';
	const KEYWORD = 'KEYWORD';
	const NEW_MESSAGES = 'NEW';
	const NOT_KEYWORD = 'UNKEYWORD';
	const OLD_MESSAGES = 'OLD';
	const ON = 'ON';
	const RECENT = 'RECENT';
	const SEEN = 'SEEN';
	const SINCE = 'SINCE';
	const SUBJECT = 'SUBJECT';
	const TEXT = 'TEXT';
	const TO = 'TO';

	const FLAG_ANSWERED = "\\ANSWERED";
	const FLAG_DELETED = "\\DELETED";
	const FLAG_DRAFT = "\\DRAFT";
	const FLAG_FLAGGED = "\\FLAGGED";
	const FLAG_SEEN = "\\SEEN";

	const ORDER_DATE = SORTDATE;
	const ORDER_ARRIVAL = SORTARRIVAL;
	const ORDER_FROM = SORTFROM;
	const ORDER_SUBJECT = SORTSUBJECT;
	const ORDER_TO = SORTTO;
	const ORDER_CC = SORTCC;
	const ORDER_SIZE = SORTSIZE;


	protected Connection $connection;

	protected Mailbox $mailbox;

	protected int $id;

	protected ?array $headers = null;

	protected ?IStructure $structure = null;

	protected ?array $flags = null;


	public function __construct(Connection $connection, Mailbox $mailbox, int $id)
	{
		$this->connection = $connection;
		$this->mailbox = $mailbox;
		$this->id = $id;
	}


	/**
	 * Header checker
	 */
	public function __isset(string $name): bool
	{
		$this->headers !== null || $this->initializeHeaders();
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
	 * @return array of string|ContactList
	 */
	public function getHeaders(): array
	{
		$this->headers !== null || $this->initializeHeaders();
		return $this->headers;
	}


	public function getHeader(string $name): null|string|ContactList
	{
		$this->headers !== null || $this->initializeHeaders();
		$index = $this->normalizeHeaderName($name);
		if (isset($this->headers[$index])) {
			return $this->headers[$index];
		} else {
			return null;
		}
	}


	public function getSender(): ?Contact
	{
		/** @var ContactList $from */
		$from = $this->getHeader('from');
		if ($from) {
			$contacts = $from->getContactsObjects();
			return (count($contacts) ? $contacts[0] : null);
		} else {
			return null;
		}
	}


	/**
	 * @return Contact[]|null
	 */
	public function getRecipients(array $headers = ['to']): ?array
	{
		$ret = [];
		foreach ($headers as $hName) {
			/** @var ContactList $header */
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
		$this->structure !== null || $this->initializeStructure();
		return $this->structure->getBody();
	}


	public function getHtmlBody(): string
	{
		$this->structure !== null || $this->initializeStructure();
		return $this->structure->getHtmlBody();
	}


	public function getTextBody(): string
	{
		$this->structure !== null || $this->initializeStructure();
		return $this->structure->getTextBody();
	}


	/**
	 * @return Attachment[]
	 */
	public function getAttachments(): array
	{
		$this->structure !== null || $this->initializeStructure();
		return $this->structure->getAttachments();
	}


	public function getFlags(): array
	{
		$this->flags !== null || $this->initializeFlags();
		return $this->flags;
	}


	public function setFlags(array $flags, $autoFlush = false): void
	{
		$this->connection->getDriver()->switchMailbox($this->mailbox->getName());
		foreach ([
			Mail::FLAG_ANSWERED,
			Mail::FLAG_DELETED,
			Mail::FLAG_DRAFT,
			Mail::FLAG_FLAGGED,
			Mail::FLAG_SEEN,
		] as $flag) {
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
	 * @return string
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
		$dashedName = lcfirst(preg_replace_callback(
			"~-.~",
			function ($matches) {
				return ucfirst(substr($matches[0], 1));
			},
			$camelCasedName
		));

		return $this->normalizeHeaderName($dashedName);
	}
}
