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
 * @copyright  2015-2016 Macquarie University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
require_once($CFG->dirroot . '/report/engagement/locallib.php');

$id = required_param('id', PARAM_INT); // Course ID.
$mid = optional_param('mid', 0, PARAM_INT); // Message ID.
$uid = optional_param('uid', 0, PARAM_INT); // User ID.
$pageparams = array('id' => $id, 'mid' => $mid, 'uid' => $uid);

$PAGE->set_url('/report/engagement/mailer_log.php', $pageparams);
$PAGE->set_pagelayout('report');

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($course->id);
$PAGE->set_context($context);
$updateurl = new moodle_url('/report/engagement/edit.php', array('id' => $id));
$reporturl = new moodle_url('/report/engagement/index.php', array('id' => $id));
$mailerurl = new moodle_url('/report/engagement/mailer.php', array('id' => $id));
$mailerlogurl = new moodle_url('/report/engagement/mailer_log.php', array('id' => $id));
$PAGE->navbar->add(get_string('reports'));
$PAGE->navbar->add(get_string('pluginname', 'report_engagement'), $reporturl);
$PAGE->navbar->add(get_string('mailer', 'report_engagement'), $mailerurl);
$PAGE->navbar->add(get_string('mailer_message_log', 'report_engagement'), $mailerlogurl);
$PAGE->set_heading($course->fullname);

global $DB;

// Load up jquery.
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');
$PAGE->requires->jquery_plugin('report_engagement-datatables', 'report_engagement');

echo $OUTPUT->header();

require_capability('report/engagement:view', $context);

// Prepare indicators.
$pluginman = core_plugin_manager::instance();
$indicators = get_plugin_list('engagementindicator');
foreach ($indicators as $name => $path) {
    $plugin = $pluginman->get_plugin_info('engagementindicator_'.$name);
    if (!$plugin->is_enabled()) {
        unset($indicators[$name]);
    }
}

// Fetch data.
if ($uid && $id) {
    // Log.
    $event = \report_engagement\event\report_viewed::create(array(
        'context' => $context, 
        'relateduserid' => $uid,
        'other' => array(
            'userid' => $uid,
            'courseid' => $id,
            'page' => 'mailer_log'
        )));
    $event->trigger();
    // Showing all messages for one user in one course.
    $data = $DB->get_records_sql("SELECT ml.*, sl.* 
                                    FROM {report_engagement_messagelog} ml 
                                    JOIN {report_engagement_sentlog} sl ON sl.messageid = ml.id 
                                   WHERE sl.courseid = ? AND sl.recipientid = ? 
                                ORDER BY sl.timesent DESC", array($id, $uid));
    $dataall = $DB->get_records_sql("SELECT ml.*, sl.* 
                                        FROM {report_engagement_messagelog} ml 
                                        JOIN {report_engagement_sentlog} sl ON sl.messageid = ml.id 
                                       WHERE sl.courseid = ? 
                                    ORDER BY sl.timesent DESC", array($id));
    $pagetitle = get_string('mailer_log_user', 'report_engagement');
} else if ($mid && $id) {
    // Log.
    $event = \report_engagement\event\report_viewed::create(array(
        'context' => $context, 
        'other' => array(
            'messageid' => $mid,
            'courseid' => $id,
            'page' => 'mailer_log'
        )));
    $event->trigger();
    // Showing for just one message.
    $data = $DB->get_records_sql("SELECT ml.*, sl.* 
                                    FROM {report_engagement_messagelog} ml 
                                    JOIN {report_engagement_sentlog} sl ON sl.messageid = ml.id 
                                    JOIN {user} u ON u.id = sl.recipientid
                                   WHERE sl.courseid = ? AND sl.messageid = ?
                                ORDER BY u.firstname ASC", array($id, $mid));
    $pagetitle = get_string('mailer_log_message', 'report_engagement');
} else {
    // Log.
    $event = \report_engagement\event\report_viewed::create(array(
        'context' => $context, 
        'other' => array(
            'courseid' => $id,
            'page' => 'mailer_log'
        )));
    $event->trigger();
    // Showing for just one message.
    // Showing all messages sent in this course.
    $data = $DB->get_records_sql("SELECT ml.*, sl.* FROM {report_engagement_messagelog} ml 
                                    JOIN {report_engagement_sentlog} sl ON sl.messageid = ml.id 
                                    JOIN {user} u ON u.id = sl.recipientid
                                   WHERE sl.courseid = ? 
                                ORDER BY sl.timesent DESC, u.firstname ASC", array($id));
    $pagetitle = get_string('mailer_log_course', 'report_engagement');
}

// Parse messages and recipients.
$messages = array();
$recipients = array();
foreach ($data as $record) {
    $message = new stdClass();
    $message->subject = $record->messagesubject;
    $message->body = $record->messagebody;
    $message->type = $record->messagetype;
    $message->timesent = $record->timesent;
    $sender = $DB->get_record('user', array('id' => $record->senderid));
    $message->sender = $sender;
    $message->courseid = $record->courseid;
    $messages[$record->messageid] = $message;
    $recipient = $DB->get_record('user', array('id' => $record->recipientid));
    $recipients[$record->messageid][] = $recipient;
}
if ($uid) {
    $countrecipientsall = array();
    $pagetitle .= fullname($recipient);
    foreach ($dataall as $record) {
        if (isset($countrecipientsall[$record->messageid])) {
            $countrecipientsall[$record->messageid] += 1;
        } else {
            $countrecipientsall[$record->messageid] = 1;
        }
    }
}

// Render data. // TODO: refactor into renderer.
$html = "";
$htmltableexport = []; // For export table.
$_maxrecipientstoshow = 5;

// Show title.
$html .= html_writer::tag('h2', $pagetitle);
// Show table.
if (count($messages)) {
    $htmltableexport[] = html_writer::start_tag('div', array('class' => 'hidden'));
    $htmltableexport[] = html_writer::start_tag('table', array('id' => 'export_table'));
    $html .= html_writer::start_tag('table', array('id' => 'message_table', 'class' => 'row-border display compact'));
        $html .= $htmltableexport[] = html_writer::start_tag('thead');
            $html .= $htmltableexport[] = html_writer::start_tag('tr');
                $html .= $htmltableexport[] = html_writer::tag('th', get_string('mailer_log_message_sent', 'report_engagement'));
                $html .= $htmltableexport[] = html_writer::tag('th', get_string('mailer_log_message_from', 'report_engagement'));
                $html .= $htmltableexport[] = html_writer::tag('th', get_string('mailer_log_message_recipients', 'report_engagement'));
                $htmltableexport[] = html_writer::tag('th', get_string('mailer_log_message_id', 'report_engagement'));
                $html .= $htmltableexport[] = html_writer::tag('th', get_string('mailer_log_message_subject', 'report_engagement'));
                $html .= $htmltableexport[] = html_writer::tag('th', get_string('mailer_log_message_body', 'report_engagement'));
            $html .= $htmltableexport[] = html_writer::end_tag('tr');
        $html .= $htmltableexport[] = html_writer::end_tag('thead');
        $html .= $htmltableexport[] = html_writer::start_tag('tbody');
            foreach ($messages as $messageid => $message) {
                // Display table.
                $viewmessageurl = new moodle_url('/report/engagement/mailer_log.php', array('id' => $id, 'mid' => $messageid));
                $html .= html_writer::start_tag('tr');
                    // Sent date.
                    $html .= html_writer::tag('td', date("j F Y g:i a", $message->timesent), array('class' => 'mailer_log_cell'));
                    // Sender.
                    $html .= html_writer::tag('td', $message->sender->email, array('class' => 'mailer_log_cell'));
                    // Recipients.
                    $recipientlist = "";
                    $n = 0;
                    foreach ($recipients[$messageid] as $recipient) {
                        if ($n < $_maxrecipientstoshow || $mid) {
                            $recipientlist .= html_writer::tag('div', fullname($recipient) . ' ' .
                                html_writer::tag('a', 
                                    html_writer::empty_tag('img', 
                                        array('src' => $OUTPUT->pix_url('i/user'), 
                                            'class' => 'icon', 
                                            'title' => get_string('mailer_log_viewbyuser', 'report_engagement'))),
                                    array("href" => new moodle_url('/report/engagement/mailer_log.php', 
                                        array('id' => $id, 'uid' => $recipient->id)))));
                        } else {
                            $recipientlist .= html_writer::tag('div', 
                                html_writer::tag('a', 
                                    count($recipients[$messageid]) - $_maxrecipientstoshow, 
                                    array('href' => new moodle_url('/report/engagement/mailer_log.php', array('id' => $id, 'mid' => $messageid)))) . ' ' . 
                                get_string('mailer_log_message_otherrecipients', 'report_engagement'));
                            break;
                        }
                        $n += 1;
                    }
                    if ($uid) {
                        $recipientlist .= html_writer::tag('div', 
                            html_writer::tag('a', 
                                $countrecipientsall[$messageid] - 1, 
                                array('href' => new moodle_url('/report/engagement/mailer_log.php', array('id' => $id, 'mid' => $messageid)))).
                                ' '.get_string('mailer_log_message_otherrecipients', 'report_engagement')); 
                    }
                    $html .= html_writer::tag('td', $recipientlist, array('class' => 'mailer_log_cell'));
                    // Subject.
                    $html .= html_writer::tag('td', base64_decode($message->subject), array('class' => 'mailer_log_cell'));
                    // Body.
                    if ($uid) {
                        $messagebodytext = message_variables_replace(base64_decode($message->body), $uid);
                    } else {
                        $messagebodytext = base64_decode($message->body);
                    }
                    $messagebody = html_writer::tag('div', $messagebodytext);
                    if (!$mid) {
                        $messagebody .= html_writer::tag('a', 
                            html_writer::empty_tag('img', 
                                array('src' => $OUTPUT->pix_url('i/preview'), 
                                    'class' => 'icon', 
                                    'title' => get_string('mailer_log_viewbymessage', 'report_engagement'))), 
                            array("href" => $viewmessageurl));
                    }
                    $html .= html_writer::tag('td', $messagebody, array('class' => 'mailer_log_cell'));
                $html .= html_writer::end_tag('tr');
                // Export table.
                $messagesubjectexport = base64_decode($message->subject);
                $messagebodyexport = base64_decode($message->body);
                foreach ($recipients[$messageid] as $recipient) {
                    $htmltableexport[] = html_writer::start_tag('tr');
                    $htmltableexport[] = html_writer::tag('td', date("j F Y g:i a", $message->timesent));
                    $htmltableexport[] = html_writer::tag('td', $message->sender->email);
                    $htmltableexport[] = html_writer::tag('td', $recipient->email);
                    $htmltableexport[] = html_writer::tag('td', $messageid);
                    $htmltableexport[] = html_writer::tag('td', message_variables_replace($messagesubjectexport, $recipient->id));
                    $htmltableexport[] = html_writer::tag('td', message_variables_replace($messagebodyexport, $recipient->id));
                    $htmltableexport[] = html_writer::end_tag('tr');
                }
            }
        $html .= $htmltableexport[] = html_writer::end_tag('tbody');
    $html .= $htmltableexport[] = html_writer::end_tag('table');
    $htmltableexport[] = html_writer::end_tag('div');
} else {
    $html .= html_writer::tag('h3', get_string('mailer_log_nomessages', 'report_engagement'));
}
// Return html for heading and main table.
echo $html;
// Return html for export table.
echo(join('', $htmltableexport));

// Scripts.
$buttonmailerloglabelcsv = get_string('button_mailer_log_label_csv', 'report_engagement');
$buttonmailerlogfnamecsv = get_string('button_mailer_log_fname_csv', 'report_engagement');
$js = "
    <script>
        $(document).ready(function(){
            var export_table = $('#export_table').DataTable({
                'dom':'Bt',
                'buttons': [ {
                    'extend':'csvHtml5',
                    'title':'$buttonmailerlogfnamecsv'
                } ]
            });
            var message_table = $('#message_table').DataTable({
                'lengthMenu':[ [5, 10, 50, 100, -1] , [5, 10, 50, 100, 'All'] ],
                'dom': 'Blfrtip',
                'buttons': [ {
                    'extend':'csvHtml5',
                    'text':'$buttonmailerloglabelcsv'
                } ]
            });
            message_table.button(0).action(function(){
                console.log('bello');
                export_table.button(0).trigger();
            });
        });
    </script>
";
echo($js);

echo $OUTPUT->footer();

