# mediawiki-extensions-WikimediaEditorTasks

Provides support for the [Wikimedia Apps](https://www.mediawiki.org/wiki/Wikimedia_Apps) team's [Suggested Edits](https://www.mediawiki.org/wiki/Wikimedia_Apps/Suggested_edits) feature, including:
* DB tables for tracking per-language edit counts meeting specified criteria and recording when app editors have reached certain editing milestones;
* An action API module for retrieving per-language edit counts of defined types for a user, along with any milestones reached.
* An action API module for fetching microcontribution suggestions.

## Dependencies

The edit suggestions API presumes that it is running on a [Wikibase](https://www.mediawiki.org/wiki/Wikibase) with [CirrusSearch](https://www.mediawiki.org/wiki/Help:CirrusSearch) and [WikibaseCirrusSearch](https://www.mediawiki.org/wiki/Help:WikibaseCirrusSearch) enabled.

The counters implementation is completely generic and has no dependencies beyond MediaWiki core.
