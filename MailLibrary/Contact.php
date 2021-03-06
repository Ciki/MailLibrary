<?php
/**
 * @author Martin Pecha
 * @author Tomáš Blatný
 */

namespace greeny\MailLibrary;

class Contact
{
	/** @var string */
	private $mailbox;

	/** @var string */
	private $host;

	/** @var string */
	private $personal;

	/** @var string */
	private $adl;


	/**
	 * @param $mailbox
	 * @param $host
	 * @param $personal
	 * @param $adl
	 */
	public function __construct($mailbox = NULL, $host = NULL, $personal = NULL,
		$adl = NULL)
	{
		$this->mailbox = $mailbox;
		$this->host = $host;
		$this->personal = $personal;
		$this->adl = $adl;
	}


	/**
	 * @return string
	 */
	public function __toString()
	{
		$address = $this->getName() ? "\"" . $this->getName() . "\" " : "";
		$address .= $this->getAdl() ? $this->getAdl() . ":" : "";
		$address .= "<" . $this->getEmail() . ">";
		return $address;
	}


	/**
	 * @return string
	 */
	public function getEmail()
	{
		return $this->mailbox . "@" . $this->host;
	}


	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->personal;
	}


	/**
	 * @return string
	 */
	public function getAdl()
	{
		return $this->adl;
	}


	/**
	 * @return string
	 */
	public function getMailbox()
	{
		return $this->mailbox;
	}


	/**
	 * @return string
	 */
	public function getHost()
	{
		return $this->host;
	}


}