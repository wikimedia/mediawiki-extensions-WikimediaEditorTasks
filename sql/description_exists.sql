-- Stores pregenerated data on whether a description exists
-- N.B. For all entries in this table, a Wikipedia page should exist for wetede_language
CREATE TABLE /*_*/wikimedia_editor_tasks_entity_description_exists (
    -- Wikibase ID number for the entity (minus the Q- prefix)
    wetede_entity_id INTEGER UNSIGNED NOT NULL,
    -- Language code for this entity in which a Wikipedia article exists
    wetede_language VARBINARY(255) NOT NULL,
    -- Whether a description exists in this language for the entity
    wetede_description_exists TINYINT NOT NULL DEFAULT 0,
    wetede_rand FLOAT NOT NULL,
    PRIMARY KEY (wetede_entity_id,wetede_language)
) /*$wgDBTableOptions*/;
-- Index on the random column to speed up sorts
CREATE INDEX /*i*/wetede_rand ON /*_*/wikimedia_editor_tasks_entity_description_exists (wetede_rand);
