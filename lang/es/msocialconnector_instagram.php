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
$string['pluginname'] = 'Conector para Instagram';
$string['igsearch'] = 'Lista de tags (separados por comas). Un * significa que se aceptan todos.';
$string['igsearch_empty'] = 'Falta la lista de tags. Introdúzcala en la <a href="../../course/modedit.php?update={$a->cmid}&return=1">configuración de MSocial</a>.';
$string['igsearch_help'] = 'Se ignoran los posts que no tengan todas las etiquetas de la lista. Si se desea analizar todos los posts introduzca el carácter asterisco "*" en este campo.' .
                            'Tenga en cuenta que analizar excesivos mensajes puede dificultar la interpretación de los resultados.';
$string['igsearchingby'] = 'Filtrando por etiquetas: "{$a}"';
$string['harvest'] = 'Buscar la actividad de los estudiantes.';

$string['no_instagram_name_advice'] = 'Deconectado de Instagram.';
$string['no_instagram_name_advice2'] = '{$a->userfullname} no está conectado a Instagram. Conéctelo pulsando en <a href="{$a->url}"><!--<img src="{$a->pixurl}/loginwithinstagram.png" alt="instagram login"/>-->instagram login.</a>';

$string['module_connected_instagram'] = 'Actividad conectada a Instagram con el profesor "{$a}" ';
$string['module_connected_instagram_usermode'] = 'Actividad analizando Instagram individualmente para cada usuario.';
$string['module_not_connected_instagram'] = 'Actividad desconectada de Instagram. Este análisis no funcionará hasta que se vuelva a conectar a Instagram.';

// SETTINGS.
$string['instagram_app_id'] = 'client_id';
$string['config_app_id'] = 'client_id según el API de Instagram (<a href="https://www.instagram.com/developer/clients/manage/" target="_blank" >https://www.instagram.com/developer/clients/manage/</a>)';
$string['instagram_app_secret'] = 'client_secret';
$string['config_app_secret'] = 'client_secret según el API de Instagram (<a href="https://www.instagram.com/developer/clients/manage/" target="_blank" >https://www.instagram.com/developer/clients/manage/</a>)';
$string['problemwithinstagramaccount'] = 'Los últimos intentos de obtener mensajes de Instagram produjeron errores. Intente reconectar la actividad a Instagram con su usuario. Mensaje recibido: {$a}';