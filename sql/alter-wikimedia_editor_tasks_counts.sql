ALTER TABLE /*_*/wikimedia_editor_tasks_counts
-- Revert Counter value
ADD COLUMN wetc_revert_count INTEGER UNSIGNED NOT NULL DEFAULT 0;
