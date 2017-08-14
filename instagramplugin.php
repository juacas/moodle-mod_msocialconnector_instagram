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

use instagram\instagram as instagram;
use instagram\GraphNodes\GraphEdge;
use instagram\GraphNodes\GraphNode;
use mod_msocial\pki_info;
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
    private $lastinteractions = array();
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
     * @return true if the plugin is making searches in the social network */
    public function is_tracking() {
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
     * {@inheritdoc}
     *
     * @see \msocial\msocial_plugin::calculate_pkis() */
    public function calculate_pkis($users, $pkis = []) {
        $pkis = parent::calculate_pkis($users, $pkis);
        foreach ($pkis as $pki) {
            if (isset($this->igcomments[$pki->user])) {
                $pki->igreplies = $this->igcomments[$pki->user];
            }
            if (isset($this->iglikes[$pki->user])) {
                $pki->iglikes = $this->iglikes[$pki->user];
            }
        }
        // Max.
        $maxcomments = 0;
        $maxlikes = 0;
        foreach ($pkis as $pki) {
            $maxcomments = max([$maxcomments, $pki->igreplies]);
            $maxlikes = max([$maxlikes, $pki->iglikes]);
        }
        foreach ($pkis as $pki) {
            $pki->max_igreplies = $maxcomments;
            $pki->max_iglikes = $maxlikes;
        }
        return $pkis;
    }

    /** The msocial has been deleted - cleanup subplugin
     *
     * @return bool */
    public function delete_instance() {
        global $DB;
        $result = true;
        if (!$DB->delete_records('msocial_interactions', array('msocial' => $this->msocial->id, 'source' => $this->get_subtype()))) {
            $result = false;
        }
        if (!$DB->delete_records('msocial_instagram_tokens', array('msocial' => $this->msocial->id))) {
            $result = false;
        }
        if (!$DB->delete_records('msocial_mapusers', array('msocial' => $this->msocial->id, 'type' => $this->get_subtype()))) {
            $result = false;
        }
        if (!$DB->delete_records('msocial_plugin_config', array('msocial' => $this->msocial->id, 'subtype' => $this->get_subtype()))) {
            $result = false;
        }
        return $result;
    }

    public function save_settings(\stdClass $data) {
        if (isset($data->{$this->get_form_field_name(self::CONFIG_IGSEARCH)})) {
            $this->set_config('igsearch', $data->{$this->get_form_field_name(self::CONFIG_IGSEARCH)});
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
        if ($this->is_enabled()) {
            $notifications = [];
            $messages = [];
            $context = \context_module::instance($this->cm->id);
            list($course, $cm) = get_course_and_cm_from_instance($this->msocial->id, 'msocial');
            $id = $cm->id;
            if (has_capability('mod/msocial:manage', $context)) {
                if ($this->is_tracking()) {
                    $harvestbutton = $OUTPUT->action_icon(
                            new \moodle_url('/mod/msocial/harvest.php', ['id' => $id, 'subtype' => $this->get_subtype()]),
                            new \pix_icon('a/refresh', get_string('harvest', 'msocialconnector_instagram')));
                } else {
                    $harvestbutton = '';
                }
                if ($this->mode == self::MODE_TAG) {
                    $token = $this->get_connection_token();
                    $urlconnect = new \moodle_url('/mod/msocial/connector/instagram/instagramSSO.php',
                            array('id' => $id, 'action' => 'connect'));
                    if ($token) {
                        $username = $token->username;
                        $errorstatus = $token->errorstatus;
                        if ($errorstatus) {
                            $notifications[] = '<p>' .
                                     get_string('problemwithinstagramaccount', 'msocialconnector_instagram', $errorstatus);
                        }

                        $messages[] = get_string('module_connected_instagram', 'msocialconnector_instagram', $username) . $OUTPUT->action_link(
                                new \moodle_url('/mod/msocial/connector/instagram/instagramSSO.php',
                                        array('id' => $id, 'action' => 'connect')), "Change user") . '/' . $OUTPUT->action_link(
                                new \moodle_url('/mod/msocial/connector/instagram/instagramSSO.php',
                                        array('id' => $id, 'action' => 'disconnect')), "Disconnect") . ' ' . $harvestbutton;
                    } else {
                        $notifications[] = get_string('module_not_connected_instagram', 'msocialconnector_instagram') . $OUTPUT->action_link(
                                new \moodle_url('/mod/msocial/connector/instagram/instagramSSO.php',
                                        array('id' => $id, 'action' => 'connect')), "Connect");
                    }
                } else { // MODE_USER
                    $messages[] = get_string('module_connected_instagram_usermode', 'msocialconnector_instagram') . $harvestbutton;
                }
                // Check instagram hashtags...
                $igsearch = $this->get_config(self::CONFIG_IGSEARCH);
                if (trim($igsearch) === "") {
                    $notifications[] = get_string('igsearch_empty', 'msocialconnector_instagram');
                } else {
                    $messages[] = get_string('igsearchingby', 'msocialconnector_instagram', $igsearch);
                }
            }
            // Check user's social credentials.
            $socialuserids = $this->get_social_userid($USER);
            if (!$socialuserids) { // Offer to register.
                $notifications[] = $this->render_user_linking($USER);
            }
            $this->notify($notifications, self::NOTIFY_WARNING);
            $this->notify($messages, self::NOTIFY_NORMAL);
        }
    }

    /** Place social-network user information or a link to connect.
     *
     * @global object $USER
     * @global object $COURSE
     * @param object $user user record
     * @return string message with the linking info of the user */
    public function render_user_linking($user) {
        global $USER, $COURSE;
        $course = $COURSE;
        $usermessage = '';
        $socialids = $this->get_social_userid($user);
        $cm = get_coursemodule_from_instance('msocial', $this->msocial->id);
        if ($socialids == null) { // Offer to register.
            $pixurl = new \moodle_url('/mod/msocial/connector/instagram/pix');
            $userfullname = fullname($user);
            if ($USER->id == $user->id) {
                $urlprofile = new \moodle_url('/mod/msocial/connector/instagram/instagramSSO.php',
                        array('id' => $cm->id, 'action' => 'connect', 'type' => 'profile'));
                $usermessage = get_string('no_instagram_name_advice2', 'msocialconnector_instagram',
                        ['userfullname' => $userfullname, 'userid' => $USER->id, 'courseid' => $course->id,
                                        'url' => $urlprofile->out(false), 'pixurl' => $pixurl->out(false)]);
            } else {
                $usermessage = get_string('no_instagram_name_advice', 'msocialconnector_instagram',
                        ['userfullname' => $userfullname, 'userid' => $user->id, 'courseid' => $course->id,
                                        'pixurl' => $pixurl->out()]);
            }
        } else {
            global $OUTPUT;
            $usermessage = $this->create_user_link($user);
            $contextmodule = \context_module::instance($this->cm->id);
            if ($USER->id == $user->id || has_capability('mod/msocial:manage', $contextmodule)) {
                $icon = new \pix_icon('t/delete', 'delete');
                $urlprofile = new \moodle_url('/mod/msocial/connector/instagram/instagramSSO.php',
                        array('id' => $this->cm->id, 'action' => 'disconnect', 'type' => 'profile', 'userid' => $user->id,
                                        'socialid' => $socialids->socialid));
                $link = \html_writer::link($urlprofile, $OUTPUT->render($icon));
                $usermessage .= $link;
            }
        }
        return $usermessage;
    }

    /**
     * {@inheritdoc}
     *
     * @see \mod_msocial\connector\msocial_connector_plugin::get_user_url() */
    public function get_user_url($user) {
        $userid = $this->get_social_userid($user);
        if ($userid) {
            $link = $this->get_social_user_url($userid);
        } else {
            $link = null;
        }
        return $link;
    }

    public function get_social_user_url($userid) {
        return "https://www.instagram.com/$userid->socialname";
    }

    public function get_interaction_url(social_interaction $interaction) {
        // instagram uid for a comment is generated with group id and comment id.
        $parts = explode('_', $interaction->uid);
        if (count($parts) == 2) {
            $url = 'https://www.instagram.com/p/' . $parts[0] . '/permalink/' . $parts[1]; // TODO:
                                                                                               // there
                                                                                               // are
                                                                                               // subinteractions???
        } else {
            $url = 'https://www.instagram.com/p/' . $parts[0];
        }

        return $url;
    }

    /**
     * {@inheritdoc}
     *
     * @see \msocial\msocial_plugin::get_pki_list() */
    public function get_pki_list() {
        if ($this->mode == self::MODE_USER) {
            $pkiobjs['igposts'] = new pki_info('igposts', null, pki_info::PKI_INDIVIDUAL, social_interaction::POST, 'POST',
                    social_interaction::DIRECTION_AUTHOR);
            $pkiobjs['igreplies'] = new pki_info('igreplies', null, pki_info::PKI_CUSTOM, social_interaction::REPLY, '*',
                    social_interaction::DIRECTION_RECIPIENT);
            $pkiobjs['iglikes'] = new pki_info('iglikes', null, pki_info::PKI_CUSTOM, social_interaction::REACTION,
                    'nativetype = "LIKE"', social_interaction::DIRECTION_RECIPIENT);
            $pkiobjs['max_igposts'] = new pki_info('max_posts', null, pki_info::PKI_AGREGATED);
            $pkiobjs['max_igreplies'] = new pki_info('max_replies', null, pki_info::PKI_CUSTOM);
            $pkiobjs['max_iglikes'] = new pki_info('max_likes', null, pki_info::PKI_CUSTOM);
        } else {
            $pkiobjs['igposts'] = new pki_info('igposts', null, pki_info::PKI_INDIVIDUAL, social_interaction::POST, 'POST',
                    social_interaction::DIRECTION_AUTHOR);
            $pkiobjs['igreplies'] = new pki_info('igreplies', null, pki_info::PKI_INDIVIDUAL, social_interaction::REPLY, '*',
                    social_interaction::DIRECTION_RECIPIENT);
            $pkiobjs['iglikes'] = new pki_info('iglikes', null, pki_info::PKI_INDIVIDUAL, social_interaction::REACTION,
                    'nativetype = "LIKE"', social_interaction::DIRECTION_RECIPIENT);
            $pkiobjs['max_igposts'] = new pki_info('max_posts', null, pki_info::PKI_AGREGATED);
            $pkiobjs['max_igreplies'] = new pki_info('max_replies', null, pki_info::PKI_AGREGATED);
            $pkiobjs['max_iglikes'] = new pki_info('max_likes', null, pki_info::PKI_AGREGATED);
        }
        return $pkiobjs;
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
     * @return type */
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
        $record = $DB->get_record('msocial_instagram_tokens', array("msocial" => $this->msocial->id));
        if ($record) {
            $token->id = $record->id;
            $DB->update_record('msocial_instagram_tokens', $token);
        } else {
            $DB->insert_record('msocial_instagram_tokens', $token);
        }
    }

    public function unset_connection_token() {
        global $DB;
        parent::unset_connection_token();
        $DB->delete_records('msocial_instagram_tokens', array('msocial' => $this->msocial->id));
    }

    public function store_interactions(array $interactions) {
        $msocialid = $this->msocial->id;
        social_interaction::store_interactions($interactions, $msocialid);
    }

    /**
     * @param social_interaction $interaction */
    public function register_interaction(social_interaction $interaction) {
        $interaction->source = $this->get_subtype();
        $this->lastinteractions[] = $interaction;
    }

    /** Obtiene el numero de reacciones recibidas en el Post, y actaliza el "score" de
     * la persona que escribio el Post
     *
     * @param GraphNode $post instagram post. */
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
        $message = $post->caption?$post->caption->text:''; // TODO: manage better no captions (images, photos, etc.)
        $postinteraction->description = $message == '' ? 'No text.' : $message;
        $this->register_interaction($postinteraction);
        // Register each reaction as an interaction...
        // $this->addScore($postname, (0.1 * sizeof($reactions)) + 1);
        return $postinteraction;
    }

    /**
     * @param mixed $reactions
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

    /** Registra la interacción con la
     * persona a la que contesta si no son la misma persona.
     * El Comment no se registrará como interacción ni se actualizará el "score" de la persona si
     * este es demasiado corto.
     *
     * @param GraphNode $comment
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
        require_once ('instagram-sdk/InstagramException.php');
        require_once ('instagram-sdk/Instagram.php');
        $errormessage = null;
        $result = new \stdClass();
        $result->messages = [];
        $result->errors = [];
        // Initialize Instagram API.
        $igsearch = $this->get_config(self::CONFIG_IGSEARCH);
        $appid = $this->get_appid();
        $appsecret = $this->get_appsecret();
        $this->lastinteractions = [];
        $callbackurl = new \moodle_url("/mod/msocial/connector/instagram/instagramSSO.php",
                array('id' => $this->cm->id, 'action' => 'callback', 'type' => 'profile'));
        $config = array('apiKey' => $appid, 'apiSecret' => $appsecret, 'apiCallback' => $callbackurl->out(false));

        $ig = new \MetzWeb\Instagram\Instagram($config);
        // Get mapped users.
        $igusers = $DB->get_records('msocial_instagram_tokens', ['msocial' => $this->msocial->id]);
        foreach ($igusers as $token) {
            try {
                $ig->setAccessToken($token->token);
                // Query instagram...
                $lastharvest = $this->get_config(self::LAST_HARVEST_TIME);
                $this->igcomments[$token->user] = 0;
                $this->iglikes[$token->user] = 0;
                $media = $ig->getUserMedia();
                if ($media->meta->code != 200) { // Error.
                    throw new \Exception($media->meta->error_message);
                }
                // Mark the token as OK...
                $DB->set_field('msocial_instagram_tokens', 'errorstatus', null, array('id' => $token->id));

                while (isset($media->data) && count($media->data) > 0) {
                    foreach ($media->data as $post) {
                        $postinteraction = $this->process_post($post);
                        // $post->users_in_photo -> mentions.
                        // $post->comments -> count of comments.
                        // $post->likes -> count of comments.
                        $this->igcomments[$token->user] += $post->comments->count;
                        if ($post->comments->count > 0) {
                            $comments = $ig->getMediaComments($post->id);
                            if ($comments->meta->code == 200) {
                                // Process comments...
                                if ($comments) {
                                    foreach ($comments->data as $comment) {
                                        $commentinteraction = $this->process_comment($comment, $postinteraction);
                                        /* @var $subcomment instagram\GraphNodes\GraphEdge */
                                        $subcomments = $comment->getField('comments');
                                        if ($subcomments) {
                                            foreach ($subcomments as $subcomment) {
                                                $this->process_comment($subcomment, $commentinteraction);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        $this->iglikes[$token->user] += $post->likes->count;
                        if ($post->likes->count > 0) {
                            $likes = $ig->getMediaLikes($post->id);
                            if ($likes->meta->code == 200) {

                                // Process reactions...
                                if ($likes) {
                                    foreach ($likes->data as $like) {
                                        $likeinteraction = $this->process_reactions($like, $postinteraction);
                                    }
                                }
                            }
                        }
                    }
                    // Get next page of posts.
                    $media = $ig->pagination($media);
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
            } catch (\Exception $e) {
                $cm = $this->cm;
                $msocial = $this->msocial;

                $errormessage = "For module msocial\\connection\\instagram: $msocial->name (id=$cm->instance) in course (id=$msocial->course) " .
                         "searching term: $igsearch  ERROR:" . $e->getMessage();
                $result->messages[] = $errormessage;
                $result->errors[] = (object) ['message' => $errormessage];
            }
        }
        // TODO: define if processsing is needed or not.
        $processedinteractions = $this->lastinteractions; // $this->process_interactions($this->lastinteractions);
        $studentinteractions = array_filter($processedinteractions,
                function ($interaction) {
                    return isset($interaction->fromid);
                });
        // TODO: define if all interactions are
        // worth to be registered or only student's.
        $this->store_interactions($processedinteractions);
        $contextcourse = \context_course::instance($this->msocial->course);
        list($students, $nonstudents, $active, $users) = eduvalab_get_users_by_type($contextcourse);
        $pkis = $this->calculate_pkis($users);
        $this->store_pkis($pkis, true);
        $this->set_config(\mod_msocial\connector\msocial_connector_plugin::LAST_HARVEST_TIME, time());

        $logmessage = "For module msocial\\connection\\instagram: \"" . $this->msocial->name . "\" (id=" . $this->msocial->id .
                 ") in course (id=" . $this->msocial->course . ")  Found " . count($this->lastinteractions) .
                 " events. Students' events: " . count($studentinteractions);
        $result->messages[] = $logmessage;

        return $result;
    }

    private function harvest_tags() {
        global $DB;
        require_once ('instagram-sdk/InstagramException.php');
        require_once ('instagram-sdk/Instagram.php');
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
            $callbackurl = new \moodle_url("/mod/msocial/connector/instagram/instagramSSO.php",
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
                    // $post->users_in_photo -> mentions.
                    // $post->comments -> count of comments.
                    // $post->likes -> count of comments.
                    if ($post->comments > 0) {
                        $comments = $ig->getMediaComments($post->id);
                        // Process comments...
                        if ($comments) {
                            foreach ($comments as $comment) {
                                $commentinteraction = $this->process_comment($comment, $postinteraction);
                                /* @var $subcomment instagram\GraphNodes\GraphEdge */
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
                                /* @var $subcomment instagram\GraphNodes\GraphEdge */
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

            $errormessage = "For module msocial\\connection\\instagram: $msocial->name (id=$cm->instance) in course (id=$msocial->course) " .
                     "searching term: $igsearch  ERROR:" . $e->getMessage();
            $result->messages[] = $errormessage;
            $result->errors[] = (object) ['message' => $errormessage];
        }
        // TODO: define if processsing is needed or not.
        $processedinteractions = $this->lastinteractions; // $this->process_interactions($this->lastinteractions);

        $studentinteractions = array_filter($processedinteractions,
                function ($interaction) {
                    return isset($interaction->fromid);
                });
        // TODO: define if all interactions are
        // worth to be registered or only student's.
        $this->store_interactions($processedinteractions);
        $contextcourse = \context_course::instance($this->msocial->course);
        list($students, $nonstudents, $active, $users) = eduvalab_get_users_by_type($contextcourse);
        $pkis = $this->calculate_pkis($users);
        $this->store_pkis($pkis, true);
        $this->set_config(\mod_msocial\connector\msocial_connector_plugin::LAST_HARVEST_TIME, time());

        $logmessage = "For module msocial\\connection\\instagram: \"" . $this->msocial->name . "\" (id=" . $this->msocial->id .
                 ") in course (id=" . $this->msocial->course . ")  Found " . count($this->lastinteractions) .
                 " events. Students' events: " . count($studentinteractions);
        $result->messages[] = $logmessage;

        if ($token) {
            $token->errorstatus = $errormessage;
            $this->set_connection_token($token);
            if ($errormessage) { // Marks this tokens as erroneous to warn the teacher.
                $message = "Updating token with id = $token->id with $errormessage";
                $result->errors[] = (object) ['message' => $message];
                $result->messages[] = $message;
            }
        }
        return $result;
    }
}
