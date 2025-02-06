[![ci](https://github.com/catalyst/moodle-tool_advancedreplace/actions/workflows/ci.yml/badge.svg?branch=MOODLE_401_STABLE)](https://github.com/catalyst/moodle-tool_advancedreplace/actions/workflows/ci.yml?branch=MOODLE_401_STABLE)

# moodle-tool_advancedreplace

This is a Moodle plugin that allows administrators to search and replace strings in the Moodle database.

It is conceptually similar to the replace tool in core, but way more powerful as it can search
and replace strings in tables and columns of the Moodle database, as well as inside files withing
the File API, including files inside zip files such as H5P.

They can use simple text search or regular expressions, and the found records can be downloaded as csv
and the per-record replacements uploaded as a new csv giving very fine grained replacement capability.

## GDPR

The plugin does not store any personal data.

## Branches

| Moodle version    | Branch              | PHP       |
|-------------------|---------------------|-----------|
| Moodle 4.1+       | `MOODLE_401_STABLE` | 7.4+      |

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

## Support

If you have issues please log them in
[GitHub](https://github.com/catalyst/moodle-tool_advancedreplace/issues).

Please note our time is limited, so if you need urgent support or want to
sponsor a new feature then please contact
[Catalyst IT Australia](https://www.catalyst-au.net/contact-us).


This plugin was developed by [Catalyst IT Australia](https://www.catalyst-au.net/).

<img alt="Catalyst IT" src="https://cdn.rawgit.com/CatalystIT-AU/moodle-auth_saml2/MOODLE_39_STABLE/pix/catalyst-logo.svg" width="400">
