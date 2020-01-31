<?php
/**
 * @author Tomáš Blatný
 */

namespace greeny\MailLibrary\Extensions;

use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

class MailLibraryExtension extends CompilerExtension
{

	public function getConfigSchema(): Schema
	{
		return Expect::structure([
				'imap' => Expect::structure([
					'username' => Expect::string(),
					'password' => Expect::string(),
					'host' => Expect::string('localhost'),
					'port' => Expect::int(993),
					'ssl' => Expect::bool(true),
				])
		]);
	}


	public function loadConfiguration()
	{
		$config = $this->getConfig()->imap;
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('connection'))
			->setClass('greeny\\MailLibrary\\Connection');

		$builder->addDefinition($this->prefix('imap'))
			->setFactory('greeny\\MailLibrary\\Drivers\\ImapDriver', [
				$config->username,
				$config->password,
				$config->host,
				$config->port,
				$config->ssl,
		]);
	}


}