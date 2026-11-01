<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Symfony\Component\HttpFoundation\Response;

final class PdfResponse
{
    /** Reuses the web controllers' pdf() until response building is extracted after the parallel branch merges. */
    public static function inline(Response $response): Response
    {
        $disposition = (string) $response->headers->get('Content-Disposition');

        $response->headers->set('Content-Disposition', (string) preg_replace('/^attachment/', 'inline', $disposition, 1));

        return $response;
    }
}
