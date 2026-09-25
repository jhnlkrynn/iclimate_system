<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A backup problem whose message is safe to show on the console and in logs:
 * it never contains credentials, hosts, connection strings or file paths.
 */
class BackupFailedException extends RuntimeException {}
