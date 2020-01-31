<?php
/**
 * @author Tomáš Blatný
 */

namespace greeny\MailLibrary\Structures;

use greeny\MailLibrary\Attachment;
use greeny\MailLibrary\Drivers\ImapDriver;
use greeny\MailLibrary\Mailbox;

class ImapStructure implements IStructure {
	const TYPE_TEXT = 0;
	const TYPE_MULTIPART = 1;
	const TYPE_MESSAGE = 2;
	const TYPE_APPLICATION = 3;
	const TYPE_AUDIO = 4;
	const TYPE_IMAGE = 5;
	const TYPE_VIDEO = 6;
	const TYPE_MODEL = 7;
	const TYPE_OTHER = 8;
	const TYPE_UNKNOWN = 9;

	const ENCODING_7BIT = 0;
	const ENCODING_8BIT = 1;
	const ENCODING_BINARY = 2;
	const ENCODING_BASE64 = 3;
	const ENCODING_QUOTED_PRINTABLE = 4;
	const ENCODING_OTHER = 5;

	protected static $typeTable = [
		self::TYPE_TEXT => 'text',
		self::TYPE_MULTIPART => 'multipart',
		self::TYPE_MESSAGE => 'message',
		self::TYPE_APPLICATION => 'application',
		self::TYPE_AUDIO => 'audio',
		self::TYPE_IMAGE => 'image',
		self::TYPE_VIDEO => 'video',
		self::TYPE_MODEL => 'other',
		self::TYPE_OTHER => 'other',
		self::TYPE_UNKNOWN => 'other',
	];

	/** @var \greeny\MailLibrary\Drivers\ImapDriver */
	protected $driver;

	/** @var int */
	protected $id;

	/** @var array */
	protected $htmlBodyIds = [];

	/** @var array */
	protected $textBodyIds = [];

	/** @var array */
	protected $attachmentsIds = [];

	/** @var string */
	protected $htmlBody = null;

	/** @var string */
	protected $textBody = null;

	/** @var Attachment[] */
	protected $attachments = null;

	/** @var Mailbox */
	protected $mailbox;

	/**
	 * @param ImapDriver $driver
	 * @param object     $structure
	 * @param int        $mailId
	 * @param Mailbox    $mailbox
	 */
	public function __construct(ImapDriver $driver, $structure, $mailId, Mailbox $mailbox)
	{
		$this->driver = $driver;
		$this->id = $mailId;
		$this->mailbox = $mailbox;
		if(!isset($structure->parts)) {
			$this->addStructurePart($structure, '0');
		} else {
			foreach((array)$structure->parts as $id => $part) {
				$this->addStructurePart($part, (string) ($id + 1));
			}
		}
	}

	/**
	 * @return string
	 */
	public function getBody()
	{
		return count($this->htmlBodyIds) ? $this->getHtmlBody() : $this->getTextBody();
	}

	/**
	 * @return string
	 */
	public function getHtmlBody()
	{
		if($this->htmlBody === null) {
			$this->driver->switchMailbox($this->mailbox->getName());
			return $this->htmlBody = $this->driver->getBody($this->id, $this->htmlBodyIds);
		} else {
			return $this->htmlBody;
		}
	}

	/**
	 * @return string
	 */
	public function getTextBody()
	{
		if($this->textBody === null) {
			$this->driver->switchMailbox($this->mailbox->getName());
			return $this->textBody = $this->driver->getBody($this->id, $this->textBodyIds);
		} else {
			return $this->textBody;
		}
	}

	/**
	 * @return Attachment[]
	 */
	public function getAttachments()
	{
		$this->driver->switchMailbox($this->mailbox->getName());
		if($this->attachments === null) {
			$this->attachments = [];
			foreach($this->attachmentsIds as $attachmentData) {
				$this->attachments[] = new Attachment($attachmentData['name'], $this->driver->getBody($this->id, [$attachmentData]), $attachmentData['type']);
			}
		}
		return $this->attachments;
	}

	protected function addStructurePart($structure, $partId)
	{
		$type = $structure->type;
		$encoding = isset($structure->encoding) ? $structure->encoding : 'UTF-8';
		$subtype = $structure->ifsubtype ? $structure->subtype : 'PLAIN';

		$parameters = [];
		if($structure->ifparameters) {
			foreach($structure->parameters as $parameter) {
				$parameters[strtolower($parameter->attribute)] = $parameter->value;
			}
		}
		if($structure->ifdparameters) {
			foreach($structure->dparameters as $parameter) {
				$parameters[strtolower($parameter->attribute)] = $parameter->value;
			}
		}

		if(isset($parameters['filename']) || isset($parameters['name'])) {
			$this->attachmentsIds[] = [
				'id' => $partId,
				'encoding' => $encoding,
				'name' => imap_utf8(isset($parameters['filename']) ? $parameters['filename'] : $parameters['name']),
				'type' => self::$typeTable[$type]. '/' . $subtype,
			];
		} else if($type === self::TYPE_TEXT) {
			if($subtype === 'HTML') {
				$this->htmlBodyIds[] = ['id' => $partId, 'encoding' => $encoding];
			} else if($subtype === 'PLAIN') {
				$this->textBodyIds[] = ['id' => $partId, 'encoding' => $encoding];
			}
		}

		if(isset($structure->parts)) {
			foreach((array)$structure->parts as $id => $part) {
				$this->addStructurePart($part, (string)($partId.'.'.($id+1)));
			}
		}
	}
}

