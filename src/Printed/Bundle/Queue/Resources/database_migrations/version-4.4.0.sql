-- Db migration for bundle version 4.4.0

CREATE INDEX IDX_F72E93D37B00651C ON queue_task (status);
CREATE INDEX IDX_F72E93D3FB7336F0 ON queue_task (queue_name);
