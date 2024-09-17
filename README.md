# moodle-tool_advancedreplace

This is a Moodle plugin that allows administrators to search and replace strings in the Moodle database.

Administrators can search and replace strings in tables and columns of the Moodle database.
They can use simple text search or regular expressions.

## GDPR
The plugin does not store any personal data.

## Examples
- Find all occurrences of "http://example.com/" followed by any number of digits on tables:

    `php admin/tool/advancedreplace/cli/find.php --regex-match="http://example.com/\d+"`
- Find all occurrences of "http://example.com/" on a table:

    `php admin/tool/advancedreplace/cli/find.php --regex-match="http://example.com/" --tables=page`

- Find all occurrences of "http://example.com/" on multiple tables:

    `php admin/tool/advancedreplace/cli/find.php --regex-match="http://example.com/" --tables=page,forum`

- Replace all occurrences of "http://example.com/" on different tables and columns:

    `php admin/tool/advancedreplace/cli/find.php --regex-match="http://example.com/" --tables=page:content,forum:message`