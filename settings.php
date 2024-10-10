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

/**
 * Plugin administration pages are defined here.
 *
 * @package    tool_advancedreplace
 * @copyright  2024 Catalyst IT Australia Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $category = new admin_category('advancereplacefolder', get_string('pluginname', 'tool_advancedreplace'));
    $ADMIN->add('tools', $category);

    $settings = new admin_settingpage('tool_advancedreplace', get_string('generalsettings', 'admin'));
    $ADMIN->add('advancereplacefolder', $settings);

    $ADMIN->add(
        'advancereplacefolder',
        new admin_externalpage(
            'tool_advancedreplace_search',
            get_string('searchpagename', 'tool_advancedreplace'),
            new moodle_url('/admin/tool/advancedreplace/search.php'),
        )
    );

    $settings->add(new admin_setting_configtextarea('tool_advancedreplace/excludetables',
        get_string('settings:excludetables', 'tool_advancedreplace'),
        get_string('settings:excludetables_help', 'tool_advancedreplace'), '', PARAM_TEXT));

    $settings->add(new admin_setting_configtextarea('tool_advancedreplace/includetables',
        get_string('settings:includetables', 'tool_advancedreplace'),
        get_string('settings:includetables_help', 'tool_advancedreplace'), '', PARAM_TEXT));
}
