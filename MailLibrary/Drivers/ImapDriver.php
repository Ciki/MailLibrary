<?php

/**
 * @author Tomáš Blatný
 */

declare(strict_types=1);

namespace greeny\MailLibrary\Drivers;

use DateTime;
use greeny\MailLibrary\ContactList;
use greeny\MailLibrary\DriverException;
use greeny\MailLibrary\Mail;
use greeny\MailLibrary\Mailbox;
use greeny\MailLibrary\Structures\ImapStructure;
use greeny\MailLibrary\Structures\IStructure;
use IMAP\Connection;
use Nette\Utils\Strings;

class ImapDriver implements IDriver
{
	protected string $username;
	protected string $password;
	protected /*resource (php<8.1) | Connection (php>=8.1)*/ $resource;
	protected string $server;
	protected ?string $currentMailbox = null;
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
	protected static array $contactHeaders = [
		'to',
		'from',
		'cc',
		'bcc',
	];


	public function __construct(string $username, string $password, string $host, int $port = 993, bool $ssl = true)
	{
		$ssl = $ssl ? '/ssl' : '/novalidate-cert';
		$this->server = '{' . $host . ':' . $port . '/imap' . $ssl . '}';
		$this->username = $username;
		$this->password = $password;
	}


	/**
	 * Connects to server
	 *
	 * @throws DriverException if connecting fails
	 */
	public function connect(): void
	{
		if (!$this->resource = @imap_open($this->server, $this->username, $this->password, CL_EXPUNGE)) { // @ - to allow throwing exceptions
			throw new DriverException("Cannot connect to IMAP server: " . imap_last_error());
		}
	}


	/**
	 * Flushes changes to server
	 *
	 * @throws DriverException if flushing fails
	 */
	public function flush(): void
	{
		imap_expunge($this->resource);
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
		$foo = imap_list($this->resource, $this->server, '*');
		if (!$foo) {
			throw new DriverException("Cannot get mailboxes from server: " . imap_last_error());
		}
		foreach ($foo as $mailbox) {
			$mailboxes[] = mb_convert_encoding(str_replace($this->server, '', $mailbox), 'UTF8', 'UTF7-IMAP');
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
		if (!imap_createmailbox($this->resource, $this->server . $name)) {
			throw new DriverException("Cannot create mailbox '$name': " . imap_last_error());
		}
	}


	/**
	 * Renames mailbox
	 *
	 * @throws DriverException
	 */
	public function renameMailbox(string $from, string $to): void
	{
		if (!imap_renamemailbox($this->resource, $this->server . $from, $this->server . $to)) {
			throw new DriverException("Cannot rename mailbox from '$from' to '$to': " . imap_last_error());
		}
	}


	/**
	 * Deletes mailbox
	 *
	 * @throws DriverException
	 */
	public function deleteMailbox(string $name): void
	{
		if (!imap_deletemailbox($this->resource, $this->server . $name)) {
			throw new DriverException("Cannot delete mailbox '$name': " . imap_last_error());
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
			if (!imap_reopen($this->resource, $this->server . $name)) {
				throw new DriverException("Cannot switch to mailbox '$name': " . imap_last_error());
			}
			$this->currentMailbox = $name;
		}
	}


	/**
	 * Finds UIDs of mails by filter
	 *
	 * @throws DriverException
	 * @return array of int UIDs
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

		if (!is_array($ids = imap_sort($this->resource, $orderBy, $reverseOrder, SE_UID | SE_NOPREFETCH, $filter, 'UTF-8'))) {
			throw new DriverException("Cannot get mails: " . imap_last_error());
		}

		return $limit === 0 ? $ids : array_slice($ids, $offset, $limit);
	}


	/**
	 * Checks if filter is applicable for this driver
	 *
	 * @throws DriverException
	 */
	public function checkFilter(string $key, mixed $value = null): void
	{
		if (!in_array($key, array_keys(self::$filterTable))) {
			throw new DriverException("Invalid filter key '$key'.");
		}
		$filtered = self::$filterTable[$key];
		if (strpos($filtered, '%s') !== false) {
			if (!is_string($value)) {
				throw new DriverException("Invalid value type for filter '$key', expected string, got " . gettype($value) . ".");
			}
		} else if (strpos($filtered, '%d') !== false) {
			if (!($value instanceof DateTime) && !is_int($value) && !strtotime($value)) {
				throw new DriverException("Invalid value type for filter '$key', expected DateTime or timestamp, or textual representation of date, got " . gettype($value) . ".");
			}
		} else if (strpos($filtered, '%b') !== false) {
			if (!is_bool($value)) {
				throw new DriverException("Invalid value type for filter '$key', expected bool, got " . gettype($value) . ".");
			}
		} else if ($value !== null) {
			throw new DriverException("Cannot assign value to filter '$key'.");
		}
	}


	/**
	 * Gets mail headers
	 *
	 * @return array of name => value (`value` is of type string, or ContactList for self::$contactHeaders)
	 */
	public function getHeaders(int $mailId): array
	{
		$raw = imap_fetchheader($this->resource, $mailId, FT_UID);
		$lines = explode("\n", Strings::fixEncoding($raw));
		$headers = [];
		$lastHeader = null;

		// normalize headers
		foreach ($lines as $line) {
			$firstCharacter = mb_substr($line, 0, 1, 'UTF-8'); // todo: correct assumption that string must be UTF-8 encoded?
			if (preg_match('/[\pZ\pC]/u', $firstCharacter) === 1) { // search for UTF-8 whitespaces
				$headers[$lastHeader] .= " " . Strings::trim($line);
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
				$decoded = imap_mime_header_decode($header);

				$text = '';
				foreach ($decoded as $part) {
					if ($part->charset !== 'UTF-8' && $part->charset !== 'default') {
						try {
							// throws ValueError since php8.0.0 for non-supported charsets, eg. `windows-1250`
							// https://www.php.net/manual/de/mbstring.supported-encodings.php
							$text .= @mb_convert_encoding($part->text, 'UTF-8', $part->charset);
						} catch (\ValueError $exception) {
							$text .= iconv($part->charset, 'UTF-8', $part->text);
						}
					} else {
						$text .= $part->text;
					}
				}

				$headers[$key] = trim($text);
			} else if (in_array(strtolower($key), self::$contactHeaders)) {
				$contacts = imap_rfc822_parse_adrlist(imap_utf8(trim($header)), 'UNKNOWN_HOST');
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
		return new ImapStructure($this, imap_fetchstructure($this->resource, $mailId, FT_UID), $mailId, $mailbox);
	}


	/**
	 * Gets part of body
	 *
	 * @param array $data requires id and encoding keys
	 * @throws DriverException
	 */
	public function getBody(int $mailId, array $data): string
	{
		$body = [];
		foreach ($data as $part) {
			assert(is_array($part));
			$dataMessage = ($part['id'] === '0') ? @imap_body($this->resource, $mailId, FT_UID | FT_PEEK) : @imap_fetchbody($this->resource, $mailId, $part['id'], FT_UID | FT_PEEK);
			if ($dataMessage === false) {
				throw new DriverException("Cannot read given message part - " . error_get_last()["message"]);
			}
			$encoding = $part['encoding'];
			if ($encoding === ImapStructure::ENCODING_BASE64) {
				$dataMessage = base64_decode($dataMessage);
			} else if ($encoding === ImapStructure::ENCODING_QUOTED_PRINTABLE) {
				$dataMessage = quoted_printable_decode($dataMessage);
			}

			// todo: other encodings?

			$body[] = $dataMessage;
		}
		return implode('\n\n', $body);
	}


	/**
	 * Gets flags for mail
	 */
	public function getFlags(int $mailId): array
	{
		$data = imap_fetch_overview($this->resource, (string) $mailId, FT_UID);
		reset($data);
		$data = current($data);
		$return = [
			Mail::FLAG_ANSWERED => false,
			Mail::FLAG_DELETED => false,
			Mail::FLAG_DRAFT => false,
			Mail::FLAG_FLAGGED => false,
			Mail::FLAG_SEEN => false,
		];
		if ($data->answered) {
			$return[Mail::FLAG_ANSWERED] = true;
		}
		if ($data->deleted) {
			$return[Mail::FLAG_DELETED] = true;
		}
		if ($data->draft) {
			$return[Mail::FLAG_DRAFT] = true;
		}
		if ($data->flagged) {
			$return[Mail::FLAG_FLAGGED] = true;
		}
		if ($data->seen) {
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
			if (!imap_setflag_full($this->resource, (string) $mailId, $flag, ST_UID)) {
				throw new DriverException("Cannot set flag '$flag': " . imap_last_error());
			}
		} else {
			if (!imap_clearflag_full($this->resource, (string) $mailId, $flag, ST_UID)) {
				throw new DriverException("Cannot unset flag '$flag': " . imap_last_error());
			}
		}
	}


	/**
	 * Copies mail to another mailbox
	 * @throws DriverException
	 */
	public function copyMail(int $mailId, string $toMailbox): void
	{
		if (!imap_mail_copy($this->resource, (string) $mailId, /* $this->server . */ $this->encodeMailboxName($toMailbox), CP_UID)) {
			throw new DriverException("Cannot copy mail to mailbox '$toMailbox': " . imap_last_error());
		}
	}


	/**
	 * Moves mail to another mailbox
	 * @throws DriverException
	 */
	public function moveMail(int $mailId, string $toMailbox): void
	{
		if (!imap_mail_move($this->resource, (string) $mailId, /* $this->server . */ $this->encodeMailboxName($toMailbox), CP_UID)) {
			throw new DriverException("Cannot copy mail to mailbox '$toMailbox': " . imap_last_error());
		}
	}


	/**
	 * Deletes mail
	 * @throws DriverException
	 */
	public function deleteMail(int $mailId): void
	{
		if (!imap_delete($this->resource, (string) $mailId, FT_UID)) {
			throw new DriverException("Cannot delete mail: " . imap_last_error());
		}
	}


	/**
	 * Builds filter string from filters
	 */
	protected function buildFilters(array $filters): string
	{
		$return = [];
		foreach ($filters as $filter) {
			$key = self::$filterTable[$filter['key']];
			$value = $filter['value'];

			if (strpos($key, '%s') !== false) {
				$data = str_replace('%s', str_replace('"', '', (string) $value), $key);
			} else if (strpos($key, '%d') !== false) {
				if ($value instanceof DateTime) {
					$timestamp = $value->getTimestamp();
				} else if (is_string($value)) {
					$timestamp = strtotime($value) ?: Time();
				} else {
					$timestamp = (int) $value;
				}
				$data = str_replace('%d', date("d M Y", $timestamp), $key);
			} else if (strpos($key, '%b') !== false) {
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
