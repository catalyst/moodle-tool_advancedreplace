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
    * 10th column of csv output - the offset of the match within the file.
    *
    * @var int
    */
    const CSV_OFFSET    = 9;

    /**
    * 11th column of csv output - the text that was matched.
    *
    * @var int
    */
    const CSV_MATCH     = 10;

    /**
    * 12th column of csv file - the replacement text.
    *
    * @var int
    */
    const CSV_REPLACE   = 11;

    /**
     * Searches the DB using a persistent record.
     *
     * @param \tool_advancedreplace\files $record
     * @param string $output path
     * @return void
     */
    public static function files(\tool_advancedreplace\files $record, string $output = ''): void {
        global $DB;
        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);
        $id = $record->get('id');
        $pattern = trim($record->get('pattern'));
        $components = trim($record->get('components'));
        $skipcomponents = trim($record->get('skipcomponents'));
        $mimetypes = trim($record->get('mimetypes'));
        $skipmimetypes = trim($record->get('skipmimetypes'));
        $filenames = trim($record->get('filenames'));
        $skipfilenames = trim($record->get('skipfilenames'));
        $skipareas = trim($record->get('skipareas'));
        $filename = \tool_advancedreplace\files::get_filename($record->to_record());
        // Create temp output directory.
        if (!$output) {
            $dir = make_request_directory();
            $output = $dir . '/' . $filename;
        }

        // Start output.
        $stream = fopen($output, 'w');

        $columnheaders = [
            'contextid', 'component', 'filearea', 'itemid', 'filepath', 'filename',
            'mimetype', 'strategy', 'internal', 'offset', 'match', 'replace',
        ];
        fputcsv($stream, $columnheaders);

        $pattern = '/' . $pattern . '/i';

        [$whereclause, $params] =
        self::make_where_clause($components, $skipcomponents, $skipareas,
        $mimetypes, $skipmimetypes, $filenames, $skipfilenames);
        $record->set('timestart', time());
        $updatetime = time();
        $updatepercent = 0;
        $matchcount = 0;
        $filecount = 0;
        $totalfiles = $DB->count_records_select('files', $whereclause, $params);

        $fileset = $DB->get_recordset_select('files', $whereclause, $params, 'component, filearea, contextid, itemid' );
        foreach ($fileset as $filerecord) {
            $matchcount += self::search_file($filerecord, $pattern, $stream);
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
    }



    /**
     * grep_file_content
     *
     * @param array $csv  Some columns to be output in the csv file.
     * @param string $content The actual bytes of the zip file.
     * @param string $pattern The regular expression to be matched.
     * @param resource $stream The handle for the output file.
     * @return int $matchcount The number of matches found.
     */
    public static function grep_content($csv, $content, $pattern, $stream): int {
        $matchcount = 0;
        if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
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
     * @param string $content The actual bytes of the zip file.
     * @param string $pattern The regular expression to be matched.
     * @param resource $stream The handle for the output file.
     * @return int $matchcount The number of matches found.
     */
    public static function unzip_content($csv, $content, $pattern, $stream): int {
        static $finfo = null;
        if ($finfo == null) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
        }

        if (strlen($content) == 0) {
            // The file is empty. There will be no match.
            return 0;
        }
        $tmpfile = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($tmpfile, $content);
        $zip = new \ZipArchive();
        $matchcount = 0;
        if ($zip->open($tmpfile) === true) {
            // Extract the contents or work with the ZIP file here.
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $filecontents = $zip->getFromIndex($i);
                $mimetype = $finfo->buffer($filecontents);
                $csv[self::CSV_INTERNAL] = $stat['name'];
                $matchcount += self::grep_content($csv, $filecontents, $pattern, $stream);
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
     * @param string $pattern A regular expression to search for.
     * @param resource $stream  The open csv file to receive the matches.
     * @return int $matchcount The number of matches found.
     */
    public static function search_file(object $filerecord, string $pattern, $stream): int {
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
                $csv[self::CSV_STRATEGY] = 'zip';
                $matchcount = self::unzip_content($csv, $file->get_content(), $pattern, $stream);
                break;
            default:
                $csv[self::CSV_STRATEGY] = 'plain';
                $csv[self::CSV_INTERNAL] = '';
                $matchcount = self::grep_content($csv, $file->get_content(), $pattern, $stream);
                break;
        }
            return $matchcount;
    }

        /**
         * Make a where clause to implement the filtering criteria.
         *
         * @param string $components Comma-seperated  component:area pairs.
         * @param string $skipcomponents Comma-separated components to be omitted.
         * @param string $skipareas Comma-separated areas to be omitted.
         * @param string $mimetypes Comma-separated mimetypes to be searched.
         * @param string $skipmimetypes Comma-separated mimetypes to be omitted.
         * @param string $filenames Comma-separated filenames to be searched.
         * @param string $skipfilenames Comma-separated filenames to be omitted.
         * @return string $whereclause A where clause ready for SQL.
         * @return array $params An array of parameters to go with the where clause.
         */
    public static function make_where_clause($components, $skipcomponents, $skipareas,
        $mimetypes, $skipmimetypes, $filenames, $skipfilenames) {
        $params = [];
        $paramnumber = 0;
        $whereclause = '';
        $and = ''; // For first one.

        if ( ! empty($components)) {
            $whereclause .= $and . '( ';
            $and = ' AND '; // For next one.
            $or = ''; // For first one.
            foreach (explode(',', $components) as $specification) {
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

        if ( ! empty($mimetypes)) {
            $whereclause .= $and . '( ';
            $and = ' AND '; // For next one.
            $or = ''; // For first time.
            foreach (explode(',', $mimetypes) as $mimetype) {
                $paramnumber ++;
                $whereclause .= $or ."(mimetype=:param{$paramnumber})";
                $params["param{$paramnumber}"] = trim($mimetype);
                $or = ' OR '; // For next one.
            }
            $whereclause .= ' )';
        }

        if ( ! empty($filenames)) {
            $whereclause .= $and . '( ';
            $and = ' AND '; // For next one.
            $or = ''; // For first time.
            foreach (explode(',', $filenames) as $filename) {
                $paramnumber ++;
                $whereclause .= $or . "(filename=:param{$paramnumber})";
                $or = ' OR '; // For next one.
                $params["param{$paramnumber}"] = trim($filename);
            }
            $whereclause .= ' )';
        }

        if ( ! empty($skipcomponents)) {
            foreach (explode(',', $skipcomponents) as $component) {
                $paramnumber ++;
                $params["param{$paramnumber}"] = trim($component);
                $whereclause .= $and . "(component!=:param{$paramnumber})";
                $and = ' AND '; // For next one.
            }
        }

        if ( ! empty($skipmimetypes)) {
            foreach (explode(',', $skipmimetypes) as $mimetype) {
                $paramnumber ++;
                $params["param{$paramnumber}"] = trim($mimetype);
                $whereclause .= " AND (mimetype!=:param{$paramnumber})";
                $and = ' AND '; // For next one.
            }
        }

        if ( ! empty($skipfilenames)) {
            foreach (explode(',', $skipfilenames) as $filename) {
                $paramnumber ++;
                $params["param{$paramnumber}"] = trim($filename);
                $whereclause .= $and . "(filename!=:param{$paramnumber})";
                $and = ' AND '; // For next one.
            }
        }

        if ( ! empty($skipareas)) {
            foreach (explode(',', $skipareas) as $area) {
                $paramnumber ++;
                $params["param{$paramnumber}"] = trim($area);
                $whereclause .= $and . "(filearea!=:param{$paramnumber})";
                $and = ' AND '; // For next one.
            }
        }

        return [$whereclause, $params];
    }
}

