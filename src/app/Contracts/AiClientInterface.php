<?php

namespace App\Contracts;

interface AiClientInterface
{
    /**
     * Return model text content, or null when AI is unavailable.
     */
    public function complete(string $prompt, bool $json = false): ?string;
}
