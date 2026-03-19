<?php

/**
 * @author Martin Pecha
 * @author Tomáš Blatný
 */

declare(strict_types=1);

namespace greeny\MailLibrary;

class Contact implements \Stringable
{
	public function __construct(
		private readonly ?string $mailbox,
		private readonly ?string $host,
		private readonly ?string $personal,
		private readonly ?string $adl,
	) {
	}


	public function __toString(): string
	{
		$address = $this->getName() ? '"' . $this->getName() . '" ' : '';
		$address .= $this->getAdl() ? $this->getAdl() . ':' : '';
		return $address . ('<' . $this->getEmail() . '>');
	}


	public function getEmail(): string
	{
		return $this->mailbox . '@' . $this->host;
	}


	public function getName(): ?string
	{
		return $this->personal;
	}


	public function getAdl(): ?string
	{
		return $this->adl;
	}


	public function getMailbox(): ?string
	{
		return $this->mailbox;
	}


	public function getHost(): ?string
	{
		return $this->host;
	}
}
