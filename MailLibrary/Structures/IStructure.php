<?php

/**
 * @author Tomáš Blatný
 */

declare(strict_types=1);

namespace greeny\MailLibrary\Structures;

use greeny\MailLibrary\Attachment;

interface IStructure
{

	function getBody(): string;

	function getHtmlBody(): string;

	function getTextBody(): string;

	/**
	 * @return Attachment[]
	 */
	function getAttachments(): array;
}
