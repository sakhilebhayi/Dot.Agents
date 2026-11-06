<?php

namespace App\Services\Http;

use RuntimeException;

/** An outbound URL the platform refuses to call (see UrlGuard). */
class UnsafeUrl extends RuntimeException {}
