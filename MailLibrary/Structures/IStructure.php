<?php

/**
 * @author Tomáš Blatný
 */

declare(strict_types=1);

namespace greeny\MailLibrary\Structures;

use greeny\MailLibrary\Attachment;

interface IStructure
{
	public function getBody(): string;


	public function getHtmlBody(): string;


	public function getTextBody(): string;


	/**
	 * @return Attachment[]
	 */
	public function getAttachments(): array;
}
