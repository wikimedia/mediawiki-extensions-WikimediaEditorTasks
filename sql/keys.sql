-- Table for storing counter keys efficiently
CREATE TABLE /*_*/wikimedia_editor_tasks_keys (
    wet_id INTEGER UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
    -- Key identifying the counter
    wet_key VARBINARY(255) NOT NULL
) /*$wgDBTableOptions*/;
CREATE UNIQUE INDEX /*i*/wet_key ON /*_*/wikimedia_editor_tasks_keys (wet_key);
