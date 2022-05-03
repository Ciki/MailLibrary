<?php
/**
 * @author Tomáš Blatný
 */

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