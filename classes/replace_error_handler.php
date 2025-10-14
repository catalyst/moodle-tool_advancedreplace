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

/**
 * Replace error handler.
 *
 * @package    tool_advancedreplace
 * @copyright  2025 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class replace_error_handler {
    /** @var \flexible_table table */
    protected $table = null;

    /** @var array row data */
    protected $rowdata = [];

    /** @var string type of replace */
    protected $type = '';

    /** @var array db columns */
    protected const DB_COLUMNS = [
        'row',
        'table',
        'column',
        'id',
        'error',
    ];

    /** @var array file columns */
    protected const FILE_COLUMNS = [
        'row',
        'component',
        'filearea',
        'filename',
        'error',
    ];

    /** @var array cli character length mapping */
    protected const CLI_CHAR_MAPPING = [
        'row' => 10,
        'id' => 10,
        'table' => 32,
        'column' => 32,
        'component' => 24,
        'filearea' => 24,
        'filename' => 32,
        'error' => 50,
    ];

    /**
     * Sets up error output.
     * @return void
     */
    protected function init(): void {
        global $PAGE;

        if (!CLI_SCRIPT) {
            $table = new \flexible_table('error-table');
            $table->baseurl = $PAGE->url;
            $table->define_columns($this->get_columns());
            $table->define_headers($this->get_headers());
            $table->set_attribute('class', 'admintable generaltable');
            $table->setup();
            $this->table = $table;
        }
    }

    /**
     * Add a row of error output
     * @param array $row output data
     * @return void
     */
    public function add(array $row): void {
        if (!CLI_SCRIPT) {
            if (!isset($this->table)) {
                $this->init();
            }
            $this->table->add_data($row);
        } else {
            // Otherwise store data to print later.
            $this->rowdata[] = $row;
        }
    }

    /**
     * Finish error output
     * @return void
     */
    public function finish(): void {
        if (!CLI_SCRIPT) {
            if (isset($this->table)) {
                $this->table->finish_output();
            }
        } else if (!empty($this->rowdata)) {
            // Print CLI error output at end.
            $format = $this->get_cli_format();
            $headers = $this->get_headers();
            mtrace(sprintf($format, ...$headers));
            foreach ($this->rowdata as $row) {
                mtrace(sprintf($format, ...$row));
            }
        }
    }

    /**
     * Sets the type of replace
     * @param string $type
     * @return void
     */
    public function set_type(string $type): void {
        $this->type = $type;
    }

    /**
     * Gets columns for the error table
     * @return array columns
     */
    protected function get_columns(): array {
        if ($this->type === 'db') {
            return self::DB_COLUMNS;
        } else if ($this->type === 'files') {
            return self::FILE_COLUMNS;
        }
        return [];
    }

    /**
     * Gets headers for the error table
     * @return array headers
     */
    protected function get_headers(): array {
        $headers = [];
        $columns = $this->get_columns();
        foreach ($columns as $column) {
            $headers[] = get_string('field_' . $column, 'tool_advancedreplace');
        }
        return $headers;
    }

    /**
     * Gets the formatting of sprintf for CLI output
     * @return string format
     */
    protected function get_cli_format(): string {
        $format = '';
        $columns = $this->get_columns();
        foreach ($columns as $column) {
            $format .= '%-' . self::CLI_CHAR_MAPPING[$column] . 's ';
        }
        return trim($format);
    }
}
