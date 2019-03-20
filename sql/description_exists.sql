-- Stores pregenerated data on whether a description exists
-- N.B. For all entries in this table, a Wikipedia page should exist for wetede_language
CREATE TABLE /*_*/wikimedia_editor_tasks_entity_description_exists (
    -- Wikibase ID number for the entity (minus the Q- prefix)
    wetede_entity_id INTEGER UNSIGNED NOT NULL,
    -- Language code for this entity in which a Wikipedia article exists
    wetede_language VARBINARY(32) NOT NULL,
    -- Whether a description exists in this language for the entity
    wetede_description_exists TINYINT NOT NULL DEFAULT 0,
    -- A random floating-point number between 0 and 1 (to support returning random entries)
    wetede_rand FLOAT NOT NULL,
    PRIMARY KEY (wetede_entity_id,wetede_language)
) /*$wgDBTableOptions*/;
-- Index to speed up sorts by RAND()
CREATE INDEX /*i*/wetede_rand ON /*_*/wikimedia_editor_tasks_entity_description_exists (wetede_language, wetede_description_exists, wetede_rand);
