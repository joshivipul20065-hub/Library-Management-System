<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Exceptions\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UploadedFileInterface;

final class Csv
{
    public const MAX_IMPORT_ROWS = 2000;
    private const MAX_IMPORT_BYTES = 2 * 1024 * 1024;

    /**
     * @param list<string> $headers
     * @param iterable<array<array-key, mixed>> $rows
     */
    public static function respond(ResponseInterface $response, string $filename, array $headers, iterable $rows): ResponseInterface
    {
        $handle = fopen('php://temp', 'r+');
        assert($handle !== false);
        fputcsv($handle, $headers, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, array_map(self::safeCell(...), array_values($row)), ',', '"', '');
        }
        rewind($handle);
        $response->getBody()->write((string) stream_get_contents($handle));
        fclose($handle);

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /** Neutralises spreadsheet formula injection. */
    public static function safeCell(mixed $value): string
    {
        $text = is_scalar($value) ? (string) $value : '';

        return preg_match('/^[=+\-@\t\r]/', $text) === 1 ? "'" . $text : $text;
    }

    /**
     * Parses an uploaded CSV into rows keyed by lower-cased header names.
     *
     * @param list<string> $requiredColumns
     * @return list<array<string, string>>
     */
    public static function parseUpload(?UploadedFileInterface $file, array $requiredColumns): array
    {
        if ($file === null || $file->getError() !== UPLOAD_ERR_OK) {
            throw new ValidationException('A CSV file is required', ['file' => 'Upload a CSV file in the "file" field']);
        }
        if (($file->getSize() ?? 0) > self::MAX_IMPORT_BYTES) {
            throw new ValidationException('CSV file is too large', ['file' => 'Maximum size is 2 MB']);
        }
        $extension = strtolower(pathinfo((string) $file->getClientFilename(), PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            throw new ValidationException('Invalid file type', ['file' => 'Only .csv files are accepted']);
        }

        $content = (string) $file->getStream();
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $handle = fopen('php://temp', 'r+');
        assert($handle !== false);
        fwrite($handle, $content);
        rewind($handle);

        $header = fgetcsv($handle, null, ',', '"', '');
        if ($header === false) {
            throw new ValidationException('CSV file is empty', ['file' => 'The file has no header row']);
        }
        $header = array_map(static fn ($h): string => strtolower(trim((string) $h)), $header);
        $missing = array_diff($requiredColumns, $header);
        if ($missing !== []) {
            throw new ValidationException('CSV is missing required columns', ['file' => 'Missing columns: ' . implode(', ', $missing)]);
        }

        $rows = [];
        while (($line = fgetcsv($handle, null, ',', '"', '')) !== false) {
            if ($line === [null] || $line === ['']) {
                continue;
            }
            if (count($rows) >= self::MAX_IMPORT_ROWS) {
                throw new ValidationException('CSV has too many rows', ['file' => 'Maximum ' . self::MAX_IMPORT_ROWS . ' rows per import']);
            }
            $row = [];
            foreach ($header as $i => $column) {
                $row[$column] = trim((string) ($line[$i] ?? ''));
            }
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }
}
