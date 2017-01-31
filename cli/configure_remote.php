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
//
// Este bloco é parte do Moodle Provas - http://tutoriais.moodle.ufsc.br/provas/
// Este projeto é financiado pela
// UAB - Universidade Aberta do Brasil (http://www.uab.capes.gov.br/)
// e é distribuído sob os termos da "GNU General Public License",
// como publicada pela "Free Software Foundation".

/**
 * Configuration script
 *
 * @package    local_exam_remote
 * @author     Antonio Carlos Mariani
 * @copyright  2010 onwards Universidade Federal de Santa Catarina (http://www.ufsc.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/webservice/lib.php');

$plugin_manager = core_plugin_manager::instance();
$installed = $plugin_manager->get_installed_plugins('local');
if (!isset($installed['exam_remote'])) {
    echo "\n=> Plugin 'exam_remote' is not installed.\n";
    exit;
}

$tokeniprestriction = '150.162.9.0/24'; // set the ip access restriction for this plugin
$email              = 'wsexam@kkk.ddd.br';
$password           = 'Vy%;' . rand(100000, 999999);

$rolename = 'wsexam';
$username = 'wsexam';
$webservice  = 'rest';
$servicename = 'Moodle Exam';

$context = context_system::instance();

// ----------------------------------------------------------------------------------
// Criate a webservice user

if (!$user = $DB->get_record('user', array('username'=>$username))) {
    $user = new stdclass();
    $user->username  = $username;
    $user->firstname = 'Webservice';
    $user->lastname  = 'Exam';
    $user->email     = $email;
    $user->password  = $password;
    $user->auth      = 'manual';
    $user->confirmed = 1;
    $user->policyagreed = 1;
    $user->mnethostid   = $CFG->mnet_localhost_id;
    $user->id = user_create_user($user, false);
    echo "- Created user '{$username}'.\n";
} else {
    echo "- User '{$username}' already exists.\n";
}

// ----------------------------------------------------------------------------------
// Criate a webservice role

if (!$roleid = $DB->get_field('role', 'id', array('shortname'=>$rolename))) {
    $roleid = create_role('Webservice Exam', $rolename, 'Webservice Exam');
    set_role_contextlevels($roleid, array(CONTEXT_SYSTEM));
    echo "- Created role '{$rolename}'.\n";
} else {
    echo "- Role '{$rolename}' already exists.\n";
}
echo "- Assigning some capabilities to the role: '{$rolename}'.\n";
assign_capability('webservice/rest:use', CAP_ALLOW, $roleid, $context->id);
assign_capability('moodle/course:managegroups', CAP_ALLOW, $roleid, $context->id);
assign_capability('moodle/course:view', CAP_ALLOW, $roleid, $context->id);
assign_capability('moodle/restore:restoreactivity', CAP_ALLOW, $roleid, $context->id);
// assign_capability('moodle/user:viewdetails', CAP_ALLOW, $roleid, $context->id);
// assign_capability('moodle/user:viewalldetails', CAP_ALLOW, $roleid, $context->id);
// assign_capability('moodle/user:viewhiddendetails', CAP_ALLOW, $roleid, $context->id);
// assign_capability('moodle/course:useremail', CAP_ALLOW, $roleid, $context->id);
// assign_capability('moodle/site:viewfullnames', CAP_ALLOW, $roleid, $context->id);
// assign_capability('moodle/site:viewuseridentity', CAP_ALLOW, $roleid, $context->id);
// assign_capability('moodle/course:viewparticipants', CAP_ALLOW, $roleid, $context->id);
// assign_capability('moodle/role:review', CAP_ALLOW, $roleid, $context->id);
// assign_capability('moodle/course:enrolreview', CAP_ALLOW, $roleid, $context->id);
// assign_capability('moodle/user:update', CAP_ALLOW, $roleid, $context->id);

// ----------------------------------------------------------------------------------
// Set the privous role to the user in the global contex

echo "- Assigning role: '{$rolename}' to the user: '{$username}' in the global context.\n";
role_assign($roleid, $user->id, $context->id);

// ----------------------------------------------------------------------------------
// Enable webservices

echo "- Enabling webservices.\n";
$configs = array(
                 array('enablewebservices', true),
                );

foreach ($configs AS $cfg) {
    if (count($cfg) == 2) {
        set_config($cfg[0], $cfg[1]);
    } else {
        set_config($cfg[0], $cfg[1], $cfg[2]);
    }
}

// ----------------------------------------------------------------------------------
// Activate the rest webservice

echo "- Enabling '{$webservice}' webservices.\n";
$activewebservices = empty($CFG->webserviceprotocols) ? array() : explode(',', $CFG->webserviceprotocols);
if (!in_array($webservice, $activewebservices)) {
    $activewebservices[] = $webservice;
    $activewebservices = array_unique($activewebservices);
    set_config('webserviceprotocols', implode(',', $activewebservices));
}

// ----------------------------------------------------------------------------------
// Add the previous user to the external service

if (!$externalservice = $DB->get_record('external_services', array('name'=>$servicename), 'id, name, enabled')) {
    die("*** Unknown service: {$servicename}. It should be added by db/services.php.\n");
}

echo "- Authorizing '{$username}' to use '{$externalservice->name}' webservices.\n";
$webservicemanager = new webservice();
$users = $webservicemanager->get_ws_authorised_users($externalservice->id);
if (!isset($users[$user->id])) {
    $serviceuser = new stdClass();
    $serviceuser->externalserviceid = $externalservice->id;
    $serviceuser->userid = $user->id;
    $webservicemanager->add_ws_authorised_user($serviceuser);
}

// ----------------------------------------------------------------------------------
// Generates a token

if ($token = $DB->get_record('external_tokens', array('userid'=>$user->id, 'externalserviceid'=>$externalservice->id))) {
    $tokenmessage = "Token:\t\t{$token->token}";
} else {
    echo "- Generating a new token.\n";
    $token = external_generate_token(EXTERNAL_TOKEN_PERMANENT, $externalservice->id, $user->id, $context, 0, $tokeniprestriction);
    $newtoken = new stdclass();
    $newtoken->id = $DB->get_field('external_tokens', 'id', array('token'=>$token));
    $newtoken->creatorid = 2;
    $DB->update_record('external_tokens', $newtoken);
    $tokenmessage = "New token:\t{$token}";
}

// ----------------------------------------------------------------------------------
// Display some useful information to configure Moodle Exam

$site = get_site();
echo "\nData needed to configure Moodle Exam:\n\n";
echo "URL:\t\t{$CFG->wwwroot}\n";
echo "Description:\t{$site->fullname}\n";
echo "{$tokenmessage}\n\n";
