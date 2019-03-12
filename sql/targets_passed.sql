-- Stores counters for which users have previously met the configured target count (if any)
CREATE TABLE /*_*/wikimedia_editor_tasks_targets_passed (
     -- User's central ID
    wettp_user INTEGER UNSIGNED NOT NULL,
    -- Key ID for the counter
    wettp_key_id INTEGER UNSIGNED NOT NULL,
    -- Count which was passed
    wettp_count INTEGER UNSIGNED NOT NULL,
    -- Timestamp establishing when the effect of meeting the target should begin.
    -- May be in the future, in cases where a delay is configured.
    wettp_effective_time BINARY(14) NOT NULL DEFAULT '19700101000000',
    PRIMARY KEY (wettp_user,wettp_key_id,wettp_count)
) /*$wgDBTableOptions*/;
