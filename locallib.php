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
 * Output rendering of engagement report
 *
 * @package    report_engagement
 * @copyright  2012 NetSpot Pty Ltd, 2015 Macquarie University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Generic settings
function report_engagement_get_generic_settings_list() {
	return array('queryspecifydatetime', 'querystartdatetime', 'queryenddatetime');
}
function report_engagement_get_generic_settings_records($courseid) {
	global $DB;
	$generic_settings = report_engagement_get_generic_settings_list();
	list($generic_settings_insql, $generic_settings_inparams) = $DB->get_in_or_equal($generic_settings, SQL_PARAMS_NAMED);
	$generic_settings_queryparams = array('courseid' => $courseid);
	$generic_settings_sql = "SELECT id, name, value FROM {report_engagement_generic} WHERE courseid = :courseid AND name $generic_settings_insql";
	$generic_settings_params = array_merge($generic_settings_inparams, $generic_settings_queryparams);
	return $DB->get_records_sql($generic_settings_sql, $generic_settings_params);
}
function report_engagement_get_generic_settings($courseid) {
	$records = report_engagement_get_generic_settings_records($courseid);
	$settings = array();
	foreach ($records as $record) {
		$setting = new stdClass();
		$setting = $record;
		$settings[$record->name] = $setting;
	}
	return $settings;
}

function report_engagement_sort_indicators($a, $b) {
    global $SESSION;
    $tsort = required_param('tsort', PARAM_ALPHANUMEXT);
    $sort = isset($SESSION->flextable['engagement-course-report']->sortby[$tsort]) ?
                $SESSION->flextable['engagement-course-report']->sortby[$tsort] : SORT_DESC;
    if ($a[$tsort] == $b[$tsort]) {
        return 0;
    }
    if ($sort != SORT_ASC) {
        return $a[$tsort] < $b[$tsort] ? -1 : 1;
    } else {
        return $a[$tsort] > $b[$tsort] ? -1 : 1;
    }
}

function report_engagement_sort_risks($a, $b) {
    global $SESSION;
    $sort = isset($SESSION->flextable['engagement-course-report']->sortby['total']) ?
                $SESSION->flextable['engagement-course-report']->sortby['total'] : SORT_DESC;
    $asum = $bsum = 0;
    foreach ($a as $name => $values) {
        $asum += $values['raw'] * $values['weight'];
    }
    foreach ($b as $name => $values) {
        $bsum += $values['raw'] * $values['weight'];
    }
    if ($asum == $bsum) {
        return 0;
    }
    if ($sort != SORT_ASC) {
        return $asum < $bsum ? -1 : 1;
    } else {
        return $asum > $bsum ? -1 : 1;
    }
}

function report_engagement_update_indicator($courseid, $new_weights, $configdata = array()) {
    global $DB;

    $weights = array();
    if ($weightrecords = $DB->get_records('report_engagement', array('course' => $courseid))) {
        foreach ($weightrecords as $record) {
            $weights[$record->indicator] = $record;
        }
    }
    foreach ($new_weights as $indicator => $weight) {
        $weight = $weight / 100;
        if (!isset($weights[$indicator])) {
            $record = new stdClass();
            $record->course = $courseid;
            $record->indicator = $indicator;
            $record->weight = $weight;
            if (isset($configdata[$indicator])) {
                $record->configdata = base64_encode(serialize($configdata[$indicator]));
            }
            $DB->insert_record('report_engagement', $record);
        } else {
            $weights[$indicator]->weight = $weight;
            if (isset($configdata[$indicator])) {
                $weights[$indicator]->configdata = base64_encode(serialize($configdata[$indicator]));
            }
            $DB->update_record('report_engagement', $weights[$indicator]);
        }
    }
}

/**
 * This function logs a sent message to the database and returns its id.
 *
 * @param string $subject The plain text subject line of the message.
 * @param string $message The text of the message.
 * @param string $type The type of message, can be 'email', 'sms', etc
 */
function message_send_log_message($subject, $message, $type){
	global $DB;
	$data = new stdClass();
	$data->messagesubject = base64_encode($subject);
	$data->messagebody = base64_encode($message);
	$data->messagetype = $type;
	return $DB->insert_record('report_engagement_messagelog', $data, true);	
} 
 
 /**
 * This function logs a send event to the database.
 *
 * @param int $messageid The id of the message
 * @param string $destination The destination address e.g. email, phone number, etc
 * @param int $recipientid The Moodle userid of the recipient
 * @param int $senderid The Moodle userid of the sender, will default to current user if not provided
 * @param int $courseid The Moodle courseid of the course, will default to current course if not provided
 *
 */
function message_send_log_send($messageid, $destination, $recipientid, $senderid = null, $courseid = null){
	global $DB, $USER, $COURSE;
	if (!isset($senderid)) {
		$senderid = $USER->id;
	}
	if (!isset($courseid)) {
		$courseid = $COURSE->id;
	}
	$data = new stdClass();
	$data->timesent = time();
	$data->messageid = $messageid;
	$data->destinationaddress = $destination;
	$data->recipientid = $recipientid;
	$data->senderid = $senderid;
	$data->courseid = $courseid;
	return $DB->insert_record('report_engagement_sentlog', $data);	
}
 
/**
 * This function saves a message and its description to the database.
 *
 * @param string $description The short description of the message
 * @param string $message The message text itself, should be base64 encoded already
 * @param int $userid Optional - the Moodle userid of the user, defaults to currently logged in user
 * @return int The id of the new row in the database
 */
function my_message_save($description, $message, $userid = null) {
	global $DB, $USER;
	if (!isset($userid)) {
		$userid = $USER->id;
	}
	$data = new stdClass();
	$data->userid = $userid;
	$data->messagesummary = base64_encode($description);
	$data->messagetext = $message;
	return $DB->insert_record('report_engagement_mymessages', $data);	
}

/**
 * This function retrieves saved 'My Messages' for the user from the database
 *
 * @param int $userid Optional - the Moodle userid of the user, defaults to currently logged in user
 * @return object The database record
 */
function my_messages_get($userid = null){
	global $DB, $USER;
	if (!isset($userid)) {
		$userid = $USER->id;
	}
	return $DB->get_records('report_engagement_mymessages', array('userid' => $userid));
}

/**
 * This function replaces the variables in a message with their actual values belonging to a specified user.
 *
 * @param string $message The text that contains needles (the variables) to replace
 * @param int $userid The Moodle userid of the user whose data will be filled in place of the variables
 * @return string The text with replacements
 */
function message_variables_replace($message, $userid) {
	global $DB;
	$user = $DB->get_record('user', array('id'=>$userid));
	$out = $message;
	$out = str_replace("{#FIRSTNAME#}", $user->firstname, $out);
	$out = str_replace("{#LASTNAME#}", $user->lastname, $out);
	$out = str_replace("{#FULLNAME#}", fullname($user), $out);
	return $out;
}

/**
 * This function replaces the variables in a message with their actual values belonging to a specified user.
 *
 * @param string $message The text that contains needles (the variables) to replace
 * @param int $userid The Moodle userid of the user whose data will be filled in place of the variables
 * @return string The text with replacements
 */
function message_variables_get_array() {
	return array(
		"{#FIRSTNAME#}" => get_string('message_variables_firstname', 'report_engagement'),
		"{#LASTNAME#}" => get_string('message_variables_lastname', 'report_engagement'),
		"{#FULLNAME#}" => get_string('message_variables_fullname', 'report_engagement'),
	);
}

/**
 * This function calls email_to_user to send the specified email to the specified user.
 *
 * @param array $message Elements message and subject
 * @param int $recipientid The Moodle userid of the recipient of the email
 * @param int $senderid The Moodle userid of the sender of the email
 * @return object An object containing a recipient object and the return value of email_to_user
 */
function message_send_customised_email($message, $recipientid, $senderid, $replytoid) {
	global $DB, $USER;
	if (!isset($senderid)) {
		$senderid = $USER->id;
	}
	if (!isset($replytoid)) {
		$replytoid = $USER->id;
	}
	$recipient = $DB->get_record('user', array('id'=>$recipientid));
	$recipient->email = 'danny.liu@mq.edu.au';	// DEBUG ONLY
	$sender = $DB->get_record('user', array('id'=>$senderid));
	$replyto = $DB->get_record('user', array('id'=>$replytoid));
	$email_body = $message['message'];
	$email_subject = $message['subject']; 
	// Prepare return variable
	$result = new stdClass();
	$result->recipient = $recipient;
	// Try send email
	try {
		//$result->result = email_to_user($recipient, $sender, $email_subject, $email_body, $email_body, '', '', true, $replyto->email, fullname($replyto));
		$result->result = true; // DEBUG ONLY
		return $result;
	} catch (Exception $e) {
		$result->result = false;
		return $result;
	}
	// try email_to_user 
	// http://articlebin.michaelmilette.com/sending-custom-emails-in-moodle-using-the-email_to_user-function/
	// https://github.com/moodle/moodle/blob/d302ba231ff20d744be953f92d4c687703c36332/lib/moodlelib.php
	// examples in the above file
	// example https://github.com/moodle/moodle/blob/b6a76cd7cdf588b8d31440d072930906fd4b357b/user/edit.php
}

