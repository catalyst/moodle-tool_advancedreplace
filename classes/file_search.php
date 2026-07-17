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
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Helper class to search and replace text in moddle files.
 *
 * @package    tool_advancedreplace
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_search {
    /** @var int Column of csv output to hold the id of the file. */
    const CSV_FILEID = 0;
    /** @var int Column of csv output to hold the course id.  */
    const CSV_COURSEID = 1;
    /** @var int Column of csv output to hold the course shortname.  */
    const CSV_COURSESNM = 2;

    /**
    * 1st column of csv output - the contextid column of the mdl_files table.
    *
    * @var int
    */
    const CSV_CONTEXTID = 3;

    /**
    * 2nd column of csv output - the component column of the mdl_files table.
    *
    * @var int
    */
    const CSV_COMPONENT = 4;

    /**
    * 3rd column of csv output - the filearea column of the mdl_files table.
    *
    * @var int
    */
    const CSV_FILEAREA  = 5;

    /**
    * 4th column of csv output - the itemid column of the mdl_files table.
    *
    * @var int
    */
    const CSV_ITEMID    = 6;

    /**
    * 5th column of csv output - the filepath column of the mdl_files table.
    *
    * @var int
    */
    const CSV_FILEPATH  = 7;

    /**
    * 6th column of csv output - the filename column of the mdl_files table.
    *
    * @var int
    */
    const CSV_FILENAME  = 8;

    /**
    * 7th column of csv output - the mimetype column of the mdl_files table.
    *
    * @var int
    */
    const CSV_MIMETYPE  = 9;

    /**
    * 8th column of csv output - the strategy used to search the file.
    *
    * @var int
    */
    const CSV_STRATEGY  = 10;

    /**
    * 9th column of csv output - some internal information, depending on the strategy.
    *
    * For example, for zip strategy, this will be path used inside the zip for the subfile being searched.
    *
    * @var int
    */
    const CSV_INTERNAL  = 11;

    /**
    * 12th column of csv file - the replacement text.
    *
    * @var int
    */
    const CSV_REPLACE   = 12;

    /**
    * 10th column of csv output - the offset of the match within the file.
    *
    * @var int
    */
    const CSV_OFFSET    = 13;

    /**
    * 11th column of csv output - the text that was matched.
    *
    * @var int
    */
    const CSV_MATCH     = 14;

    /** @var int Chunk size for splitting. 10MB to make border cases rare. */
    const CHUNK_SIZE = 10 * 1024 * 1024;

    /**
     * Transforms a file record into critera for a where clause
     * @param \tool_advancedreplace\files $record
     * @return object
     */
    public static function get_criteria(files $record): object {
        return (object) [
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
    }

    /**
     * Searches the DB using a persistent record.
     *
     * @param \tool_advancedreplace\files $record
     * @param string $output path
     * @param int $startid minimum id for sql
     * @param int $endid maximum id for sql
     * @param bool $finalshard True if this is the ladt shard
     * @return void
     */
    public static function files(
        files $record,
        string $output = '',
        int $startid = 0,
        int $endid = 0,
        bool $finalshard = false
    ) {
        global $DB;
        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);
        $processing = true;
        $criteria = self::get_criteria($record);

        $id = $record->get('id');
        $logmessage = "Advanced search in files, job $id.";
        $shard = $record->is_shard();
        // Create a shared temp output directory.
        if (!$output) {
            $tempfile = true;
            $dir = make_temp_directory('tool_advancedreplace');
            $output = $dir . '/' . $record->get_temp_filename();
        }

        [$whereclause, $params] = self::make_where_clause($criteria);
        // If we are running a shard, then restrict the range of id.
        if (!empty($startid) || !empty($endid)) {
            if (empty($finalshard)) {
                $logmessage .= " Shard from $startid to $endid.";
                $whereclause .= ' AND f.id between :startid and :endid';
                $params['startid'] = $startid;
                $params['endid'] = $endid;
            } else {
                $logmessage .= " Final shard from $startid.";
                $whereclause .= ' AND f.id >= :startid';
                $params['startid'] = $startid;
            }
        }

        // If the output file already exists, try to resume.
        if (file_exists($output)) {
            // This must be a resumed job. We need to append to previous output.
            [$resumeid, $matchcount] = self::resume($output);
        } else {
            $resumeid = 0;
            $matchcount = 0;
        }
        if (!empty($resumeid)) {
            $logmessage .= " Resume from $resumeid.";
            $stream = fopen($output, 'a');
            $whereclause .= ' AND f.id >= :resumeid ';
            $params['resumeid'] = $resumeid;
        } else {
            $stream = fopen($output, 'w');
            $columnheaders = [
                'fileid', 'courseid', 'shortname', 'contextid', 'component', 'filearea', 'itemid', 'filepath', 'filename',
                'mimetype', 'strategy', 'internal', 'replace', 'offset', 'match',
            ];
            fputcsv($stream, $columnheaders);
        }

        mtrace($logmessage);
        $record->set('timestart', time());
        $filecount = 0;
        $total = $DB->get_record_sql("SELECT COUNT('x') total FROM {files} f WHERE " . $whereclause, $params);
        $totalfiles = $total->total;
        $record->mark_started($totalfiles);
        $sql = "
            SELECT
                f.id, f.component, f.filearea, f.contextid, f.itemid, f.filename, f.filepath, f.mimetype,
                c.id AS courseid, c.shortname
            FROM {files} f
            JOIN {context} ctx ON ctx.id = f.contextid
            LEFT JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = 70
            LEFT JOIN {course} c ON c.id = CASE
                                               WHEN ctx.contextlevel = 50 THEN ctx.instanceid
                                               WHEN ctx.contextlevel = 70 THEN cm.course
                                           END
            WHERE $whereclause
            ORDER BY f.id
        ";
        $fileset = $DB->get_recordset_sql($sql, $params);
        foreach ($fileset as $filerecord) {
            $record->update_progress_bar("Searching in $filerecord->component:$filerecord->filename");
            $matchcount += self::search_file($filerecord, $criteria, $stream);
            $filecount++;
            // Update status. If this returns false, the record is gone so stop searching.
            if (!$processing = $record->update_status($filecount, $matchcount)) {
                break;
            }
        }
        $fileset->close();
        fclose($stream);

        if ($processing) {
            $record->mark_finished($matchcount);
            $record->save_pluginfile($output);
        }
        // Remove temp file.
        if (isset($tempfile) && file_exists($output) && !$shard) {
            @unlink($output);
        }

        if ($processing && $shard) {
            $parent = $record->get_parent();
            if (isset($parent) && $parent->shards_finished()) {
                self::combine_shard_output($parent);
            }
        }
    }

    /**
     * Combines shard output into the parent once all shards are finished
     * @param \tool_advancedreplace\files $parent
     * @return void
     */
    public static function combine_shard_output(files $parent): void {
        // Load shards.
        $files = [];
        $shards = $parent->get_all_shards();
        $matches = 0;
        foreach ($shards as $shard) {
            $files[] = $shard->get_temp_filepath();
            $matches += $shard->get('matches');
        }

        // Update parent.
        $parent->mark_finished($matches);

        if (!empty($matches)) {
            // Copy data into one csv.
            $dir = make_request_directory();
            $outputpath = $dir . '/' . $parent->get_filename();
            $output = fopen($outputpath, 'w');
            $firstfile = true;
            foreach ($files as $file) {
                if (!$input = fopen($file, 'r')) {
                    continue;
                }

                // Read and discard the header from later files.
                if (!$firstfile) {
                    fgets($input);
                }

                // Pass the rest of the input to the output.
                while (!feof($input)) {
                    $buffer = fread($input, 8192);
                    fwrite($output, $buffer);
                }
                fclose($input);
                $firstfile = false;
            }
            fclose($output);

            // Create new pluginfile.
            $parent->save_pluginfile($outputpath);
        }

        // Remove old temp files.
        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
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
                $matchcount++;
                $group = 0;
                while (!empty($matches[$group][$index])) {
                    // Group = 0 for matching the whole regex.
                    // Other groups are for matching parenthesised groups in the regex.
                    $csv[self::CSV_OFFSET + 2 * $group] = $matches[$group][$index][1];
                    $csv[self::CSV_MATCH + 2 * $group] = $matches[$group][$index][0];
                    $group++;
                }
                fputcsv($stream, $csv);
            }
        }
        return $matchcount;
    }

    /**
     * Handles processing of a grep search by splitting files into smaller chunks when required.
     *
     * @param array $csv Some columns to be output in the csv file.
     * @param \stored_file|\ZipArchive $file The file or zip archive.
     * @param object $criteria The regular expression to be matched.
     * @param resource $stream The stream handle for the output file.
     * @param array $zipstat information about the file inside a zip full.
     * @return int $matchcount The number of matches found.
     */
    public static function grep_processor($csv, $file, $criteria, $stream, $zipstat = []) {
        $storedfile = $file instanceof \stored_file;

        // For files smaller than chunk size, just open them directly.
        $filesize = $storedfile ? $file->get_filesize() : $zipstat['size'];
        if ($filesize < self::CHUNK_SIZE) {
            $content = $storedfile ? $file->get_content() : $file->getFromIndex($zipstat['index']);
            return self::grep_content($csv, $content, $criteria, $stream);
        }

        // For large files, use a file handle stream instead.
        $matchcount = 0;
        $handle = $storedfile ? $file->get_content_file_handle() : $file->getStream($file->getNameIndex($zipstat['index']));
        if (empty($handle)) {
            return $matchcount;
        }

        $prev = '';
        while (!feof($handle)) {
            // Prepend the last few characters to the next iteration so no border cases are missed.
            $chunk = $prev . fread($handle, self::CHUNK_SIZE);
            $matchcount += self::grep_content($csv, $chunk, $criteria, $stream);
            $prev = substr($chunk, -100);
        }

        fclose($handle);
        return $matchcount;
    }

    /**
     * Search for the pattern in (the subfiles of ) a zip file.
     *
     * @param array $csv Some columns to be output in the csv file.
     * @param \stored_file $file stored_file instance.
     * @param object $criteria The searching criteria.
     * @param resource $stream The handle for the output file.
     * @return int $matchcount The number of matches found.
     */
    public static function unzip_content(array $csv, \stored_file $file, object $criteria, $stream): int {
        static $finfo = null;
        static $dir = null;
        if ($finfo == null) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
        }
        if ($dir === null) {
            $dir = make_request_directory();
        }

        // Copy zip file to local temp.
        $matchcount = 0;
        $tmpzip = tempnam($dir, 'zip');
        if (!$tmpzip || !$file->copy_content_to($tmpzip)) {
            @unlink($tmpzip);
            return $matchcount;
        }

        $zip = new \ZipArchive();
        if (!empty($criteria->zipfilenames)) {
            $namepattern = '%' . $criteria->zipfilenames . '%i';
        } else {
            $namepattern = '';
        }
        if (!empty($criteria->skipzipfilenames)) {
            $skipnamepattern = '%' . $criteria->skipzipfilenames . '%i';
        } else {
            $skipnamepattern = '';
        }
        if ($zip->open($tmpzip) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);

                // Filter by file name.
                if (!empty($namepattern)) {
                    if (!preg_match($namepattern, $stat['name'])) {
                        continue;
                    }
                }
                if (!empty($skipnamepattern)) {
                    if (preg_match($skipnamepattern, $stat['name'])) {
                        continue;
                    }
                }
                $csv[self::CSV_INTERNAL] = $stat['name'];
                $matchcount += self::grep_processor($csv, $zip, $criteria, $stream, $stat);
            }
            $zip->close();
        }
        // Todo: handle exception if zip file cannot be openned.
        unlink($tmpzip);
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
        $matchcount = 0;
        $file = $fs->get_file(
            $filerecord->contextid,
            $filerecord->component,
            $filerecord->filearea,
            $filerecord->itemid,
            $filerecord->filepath,
            $filerecord->filename
        );

        if (!$file) {
            return $matchcount;
        }

        // Skip searching external files.
        if ($file->is_external_file()) {
            // Check whether the repository uses internal files.
            $repository = \repository::get_repository_by_id($file->get_repository_id(), \context_system::instance());
            if (!$repository->has_moodle_files()) {
                return $matchcount;
            }
        }

        $csv = [
            self::CSV_FILEID    => $filerecord->id,
            self::CSV_COURSEID  => $filerecord->courseid,
            self::CSV_COURSESNM => $filerecord->shortname,
            self::CSV_CONTEXTID => $filerecord->contextid,
            self::CSV_COMPONENT => $filerecord->component,
            self::CSV_FILEAREA  => $filerecord->filearea,
            self::CSV_ITEMID    => $filerecord->itemid,
            self::CSV_FILEPATH  => $filerecord->filepath,
            self::CSV_FILENAME  => $filerecord->filename,
            self::CSV_MIMETYPE  => $filerecord->mimetype,
            self::CSV_STRATEGY  => 'plain',
            self::CSV_INTERNAL  => '',
            self::CSV_REPLACE   => '',
        ];
        switch ($filerecord->mimetype) {
            case 'application/zip.h5p':
            case 'application/zip':
                if (!empty($criteria->openzips)) {
                    $csv[self::CSV_STRATEGY] = 'zip';
                    $matchcount = self::unzip_content($csv, $file, $criteria, $stream);
                }
                break;
            default:
                $matchcount = self::grep_processor($csv, $file, $criteria, $stream);
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

        if (!empty($criteria->components)) {
            $whereclause .= $and . '( ';
            $and = ' AND '; // For next one.
            $or = ''; // For first one.
            foreach (explode(',', $criteria->components) as $specification) {
                $subspecifications = explode(':', $specification);
                $paramnumber++;
                $whereclause .= $or . "(component=:param{$paramnumber}";
                $or = ' OR '; // For next time.
                $params["param{$paramnumber}"] = trim($subspecifications[0]);
                if (!empty($subspecifications[1])) {
                    $paramnumber++;
                    $whereclause .= " AND filearea=:param{$paramnumber}";
                    $params["param{$paramnumber}"] = trim($subspecifications[1]);
                }
                $whereclause .= ')';
            }
            $whereclause .= ' )';
        }

        if (!empty($criteria->mimetypes)) {
            $whereclause .= $and . '( ';
            $and = ' AND '; // For next one.
            $or = ''; // For first time.
            foreach (explode(',', $criteria->mimetypes) as $mimetype) {
                $paramnumber++;
                $whereclause .= $or . "(mimetype=:param{$paramnumber})";
                $params["param{$paramnumber}"] = trim($mimetype);
                $or = ' OR '; // For next one.
            }
            $whereclause .= ' )';
        }

        if (!empty($criteria->filenames)) {
            $whereclause .= $and . '( ';
            $and = ' AND '; // For next one.
            $or = ''; // For first time.
            foreach (explode(',', $criteria->filenames) as $filename) {
                $paramnumber++;
                $whereclause .= $or . "(filename=:param{$paramnumber})";
                $or = ' OR '; // For next one.
                $params["param{$paramnumber}"] = trim($filename);
            }
            $whereclause .= ' )';
        }

        if (!empty($criteria->skipcomponents)) {
            foreach (explode(',', $criteria->skipcomponents) as $component) {
                $paramnumber++;
                $params["param{$paramnumber}"] = trim($component);
                $whereclause .= $and . "(component!=:param{$paramnumber})";
                $and = ' AND '; // For next one.
            }
        }

        if (!empty($criteria->skipmimetypes)) {
            foreach (explode(',', $criteria->skipmimetypes) as $mimetype) {
                $paramnumber++;
                $params["param{$paramnumber}"] = trim($mimetype);
                $whereclause .= " AND (mimetype!=:param{$paramnumber})";
                $and = ' AND '; // For next one.
            }
        }

        if (!empty($criteria->skipfilenames)) {
            foreach (explode(',', $criteria->skipfilenames) as $filename) {
                $paramnumber++;
                $params["param{$paramnumber}"] = trim($filename);
                $whereclause .= $and . "(filename!=:param{$paramnumber})";
                $and = ' AND '; // For next one.
            }
        }

        if (!empty($criteria->skipareas)) {
            foreach (explode(',', $criteria->skipareas) as $area) {
                $paramnumber++;
                $params["param{$paramnumber}"] = trim($area);
                $whereclause .= $and . "(filearea!=:param{$paramnumber})";
                $and = ' AND '; // For next one.
            }
        }

        if (empty($whereclause)) {
            $whereclause = '1 = 1';
        }

        return [$whereclause, $params];
    }

    /**
     * Determine the id of a line from the output csv file.
     *
     * @param string $line of an output file.
     */
    public static function get_id_from_csv(string $line): int {
        // Interpret the last line as a csv line.
        $csv = str_getcsv($line, ",", "\"", "\\");
        // Check a few columns to ensure we have a valid line.
        if (empty($csv[self::CSV_CONTEXTID])) {
            return 0;
        }
        if (!ctype_digit($csv[self::CSV_CONTEXTID])) {
            return 0;
        }
        if (empty($csv[self::CSV_FILEID])) {
            return 0;
        }
        if (!ctype_digit($csv[self::CSV_FILEID])) {
            return 0;
        }

        return $csv[self::CSV_FILEID];
    }

    /**
     * Look at the previous output file to decide how to resume.
     *
     * @param string $filename
     * @return int $resumeid The first id that should be scanned.
     * @return int $matchcount The number of matches left in the file.
     */
    public static function resume(string $filename): array {
        if (!file_exists($filename)) {
            return [0, 0];
        }

        $lines = file($filename, FILE_IGNORE_NEW_LINES);
        if (count($lines) < 3) {
            // Too small to resume.
            return [0, 0];
        }
        $lastline = end($lines);
        $resumeid = self::get_id_from_csv($lastline);
        if (empty($resumeid)) {
            array_pop($lines);
            $lastline = end($lines);
            $resumeid = self::get_id_from_csv($lastline);
            if (empty($resumeid)) {
                // If last two lines are bad, give up.
                return [0, 0];
            }
        }
        // Now remove all lines that match this id.
        while (true) {
            array_pop($lines);
            $line = end($lines);
            $lineid = self::get_id_from_csv($line);
            if ($lineid != $resumeid) {
                // Leave this line in place.
                break;
            }
            if (count($lines) < 3) {
                // Too small to resume.
                return [0, 0];
            }
        }
        // Re-write the file, without the matched id lines.
        file_put_contents($filename, implode(PHP_EOL, $lines) . PHP_EOL);
        $matchcount = count($lines) - 1;
        return [$resumeid, $matchcount];
    }
}
