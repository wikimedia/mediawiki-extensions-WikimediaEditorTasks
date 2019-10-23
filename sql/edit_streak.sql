-- Stores streak length and last edit time when user making edits.
CREATE TABLE /*_*/wikimedia_editor_tasks_edit_streak (
    -- User's central ID
    wetes_user INTEGER UNSIGNED NOT NULL,
    -- Length of edit streak
    wetes_streak_length INTEGER UNSIGNED NOT NULL,
    -- Timestamp of last edit time
    wetes_last_edit_time BINARY(14) NOT NULL DEFAULT '19700101000000',
    PRIMARY KEY (wetes_user)
) /*$wgDBTableOptions*/;
