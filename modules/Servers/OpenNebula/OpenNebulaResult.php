<?php

namespace Modules\Servers\OpenNebula;

/**
 * One answer from oned's XML-RPC API.
 *
 * OpenNebula answers every call with [success, data-or-message, error code].
 * On a refusal the second element is the reason; the code says what kind it
 * was. Nothing here throws for an answer oned gave, so callers can say exactly
 * what went wrong.
 */
final class OpenNebulaResult
{
    public const AUTHENTICATION = 0x0100;

    public const AUTHORIZATION = 0x0200;

    public const NO_EXISTS = 0x0400;

    public const ACTION = 0x0800;

    public function __construct(
        public readonly bool $ok,
        public readonly int $status,
        public readonly mixed $data = null,
        public readonly string $error = '',
    ) {}

    public static function failed(string $error, int $status = 0): self
    {
        return new self(false, $status, null, $error);
    }

    /** The user the module signs in as may not do this. */
    public function isPermissionDenied(): bool
    {
        return $this->status === self::AUTHORIZATION || $this->status === self::AUTHENTICATION;
    }

    /** oned answered that the object asked about is not there. */
    public function isMissing(): bool
    {
        return ! $this->ok && $this->status === self::NO_EXISTS;
    }

    /** The transport failed (no answer at all), as opposed to oned refusing. */
    public function isUnreachable(): bool
    {
        return ! $this->ok && $this->status === 0;
    }
}
