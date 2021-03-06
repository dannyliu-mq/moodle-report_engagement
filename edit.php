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
 * Displays indicator reports for a chosen course
 *
 * @package    report_engagement
 * @copyright  2012 NetSpot Pty Ltd, 2015-2016 Macquarie University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/edit_form.php');
require_once(dirname(__FILE__).'/locallib.php');

$id = required_param('id', PARAM_INT); // Course ID.
$url = new moodle_url('/report/engagement/edit.php', array('id' => $id));
$reporturl = new moodle_url('/report/engagement/index.php', array('id' => $id));
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);
require_capability('report/engagement:manage', $context);

$strpluginname = get_string('pluginname', 'report_engagement');

$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_context($context);
$PAGE->navbar->add(get_string('reports'));
$PAGE->navbar->add($strpluginname, $reporturl);
$PAGE->navbar->add(get_string('updatesettings', 'report_engagement'), $url);
$PAGE->set_title("$course->shortname: $strpluginname");
$PAGE->set_heading($course->fullname);

$indicators = get_plugin_list('engagementindicator');
$mform = new report_engagement_edit_form(null, array('id' => $id, 'indicators' => $indicators));

$message = '';
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/report/engagement/index.php', array('id' => $id)));
} else if ($formdata = $mform->get_data()) {
    $message = $OUTPUT->notification(get_string('changessaved'), 'notifysuccess');
    $weights = array();
    foreach (array_keys($indicators) as $indicator) {
        $key = "weighting_$indicator";
        $weights[$indicator] = isset($formdata->$key) ? $formdata->$key : 0;
    }

    // Process generic settings.
    $genericsettingslist = report_engagement_get_generic_settings_list();
    $recordsgenericsettings = report_engagement_get_generic_settings_records($id);
    foreach ($genericsettingslist as $setting) {
        $record = new stdClass();
        $record->name = $setting;
        $record->value = $formdata->{"$setting"};
        $record->courseid = $id;
        foreach ($recordsgenericsettings as $recordid => $recordobj) {
            if ($recordobj->name == $setting) {
                $record->id = $recordid;
                continue;
            }
        }
        if (isset($record->id)) {
            $DB->update_record('report_engagement_generic', $record);        
        } else {
            $DB->insert_record('report_engagement_generic', $record);
        }
    }

    // Process thresholds and other indicator specific settings.
    $configdata = array();
    foreach (array_keys($indicators) as $indicator) {
        $indicatorfile = "$CFG->dirroot/mod/engagement/indicator/$indicator/locallib.php";
        if (file_exists($indicatorfile)) {
            require_once($indicatorfile);
            $func = "engagementindicator_{$indicator}_process_edit_form";
            $configdata[$indicator] = $func($formdata);
        }
    }
    report_engagement_update_indicator($id, $weights, $configdata);
}

$data = array();
// Get current values and populate form.
if ($indicators = $DB->get_records('report_engagement', array('course' => $id))) {
    foreach ($indicators as $indicator) {
        $data["weighting_{$indicator->indicator}"] = $totalweights = $indicator->weight * 100;
        $configdata = unserialize(base64_decode($indicator->configdata));
        if (is_array($configdata)) {
            // Pre-process config data if necessary
            $indicatorfile = "$CFG->dirroot/mod/engagement/indicator/{$indicator->indicator}/locallib.php";
            if (file_exists($indicatorfile)) {
                require_once($indicatorfile);
                $func = "engagementindicator_{$indicator->indicator}_preprocess_configdata_for_edit_form";
                if (function_exists($func)) {
                    $configdata = $func($configdata);
                }
            }
            // Merge config data with form data
            $data = array_merge($data, $configdata);
        }
    }
} else {
    // Set defaults for indicator weightings.
    $indicators = get_plugin_list('engagementindicator');
    $weights = [];
    for ($i = 1; $i <= count($indicators); $i++) {
        if ($i != count($indicators)) {
            $weights[] = intval(100 / count($indicators));
        } else {
            $weights[] = 100 - array_sum($weights);
        }
    }
    $i = 0;
    foreach ($indicators as $name => $path) {
        $data["weighting_{$name}"] = $weights[$i];
        $i++;
    }
}
// Generic settings
$genericsettings = report_engagement_get_generic_settings($id);
foreach ($genericsettings as $name => $setting) {
    $data = array_merge($data, array($name => $setting->value));
}
// Set form data
$mform->set_data($data);

// Write to log
$event = \report_engagement\event\settings_updated::create(array(
    'context' => $context, 
    'other' => array(
        'courseid' => $id
    )));
$event->trigger();

echo $OUTPUT->header();
echo $message;
$mform->display();
echo $OUTPUT->footer();
