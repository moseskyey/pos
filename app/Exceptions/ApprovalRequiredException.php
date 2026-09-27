<?php

namespace App\Exceptions;

/**
 * Thrown when an action needs a manager PIN. The UI shows the PIN modal and
 * retries the action with the approver recorded.
 */
class ApprovalRequiredException extends BusinessRuleException
{
    public function __construct(public string $action, public string $permission, string $message)
    {
        parent::__construct($message);
    }
}
