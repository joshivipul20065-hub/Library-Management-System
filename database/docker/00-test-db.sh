#!/bin/bash
# Creates the integration-test database and grants the application user access to it.
set -e
mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_TEST_NAME:-library_test}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`${DB_TEST_NAME:-library_test}\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
SQL
