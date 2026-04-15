<?php
declare(strict_types=1);

/**
 * Honeypot anti-bot middleware.
 * Mirrors middleware/honeypot.js — if the hidden `website` field is filled in,
 * return a fake success so bots think they succeeded.
 */
class Honeypot
{
    public static function check(Request $req, array $fakeSuccess): void
    {
        $website = trim((string) ($req->body['website'] ?? ''));
        if ($website !== '') {
            // Bot detected — return fake success silently
            Response::json($fakeSuccess, 201);
        }
    }
}
