<a href="https://github.com/catalyst/moodle-tool_advancedreplace/actions/workflows/ci.yml?query=branch%3AMOODLE_401_STABLE">
<img src="https://github.com/catalyst/moodle-tool_advancedreplace/workflows/ci/badge.svg?branch=MOODLE_401_STABLE">
</a>


# moodle-tool_advancedreplace

This is a Moodle plugin that allows administrators to search and replace strings in the Moodle database.

Administrators can search and replace strings in tables and columns of the Moodle database.
They can use simple text search or regular expressions.

## GDPR
The plugin does not store any personal data.

## Branches

| Moodle version    | Branch             | PHP       |
|-------------------|--------------------|-----------|
| Moodle 4.1+       | `main`             | 7.4+      |

## Installation

1. Install the plugin the same as any standard Moodle plugin, you can use
   git to clone it into your source:

   ```sh
   git clone git@github.com:catalyst/moodle-tool_advancedreplace.git admin/tool/advancedreplace

## Examples
Find all occurrences of "http://example.com/" followed by any number of digits on tables:

```sh
cd admin/tool/advancedreplace/cli/
php find.php --regex-match="http://example.com/\d+" --output=result.csv
```

Find all occurrences of "http://example.com/" in a table:

```sh
php find.php --regex-match="http://example.com/\d+" --tables=page --output=result.csv
```

Find all occurrences of "http://example.com/" in multiple tables:

```sh
php find.php --regex-match="http://example.com/\d+" --tables=page,forum --output=result.csv
```

Find all occurrences of "http://example.com/" in different tables and columns:

```sh
php find.php --regex-match="http://example.com/\d+" --tables=page:content,forum:message --output=result.csv
```

Find all occurrences of "http://example.com/" in all tables except the ones specified:

```sh
php find.php --regex-match="http://example.com/\d+" --skip-tables=page,forum --output=result.csv
```

Find all occurrences of "http://example.com/" in all columns except the ones specified:

```sh
php find.php --regex-match="http://example.com/\d+" --tables=page --skip-columns=intro,display --output=result.csv
```

  
