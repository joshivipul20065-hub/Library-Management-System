<?php

declare(strict_types=1);

namespace App\Validators;

/** Field definitions for every request payload. */
final class Rules
{
    private const PHONE = '/^[0-9+\-\s().]{6,30}$/';
    private const ROLL_NO = '/^[A-Za-z0-9\-\/_.]+$/';

    /** @return array<string, Field> */
    public static function login(): array
    {
        return [
            'email' => Field::email(),
            'password' => Field::string('Password', 255),
        ];
    }

    /** @return array<string, Field> */
    public static function book(): array
    {
        return [
            'isbn' => Field::isbn(),
            'title' => Field::string('Title', 255),
            'authors' => Field::stringList('Authors', 20, 150),
            'publisher' => Field::string('Publisher', 150, false),
            'edition' => Field::string('Edition', 50, false),
            'published_year' => Field::int('Published year', 1000, (int) date('Y') + 1, false),
            'category_id' => Field::int('Category', 1, PHP_INT_MAX, false),
            'language' => Field::string('Language', 40, false),
            'description' => Field::string('Description', 5000, false),
            'shelf_location' => Field::string('Shelf location', 50, false),
            'copies' => Field::int('Copies', 0, 100, false),
            // Only Open Library cover links (from "Fetch details"); uploaded covers use POST /books/{id}/cover.
            'cover_url' => Field::text(
                'Cover URL',
                '#^https://covers\.openlibrary\.org/b/(id|isbn|olid)/[A-Za-z0-9]+-[SML]\.jpg$#',
                'Cover must be an Open Library cover link',
                255,
                false
            ),
        ];
    }

    /** @return array<string, Field> */
    public static function copies(): array
    {
        return [
            'count' => Field::int('Count', 1, 100, false),
            'barcodes' => Field::stringList('Barcodes', 100, 64),
        ];
    }

    /** @return array<string, Field> */
    public static function category(): array
    {
        return [
            'name' => Field::string('Name', 100),
            'description' => Field::string('Description', 500, false),
        ];
    }

    /** @return array<string, Field> */
    public static function author(): array
    {
        return ['name' => Field::string('Name', 150)];
    }

    /** @return array<string, Field> */
    public static function student(): array
    {
        return [
            'name' => Field::string('Name', 120),
            'email' => Field::email(),
            'roll_no' => Field::text('Roll number', self::ROLL_NO, 'Roll number may contain letters, digits, - / _ . only', 40),
            'department' => Field::string('Department', 100),
            'year' => Field::int('Year', 1, 8),
            'phone' => Field::text('Phone', self::PHONE, 'Enter a valid phone number', 30, false),
            'address' => Field::string('Address', 255, false),
            'max_books' => Field::int('Max books', 1, 50, false),
            'password' => Field::string('Password', 72, false),
        ];
    }

    /** @return array<string, Field> */
    public static function profile(): array
    {
        return [
            'name' => Field::string('Name', 120),
            'phone' => Field::text('Phone', self::PHONE, 'Enter a valid phone number', 30, false),
            'address' => Field::string('Address', 255, false),
        ];
    }

    /** @return array<string, Field> */
    public static function issue(): array
    {
        return [
            'student_id' => Field::int('Student', 1, PHP_INT_MAX, false),
            'roll_no' => Field::string('Roll number', 40, false),
            'barcode' => Field::string('Barcode', 64),
            'due_date' => Field::date('Due date', false),
        ];
    }

    /** @return array<string, Field> */
    public static function reservation(): array
    {
        return ['book_id' => Field::int('Book', 1, PHP_INT_MAX)];
    }

    /** @return array<string, Field> */
    public static function changePassword(): array
    {
        return [
            'current_password' => Field::string('Current password', 255),
            'new_password' => Field::string('New password', 72),
        ];
    }

    /** @return array<string, Field> */
    public static function resetPassword(): array
    {
        return [
            'token' => Field::text('Token', '/^[a-f0-9]{64}$/', 'Reset link is invalid', 64),
            'password' => Field::string('Password', 72),
        ];
    }

    /** @return array<string, Field> */
    public static function waive(): array
    {
        return ['reason' => Field::string('Reason', 255, false)];
    }

    /** @return array<string, Field> */
    public static function dateRange(): array
    {
        return [
            'from' => Field::date('From', false),
            'to' => Field::date('To', false),
            'limit' => Field::int('Limit', 1, 100, false),
            'months' => Field::int('Months', 1, 36, false),
        ];
    }
}
