<?php

/**
 * @author Tomáš Blatný
 */

declare(strict_types=1);

namespace greeny\MailLibrary;

use Iterator;
use Countable;

class ContactList implements Iterator, Countable, \Stringable
{
	/** @var Contact[] */
	protected array $contacts = [];
    
	protected array $builtContacts = [];


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


	public function getContacts(): array
	{
		return $this->builtContacts;
	}


	public function getContactsObjects(): array
	{
		return $this->contacts;
	}


	public function __toString(): string
	{
		return implode(', ', $this->builtContacts);
	}


	public function current(): mixed
	{
		return current($this->builtContacts);
	}


	public function next(): void
	{
		next($this->builtContacts);
	}


	public function key(): mixed
	{
		return key($this->builtContacts);
	}


	public function valid(): bool
	{
		$key = key($this->builtContacts);
		return ($key !== null && $key !== false);
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
