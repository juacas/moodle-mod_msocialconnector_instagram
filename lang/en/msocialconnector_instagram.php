<?php
// This file is part of instagramCount activity for Moodle http://moodle.org/
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
/* ***************************
 * Module developed at the University of Valladolid
 * Designed and directed by Juan Pablo de Castro at telecommunication engineering school
 * Copyright 2017 onwards EdUVaLab http://www.eduvalab.uva.es
 * @author Juan Pablo de Castro
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package msocial
 * *******************************************************************************
 */
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
$string['pluginname'] = 'Instagram Connector';
$string['igsearch'] = 'String to search for in Instagram Tags';
$string['igsearch_empty'] = 'Search term absent. Configure it in this activity <a href="../../course/modedit.php?update={$a->cmid}&return=1">settings</a>.';
$string['igsearch_help'] = 'If specified, only media published with the specific tag is collected for analysis.';
$string['igsearchingby'] = 'Searching for tag: "{$a}"';
$string['harvest'] = 'Search instagram groups for student activity';

$string['no_instagram_name_advice'] = 'Unlinked from instagram.';
$string['no_instagram_name_advice2'] = '{$a->userfullname} is not linked to instagram. Register using instagram clicking in <a href="{$a->url}"><img src="{$a->pixurl}/loginwithinstagram.png" alt="instagram login"/></a>';

$string['module_connected_instagram'] = 'Module connected with instagram as user "{$a}" ';
$string['module_connected_instagram_usermode'] = 'Module searching instagram individually by users.';
$string['module_not_connected_instagram'] = 'Module disconnected from instagram. It won\'t work until a instagram account is linked again.';

// SETTINGS.
$string['instagram_app_id'] = 'client_id';
$string['config_app_id'] = 'client_id according to instagramAPI (<a href="https://www.instagram.com/developer/clients/manage/" target="_blank" >https://www.instagram.com/developer/clients/manage/</a>)';
$string['instagram_app_secret'] = 'client_secret';
$string['config_app_secret'] = 'client_secret according to instagramAPI (<a href="https://www.instagram.com/developer/clients/manage/" target="_blank" >https://www.instagram.com/developer/clients/manage/</a>)';
$string['problemwithinstagramaccount'] = 'Recent attempts to get the posts resulted in an error. Try to reconnect instagram with your user. Message: {$a}';