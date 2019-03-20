-- Counter values per user
CREATE TABLE /*_*/wikimedia_editor_tasks_counts (
    -- User's central ID
    wetc_user INTEGER UNSIGNED NOT NULL,
    -- Key ID for the counter
    wetc_key_id INTEGER UNSIGNED NOT NULL,
    -- Language code for this count
    wetc_lang VARBINARY(32) NOT NULL,
    -- Counter value
    wetc_count INTEGER UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (wetc_user,wetc_key_id,wetc_lang)
) /*$wgDBTableOptions*/;
