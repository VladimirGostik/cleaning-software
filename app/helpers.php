<?php

declare(strict_types=1);

if (! function_exists('get_client_ip')) {
    /**
     * Resolve the real client IP address, respecting reverse-proxy headers.
     */
    function get_client_ip(): string
    {
        $request = request();

        if ($request === null) {
            return '';
        }

        $realIp = $request->header('X-Real-IP');

        if (is_string($realIp) && $realIp !== '') {
            return $realIp;
        }

        return $request->ip() ?? '';
    }
}
