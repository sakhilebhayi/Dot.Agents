<?php

namespace App\Exceptions;

use RuntimeException;

/** A run that may not start. The message is written for the person who asked. */
class RunRefused extends RuntimeException {}
