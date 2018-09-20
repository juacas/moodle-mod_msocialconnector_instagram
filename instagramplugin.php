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
namespace mod_msocial\connector;

use mod_msocial\kpi_info;
use mod_msocial\social_user;
use mod_msocial\users_struct;
use msocial\msocial_plugin;

defined('MOODLE_INTERNAL') || die();
global $CFG;

/** library class for social network instagram plugin extending social plugin base class
 *
 * @package msocialconnector_instagram
 * @copyright 2017 Juan Pablo de Castro {@email jpdecastro@tel.uva.es}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later */
class msocial_connector_instagram extends msocial_connector_plugin {
    const CONFIG_IGSEARCH = 'igsearch';
    const CONFIG_MIN_WORDS = 'igminwords';
    const MODE_USER = 'user';
    const MODE_TAG = 'tag';
    private $iglikes = [];
    private $igcomments = [];
    private $mode = self::MODE_USER;

    /** Get the name of the plugin
     *
     * @return string */
    public function get_name() {
        return get_string('pluginname', 'msocialconnector_instagram');
    }

    /**
     *
     * {@inheritDoc}
     * @see \msocial\msocial_plugin::can_harvest()
     */
    public function can_harvest() {
        $igsearch = $this->get_config(self::CONFIG_IGSEARCH);
        switch ($this->mode) {
            case self::MODE_TAG:
                return ($this->is_enabled() && $this->get_connection_token() !== null && $igsearch != null);
            case self::MODE_USER:
                return $this->is_enabled() && $igsearch != null;
            default:
                return false;
        }
    }
    /**
     *
     * @param users_struct $users structure with arrays of userids
     */
    protected function calculate_custom_kpis(users_struct $users) {

        $interactions = $this->get_interactions($this->msocial->startdate, $this->msocial->enddate, $users);
        $this->igcomments = [];
        $this->iglikes = [];
        foreach ($interactions as $interaction) {
            $interactionjson = json_decode($interaction->rawdata);
            $comments = $interactionjson->comments->count;
            $likes = $interactionjson->likes->count;
            if (!isset($this->igcomments[$interaction->fromid])) {
                $this->igcomments[$interaction->fromid] = 0;
            } else {
                $this->igcomments[$interaction->fromid] += $comments;
            }
            if (!isset($this->iglikes[$interaction->fromid])) {
                $this->iglikes[$interaction->fromid] = 0;
            } else {
                $this->iglikes[$interaction->fromid] += $likes;
            }
        }
    }
    /**
     * {@inheritdoc}
     *
     * @see \msocial\msocial_plugin::calculate_kpis() */
    public function calculate_kpis(users_struct $users, $kpis = []) {
        $kpis = parent::calculate_kpis($users, $kpis);
        // Calculate stats igreplies and igcomments from interactions if needed.
        if (count($this->igcomments) == 0) {
            $this->calculate_custom_kpis($users);
        }
        foreach ($kpis as $kpi) {
            if (isset($this->igcomments[$kpi->userid])) {
                $kpi->igreplies = $this->igcomments[$kpi->userid];
            }
            if (isset($this->iglikes[$kpi->userid])) {
                $kpi->iglikes = $this->iglikes[$kpi->userid];
            }
        }
        // Max.
        $maxcomments = 0;
        $maxlikes = 0;
        foreach ($kpis as $kpi) {
            $maxcomments = max([$maxcomments, $kpi->igreplies]);
            $maxlikes = max([$maxlikes, $kpi->iglikes]);
        }
        foreach ($kpis as $kpi) {
            $kpi->max_igreplies = $maxcomments;
            $kpi->max_iglikes = $maxlikes;
        }
        return $kpis;
    }

    /** The msocial has been deleted - cleanup subplugin
     *
     * @return bool */
    public function delete_instance() {
        global $DB;
        $result = true;
        if (!$DB->delete_records('msocial_interactions',
                array('msocial' => $this->msocial->id, 'source' => $this->get_subtype()))) {
            $result = false;
        }
        if (!$DB->delete_records('msocial_instagram_tokens',
                array('msocial' => $this->msocial->id))) {
            $result = false;
        }
        if (!$DB->delete_records('msocial_mapusers',
                array('msocial' => $this->msocial->id, 'type' => $this->get_subtype()))) {
            $result = false;
        }
        if (!$DB->delete_records('msocial_plugin_config',
                array('msocial' => $this->msocial->id, 'subtype' => $this->get_subtype()))) {
            $result = false;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \msocial\msocial_plugin::get_settings() */
    public function get_settings(\MoodleQuickForm $mform) {
        $formfieldname = $this->get_form_field_name(self::CONFIG_IGSEARCH);
        $mform->addElement('text', $formfieldname, get_string("igsearch", "msocialconnector_instagram"), array('size' => '20'));
        $mform->setType($formfieldname, PARAM_TEXT);
        $mform->addHelpButton($formfieldname, 'igsearch', 'msocialconnector_instagram');
    }

    /**
     * {@inheritdoc}
     *
     * @see \msocial\msocial_plugin::data_preprocessing() */
    public function data_preprocessing(&$defaultvalues) {
        $defaultvalues[$this->get_form_field_name(self::CONFIG_IGSEARCH)] = $this->get_config(self::CONFIG_IGSEARCH);
        parent::data_preprocessing($defaultvalues);
    }

    /**
     * {@inheritdoc}
     *
     * @see \msocial\msocial_plugin::save_settings() */
    public function save_settings(\stdClass $data) {
        if (isset($data->{$this->get_form_field_name(self::CONFIG_IGSEARCH)})) {
            $this->set_config(self::CONFIG_IGSEARCH, $data->{$this->get_form_field_name(self::CONFIG_IGSEARCH)});
        }
        return true;
    }

    public function get_subtype() {
        return 'instagram';
    }

    public function get_category() {
        return msocial_plugin::CAT_ANALYSIS;
    }

    /**
     * {@inheritdoc}
     *
     * @see \mod_msocial\connector\msocial_connector_plugin::get_icon() */
    public function get_icon() {
        return new \moodle_url('/mod/msocial/connector/instagram/pix/instagram_icon.png');
    }

    /**
     * @global \core_renderer $OUTPUT
     * @global \moodle_database $DB */
    public function render_header() {
        global $OUTPUT, $DB, $USER;
        $notifications = [];
        $messages = [];
        if ($this->is_enabled()) {
            $context = \context_module::instance($this->cm->id);
            list($course, $cm) = get_course_and_cm_from_instance($this->msocial->id, 'msocial');
            $id = $cm->id;
            if (has_capability('mod/msocial:manage', $context)) {
                if ($this->mode == self::MODE_TAG) {
                    $token = $this->get_connection_token();
                    $urlconnect = new \moodle_url('/mod/msocial/connector/instagram/connectorSSO.php',
                            array('id' => $id, 'action' => 'connect'));
                    if ($token) {
                        $username = $token->username;
                        $errorstatus = $token->errorstatus;
                        if ($errorstatus) {
                            $notifications[] = '<p>' .
                                     get_string('problemwithinstagramaccount', 'msocialconnector_instagram', $errorstatus);
                        }

                        $messages[] = get_string('module_connected_instagram', 'msocialconnector_instagram', $username) .
                                        $OUTPUT->action_link(new \moodle_url('/mod/msocial/connector/instagram/connectorSSO.php',
                                        array('id' => $id, 'action' => 'connect')), "Change user") .
                                  '/' . $OUTPUT->action_link(new \moodle_url('/mod/msocial/connector/instagram/connectorSSO.php',
                                        array('id' => $id, 'action' => 'disconnect')), "Disconnect") . ' ';
                    } else {
                        $notifications[] = get_string('module_not_connected_instagram', 'msocialconnector_instagram') .
                                            $OUTPUT->action_link($urlconnect, "Connect");
                    }

                } else { // MODE_USER.
                    $messages[] = get_string('module_connected_instagram_usermode', 'msocialconnector_instagram');
                }
            }

            // Check user's social credentials.
            $socialuserids = $this->get_social_userid($USER);

            if (!$socialuserids) { // Offer to register.
                $notifications[] = $this->render_user_linking($USER, false, true);
            }
            // Check instagram hashtags...
            $igsearch = $this->get_config(self::CONFIG_IGSEARCH);
            if (trim($igsearch) === "") {
                $notifications[] = get_string('igsearch_empty', 'msocialconnector_instagram', ['cmid' => $cm->id]);
            } else {
                $messages[] = get_string('igsearchingby', 'msocialconnector_instagram', $igsearch);
            }

        }
        return [$messages, $notifications];
    }
    public function render_harvest_link() {
        global $OUTPUT;
        $harvestbutton = $OUTPUT->action_icon(
                new \moodle_url('/mod/msocial/harvest.php', ['id' => $this->cm->id, 'subtype' => $this->get_subtype()]),
                new \pix_icon('a/refresh', get_string('harvest', 'msocialconnector_instagram')));
        return $harvestbutton;
    }
    /**
     *
     * {@inheritDoc}
     * @see \mod_msocial\connector\msocial_connector_plugin::get_social_userid()
     */
    public function get_social_userid($user) {
        // Check user token if harvest mode is MODE_USER.
        if ($this->mode == self::MODE_USER) {
            if ($user instanceof \stdClass) {
                $userid = $user->id;
            } else {
                $userid = (int) $user;
            }
            $usertoken = $this->get_user_tokens($userid);
            if ($usertoken === false) {
                return false;
            }
        }
        return parent::get_social_userid($user);
    }
    /**
     *
     * {@inheritDoc}
     * @see \mod_msocial\connector\msocial_connector_plugin::get_social_user_url()
     */
    public function get_social_user_url(social_user $userid) {
        return "https://www.instagram.com/$userid->socialname";
    }

    public function get_interaction_url(social_interaction $interaction) {
        // Instagram url is embedded in json.
        if ($interaction->nativetype == 'POST') {
            $json = json_decode($interaction->rawdata);
            $url = $json->link;
        } else if ($interaction->nativetype == 'like') {
            $url = $this->get_social_user_url(new social_user($interaction->nativefrom, $interaction->nativefromname));
        } else {
            $url = '';
        }
        return $url;
    }

    /**
     * {@inheritdoc}
     *
     * @see \msocial\msocial_plugin::get_kpi_list() */
    public function get_kpi_list() {
        $kpiobjs['igposts'] = new kpi_info('igposts', get_string('kpi_description_igposts', 'msocialconnector_instagram'),
                kpi_info::KPI_INDIVIDUAL, kpi_info::KPI_CALCULATED,
                social_interaction::POST, 'POST', social_interaction::DIRECTION_AUTHOR);
        $kpiobjs['igmentions'] = new kpi_info('igmentions', get_string('kpi_description_igmentions', 'msocialconnector_instagram'),
                kpi_info::KPI_INDIVIDUAL, kpi_info::KPI_CALCULATED,
                social_interaction::MENTION, '*', social_interaction::DIRECTION_RECIPIENT);
        $kpiobjs['igreplies'] = new kpi_info('igreplies', get_string('kpi_description_igreplies', 'msocialconnector_instagram'),
                kpi_info::KPI_INDIVIDUAL, kpi_info::KPI_CUSTOM,
                social_interaction::REPLY, '*', social_interaction::DIRECTION_RECIPIENT);
        $kpiobjs['iglikes'] = new kpi_info('iglikes', get_string('kpi_description_iglikes', 'msocialconnector_instagram'),
                kpi_info::KPI_INDIVIDUAL, kpi_info::KPI_CUSTOM,
                social_interaction::REACTION, 'nativetype = "LIKE"', social_interaction::DIRECTION_RECIPIENT);
        $kpiobjs['max_igposts'] = new kpi_info('max_igposts', null, kpi_info::KPI_AGREGATED, kpi_info::KPI_CUSTOM);
        $kpiobjs['max_igmentions'] = new kpi_info('max_igmentions', null, kpi_info::KPI_AGREGATED, kpi_info::KPI_CUSTOM);
        $kpiobjs['max_igreplies'] = new kpi_info('max_igreplies', null, kpi_info::KPI_AGREGATED, kpi_info::KPI_CUSTOM);
        $kpiobjs['max_iglikes'] = new kpi_info('max_iglikes', null, kpi_info::KPI_AGREGATED, kpi_info::KPI_CUSTOM);
        return $kpiobjs;
    }

    /**
     * @global $CFG
     * @return string */
    private function get_appid() {
        global $CFG;
        $appid = get_config('msocialconnector_instagram', 'appid');
        return $appid;
    }

    /**
     * @global $CFG
     * @return string */
    private function get_appsecret() {
        global $CFG;
        $appsecret = get_config('msocialconnector_instagram', 'appsecret');
        return $appsecret;
    }

    /**
     * {@inheritdoc}
     *
     * @global moodle_database $DB
     * @return \stdClass */
    public function get_connection_token() {
        global $DB;
        if ($this->msocial) {
            $token = $DB->get_record('msocial_instagram_tokens', ['msocial' => $this->msocial->id, 'ismaster' => 1]);
        } else {
            $token = null;
        }
        return $token;
    }
    /**
     * Get tokens of all users or just of the specified userid.
     * @param int $userid if null returns all users' tokens
     * @return array
     */
    public function get_user_tokens($userid = null) {
        global $DB;
        if ($userid == null) {
            return $DB->get_records('msocial_instagram_tokens', ['msocial' => $this->msocial->id]);
        } else {
            return $DB->get_record('msocial_instagram_tokens', ['msocial' => $this->msocial->id, 'userid' => $userid]);
        }
    }
    /**
     * {@inheritdoc}
     *
     * @global moodle_database $DB
     * @see msocial_connector_plugin::set_connection_token() */
    public function set_connection_token($token) {
        global $DB;
        $token->msocial = $this->msocial->id;
        if (!isset($token->ismaster)) {
            $token->ismaster = 1;
        }
        if (empty($token->errorstatus)) {
            $token->errorstatus = null;
        }
        $record = $DB->get_record('msocial_instagram_tokens', array("msocial" => $this->msocial->id, 'userid' => $token->userid ));
        if ($record) {
            $token->id = $record->id;
            $DB->update_record('msocial_instagram_tokens', $token);
        } else {
            $DB->insert_record('msocial_instagram_tokens', $token);
        }
    }
    /**
     *
     * {@inheritDoc}
     * @see \msocial\msocial_plugin::reset_userdata()
     */
    public function reset_userdata($data) {
        // Forget user tokens.
        $this->unset_connection_token();
        // Remove mapusers.
        global $DB;
        $msocial = $this->msocial;
        $DB->delete_records('msocial_mapusers',['msocial' => $msocial->id, 'type' => $this->get_subtype()]);
        return array('component'=>$this->get_name(), 'item'=>get_string('resetdone', 'msocial',
                "MSOCIAL $msocial->id: mapusers, tokens"), 'error'=>false);
    }
    public function unset_connection_token() {
        global $DB;
        $DB->delete_records('msocial_instagram_tokens', array('msocial' => $this->msocial->id));
    }

    /** Obtiene el numero de reacciones recibidas en el Post, y actaliza el "score" de
     * la persona que escribio el Post
     *
     * @param mixed $post instagram post. */
    protected function process_post($post) {
        list($postname, $postid) = $this->userinstagramidfor($post);
        $postinteraction = new social_interaction();
        $postinteraction->uid = $post->id;
        $postinteraction->nativefrom = $postid;
        $postinteraction->nativefromname = $postname;
        $postinteraction->fromid = $this->get_userid($postid);
        $postinteraction->rawdata = json_encode($post);
        $date = new \DateTime();
        $date->setTimestamp($post->created_time);
        $postinteraction->timestamp = $date;
        $postinteraction->type = social_interaction::POST;
        $postinteraction->nativetype = 'POST';
        $message = $post->caption ? $post->caption->text : ''; // TODO: manage better no captions
                                                               // (images, photos, etc.)
        $postinteraction->description = $message == '' ? 'No text.' : $message;
        $this->register_interaction($postinteraction);
        // Register each reaction as an interaction...
        return $postinteraction;
    }

    /**
     * @param mixed $reaction
     * @param social_interaction $parentinteraction */
    protected function process_reactions($reaction, $parentinteraction) {
        $nativetype = 'like';
        $reactioninteraction = new social_interaction();
        $reactuserid = $reaction->id;
        $reactioninteraction->fromid = $this->get_userid($reactuserid);
        $reactioninteraction->nativefrom = $reactuserid;
        $reactioninteraction->nativefromname = $reaction->username;
        $reactioninteraction->uid = $parentinteraction->uid . '-' . $reactioninteraction->nativefrom;
        $reactioninteraction->parentinteraction = $parentinteraction->uid;
        $reactioninteraction->nativeto = $parentinteraction->nativefrom;
        $reactioninteraction->toid = $parentinteraction->fromid;
        $reactioninteraction->nativetoname = $parentinteraction->nativefromname;
        $reactioninteraction->rawdata = json_encode($reaction);
        $reactioninteraction->timestamp = null;
        $reactioninteraction->type = social_interaction::REACTION;
        $reactioninteraction->nativetype = $nativetype;
        $this->register_interaction($reactioninteraction);
    }

    /**
     * @param mixed $mention
     * @param social_interaction $parentinteraction */
    protected function process_mention($mention, $parentinteraction) {
        $nativetype = 'userinphoto';
        $mentioninguserid = $mention->id;
        $mentioninteraction = new social_interaction();
        $mentioninteraction->fromid = $this->get_userid($mentioninguserid);
        $mentioninteraction->nativefrom = $mention->id;
        $mentioninteraction->nativefromname = $mention->username;
        $mentioninteraction->uid = $parentinteraction->uid . '-' . $mentioninteraction->nativefrom;
        $mentioninteraction->parentinteraction = $parentinteraction->uid;
        $mentioninteraction->nativeto = $parentinteraction->nativefrom;
        $mentioninteraction->toid = $parentinteraction->fromid;
        $mentioninteraction->nativetoname = $parentinteraction->nativefromname;
        $mentioninteraction->rawdata = json_encode($mention);
        $mentioninteraction->timestamp = null;
        $mentioninteraction->type = social_interaction::MENTION;
        $mentioninteraction->nativetype = $nativetype;
        $this->register_interaction($mentioninteraction);
    }

    /** Registra la interacción con la
     * persona a la que contesta si no son la misma persona.
     * El Comment no se registrará como interacción ni se actualizará el "score" de la persona si
     * este es demasiado corto.
     *
     * @param mixed $comment
     * @param social_interaction $post */
    protected function process_comment($comment, $postinteraction) {
        $tooshort = $this->is_short_comment($comment->getField('message'));

        // Si el comentario es mayor de dos palabras...
        if (!$tooshort) {
            // TODO: manage auto-messaging activity.
            $commentid = $comment->from->id;
            $comentname = $coment->from->username;
            $commentinteraction = new social_interaction();
            $commentinteraction->uid = $comment->id;
            $commentinteraction->fromid = $this->get_userid($commentid);
            $commentinteraction->nativefromname = $commentname;
            $commentinteraction->nativefrom = $commentid;
            $commentinteraction->toid = $postinteraction->fromid;
            $commentinteraction->nativeto = $postinteraction->nativefrom;
            $commentinteraction->nativetoname = $postinteraction->nativefromname;
            $commentinteraction->parentinteraction = $postinteraction->uid;
            $commentinteraction->rawdata = json_encode($comment);
            $commentinteraction->timestamp = new \DateTime($comment->created_time);
            $commentinteraction->type = social_interaction::REPLY;
            $commentinteraction->nativetype = "comment";
            $commentinteraction->description = $comment->text;
            $this->register_interaction($commentinteraction);
            return $commentinteraction;
        }
    }

    /** Classify the text as too short to be relevant
     * TODO: implement relevance logic.
     * @param string $message
     * @return boolean $ok */
    protected function is_short_comment($message) {
        $numwords = str_word_count($message, 0);
        $minwords = $this->get_config(self::CONFIG_MIN_WORDS);
        return ($numwords <= ($minwords == null ? 2 : $minwords));
    }

    /** Gets username and userid of the author of the post.
     * @param mixed $in json response
     * @return array(string,string) $name, $id */
    protected function userinstagramidfor($in) {
        $author = $in->user;
        if ($author !== null) { // User unknown (lack of permissions probably).
            $name = $author->username;
            $id = $author->id;
        } else {
            $name = '';
            $id = null;
        }
        return [$name, $id];
    }
    /**
     * {@inheritDoc}
     * @see \mod_msocial\connector\msocial_connector_plugin::preferred_harvest_intervals()
     */
    public function preferred_harvest_intervals() {
        return new harvest_intervals (24 * 3600, 5000, 0, 0);
    }
    /** Instagram content are grouped by tag.
     * Searching by tag may need special permissions from Instagram
     * API sandbox mode allows to gather personal medias by user. Will need to store individual
     * tokens.
     *
     * @global moodle_database $DB
     * @return mixed $result->statuses $result->messages[]string $result->errors[]->message */
    public function harvest() {
        switch ($this->mode) {
            case self::MODE_TAG:
                return $this->harvest_tags();
            case self::MODE_USER:
                return $this->harvest_users();
        }
    }

    public function harvest_users() {
        global $DB;
        require_once('vendor/instagram-sdk/InstagramException.php');
        require_once('vendor/instagram-sdk/Instagram.php');
        $errormessage = null;
        $result = new \stdClass();
        $result->messages = [];
        $result->errors = [];
        // Initialize Instagram API.
        $igsearch = $this->get_config(self::CONFIG_IGSEARCH);
        $appid = $this->get_appid();
        $appsecret = $this->get_appsecret();
        $this->lastinteractions = [];
        $callbackurl = new \moodle_url("/mod/msocial/connector/instagram/connectorSSO.php",
                array('id' => $this->cm->id, 'action' => 'callback', 'type' => 'profile'));
        $config = array('apiKey' => $appid, 'apiSecret' => $appsecret, 'apiCallback' => $callbackurl->out(false));
        $igsearch = $this->get_config(self::CONFIG_IGSEARCH);
        if ($igsearch && $igsearch != '*') {
            $igtags = [];
            $igsearchtags = explode(',', $igsearch);
            // Clean tag marks.
            foreach ($igsearchtags as $tag) {
                $igtags[] = trim(str_replace('#', '', $tag));
            }
            if (count($igtags) > 0) {
                $igsearch = implode(' AND ', $igtags);
            }
        }
        $tagparser = new \tag_parser($igsearch);
        $ig = new \MetzWeb\Instagram\Instagram($config);
        $lastharvest = $this->get_config(self::LAST_HARVEST_TIME);
        // Get mapped users.
        $igusertokens = $this->get_user_tokens();
        foreach ($igusertokens as $token) {
            try {
                $ig->setAccessToken($token->token);
                // Query instagram...
                $this->igcomments[$token->userid] = 0;
                $this->iglikes[$token->userid] = 0;
                $media = $ig->getUserMedia('self', 100);
                // Exceeded requests per hour.
                if (isset($media->code) && $media->code == 429) {
                    mtrace("<li>ERROR: $media->error_type ==> User $token->username exceeded requests: $media->error_message.");
                    continue;
                }
                if ($media->meta->code != 200) { // Error.
                    throw new \Exception($media->meta->error_message);
                }
                // Mark the token as OK...
                $DB->set_field('msocial_instagram_tokens', 'errorstatus', null, array('id' => $token->id));
                // Iterate user's media.
                while (isset($media->data) && count($media->data) > 0) {
                    mtrace("<li>Analysing " . count($media->data) . " posts from user $token->username. ");
                    foreach ($media->data as $post) {
                        if (!msocial_time_is_between($post->created_time, $this->msocial->startdate, $this->msocial->enddate)) {
                            continue;
                        }
                        if (!$tagparser->check_hashtaglist(implode(',', $post->tags))) {
                            continue;
                        }
                        $postinteraction = $this->process_post($post);
                        // Use $post->users_in_photo -> mentions.
                        // Can use $post->comments -> count of comments.
                        // Can use $post->likes -> count of comments.
                        $this->igcomments[$token->userid] += $post->comments->count;
                        if ($post->comments->count > 0) {
                            $comments = $ig->getMediaComments($post->id);
                            // Exceeded requests per hour.
                            if (isset($comments->code) && $comments->code == 429) {
                                mtrace("ERROR on media comments: $comments->error_type ==> User $token->username exceeded requests: $comments->error_message.");
                            } else if ($comments->meta->code == 200) {
                                // Process comments...
                                if ($comments) {
                                    mtrace("<li>Analysing " . count($comments->data) . " comments for user $token->username. ");
                                    foreach ($comments->data as $comment) {
                                        $commentinteraction = $this->process_comment($comment, $postinteraction);
                                        /* @var $subcomment mixed */
                                        $subcomments = $comment->getField('comments');
                                        if ($subcomments) {
                                            foreach ($subcomments as $subcomment) {
                                                $this->process_comment($subcomment, $commentinteraction);
                                            }
                                        }
                                    }
                                }
                            } else {
                                mtrace("<li>Can't retrieve list of comments for user $token->username for post $post->id,  ");
                            }
                        }
                        $this->iglikes[$token->userid] += $post->likes->count;
                        if ($post->likes->count > 0) {
                            if (false) { // TODO: API retired.
                                $likes = $ig->getMediaLikes($post->id);
                                if ($likes->meta->code == 200) {
                                    // Process reactions...
                                    if ($likes) {
                                        mtrace("<li>Analysing " . count($likes->data) . " like reactions for user $token->username. ");
                                        foreach ($likes->data as $like) {
                                            $likeinteraction = $this->process_reactions($like, $postinteraction);
                                        }
                                    }
                                }
                            }
                        }
                        if ($post->users_in_photo && count($post->users_in_photo) > 0) {
                            // Process reactions...
                            foreach ($post->users_in_photo as $userinphoto) {
                                $mentioninteraction = $this->process_mention($userinphoto->user, $postinteraction);
                            }
                        }
                    }
                    if (msocial_time_is_between($post->created_time, $this->msocial->startdate, $this->msocial->enddate)) {
                        // Get next page of posts.
                        $media = $ig->pagination($media);
                    } else {
                        break;
                    }
                }
            } catch (\Exception $e) {
                $cm = $this->cm;
                $msocial = $this->msocial;
                $igtags = empty($igsearch) ? '' : $igsearch;
                $errormessage = "For module msocial\\connection\\instagram: $msocial->name (id=$cm->instance) in course (id=$msocial->course) " .
                "searching term: $igtags ERROR:" . $e->getMessage();

                $result->errors[] = (object) ['message' => $errormessage];
            }
            if ($token) {
                $token->errorstatus = $errormessage;
                $token->lastused = time();
                $this->set_connection_token($token);
                if ($errormessage) { // Marks this tokens as erroneous to warn the teacher.
                    $message = "Updating token with id = $token->id with $errormessage";
                    $result->errors[] = (object) ['message' => $message];
                }
            }
        }
        return $this->post_harvest($result);
    }

    private function harvest_tags() {
        global $DB;
        require_once('vendor/instagram-sdk/InstagramException.php');
        require_once('vendor/instagram-sdk/Instagram.php');
        $errormessage = null;
        $result = new \stdClass();
        $result->messages = [];
        $result->errors = [];
        // Initialize Instagram API.
        $igsearch = $this->get_config(self::CONFIG_IGSEARCH);
        $appid = $this->get_appid();
        $appsecret = $this->get_appsecret();
        $this->lastinteractions = [];
        // TODO: Check time configuration in some plattforms workaround:
        // date_default_timezone_set('Europe/Madrid');!
        try {
            $callbackurl = new \moodle_url("/mod/msocial/connector/instagram/connectorSSO.php",
                    array('id' => $this->cm->id, 'action' => 'callback', 'type' => 'profile'));
            $config = array('apiKey' => $appid, 'apiSecret' => $appsecret, 'apiCallback' => $callbackurl->out(false));

            $ig = new \MetzWeb\Instagram\Instagram($config);
            $token = $this->get_connection_token();
            $ig->setAccessToken($token->token);
            // Query instagram...
            $since = '';
            $lastharvest = $this->get_config(self::LAST_HARVEST_TIME);
            if ($lastharvest) {
                $since = "&since=$lastharvest";
            }
            $media = $ig->getTagMedia($igsearch);
            if (isset($media->meta->code)) { // Error.
                throw new \Exception($media->meta->error_message);
            }
            // Mark the token as OK...
            $DB->set_field('msocial_instagram_tokens', 'errorstatus', null, array('id' => $token->id));
            while (count($media->data) > 0) {
                foreach ($media->data as $post) {
                    $postinteraction = $this->process_post($post);
                    // The $post->users_in_photo ---> mentions.
                    // The $post->comments ---> count of comments.
                    // The $post->likes ---> count of comments.
                    if ($post->comments > 0) {
                        $comments = $ig->getMediaComments($post->id);
                        // Process comments...
                        if ($comments) {
                            foreach ($comments as $comment) {
                                $commentinteraction = $this->process_comment($comment, $postinteraction);
                                /* @var mixed $subcomment  */
                                $subcomments = $comment->getField('comments');
                                if ($subcomments) {
                                    foreach ($subcomments as $subcomment) {
                                        $this->process_comment($subcomment, $commentinteraction);
                                    }
                                }
                            }
                        }
                    }
                    if ($post->likes > 0) {
                        $likes = $ig->getMediaLikes($post->id);
                        // Process reactions...
                        if ($likes) {
                            foreach ($likes as $like) {
                                $likeinteraction = $this->process_reactions($like, $postinteraction);
                            }
                        }
                    }
                }
                // Get next page of posts.
                $media = $ig->pagination($media);
            }
        } catch (\Exception $e) {
            $cm = $this->cm;
            $msocial = $this->msocial;

            $errormessage = "For module msocial\\connection\\instagram: $msocial->name (id=$cm->instance) " .
                            "in course (id=$msocial->course) searching term: $igsearch  ERROR:" . $e->getMessage();
            $result->messages[] = $errormessage;
            $result->errors[] = (object) ['message' => $errormessage];
        }
        if ($token) {
            $token->errorstatus = $errormessage;
            $this->set_connection_token($token);
            if ($errormessage) { // Marks this tokens as erroneous to warn the teacher.
                $message = "Updating token with id = $token->id with $errormessage";
                $result->errors[] = (object) ['message' => $message];
                $result->messages[] = $message;
            }
        }
        return $this->post_harvest($result);
    }
}
