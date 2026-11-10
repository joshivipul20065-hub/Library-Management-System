# Migrations

`schema.sql` is the baseline. Each later schema change goes in this folder as
`NNN_description.sql` (for example `002_add_book_tags.sql`).

`php bin/migrate.php` (it runs automatically when the API container starts):

1. loads `schema.sql` if the database is empty,
2. applies every `*.sql` file in this folder that isn't already listed in `schema_migrations`, in filename order,
3. records each file it applies.
