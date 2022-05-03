<?php
/**
 * @author Martin Pecha
 * @author Tomáš Blatný
 */

namespace greeny\MailLibrary;

class Contact
{

	public function __construct(
		private ?string $mailbox,
		private ?string $host,
		private ?string $personal,
		private ?string $adl,
	)
	{

	}


	public function __toString(): string
	{
		$address = $this->getName() ? "\"" . $this->getName() . "\" " : "";
		$address .= $this->getAdl() ? $this->getAdl() . ":" : "";
		$address .= "<" . $this->getEmail() . ">";
		return $address;
	}


	public function getEmail(): ?string
	{
		return $this->mailbox . "@" . $this->host;
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