<?php
// This file is part of MSocial activity for Moodle http://moodle.org/
//
// MSocial for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// MSocial for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.
/*
 * **************************
 * Module developed at the University of Valladolid
 * Designed and directed by Juan Pablo de Castro at telecommunication engineering school
 * Copyright 2017 onwards EdUVaLab http://www.eduvalab.uva.es
 * @author Juan Pablo de Castro
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package msocial
 * *******************************************************************************
 */
use MetzWeb\Instagram\Instagram;
use mod_msocial\connector\msocial_connector_instagram;

require_once('vendor/instagram-sdk/InstagramException.php');
require_once('vendor/instagram-sdk/Instagram.php');
require_once("../../../../config.php");
require_once('../../classes/msocialconnectorplugin.php');
require_once('instagramplugin.php');
global $CFG;
$id = required_param('id', PARAM_INT); // MSocial module instance.
$action = optional_param('action', false, PARAM_ALPHA);
$type = optional_param('type', 'connect', PARAM_ALPHA);
$cm = get_coursemodule_from_id('msocial', $id);
$course = get_course($cm->course);
require_login($course);
$context = context_module::instance($id);
$msocial = $DB->get_record('msocial', array('id' => $cm->instance), '*', MUST_EXIST);
$plugin = new msocial_connector_instagram($msocial);

$interactionurl = new moodle_url('/mod/msocial/connector/instagram/connectorSSO.php',
        array('id' => $id, 'action' => $action, 'type' => $type));

$appid = get_config("msocialconnector_instagram", "appid");
$appsecret = get_config("msocialconnector_instagram", "appsecret");
$message = '';

if ($action == 'connect') {
    // GetToken.
    $callbackurl = new moodle_url("/mod/msocial/connector/instagram/connectorSSO.php",
            array('id' => $id, 'action' => 'callback', 'type' => $type));
    $config = array('apiKey' => $appid, 'apiSecret' => $appsecret, 'apiCallback' => $callbackurl->out(false));
    $ig = new Instagram($config);
    $loginurl = $ig->getLoginUrl(['basic']);

    header("Location: $loginurl");
    die();
} else if ($action == 'callback') {
    $code = required_param('code', PARAM_RAW);
    try {
        $config = array('apiKey' => $appid, 'apiSecret' => $appsecret, 'apiCallback' => $interactionurl->out(false));

        $ig = new Instagram($config);
        $data = $ig->getOAuthToken($code);
        if (isset($data->code)) {
            $message .= $data->error_message;
        } else if (isset($data->access_token)) {
            $username = $data->user->username;
            // Save tokens for future use.
            if ($type === 'connect' && has_capability('mod/msocial:manage', $context)) {
                $record = new stdClass();
                $record->token = $data->access_token;
                $record->username = $username;
                $plugin->set_connection_token($record);
                $message = get_string('module_connected_instagram', 'msocialconnector_instagram', $username);
                // Fill the profile with username in instagram.
            } else if ($type === 'profile') {

                $plugin->set_social_userid($USER, $data->user->id, $username);
                $token = (object) ['token' => $data->access_token, 'msocial' => $plugin->msocial->id, 'ismaster' => 0,
                                'userid' => $USER->id, 'username' => $username];
                $plugin->set_connection_token($token);
                $message .= "Profile updated with instagram user $username.";
            } else {
                print_error('unknownuseraction');
            }
        } else {
            // The user denied the request.
            $message .= get_string('module_not_connected_instagram', 'msocialconnector_instagram');
        }
    } catch (Exception $e) {
        // When validation fails or other local issues
        $message .= ('instagram SDK returned an error: ' . $e->getMessage()); // TODO: pasar a lang.
    }

    // Show headings and menus of page.
    $PAGE->set_url($interactionurl);
    $PAGE->set_title(format_string($cm->name));

    $PAGE->set_heading($course->fullname);
    // Print the page header.
    echo $OUTPUT->header();
    echo $OUTPUT->box($message);
    echo $OUTPUT->continue_button(new moodle_url('/mod/msocial/view.php', array('id' => $id)));
} else if ($action == 'disconnect') {
    if ($type == 'profile') {
        $userid = required_param('userid', PARAM_INT);
        $socialid = required_param('socialid', PARAM_RAW_TRIMMED);
        if ($userid != $USER->id) {
            require_capability('mod/msocial:manage', $context);
        }
        $user = (object) ['id' => $userid];
        // Remove the mapping.
        $plugin->unset_social_userid($user, $socialid);
        // Show headings and menus of page.
        $PAGE->set_url($interactionurl);
        $PAGE->set_title(format_string($cm->name));
        $PAGE->set_heading($course->fullname);
        // Print the page header.
        echo $OUTPUT->header();
        echo $OUTPUT->box($plugin->render_user_linking($user));
        echo $OUTPUT->continue_button(new moodle_url('/mod/msocial/view.php', array('id' => $id)));
    } else {
        require_capability('mod/msocial:manage', $context);
        $plugin->unset_connection_token();
        // Show headings and menus of page.
        $PAGE->set_url($interactionurl);
        $PAGE->set_title(format_string($cm->name));
        $PAGE->set_heading($course->fullname);
        // Print the page header.
        echo $OUTPUT->header();
        echo $OUTPUT->box(get_string('module_not_connected_instagram', 'msocialconnector_instagram'));
        echo $OUTPUT->continue_button(new moodle_url('/mod/msocial/view.php', array('id' => $id)));
    }
} else {
    print_error("Bad action code");
}
echo $OUTPUT->footer();