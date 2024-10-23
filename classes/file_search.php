<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_advancedreplace;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir.'/filelib.php');

/**
 * Helper class to search and replace text in moddle files.
 *
 * @package    tool_advancedreplace
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_search {
    /**
    * 1st column of csv output - the contextid column of the mdl_files table.
    *
    * @var int
    */
    const CSV_CONTEXTID = 0;

    /**
    * 2nd column of csv output - the component column of the mdl_files table.
    *
    * @var int
    */
    const CSV_COMPONENT = 1;

    /**
    * 3rd column of csv output - the filearea column of the mdl_files table.
    *
    * @var int
    */
    const CSV_FILEAREA  = 2;

    /**
    * 4th column of csv output - the itemid column of the mdl_files table.
    *
    * @var int
    */
    const CSV_ITEMID    = 3;

    /**
    * 5th column of csv output - the filepath column of the mdl_files table.
    *
    * @var int
    */
    const CSV_FILEPATH  = 4;

    /**
    * 6th column of csv output - the filename column of the mdl_files table.
    *
    * @var int
    */
    const CSV_FILENAME  = 5;

    /**
    * 7th column of csv output - the mimetype column of the mdl_files table.
    *
    * @var int
    */
    const CSV_MIMETYPE  = 6;

    /**
    * 8th column of csv output - the strategy used to search the file.
    *
    * @var int
    */
    const CSV_STRATEGY  = 7;

    /**
    * 9th column of csv output - some internal information, depending on the strategy.
    *
    * For example, for zip strategy, this will be path used inside the zip for the subfile being searched.
    *
    * @var int
    */
    const CSV_INTERNAL  = 8;

    /**
    * 12th column of csv file - the replacement text.
    *
    * @var int
    */
    const CSV_REPLACE   = 9;

    /**
    * 10th column of csv output - the offset of the match within the file.
    *
    * @var int
    */
    const CSV_OFFSET    = 10;

    /**
    * 11th column of csv output - the text that was matched.
    *
    * @var int
    */
    const CSV_MATCH     = 11;

    /**
     * Searches the DB using a persistent record.
     *
     * @param \tool_advancedreplace\files $record
     * @param string $output path
     * @return void
     */
    public static function files(files $record, string $output = '') {
        global $DB;
        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);
        $criteria = (object) [
            'pattern' => '%' . trim($record->get('pattern')) . '%i',
            'components' => trim($record->get('components')),
            'skipcomponents' => trim($record->get('skipcomponents')),
            'mimetypes' => trim($record->get('mimetypes')),
            'skipmimetypes' => trim($record->get('skipmimetypes')),
            'filenames' => trim($record->get('filenames')),
            'skipfilenames' => trim($record->get('skipfilenames')),
            'skipareas' => trim($record->get('skipareas')),
            'openzips' => trim($record->get('openzips')),
            'zipfilenames' => trim($record->get('zipfilenames')),
            'skipzipfilenames' => trim($record->get('skipzipfilenames')),
        ];

        $id = $record->get('id');
        $filename = $record->get_filename();
        // Create temp output directory.
        if (!$output) {
            $tempfile = true;
            $dir = make_temp_directory('tool_advancedreplace');
            $output = $dir . '/' . $record->get_temp_filename();
        }

        $stream = fopen($output, 'w');
        $columnheaders = [
            'contextid', 'component', 'filearea', 'itemid', 'filepath', 'filename',
            'mimetype', 'strategy', 'internal', 'replace', 'offset', 'match',
        ];
        fputcsv($stream, $columnheaders);

        [$whereclause, $params] = self::make_where_clause($criteria);
        $record->set('timestart', time());
        $updatetime = time();
        $updatepercent = 0;
        $matchcount = 0;
        $filecount = 0;
        $totalfiles = $DB->count_records_select('files', $whereclause, $params);

        $fileset = $DB->get_recordset_select('files', $whereclause, $params, 'component, filearea, contextid, itemid' );
        foreach ($fileset as $filerecord) {
            $matchcount += self::search_file($filerecord, $criteria, $stream);
            $filecount ++;
            $time = time();
            $percent = round(100 * $filecount / $totalfiles, 2);
            if ($time > $updatetime + 10 || $percent > $updatepercent + 5) {
                // Update progress bar after 5 percent or 10 seconds.
                $record->set('progress', $percent);
                $record->set('matches', $matchcount);
                $record->save();
                $updatetime = $time;
                $updatepercent = $percent;
            }
        }
        $fileset->close();
        fclose($stream);

        $record->set('timeend', time());
        $record->set('progress', 100);
        $record->set('matches', $matchcount);
        $record->save();

        // Save as pluginfile.
        if (!empty($matchcount)) {
            $fs = get_file_storage();
            $fileinfo = [
                'contextid' => \context_system::instance()->id,
                'component' => 'tool_advancedreplace',
                'filearea'  => 'files',
                'itemid'    => $id,
                'filepath'  => '/',
                'filename'  => $filename,
            ];
            $fs->create_file_from_pathname($fileinfo, $output);
        }

        // Remove temp file.
        if (isset($tempfile) && file_exists($output)) {
            @unlink($output);
        }
    }



    /**
     * grep_file_content
     *
     * @param array $csv  Some columns to be output in the csv file.
     * @param string $filecontents The actual bytes of the zip file.
     * @param object $criteria The regular expression to be matched.
     * @param resource $stream The handle for the output file.
     * @return int $matchcount The number of matches found.
     */
    public static function grep_content($csv, $filecontents, $criteria, $stream): int {
        $matchcount = 0;
        if (preg_match_all($criteria->pattern, $filecontents, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $index => $match) {
                $matchcount ++;
                $group = 0;
                while (!empty($matches[$group][$index])) {
                    // Group = 0 for matching the whole regex.
                    // Other groups are for matching parenthesised groups in the regex.
                    $csv[self::CSV_OFFSET + 2 * $group] = $matches[$group][$index][1];
                    $csv[self::CSV_MATCH + 2 * $group] = $matches[$group][$index][0];
                    $group ++;
                }
                fputcsv($stream, $csv);
            }
        }
        return $matchcount;
    }

    /**
     * Search for the pattern in (the subfiles of ) a zip file.
     *
     * @param array $csv Some columns to be output in the csv file.
     * @param string $filecontents The actual bytes of the zip file.
     * @param object $criteria The searching criteria.
     * @param resource $stream The handle for the output file.
     * @return int $matchcount The number of matches found.
     */
    public static function unzip_content(array $csv, string $filecontents, object $criteria, $stream): int {
        static $finfo = null;
        if ($finfo == null) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
        }

        if (strlen($filecontents) == 0) {
            // The file is empty. There will be no match.
            return 0;
        }
        $tmpfile = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($tmpfile, $filecontents);
        $zip = new \ZipArchive();
        $matchcount = 0;
        if (! empty ($criteria->zipfilenames)) {
            $namepattern = '%' . $criteria->zipfilenames . '%i';
        } else {
            $namepattern = '';
        }
        if (! empty ($criteria->skipzipfilenames)) {
            $skipnamepattern = '%' . $criteria->skipzipfilenames . '%i';
        } else {
            $skipnamepattern = '';
        }
        if ($zip->open($tmpfile) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);

                // Filter by file name.
                if ( ! empty($namepattern)) {
                    if ( ! preg_match($namepattern, $stat['name'])) {
                        continue;
                    }
                }
                if ( ! empty($skipnamepattern)) {
                    if ( preg_match($skipnamepattern, $stat['name'])) {
                        continue;
                    }
                }

                $csv[self::CSV_INTERNAL] = $stat['name'];
                $matchcount += self::grep_content($csv, $filecontents, $criteria, $stream);
            }
            $zip->close();
        }
        // Todo: handle exception if zip file cannot be openned.
        unlink($tmpfile);
        return $matchcount;
    }


    /**
     * Search the file, looking for the regular expression.
     * Report the matches into the stream.
     *
     * @param object $filerecord  A row from mdl_files table, indicating the file to be searched.
     * @param object $criteria A regular expression to search for.
     * @param resource $stream  The open csv file to receive the matches.
     * @return int $matchcount The number of matches found.
     */
    public static function search_file(object $filerecord, object $criteria, $stream): int {
        static $fs = null;
        if (empty($fs)) {
            $fs = get_file_storage();
        }
        $file = $fs->get_file(
            $filerecord->contextid,
            $filerecord->component,
            $filerecord->filearea,
            $filerecord->itemid,
            $filerecord->filepath,
            $filerecord->filename
        );

        $csv = [
            self::CSV_CONTEXTID => $filerecord->contextid,
            self::CSV_COMPONENT => $filerecord->component,
            self::CSV_FILEAREA  => $filerecord->filearea,
            self::CSV_ITEMID    => $filerecord->itemid,
            self::CSV_FILEPATH  => $filerecord->filepath,
            self::CSV_FILENAME  => $filerecord->filename,
            self::CSV_MIMETYPE  => $filerecord->mimetype,
        ];
        switch ($filerecord->mimetype) {
            case 'application/zip.h5p':
            case 'application/zip':
                if (empty($criteria->openzips)) {
                    $matchcount = 0;
                } else {
                    $csv[self::CSV_STRATEGY] = 'zip';
                    $matchcount = self::unzip_content($csv, $file->get_content(), $criteria, $stream);
                }
                    break;
            default:
                $csv[self::CSV_STRATEGY] = 'plain';
                $csv[self::CSV_INTERNAL] = '';
                $matchcount = self::grep_content($csv, $file->get_content(), $criteria, $stream);
                    break;
        }
                return $matchcount;
    }

            /**
             * Make a where clause to implement the filtering criteria.
             *
             * The search parameters are:
             * ->components Comma-seperated  component:area pairs.
             * ->skipcomponents Comma-separated components to be omitted.
             * ->skipareas Comma-separated areas to be omitted.
             * ->mimetypes Comma-separated mimetypes to be searched.
             * ->skipmimetypes Comma-separated mimetypes to be omitted.
             * ->filenames Comma-separated filenames to be searched.
             * ->skipfilenames Comma-separated filenames to be omitted.
             *
             * @param object $criteria The search criteria in an object.
             * @return string $whereclause A where clause ready for SQL.
             * @return array $params An array of parameters to go with the where clause.
             */
    public static function make_where_clause(object $criteria): array {
        $params = [];
        $paramnumber = 0;
        $whereclause = '';
        $and = ''; // For first one.

        if ( ! empty($criteria->components)) {
            $whereclause .= $and . '( ';
            $and = ' AND '; // For next one.
            $or = ''; // For first one.
            foreach (explode(',', $criteria->components) as $specification) {
                $subspecifications = explode(':', $specification);
                $paramnumber ++;
                $whereclause .= $or . "(component=:param{$paramnumber}";
                $or = ' OR '; // For next time.
                $params["param{$paramnumber}"] = trim($subspecifications[0]);
                if (! empty($subspecifications[1])) {
                    $paramnumber ++;
                    $whereclause .= " AND filearea=:param{$paramnumber}";
                    $params["param{$paramnumber}"] = trim($subspecifications[1]);
                }
                $whereclause .= ')';
            }
            $whereclause .= ' )';
        }

        if ( ! empty($criteria->mimetypes)) {
            $whereclause .= $and . '( ';
            $and = ' AND '; // For next one.
            $or = ''; // For first time.
            foreach (explode(',', $criteria->mimetypes) as $mimetype) {
                $paramnumber ++;
                $whereclause .= $or ."(mimetype=:param{$paramnumber})";
                $params["param{$paramnumber}"] = trim($mimetype);
                $or = ' OR '; // For next one.
            }
            $whereclause .= ' )';
        }

        if ( ! empty($criteria->filenames)) {
            $whereclause .= $and . '( ';
            $and = ' AND '; // For next one.
            $or = ''; // For first time.
            foreach (explode(',', $criteria->filenames) as $filename) {
                $paramnumber ++;
                $whereclause .= $or . "(filename=:param{$paramnumber})";
                $or = ' OR '; // For next one.
                $params["param{$paramnumber}"] = trim($filename);
            }
            $whereclause .= ' )';
        }

        if ( ! empty($criteria->skipcomponents)) {
            foreach (explode(',', $criteria->skipcomponents) as $component) {
                $paramnumber ++;
                $params["param{$paramnumber}"] = trim($component);
                $whereclause .= $and . "(component!=:param{$paramnumber})";
                $and = ' AND '; // For next one.
            }
        }

        if ( ! empty($criteria->skipmimetypes)) {
            foreach (explode(',', $criteria->skipmimetypes) as $mimetype) {
                $paramnumber ++;
                $params["param{$paramnumber}"] = trim($mimetype);
                $whereclause .= " AND (mimetype!=:param{$paramnumber})";
                $and = ' AND '; // For next one.
            }
        }

        if ( ! empty($criteria->skipfilenames)) {
            foreach (explode(',', $criteria->skipfilenames) as $filename) {
                $paramnumber ++;
                $params["param{$paramnumber}"] = trim($filename);
                $whereclause .= $and . "(filename!=:param{$paramnumber})";
                $and = ' AND '; // For next one.
            }
        }

        if ( ! empty($criteria->skipareas)) {
            foreach (explode(',', $criteria->skipareas) as $area) {
                $paramnumber ++;
                $params["param{$paramnumber}"] = trim($area);
                $whereclause .= $and . "(filearea!=:param{$paramnumber})";
                $and = ' AND '; // For next one.
            }
        }

        return [$whereclause, $params];
    }
}



