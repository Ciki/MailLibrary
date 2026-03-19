<?php

/**
 * @author Tomáš Blatný
 */

declare(strict_types=1);

namespace greeny\MailLibrary;

use Countable;
use Iterator;

/**
 * @implements Iterator<int, string>
 */
class ContactList implements Iterator, Countable, \Stringable
{
	/**
	 * @var Contact[]
	 */
	protected array $contacts = [];

	/**
	 * @var string[]
	 */
	protected array $builtContacts = [];


	public function __toString(): string
	{
		return implode(', ', $this->builtContacts);
	}


	public function addContact(
		?string $mailbox = null,
		?string $host = null,
		?string $personal = null,
		?string $adl = null
	): void {
		$this->contacts[] = new Contact($mailbox, $host, $personal, $adl);
	}


	public function build(): void
	{
		$return = [];
		foreach ($this->contacts as $contact) {
			$return[] = $contact->__toString();
		}

		$this->builtContacts = $return;
	}


	/**
	 * @return string[]
	 */
	public function getContacts(): array
	{
		return $this->builtContacts;
	}


	/**
	 * @return Contact[]
	 */
	public function getContactsObjects(): array
	{
		return $this->contacts;
	}


	public function current(): string
	{
		$current = current($this->builtContacts);
		return $current === false ? '' : $current;
	}


	public function next(): void
	{
		next($this->builtContacts);
	}


	public function key(): int
	{
		return (int) key($this->builtContacts);
	}


	public function valid(): bool
	{
		$key = key($this->builtContacts);
		return $key !== null;
	}


	public function rewind(): void
	{
		reset($this->builtContacts);
	}


	public function count(): int
	{
		return count($this->builtContacts);
	}
}
