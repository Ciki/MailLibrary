<?php
/**
 * @author Tomáš Blatný
 */

namespace greeny\MailLibrary;

use greeny\MailLibrary\Drivers\IDriver;

class Connection
{
	protected IDriver $driver;
	protected bool $connected = false;
	protected ?array $mailboxes = null;


	public function __construct(IDriver $driver)
	{
		$this->driver = $driver;
	}


	public function isConnected(): bool
	{
		return $this->connected;
	}


	/**
	 * @throws ConnectionException
	 */
	public function connect(): Connection
	{
		if (!$this->connected) {
			try {
				$this->driver->connect();
				$this->connected = true;
			} catch (DriverException $e) {
				throw new ConnectionException("Cannot connect to server.", $e->getCode(), $e);
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
	public function flush(): Connection
	{
		$this->connected || $this->connect();
		$this->driver->flush();
		return $this;
	}


	/**
	 * Gets all mailboxes
	 * @return Mailbox[]
	 */
	public function getMailboxes(): array
	{
		$this->mailboxes !== null || $this->initializeMailboxes();
		return $this->mailboxes;
	}


	/**
	 * Gets mailbox by name
	 * @throws ConnectionException
	 */
	public function getMailbox(string $name): Mailbox
	{
		$this->mailboxes !== null || $this->initializeMailboxes();
		if (isset($this->mailboxes[$name])) {
			return $this->mailboxes[$name];
		} else {
			throw new ConnectionException("Mailbox '$name' does not exist.");
		}
	}


	/**
	 * Creates mailbox
	 * @throws DriverException
	 */
	public function createMailbox(string $name): Mailbox
	{
		$this->connected || $this->connect();
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
		$this->connected || $this->connect();
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
		$this->connected || $this->connect();
		$this->driver->deleteMailbox($name);
		$this->mailboxes = null;
	}


	/**
	 * Switches currently used mailbox
	 * @throws DriverException
	 */
	public function switchMailbox(string $name): Mailbox
	{
		$this->connected || $this->connect();
		$this->driver->switchMailbox($name);
		return $this->getMailbox($name);
	}


	/**
	 * Initializes mailboxes
	 */
	protected function initializeMailboxes(): void
	{
		$this->connected || $this->connect();
		$this->mailboxes = [];
		foreach ($this->driver->getMailboxes() as $name) {
			$this->mailboxes[$name] = new Mailbox($this, $name);
		}
	}


}