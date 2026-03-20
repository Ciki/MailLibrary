<?php

/**
 * @author Tomáš Blatný
 */

declare(strict_types=1);

namespace greeny\MailLibrary\Drivers;

use DateTimeInterface;
use greeny\MailLibrary\ContactList;
use greeny\MailLibrary\DriverException;
use greeny\MailLibrary\Mail;
use greeny\MailLibrary\Mailbox;
use greeny\MailLibrary\Structures\ImapStructure;
use greeny\MailLibrary\Structures\IStructure;
use IMAP\Connection;
use Nette\Utils\Strings;

/**
 * @phpstan-import-type ImapPart from ImapStructure
 */
class ImapDriver implements IDriver
{
	protected Connection|false $resource = false;

	protected string $server;

	protected ?string $currentMailbox = null;

	/**
	 * @var array<string, string>
	 */
	protected static array $filterTable = [
		Mail::ANSWERED => '%bANSWERED',
		Mail::BCC => 'BCC "%s"',
		Mail::BEFORE => 'BEFORE "%d"',
		Mail::BODY => 'BODY "%s"',
		Mail::CC => 'CC "%s"',
		Mail::DELETED => '%bDELETED',
		Mail::FLAGGED => '%bFLAGGED',
		Mail::FROM => 'FROM "%s"',
		Mail::KEYWORD => 'KEYWORD "%s"',
		Mail::NEW_MESSAGES => 'NEW',
		Mail::NOT_KEYWORD => 'UNKEYWORD "%s"',
		Mail::OLD_MESSAGES => 'OLD',
		Mail::ON => 'ON "%d"',
		Mail::RECENT => 'RECENT',
		Mail::SEEN => '%bSEEN',
		Mail::SINCE => 'SINCE "%d"',
		Mail::SUBJECT => 'SUBJECT "%s"',
		Mail::TEXT => 'TEXT "%s"',
		Mail::TO => 'TO "%s"',
	];

	/**
	 * @var string[]
	 */
	protected static array $contactHeaders = [
		'to',
		'from',
		'cc',
		'bcc',
	];


	public function __construct(
		protected string $username,
		protected string $password,
		string $host,
		int $port = 993,
		bool $ssl = true
	) {
		$ssl = $ssl ? '/ssl' : '/novalidate-cert';
		$this->server = '{' . $host . ':' . $port . '/imap' . $ssl . '}';
	}


	/**
	 * Connects to server
	 *
	 * @throws DriverException if connecting fails
	 */
	public function connect(): void
	{
		if (!$this->resource = @imap_open($this->server, $this->username, $this->password, CL_EXPUNGE)) { // @ - to allow throwing exceptions
			throw new DriverException('Cannot connect to IMAP server: ' . imap_last_error());
		}
	}


	protected function getResource(): Connection
	{
		if (!$this->resource instanceof Connection) {
			$this->connect();
		}

		assert($this->resource instanceof Connection);
		return $this->resource;
	}


	/**
	 * Flushes changes to server
	 *
	 * @throws DriverException if flushing fails
	 */
	public function flush(): void
	{
		imap_expunge($this->getResource());
	}


	/**
	 * Gets all mailboxes
	 *
	 * @return string[]
	 * @throws DriverException
	 */
	public function getMailboxes(): array
	{
		$mailboxes = [];
		$foo = imap_list($this->getResource(), $this->server, '*');
		if (!$foo) {
			throw new DriverException('Cannot get mailboxes from server: ' . imap_last_error());
		}

		foreach ($foo as $mailbox) {
			assert(is_string($mailbox));
			$mailboxes[] = mb_convert_encoding(str_replace($this->server, '', $mailbox), 'UTF-8', 'UTF7-IMAP');
		}

		return $mailboxes;
	}


	/**
	 * Creates new mailbox
	 *
	 * @throws DriverException
	 */
	public function createMailbox(string $name): void
	{
		if (!imap_createmailbox($this->getResource(), $this->server . $name)) {
			throw new DriverException("Cannot create mailbox '{$name}': " . imap_last_error());
		}
	}


	/**
	 * Renames mailbox
	 *
	 * @throws DriverException
	 */
	public function renameMailbox(string $from, string $to): void
	{
		if (!imap_renamemailbox($this->getResource(), $this->server . $from, $this->server . $to)) {
			throw new DriverException("Cannot rename mailbox from '{$from}' to '{$to}': " . imap_last_error());
		}
	}


	/**
	 * Deletes mailbox
	 *
	 * @throws DriverException
	 */
	public function deleteMailbox(string $name): void
	{
		if (!imap_deletemailbox($this->getResource(), $this->server . $name)) {
			throw new DriverException("Cannot delete mailbox '{$name}': " . imap_last_error());
		}
	}


	/**
	 * Switches current mailbox
	 *
	 * @throws DriverException
	 */
	public function switchMailbox(string $name): void
	{
		if ($name !== $this->currentMailbox) {
			$this->flush();
			if (!imap_reopen($this->getResource(), $this->server . $name)) {
				throw new DriverException("Cannot switch to mailbox '{$name}': " . imap_last_error());
			}

			$this->currentMailbox = $name;
		}
	}


	/**
	 * @param array<int, array{key: string, value: string|int|DateTimeInterface|bool|null}> $filters
	 * @throws DriverException
	 * @return int[] of UIDs
	 */
	public function getMailIds(
		array $filters,
		int $limit = 0,
		int $offset = 0,
		int $orderBy = Mail::ORDER_ARRIVAL,
		string $orderType = 'ASC'
	): array {
		$filter = $this->buildFilters($filters);

		$reverseOrder = $orderType === 'ASC';

		if (!is_array($ids = imap_sort($this->getResource(), $orderBy, $reverseOrder, SE_UID | SE_NOPREFETCH, $filter, 'UTF-8'))) {
			throw new DriverException('Cannot get mails: ' . imap_last_error());
		}

		/** @var int[] $ids */
		return $limit === 0 ? $ids : array_slice($ids, $offset, $limit);
	}


	/**
	 * Checks if filter is applicable for this driver
	 *
	 * @throws DriverException
	 */
	public function checkFilter(string $key, string|int|DateTimeInterface|bool|null $value = null): void
	{
		if (!in_array($key, array_keys(self::$filterTable), true)) {
			throw new DriverException("Invalid filter key '{$key}'.");
		}

		$filtered = self::$filterTable[$key];
		if (str_contains($filtered, '%s')) {
			if (!is_string($value)) {
				throw new DriverException("Invalid value type for filter '{$key}', expected string, got " . gettype($value) . '.');
			}
		} elseif (str_contains($filtered, '%d')) {
			if (!($value instanceof DateTimeInterface) && !is_int($value) && (!is_string($value) || strtotime($value) === false)) {
				throw new DriverException("Invalid value type for filter '{$key}', expected DateTime or timestamp, or textual representation of date, got " . gettype($value) . '.');
			}
		} elseif (str_contains($filtered, '%b')) {
			if (!is_bool($value)) {
				throw new DriverException("Invalid value type for filter '{$key}', expected bool, got " . gettype($value) . '.');
			}
		} elseif ($value !== null) {
			throw new DriverException("Cannot assign value to filter '{$key}'.");
		}
	}


	/**
	 * Gets mail headers
	 * @return array<string, string|ContactList> of name => value (`value` is of type string, or ContactList for self::$contactHeaders)
	 */
	public function getHeaders(int $mailId): array
	{
		$raw = imap_fetchheader($this->getResource(), $mailId, FT_UID);
		if ($raw === false) {
			throw new DriverException("Cannot get headers for mail '{$mailId}': " . imap_last_error());
		}

		$lines = explode("\n", Strings::fixEncoding($raw));
		$headers = [];
		$lastHeader = null;

		// normalize headers
		foreach ($lines as $line) {
			$firstCharacter = mb_substr($line, 0, 1, 'UTF-8'); // todo: correct assumption that string must be UTF-8 encoded?
			if ($lastHeader !== null && preg_match('/[\pZ\pC]/u', $firstCharacter) === 1) { // search for UTF-8 whitespaces
				$headers[$lastHeader] .= ' ' . Strings::trim($line);
			} else {
				$parts = explode(':', $line);
				$name = Strings::trim($parts[0]);
				unset($parts[0]);

				$headers[$name] = Strings::trim(implode(':', $parts));
				$lastHeader = $name;
			}
		}

		foreach ($headers as $key => $header) {
			if (trim($key) === '') {
				unset($headers[$key]);
				continue;
			}

			if (strtolower($key) === 'subject') {
				/** @var array<int, object{text?: string, charset?: string}> $decoded */
				$decoded = (array) imap_mime_header_decode($header);
				$text = '';
				foreach ($decoded as $part) {
					$partCharset = $part->charset ?? 'default';
					$partText = $part->text ?? '';
					if ($partCharset !== 'UTF-8' && $partCharset !== 'default') {
						try {
							// throws ValueError since php8.0.0 for non-supported charsets, eg. `windows-1250`
							// https://www.php.net/manual/de/mbstring.supported-encodings.php
							$text .= (string) @mb_convert_encoding($partText, 'UTF-8', $partCharset);
						} catch (\ValueError) {
							$text .= (string) iconv($partCharset, 'UTF-8', $partText);
						}
					} else {
						$text .= $partText;
					}
				}

				$headers[$key] = trim($text);
			} elseif (in_array(strtolower($key), self::$contactHeaders, true)) {
				/** @var array<int, object{text?: string, charset?: string}> $decoded */
				$decoded = (array) imap_mime_header_decode(trim($header));
				$decodedHeaderValue = '';
				foreach ($decoded as $part) {
					$partCharset = $part->charset ?? 'default';
					$partText = $part->text ?? '';
					if ($partCharset === 'default') {
						$decodedHeaderValue .= $partText;
					} else {
						try {
							$decodedHeaderValue .= (string) @mb_convert_encoding($partText, 'UTF-8', $partCharset);
						} catch (\ValueError) {
							$decodedHeaderValue .= (string) iconv($partCharset, 'UTF-8', $partText);
						}
					}
				}

				$headerValue = $this->sanitizeContactHeader($decodedHeaderValue);
				/** @var array<int, object{mailbox?: string, host?: string, personal?: string, adl?: string}> $contacts */
				$contacts = imap_rfc822_parse_adrlist($headerValue, 'UNKNOWN_HOST');
				$list = new ContactList();
				foreach ($contacts as $contact) {
					$list->addContact(
						$contact->mailbox ?? null,
						$contact->host ?? null,
						$contact->personal ?? null,
						$contact->adl ?? null
					);
				}

				$list->build();
				$headers[$key] = $list;
			} else {
				$headers[$key] = trim(imap_utf8($header));
			}
		}

		return $headers;
	}


	/**
	 * Creates structure for mail
	 */
	public function getStructure(int $mailId, Mailbox $mailbox): IStructure
	{
		$resource = $this->getResource();
		$structure = imap_fetchstructure($resource, $mailId, FT_UID);
		if (!$structure instanceof \stdClass) {
			throw new DriverException("Cannot get structure for mail '{$mailId}': " . imap_last_error());
		}

		/** @var ImapPart $structure */
		return new ImapStructure($this, $structure, $mailId, $mailbox);
	}


	/**
	 * Gets part of body
	 *
	 * @param array<int, array{id: string, encoding: int, charset?: string}> $data
	 * @throws DriverException
	 */
	public function getBody(int $mailId, array $data): string
	{
		$body = [];
		foreach ($data as $part) {
			$dataMessage = ($part['id'] === '0') ? @imap_body($this->getResource(), $mailId, FT_UID | FT_PEEK) : @imap_fetchbody($this->getResource(), $mailId, (string) $part['id'], FT_UID | FT_PEEK);
			if ($dataMessage === false) {
				$lastError = error_get_last();
				throw new DriverException('Cannot read given message part - ' . ($lastError['message'] ?? 'unknown error'));
			}
			
			$dataMessage = $this->decodeBodyPart(
				$dataMessage,
				(int) $part['encoding'],
				(string) ($part['charset'] ?? 'UTF-8'),
			);

			$body[] = $dataMessage;
		}

		return implode("\n\n", $body);
	}


	/**
	 * Gets flags for mail
	 * @return array<string, bool>
	 */
	public function getFlags(int $mailId): array
	{
		$data = imap_fetch_overview($this->getResource(), (string) $mailId, FT_UID);
		if (!is_array($data)) {
			throw new DriverException("Cannot get flags for mail '{$mailId}': " . imap_last_error());
		}

		$overview = reset($data);
		if (!$overview instanceof \stdClass) {
			throw new DriverException("Cannot get flags for mail '{$mailId}': " . imap_last_error());
		}

		$return = [
			Mail::FLAG_ANSWERED => false,
			Mail::FLAG_DELETED => false,
			Mail::FLAG_DRAFT => false,
			Mail::FLAG_FLAGGED => false,
			Mail::FLAG_SEEN => false,
		];
		if ($overview->answered) {
			$return[Mail::FLAG_ANSWERED] = true;
		}

		if ($overview->deleted) {
			$return[Mail::FLAG_DELETED] = true;
		}

		if ($overview->draft) {
			$return[Mail::FLAG_DRAFT] = true;
		}

		if ($overview->flagged) {
			$return[Mail::FLAG_FLAGGED] = true;
		}

		if ($overview->seen) {
			$return[Mail::FLAG_SEEN] = true;
		}

		return $return;
	}


	/**
	 * Sets one flag for mail
	 * @throws DriverException
	 */
	public function setFlag(int $mailId, string $flag, bool $value): void
	{
		if ($value) {
			imap_setflag_full($this->getResource(), (string) $mailId, $flag, ST_UID);
		} else {
			imap_clearflag_full($this->getResource(), (string) $mailId, $flag, ST_UID);
		}
	}


	/**
	 * Copies mail to another mailbox
	 * @throws DriverException
	 */
	public function copyMail(int $mailId, string $toMailbox): void
	{
		if (!imap_mail_copy($this->getResource(), (string) $mailId, /* $this->server . */ $this->encodeMailboxName($toMailbox), CP_UID)) {
			throw new DriverException("Cannot copy mail to mailbox '{$toMailbox}': " . imap_last_error());
		}
	}


	/**
	 * Moves mail to another mailbox
	 * @throws DriverException
	 */
	public function moveMail(int $mailId, string $toMailbox): void
	{
		if (!imap_mail_move($this->getResource(), (string) $mailId, /* $this->server . */ $this->encodeMailboxName($toMailbox), CP_UID)) {
			throw new DriverException("Cannot copy mail to mailbox '{$toMailbox}': " . imap_last_error());
		}
	}


	/**
	 * Deletes mail
	 * @throws DriverException
	 */
	public function deleteMail(int $mailId): void
	{
		imap_delete($this->getResource(), (string) $mailId, FT_UID);
	}


	/**
	 * Sanitizes contact header by quoting display names that contain commas but are not already quoted.
	 * e.g. "Slovenska sporitelna, a.s. <notifikacie@slspsk>" => ""Slovenska sporitelna, a.s. " <notifikacie@slspsk>"
	 *
	 * @internal for testing
	 */
	public function sanitizeContactHeader(string $headerValue): string
	{
		if (str_contains($headerValue, ',') && !str_contains($headerValue, '"') && str_contains($headerValue, '<')) {
			return (string) preg_replace('/^([^<]+)<([^>]+)>$/u', '"$1" <$2>', $headerValue);
		}

		return $headerValue;
	}


	/**
	 * Decodes body part data: applies transfer encoding (base64/QP) and converts charset to UTF-8.
	 *
	 * @internal for testing
	 */
	public function decodeBodyPart(string $data, int $encoding, string $charset = 'UTF-8'): string
	{
		if ($encoding === ImapStructure::ENCODING_BASE64) {
			// strict=false: real-world emails often contain slightly malformed base64
			$data = base64_decode($data, false);
		} elseif ($encoding === ImapStructure::ENCODING_QUOTED_PRINTABLE) {
			$data = quoted_printable_decode($data);
		}

		if ($charset !== '' && strtoupper($charset) !== 'UTF-8' && strtoupper($charset) !== 'US-ASCII') {
			try {
				$data = (string) @mb_convert_encoding($data, 'UTF-8', $charset);
			} catch (\ValueError) {
				$converted = iconv($charset, 'UTF-8//IGNORE', $data);
				if ($converted !== false) {
					$data = $converted;
				}
			}
		}

		return $data;
	}


	/**
	 * Builds filter string from filters
	 * @param array<int, array{key: string, value: string|int|DateTimeInterface|bool|null}> $filters
	 */
	protected function buildFilters(array $filters): string
	{
		$return = [];
		foreach ($filters as $filter) {
			$key = self::$filterTable[$filter['key']];
			$value = $filter['value'];

			if (str_contains((string) $key, '%s')) {
				$sValue = $value instanceof DateTimeInterface ? $value->format('d M Y') : (string) $value;
				$data = str_replace('%s', str_replace('"', '', $sValue), $key);
			} elseif (str_contains((string) $key, '%d')) {
				if ($value instanceof DateTimeInterface) {
					$timestamp = $value->getTimestamp();
				} elseif (is_string($value)) {
					$timestamp = strtotime($value) ?: time();
				} else {
					$timestamp = (int) $value;
				}

				$data = str_replace('%d', date('d M Y', $timestamp), $key);
			} elseif (str_contains((string) $key, '%b')) {
				$data = str_replace('%b', ((bool) $value ? '' : 'UN'), $key);
			} else {
				$data = $key;
			}

			$return[] = $data;
		}

		return implode(' ', $return);
	}


	/**
	 * Builds list from ids array
	 * @param int[] $ids
	 */
	protected function buildIdList(array $ids): string
	{
		sort($ids);
		return implode(',', $ids);
	}


	/**
	 * Converts mailbox name encoding as defined in IMAP RFC 2060.
	 */
	protected function encodeMailboxName(string $name): string
	{
		return mb_convert_encoding($name, 'UTF7-IMAP', 'UTF-8');
	}
}
