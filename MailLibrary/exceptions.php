<?php

/**
 * @author Tomáš Blatný
 */

declare(strict_types=1);

namespace greeny\MailLibrary;

use Exception;

class MailException extends Exception
{
}

class DriverException extends MailException
{
}

class ConnectionException extends MailException
{
}

class MailboxException extends MailException
{
}

class FilterException extends MailException
{
}

class InvalidFilterValueException extends FilterException
{
}

class NotImplementedException extends MailException
{
}
