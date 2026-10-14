<?php

namespace App\Services\Contracts;

interface PaymentNotificationServiceInterface
{
    public const OUTCOME_PROCESSED = 'processed';

    public const OUTCOME_DUPLICATE = 'duplicate';

    public const OUTCOME_NOT_FOUND = 'not_found';

    public const OUTCOME_CONFLICT = 'conflict';

    public const OUTCOME_INVALID = 'invalid';

    /**
     * Process a validated, authenticated bank payment notification.
     *
     * @param  array{event: string, data: array<string, string>, timestamp: string}  $payload
     */
    public function process(array $payload): string;
}
