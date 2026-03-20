<?php

/**
 * @author Tomáš Blatný
 */

declare(strict_types=1);

namespace greeny\MailLibrary;

use greeny\MailLibrary\Drivers\IDriver;

class Connection
{
	protected bool $connected = false;

	/**
	 * @var Mailbox[]|null
	 */
	protected ?array $mailboxes = null;


	public function __construct(
		protected IDriver $driver
	) {
	}


	public function isConnected(): bool
	{
		return $this->connected;
	}


	/**
	 * @throws ConnectionException
	 */
	public function connect(): self
	{
		if (!$this->connected) {
			try {
				$this->driver->connect();
				$this->connected = true;
			} catch (DriverException $e) {
				throw new ConnectionException('Cannot connect to server.', $e->getCode(), $e);
			}
		}

		return $this;
	}


	public function getDriver(): IDriver
	{
		return $this->driver;
	}


	/**
	 * Flushes changes to server
	 * @throws DriverException
	 */
	public function flush(): self
	{
		if (!$this->connected) {
			$this->connect();
		}
		
		$this->driver->flush();
		return $this;
	}


	/**
	 * Gets all mailboxes
	 * @return Mailbox[]
	 */
	public function getMailboxes(): array
	{
		if ($this->mailboxes === null) {
			$this->initializeMailboxes();
		}
		
		assert(is_array($this->mailboxes));
		return $this->mailboxes;
	}


	/**
	 * Gets mailbox by name
	 * @throws ConnectionException
	 */
	public function getMailbox(string $name): Mailbox
	{
		if ($this->mailboxes === null) {
			$this->initializeMailboxes();
		}
		
		if (isset($this->mailboxes[$name])) {
			return $this->mailboxes[$name];
		}
		
		throw new ConnectionException("Mailbox '{$name}' does not exist.");
	}


	/**
	 * Creates mailbox
	 * @throws DriverException
	 */
	public function createMailbox(string $name): Mailbox
	{
		if (!$this->connected) {
			$this->connect();
		}
		
		$this->driver->createMailbox($name);
		$this->mailboxes = null;
		return $this->getMailbox($name);
	}


	/**
	 * Renames mailbox
	 * @throws DriverException
	 */
	public function renameMailbox(string $from, string $to): Mailbox
	{
		if (!$this->connected) {
			$this->connect();
		}
		
		$this->driver->renameMailbox($from, $to);
		$this->mailboxes = null;
		return $this->getMailbox($to);
	}


	/**
	 * Deletes mailbox
	 * @throws DriverException
	 */
	public function deleteMailbox(string $name): void
	{
		if (!$this->connected) {
			$this->connect();
		}
		
		$this->driver->deleteMailbox($name);
		$this->mailboxes = null;
	}


	/**
	 * Switches currently used mailbox
	 * @throws DriverException
	 */
	public function switchMailbox(string $name): Mailbox
	{
		if (!$this->connected) {
			$this->connect();
		}
		
		$this->driver->switchMailbox($name);
		return $this->getMailbox($name);
	}


	/**
	 * Initializes mailboxes
	 */
	protected function initializeMailboxes(): void
	{
		if (!$this->connected) {
			$this->connect();
		}
		
		$this->mailboxes = [];
		foreach ($this->driver->getMailboxes() as $name) {
			$this->mailboxes[$name] = new Mailbox($this, $name);
		}
	}
}
