<?php

namespace ChaoticIngenuity\LaravelMCP\Exceptions;

use Exception;

class MCPAuthenticationException extends Exception
{
    public function __construct(string $message = 'MCP authentication failed', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}