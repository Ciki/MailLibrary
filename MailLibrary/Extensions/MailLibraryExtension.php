<?php

/**
 * @author Tomáš Blatný
 */

declare(strict_types=1);

namespace greeny\MailLibrary\Extensions;

use greeny\MailLibrary\Connection;
use greeny\MailLibrary\Drivers\ImapDriver;
use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

class MailLibraryExtension extends CompilerExtension
{
	#[\Override]
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'imap' => Expect::structure([
				'username' => Expect::string(),
				'password' => Expect::string(),
				'host' => Expect::string('localhost'),
				'port' => Expect::int(993),
				'ssl' => Expect::bool(true),
			]),
		]);
	}


	public function loadConfiguration(): void
	{
		/** @var \stdClass $config */
		$config = $this->getConfig();
		/** @var object{username: string, password: string, host: string, port: int, ssl: bool} $imap */
		$imap = $config->imap;
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('connection'))
			->setType(Connection::class);

		$builder->addDefinition($this->prefix('imap'))
			->setFactory(ImapDriver::class, [
				$imap->username,
				$imap->password,
				$imap->host,
				$imap->port,
				$imap->ssl,
			]);
	}
}
