# Changes

## 2025080707

### Security

- The plugin can now only be used by site administrators. Previously any user with the
  `moodle/site:config` capability could run searches and replacements. The plugin's menu and settings
  are now hidden from everyone else.
- Search text shown in the title and breadcrumb of the search result pages is now shown as plain text,
  so any HTML it contains is no longer rendered.
- Values from an uploaded replacement CSV that are shown in the replacement error table are now shown
  as plain text, so any HTML they contain is no longer rendered.
- Search names shown in the search lists and on the search result pages are now formatted as plain
  text (multi-language names are still supported), so any other HTML they contain is no longer
  rendered.

### Added

- Searches and replacements are now recorded in the Moodle logs:
  - Database and file searches being created, deleted or requeued.
  - Bulk replacements (database or files), with the number of rows processed and how many were
    replaced, skipped, already replaced or had errors. A replacement interrupted part way through is
    still logged and marked as interrupted.

  Search patterns and replaced are recorded in the logs.

### Changed

- Regular expressions are checked when a search is saved (in the form or from the CLI), and invalid
  ones are rejected with an error. **Note:** Searches saved earlier with an invalid regular expression can no
  longer be requeued; copy the search and correct the pattern instead. **Note** The regular expressions are checked with
  PHP's regex engine. The database may have its own syntax, and therefore still reject the expression.
- `%` and `/` can now be used in regular expressions without escaping them.
- If a regular expression fails while a search is running (for example, a pattern that is too complex
  for PHP to evaluate), the search task output now names the file or record and the reason. Previously
  it was silently treated as no match.
- The search result pages show at most 500 rows per page.
- Rows in a replacement CSV that refer to a table or column that does not exist, or that have a
  non-numeric id, are now listed as errors and the rest of the file is still processed. Previously
  they stopped the replacement with a fatal error.
