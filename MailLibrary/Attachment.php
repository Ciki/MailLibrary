<?php

/**
 * @author Tomáš Blatný
 */

declare(strict_types=1);

namespace greeny\MailLibrary;

class Attachment
{
	public function __construct(
		protected string $name,
		protected string $content,
		protected string $type
	) {
	}


	public function getName(): string
	{
		return $this->name;
	}


	public function saveAs(string $filename): bool
	{
		return file_put_contents($filename, $this->content) !== false;
	}


	public function getContent(): string
	{
		return $this->content;
	}


	public function getType(): string
	{
		return $this->type;
	}
}
