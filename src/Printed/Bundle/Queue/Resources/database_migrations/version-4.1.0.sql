-- Db migration for bundle version 4.1.0
-- Tailored for PostgreSQL. Please adapt, if using different database.

ALTER TABLE queue_task ADD completion_percentage INT NOT NULL DEFAULT 0;

UPDATE queue_task
SET completion_percentage = 100
WHERE status = 3;

ALTER TABLE queue_task ALTER completion_percentage DROP DEFAULT;

ALTER TABLE queue_task ADD cancellation_requested BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE queue_task ALTER cancellation_requested DROP DEFAULT;

CREATE UNIQUE INDEX UNIQ_F72E93D31D839BF0 ON queue_task (id_public);