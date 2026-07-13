<?php

namespace App\Support;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Server-side SVG QR helper shared by the QR endpoints (shared-cart join, table,
 * storefront). QR is just a visual encoding of a link — authz stays in the API.
 */
final class QrSvg
{
    /**
     * Absolute URL on the caller's OWN origin (request host + a relative path),
     * so a QR always points at the host being browsed even when APP_URL is
     * misconfigured. See the relative-URL rule.
     */
    public static function absolute(Request $request, string $relativePath): string
    {
        return $request->getSchemeAndHttpHost() . $relativePath;
    }

    /** A cacheable SVG QR response encoding $data. */
    public static function response(string $data): Response
    {
        $result = (new Builder(writer: new SvgWriter(), data: $data, size: 260, margin: 12))->build();

        return response($result->getString(), 200, [
            'Content-Type' => $result->getMimeType(),
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
