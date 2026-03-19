<?php

/**
 * @author Tomáš Blatný
 */

declare(strict_types=1);

namespace greeny\MailLibrary\Structures;

use greeny\MailLibrary\Attachment;
use greeny\MailLibrary\Drivers\ImapDriver;
use greeny\MailLibrary\Mailbox;

class ImapStructure implements IStructure
{
	public const int TYPE_TEXT = 0;
	public const int TYPE_MULTIPART = 1;
	public const int TYPE_MESSAGE = 2;
	public const int TYPE_APPLICATION = 3;
	public const int TYPE_AUDIO = 4;
	public const int TYPE_IMAGE = 5;
	public const int TYPE_VIDEO = 6;
	public const int TYPE_MODEL = 7;
	public const int TYPE_OTHER = 8;
	public const int TYPE_UNKNOWN = 9;

	public const int ENCODING_7BIT = 0;
	public const int ENCODING_8BIT = 1;
	public const int ENCODING_BINARY = 2;
	public const int ENCODING_BASE64 = 3;
	public const int ENCODING_QUOTED_PRINTABLE = 4;
	public const int ENCODING_OTHER = 5;

	protected static array $typeTable = [
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

	protected array $htmlBodyIds = [];

	protected array $textBodyIds = [];

	protected array $attachmentsIds = [];

	protected ?string $htmlBody = null;

	protected ?string $textBody = null;

	/**
	 * @var ?Attachment[]
	 */
	protected ?array $attachments = null;


	public function __construct(
		protected ImapDriver $driver,
		object $structure,
		protected int $id,
		protected Mailbox $mailbox
	) {
		if (!isset($structure->parts)) {
			$this->addStructurePart($structure, '0');
		} else {
			foreach ((array) $structure->parts as $this->id => $part) {
				$this->addStructurePart($part, (string) ($this->id + 1));
			}
		}
	}


	public function getBody(): string
	{
		return count($this->htmlBodyIds) ? $this->getHtmlBody() : $this->getTextBody();
	}


	public function getHtmlBody(): string
	{
		if ($this->htmlBody === null) {
			$this->driver->switchMailbox($this->mailbox->getName());
			return $this->htmlBody = $this->driver->getBody($this->id, $this->htmlBodyIds);
		}
		return $this->htmlBody;
	}


	public function getTextBody(): string
	{
		if ($this->textBody === null) {
			$this->driver->switchMailbox($this->mailbox->getName());
			return $this->textBody = $this->driver->getBody($this->id, $this->textBodyIds);
		}
		return $this->textBody;
	}


	/**
	 * @return Attachment[]
	 */
	public function getAttachments(): array
	{
		$this->driver->switchMailbox($this->mailbox->getName());
		if ($this->attachments === null) {
			$this->attachments = [];
			foreach ($this->attachmentsIds as $attachmentData) {
				$this->attachments[] = new Attachment($attachmentData['name'], $this->driver->getBody($this->id, [$attachmentData]), $attachmentData['type']);
			}
		}

		return $this->attachments;
	}


	protected function addStructurePart(object $structure, string $partId): void
	{
		$type = $structure->type;
		$encoding = $structure->encoding ?? 'UTF-8';
		$subtype = $structure->ifsubtype ? $structure->subtype : 'PLAIN';

		$parameters = [];
		if ($structure->ifparameters) {
			foreach ($structure->parameters as $parameter) {
				$parameters[strtolower((string) $parameter->attribute)] = $parameter->value;
			}
		}

		if ($structure->ifdparameters) {
			foreach ($structure->dparameters as $parameter) {
				$parameters[strtolower((string) $parameter->attribute)] = $parameter->value;
			}
		}

		if (isset($parameters['filename']) || isset($parameters['name'])) {
			$this->attachmentsIds[] = [
				'id' => $partId,
				'encoding' => $encoding,
				'name' => imap_utf8($parameters['filename'] ?? $parameters['name']),
				'type' => self::$typeTable[$type] . '/' . $subtype,
			];
		} elseif ($type === self::TYPE_TEXT) {
			if ($subtype === 'HTML') {
				$this->htmlBodyIds[] = [
					'id' => $partId,
					'encoding' => $encoding,
				];
			} elseif ($subtype === 'PLAIN') {
				$this->textBodyIds[] = [
					'id' => $partId,
					'encoding' => $encoding,
				];
			}
		}

		if (isset($structure->parts)) {
			foreach ((array) $structure->parts as $id => $part) {
				$this->addStructurePart($part, $partId . '.' . ($id + 1));
			}
		}
	}
}
