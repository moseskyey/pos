<?php

namespace App\Exceptions;

use RuntimeException;

/** A user-facing business rule violation (shown as a toast / flash). */
class BusinessRuleException extends RuntimeException {}
