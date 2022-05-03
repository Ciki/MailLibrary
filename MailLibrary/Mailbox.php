<?php
/**
 * @author Tomáš Blatný
 */

namespace greeny\MailLibrary;

class Mailbox
{

	public function __construct(
		protected Connection $connection,
		protected string $name)
	{

	}


	public function getName(): string
	{
		return $this->name;
	}


	public function getMails(): Selection
	{
		return new Selection($this->connection, $this);
	}


}