<?php

/**
 * @author Tomáš Blatný
 */

declare(strict_types=1);

namespace greeny\MailLibrary;

use ArrayAccess;
use Countable;
use Iterator;

class Selection implements ArrayAccess, Countable, Iterator
{
	protected ?array $mails = null;

	protected int $iterator = 0;

	protected array $mailIndexes = [];

	protected array $filters = [];

	protected int $limit = 0;

	protected int $offset = 0;

	protected int $orderBy = Mail::ORDER_DATE;

	// ASC|DESC
	protected string $orderType = 'ASC';


	public function __construct(protected Connection $connection, protected Mailbox $mailbox)
    {
    }


	/**
	 * Adds condition to selection
	 */
	public function where(string $key, mixed $value = null): self
	{
		$this->connection->getDriver()->checkFilter($key, $value);
		$this->filters[] = ['key' => $key, 'value' => $value];
		return $this;
	}


	/**
	 * Adds limit (like SQL)
	 *
	 * @throws InvalidFilterValueException
	 */
	public function limit(int $limit): self
	{
		if ($limit < 0) {
			throw new InvalidFilterValueException("Limit must be bigger or equal to 0, '{$limit}' given.");
		}
        
		$this->limit = $limit;
		return $this;
	}


	/**
	 * Adds offset (like SQL)
	 *
	 * @throws InvalidFilterValueException
	 */
	public function offset(int $offset): self
	{
		if ($offset < 0) {
			throw new InvalidFilterValueException("Offset must be bigger or equal to 0, '{$offset}' given.");
		}
        
		$this->offset = $offset;
		return $this;
	}


	/**
	 * @throws InvalidFilterValueException
	 */
	public function page(int $page, int $itemsPerPage): self
	{
		if ($page <= 0) {
			throw new InvalidFilterValueException("Page must be at least 1, '{$page}' given.");
		}
        
		if ($itemsPerPage <= 0) {
			throw new InvalidFilterValueException("Items per page must be at least 1, '{$itemsPerPage}' given.");
		}
        
		$this->offset(($page - 1) * $itemsPerPage);
		$this->limit($itemsPerPage);
		return $this;
	}


	public function order(int $by, string $type = 'ASC'): self
	{
		$type = strtoupper($type);
		if (!in_array($type, ['ASC', 'DESC'], true)) {
			throw new InvalidFilterValueException("Sort type must be ASC or DESC, '{$type}' given.");
		}
        
		$this->orderBy = $by;
		$this->orderType = $type;
		return $this;
	}


	public function countMails(): int
	{
		if ($this->mails === null) {
            $this->fetchMails();
        }
		return count($this->mails);
	}


	/**
	 * Gets all mails filtered by conditions
	 *
	 * @return Mail[]
	 */
	public function fetchAll(): array
	{
		if ($this->mails === null) {
            $this->fetchMails();
        }
		return $this->mails;
	}


	/**
	 * Fetches mail ids from server
	 */
	protected function fetchMails(): void
	{
		$this->connection->getDriver()->switchMailbox($this->mailbox->getName());
		$ids = $this->connection->getDriver()->getMailIds($this->filters, $this->limit, $this->offset, $this->orderBy, $this->orderType);
		$i = 0;
		$this->mails = [];
		$this->iterator = 0;
		$this->mailIndexes = [];
		foreach ($ids as $id) {
			$this->mails[$id] = new Mail($this->connection, $this->mailbox, $id);
			$this->mailIndexes[$i++] = $id;
		}
	}


	// INTERFACE ArrayAccess

	public function offsetExists(mixed $offset): bool
	{
		if ($this->mails === null) {
            $this->fetchMails();
        }
		return isset($this->mails[$offset]);
	}


	/**
	 * @throws MailboxException
	 */
	public function offsetGet(mixed $offset): Mail
	{
		if ($this->mails === null) {
            $this->fetchMails();
        }
		if (isset($this->mails[$offset])) {
			return $this->mails[$offset];
		}
        throw new MailboxException("There is no email with id '{$offset}'.");
	}


	/**
	 * @throws MailboxException
	 */
	public function offsetSet(mixed $offset, mixed $value): void
	{
		throw new MailboxException("Cannot set a readonly mail.");
	}


	/**
	 * @throws MailboxException
	 */
	public function offsetUnset(mixed $offset): void
	{
		throw new MailboxException("Cannot unset a readonly mail.");
	}


	// INTERFACE Countable

	public function count(): int
	{
		return $this->countMails();
	}


	// INTERFACE Iterator

	public function current(): Mail
	{
		return $this->mails[$this->mailIndexes[$this->iterator]];
	}


	public function next(): void
	{
		$this->iterator++;
	}


	public function key(): int
	{
		return $this->mailIndexes[$this->iterator];
	}


	public function valid(): bool
	{
		return isset($this->mailIndexes[$this->iterator]);
	}


	public function rewind(): void
	{
		if ($this->mails === null) {
            $this->fetchMails();
        }
		$this->iterator = 0;
	}
}
