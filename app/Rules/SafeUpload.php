<?php

namespace App\Rules;

/**
 * Canonical rule set for user-supplied file uploads.
 *
 * Why: the default `mimes:` Laravel rule only checks the file's extension —
 * an attacker can rename `shell.php` to `shell.pdf` and pass. We defend in
 * layers: the extension MUST match (`mimes`) AND the detected MIME from the
 * file's actual bytes MUST match (`mimetypes`). Both have to agree, so a
 * renamed or disguised payload is rejected before it ever reaches storage.
 *
 * How to apply: use in FormRequests / controller validate() calls anywhere
 * a user uploads a file that will be stored on disk.
 *
 *     'file' => ['required', 'file', 'max:10240', ...SafeUpload::documents()]
 */
final class SafeUpload
{
    private const DOCUMENT_MIMES      = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];
    private const DOCUMENT_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/png',
        'image/jpeg',
    ];

    private const PDF_IMAGE_MIMES      = ['pdf', 'jpg', 'jpeg', 'png'];
    private const PDF_IMAGE_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    private const IMAGE_MIMES      = ['jpg', 'jpeg', 'png', 'webp'];
    private const IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public static function documents(): array
    {
        return [
            'mimes:' . implode(',', self::DOCUMENT_MIMES),
            'mimetypes:' . implode(',', self::DOCUMENT_MIME_TYPES),
        ];
    }

    public static function pdfOrImage(): array
    {
        return [
            'mimes:' . implode(',', self::PDF_IMAGE_MIMES),
            'mimetypes:' . implode(',', self::PDF_IMAGE_MIME_TYPES),
        ];
    }

    public static function image(): array
    {
        return [
            'mimes:' . implode(',', self::IMAGE_MIMES),
            'mimetypes:' . implode(',', self::IMAGE_MIME_TYPES),
        ];
    }
}
