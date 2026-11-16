-- Runs once, when the Postgres volume is first created.
-- Separate database for the PHPUnit suite so tests never touch dev data.
CREATE DATABASE notes_testing;
