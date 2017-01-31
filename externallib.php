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
 * Plugin webservices.
 *
 * @package    local_exam_remote
 * @author     Antonio Carlos Mariani
 * @copyright  2010 onwards Universidade Federal de Santa Catarina (http://www.ufsc.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

class local_exam_remote_external extends external_api {

    static $capabilities = array('local/exam_remote:write_exam',
                                 'local/exam_remote:take_exam',
                                 'local/exam_remote:supervise_exam',
                                 'local/exam_remote:monitor_exam',
                                );

// --------------------------------------------------------------------------------------------------------

    /**
     * Describes the parameters for authenticate.
     *
     * @return external_external_function_parameters
     */
    public static function authenticate_parameters() {
        return new external_function_parameters(
                        array('username' => new external_value(PARAM_TEXT, 'Username'),
                              'password' => new external_value(PARAM_RAW, 'Password'))
                   );
    }

    /**
     * Authenticate the user
     *
     * @param String $username
     * @param String $password No crypt password
     * @return boolean
     */
    public static function authenticate($username, $password) {
        global $DB;

        $params = self::validate_parameters(self::authenticate_parameters(),
                            array('username' => $username, 'password' => $password));

        if ($user = core_user::get_user_by_username($username, 'id, auth, password, deleted, suspended')) {
            if ($user->deleted || $user->suspended) {
                return false;
            } else {
                return validate_internal_user_password($user, $password);
            }
        } else {
            return false;
        }
    }

    /**
     * Describes the authenticate return value.
     *
     * @return external_value
     */
    public static function authenticate_returns() {
        return new external_value(PARAM_BOOL);
    }

// --------------------------------------------------------------------------------------------------------

    /**
     * Describes the parameters for get_user_capabilities.
     *
     * @return external_external_function_parameters
     */
    public static function get_user_capabilities_parameters() {
        return new external_function_parameters(
                        array('username'  => new external_value(PARAM_TEXT, 'Username'),
                              'shortname' => new external_value(PARAM_TEXT, 'Course shortname')
                             )
                   );
    }

    /**
     * Returns the the capabilities the user have on a course data given its shortname.
     *
     * @param String $username Username
     * @param String $shortname Course shortname
     * @return array
     */
    public static function get_user_capabilities($username, $shortname) {
        global $DB;

        $params = self::validate_parameters(self::get_user_capabilities_parameters(),
                    array('username' => $username, 'shortname' => $shortname));

        $capabilities = array();

        if (!$courseid = $DB->get_field('course', 'id', array('shortname' => $shortname), IGNORE_MULTIPLE)) {
            return $capabilities;
        }

        if (!$user = $DB->get_record('user', array('username' => $username), 'id', IGNORE_MULTIPLE)) {
            return $capabilities;
        }

        $context = context_course::instance($courseid);
        foreach (self::$capabilities as $cap) {
            if (has_capability($cap, $context, $user)) {
                $capabilities[] = $cap;
            }
        }

        return $capabilities;
    }

    /**
     * Describes the user_capabilities return value.
     *
     * @return external_value
     */
    public static function get_user_capabilities_returns() {
        return new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'Capability')
               );
    }

// --------------------------------------------------------------------------------------------------------

    /**
     * Describes the parameters for get_users.
     *
     * @return external_external_function_parameters
     */
    public static function get_users_parameters() {
        return new external_function_parameters(
                    array(
                        'field' => new external_value(PARAM_ALPHA, 'the search field can be
                                        \'id\' or \'idnumber\' or \'username\' or \'email\''),
                        'value' => new external_value(PARAM_RAW, 'the value to match'),
                        'customfields' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Custom field'),
                                                'User custom fields (also known as user profile fields)', VALUE_OPTIONAL)

                    )
               );
    }

    /**
     * Returns an array of visible courses (id, shortname, fullname, categoryid)
     * and the capabilities (expcept local/exam_remote:take_exam) the user play in the corresponding courses, exc
     *
     * @param String $field An user field
     * @param String $value A value for the field
     * @param array $customfields a collection of user custom fields
     * @return array of users
     */
    public static function get_users($field, $value, $customfields=array()) {
        global $DB;

        $params = self::validate_parameters(self::get_users_parameters(),
                    array('field' => $field, 'value' => $value, 'customfields' => $customfields));

        switch ($field) {
            case 'id':
                $paramtype = core_user::get_property_type('id');
                break;
            case 'idnumber':
                $paramtype = core_user::get_property_type('idnumber');
                break;
            case 'username':
                $paramtype = core_user::get_property_type('username');
                break;
            case 'email':
                $paramtype = core_user::get_property_type('email');
                break;
            default:
                throw new coding_exception('invalid field parameter',
                        'The search field \'' . $field . '\' is not supported, look at the web service documentation');
        }

        // Clean the value.
        $cleanedvalue = clean_param($value, $paramtype);
        if ($value != $cleanedvalue) {
            throw new invalid_parameter_exception('The field \'' . $field .
                    '\' value is invalid: ' . $value . '(cleaned value: '.$cleanedvalue.')');
        }

        $userfields = 'id, username, idnumber, firstname, lastname, email, lang, city, country, timezone';
        $users = $DB->get_records('user', array($field => $cleanedvalue, 'deleted'=>0, 'suspended'=>0), '', $userfields);

        if (empty($users)) {
            return array();
        }

        foreach ($users as $user) {
            $user->customfields = array();
        }
        if (!empty($customfields)) {
            foreach ($users as $user) {
                $data = profile_user_record($user->id, false);
                foreach ($customfields as $cf) {
                    if (isset($data->$cf)) {
                        $user->customfields[] = array('shortname' => $cf, 'value' => $data->$cf);
                    }
                }
            }
        }

        return $users;
    }

    /**
     * Describes the get_users return value.
     *
     * @return external_multiple_structure
     */
    public static function get_users_returns() {
        return new external_multiple_structure(
                      new external_single_structure(
                          array(
                            'id' =>
                                new external_value(core_user::get_property_type('id'), 'User id.'),
                            'username' =>
                                new external_value(core_user::get_property_type('username'), 'Username'),
                            'firstname' =>
                                new external_value(core_user::get_property_type('firstname'), 'The first name(s) of the user'),
                            'lastname' =>
                                new external_value(core_user::get_property_type('lastname'), 'The family name of the user'),
                            'email' =>
                                new external_value(core_user::get_property_type('email'), 'A valid and unique email address'),
                            'idnumber' =>
                                new external_value(core_user::get_property_type('idnumber'), 'An arbitrary ID code number perhaps from the institution',
                                    VALUE_DEFAULT, ''),
                            'lang' =>
                                new external_value(core_user::get_property_type('lang'), 'Language code such as "en", must exist on server', VALUE_DEFAULT,
                                    core_user::get_property_default('lang'), core_user::get_property_null('lang')),
                            'timezone' =>
                                new external_value(core_user::get_property_type('timezone'), 'Timezone code such as Australia/Perth, or 99 for default',
                                    VALUE_OPTIONAL),
                            'city' =>
                                new external_value(core_user::get_property_type('city'), 'Home city of the user', VALUE_OPTIONAL),
                            'country' =>
                                new external_value(core_user::get_property_type('country'), 'Home country code of the user, such as AU or CZ', VALUE_OPTIONAL),
                            'customfields' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'shortname' => new external_value(PARAM_ALPHANUMEXT, 'The shortname of the custom field'),
                                        'value' => new external_value(PARAM_RAW, 'The value of the custom field')
                                    )
                                ), 'User custom fields (also known as user profil fields)', VALUE_OPTIONAL)
                          )
                      )
               );
    }

// --------------------------------------------------------------------------------------------------------

    /**
     * Describes the parameters for get_user_courses.
     *
     * @return external_external_function_parameters
     */
    public static function get_user_courses_parameters() {
        return new external_function_parameters(
                        array('username'    => new external_value(PARAM_TEXT, 'Username'),
                              'onlyvisible' => new external_value(PARAM_INT, 'Only visible courses', VALUE_DEFAULT, 1))
                   );
    }

    /**
     * Returns an array of visible courses (id, shortname, fullname, categoryid)
     * and the capabilities (expcept local/exam_remote:take_exam) the user play in the corresponding courses, exc
     *
     * @param String $username
     * @param boolean $onlyvisible if true return only visible courses
     * @return array of courses
     */
    public static function get_user_courses($username, $onlyvisible=1) {
        global $DB;

        $params = self::validate_parameters(self::get_user_courses_parameters(),
                            array('username' => $username, 'onlyvisible' => $onlyvisible));

        if (!$userid = $DB->get_field('user', 'id', array('username'=>$username, 'deleted'=>0, 'suspended'=>0))) {
            return array();
        }

        return self::get_local_user_courses($userid, $onlyvisible);
    }

    /**
     * Describes the get_user_courses return value.
     *
     * @return external_multiple_structure
     */
    public static function get_user_courses_returns() {
        return new external_multiple_structure(
                      new external_single_structure(
                          array(
                              'id'           => new external_value(PARAM_INT,  'Course id'),
                              'shortname'    => new external_value(PARAM_TEXT, 'Course shortname'),
                              'fullname'     => new external_value(PARAM_TEXT, 'Course fullname'),
                              'visible'      => new external_value(PARAM_INT,  'Is the course visible?'),
                              'categoryid'   => new external_value(PARAM_INT, 'Category id'),
                              'capabilities' => new external_multiple_structure(new external_value(PARAM_TEXT), 'User capabilities'),
                          )
                      )
               );
    }

// --------------------------------------------------------------------------------------------------------

    /**
     * Describes the parameters for get_categories.
     *
     * @return external_external_function_parameters
     */
    public static function get_categories_parameters() {
        return new external_function_parameters(
                        array('categoryids'=>new external_multiple_structure(new external_value(PARAM_INT, 'Category id')))
                   );
    }

    /**
     * Returns an array of categories (id, name, path) given an array or category ids
     *
     * @param Array $categoryids
     * @return Array
     */
    public static function get_categories($categoryids) {
        global $DB;

        $params = self::validate_parameters(self::get_categories_parameters(), array('categoryids'=>$categoryids));

        if (empty($categoryids)) {
            return array();
        }

        $str_categoryids = implode(',', $categoryids);
        $sql = "SELECT DISTINCT cc2.id, cc2.name, cc2.path
                  FROM {course_categories} cc
                  JOIN {course_categories} cc2 ON (cc2.id = cc.id OR cc.path LIKE CONCAT('%/',cc2.id,'/%') )
                 WHERE cc.id IN ({$str_categoryids})
              ORDER BY cc2.depth, cc2.name";
        $cats = $DB->get_records_sql($sql);
        foreach ($cats AS $catid=>$cat) {
            $path = explode('/', $cat->path);
            unset($path[0]);
            $cats[$catid]->path = $path;
        }

        return array_values($cats);
    }

    /**
     * Describes the get_categories_returns return value.
     *
     * @return external_multiple_structure
     */
    public static function get_categories_returns() {
        return new external_multiple_structure(
                     new external_single_structure(
                         array('id'   => new external_value(PARAM_INT, 'Category id'),
                               'name' => new external_value(PARAM_TEXT, 'Category name'),
                               'path' => new external_multiple_structure(new external_value(PARAM_INT), 'Category path'),
                         )
                     )
               );
    }

// --------------------------------------------------------------------------------------------------------

    /**
     * Describes the parameters for get_students.
     *
     * @return external_external_function_parameters
     */
    public static function get_students_parameters() {
        return new external_function_parameters(
                        array('shortname'=>new external_value(PARAM_TEXT, 'Course shortname', VALUE_DEFAULT, ''),
                              'customfields'=>new external_multiple_structure(new external_value(PARAM_TEXT, 'User custom field shortname'),
                                                                            'Array of user custom fields', VALUE_DEFAULT, array())
                             )
                    );
    }

    public static function get_students($shortname, $customfields = array()) {
        global $DB, $CFG;

        $params = self::validate_parameters(self::get_students_parameters(),
                                            array('shortname'=>$shortname, 'customfields'=>$customfields));

        if (!$courseid = $DB->get_field('course', 'id', array('shortname'=>$shortname))) {
            $site = get_site();
            $a = new stdClass();
            $a->shortname = $shortname;
            $a->site = $site->fullname;
            throw new moodle_exception('unknown_course' , 'local_exam_remote', '', $a);
        }
        $context = context_course::instance($courseid);

		$userfields = 'u.id, u.username, u.firstname, u.lastname, u.email, u.city, u.country, u.lang, u.timezone';
		$students = get_enrolled_users($context, 'local/exam_remote:take_exam', 0, $userfields, null, 0, 0, true);

        foreach ($students AS $st) {
            $st->customfields = array();
            if (!empty($customfields)) {
                profile_load_custom_fields($st);
                foreach ($customfields AS $f) {
                    if (isset($st->profile[$f])) {
                        $obj = new stdClass();
                        $obj->field = $f;
                        $obj->value = $st->profile[$f];
                        $st->customfields[] = $obj;
                    }
                }
                unset($st->profile);
            }
        }

        return $students;
    }

    /**
     * Describes the get_students return value.
     *
     * @return external_multiple_structure
     */
    public static function get_students_returns() {
        return new external_multiple_structure(
                    new external_single_structure(
                        array('id' => new external_value(PARAM_TEXT, 'User id'),
                              'username' => new external_value(PARAM_TEXT, 'Username'),
                              'firstname' => new external_value(PARAM_TEXT, 'User firstname'),
                              'lastname' => new external_value(PARAM_TEXT, 'User lastname'),
                              'email' => new external_value(PARAM_TEXT, 'User email'),
                              'city' => new external_value(PARAM_TEXT, 'User city'),
                              'country' => new external_value(PARAM_TEXT, 'User country'),
                              'lang' => new external_value(PARAM_TEXT, 'User lang'),
                              'timezone' => new external_value(PARAM_TEXT, 'User timezone'),
                              'customfields' => new external_multiple_structure(
                                     new external_single_structure(array('field' => new external_value(PARAM_TEXT, 'Field name'),
                                                                         'value' => new external_value(PARAM_TEXT, 'Field value'))))
                        )
                    )
        );
    }

// --------------------------------------------------------------------------------------------------------

    /**
     * Describes the parameters for restore_activity.
     *
     * @return external_external_function_parameters
     */
    public static function restore_activity_parameters() {
        return new external_function_parameters(
                array('shortname' => new external_value(PARAM_TEXT, 'Course shortname', VALUE_DEFAULT, ''),
                      'username' => new external_value(PARAM_TEXT, 'Username', VALUE_DEFAULT, ''),
                     )
        );
    }

    public static function restore_activity($shortname='', $username='') {
        global $CFG, $USER, $DB;

        $params = self::validate_parameters(self::restore_activity_parameters(),
                        array('shortname' => $shortname, 'username' => $username));

        $context = context_user::instance($USER->id);
        self::validate_context($context);

        if (!$course = $DB->get_record('course', array('shortname'=>$shortname))) {
            $site = get_site();
            $a = new stdClass();
            $a->shortname = $shortname;
            $a->site = $site->fullname;
            return get_string('unknown_course', 'local_exam_remote', $a);
        }
        if (!$user = $DB->get_record('user', array('username'=>$username))) {
            return get_string('unknown_user', 'local_exam_remote', $username);
        }

        $context = context_course::instance($course->id);
        if (!has_capability('moodle/restore:restoreactivity', $context, $user)) {
            return get_string('no_permission', 'local_exam_remote', 'moodle/restore:restoreactivity');
        }

        if (!isset($_FILES['backup_file']) || !isset($_FILES['backup_file']['tmp_name'])) {
            return get_string('backup_file_not_found', 'local_exam_remote');
        }

        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $tmp_backup_dir = $CFG->dataroot . '/temp/backup';
        check_dir_exists($tmp_backup_dir);

        try {
            $rand_backup_path = 'activity_restore_' . date('YmdHis') . '_' . rand();
            $fp = get_file_packer('application/vnd.moodle.backup');

            $extracted = $fp->extract_to_pathname($_FILES['backup_file']['tmp_name'], $tmp_backup_dir.'/'.$rand_backup_path);
            if (!$extracted) {
                throw new backup_helper_exception('missing_moodle_backup_file', $rand_backup_path);
            }

            $adminid = $DB->get_field('user', 'id', array('username'=>'admin'));
            $controller = new restore_controller($rand_backup_path, $course->id, backup::INTERACTIVE_NO, backup::MODE_GENERAL,
                                                 $adminid, backup::TARGET_EXISTING_ADDING);
            if ($controller->execute_precheck()) {
                $controller->execute_plan();
                return 'OK';
            } else {
                return get_string('precheck_failed', 'local_exam_remote');
            }

        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Describes the restore_activity return value.
     *
     * @return external_value
     */
    public static function restore_activity_returns() {
        return new external_value(PARAM_TEXT, 'Resultado da restauração. OK se a restauração for realizada com sucesso');
    }

// --------------------------------------------------------------------------------------------------------
// Auxiliary functions

    /**
     * Return the course list the user has some exam capability except for taking exam
     *
     * @param int $userid The user Moodle id
     * @param boolean $onlyvisible if true return only the visible courses
     * @return array List of courses
     */
    public static function get_local_user_courses($userid, $onlyvisible=true) {
        global $DB;

        $capabilities = array();
        foreach (self::$capabilities as $cap) {
            if ($cap != 'local/exam_remote:take_exam') {
                $capabilities[] = $cap;
            }
        }

        $visible = $onlyvisible ? 'AND c.visible = 1' : '';

        $sql = "SELECT c.id, c.shortname, c.fullname, c.visible, c.category as categoryid
                  FROM {course} c
                  JOIN (SELECT c.id as courseid
                          FROM {role_assignments} ra
                          JOIN {context} ctx ON (ctx.id = ra.contextid AND ctx.contextlevel = :catcontext1)
                          JOIN {context} ctxs ON (ctxs.id = ctx.id OR
                                                  (ctxs.contextlevel = :catcontext2 AND
                                                   ctxs.depth > ctx.depth AND
                                                   ctxs.path LIKE CONCAT(ctx.path, '/%')))
                          JOIN {course} c ON (c.category = ctxs.instanceid {$visible})
                         WHERE ra.userid = :userid1

                         UNION

                        SELECT e.courseid
                          FROM {enrol} e
                          JOIN {user_enrolments} ue ON (ue.enrolid = e.id)
                         WHERE ue.status = :active
                           AND e.status = :enabled
                           AND ue.timestart < :now1 AND (ue.timeend = 0 OR ue.timeend > :now2)
                           AND ue.userid = :userid2
                       ) j
                    ON (j.courseid = c.id)
                 WHERE c.id <> :siteid
                   {$visible}";
        $params = array();
        $params['siteid']  = SITEID;
        $params['userid1'] = $userid;
        $params['userid2'] = $userid;
        $params['active']  = ENROL_USER_ACTIVE;
        $params['enabled'] = ENROL_INSTANCE_ENABLED;
        $params['now1']    = round(time(), -2); // improves db caching
        $params['now2']    = $params['now1'];
        $params['catcontext1']  = CONTEXT_COURSECAT;
        $params['catcontext2']  = CONTEXT_COURSECAT;

        $courses = array();
        foreach ($DB->get_recordset_sql($sql, $params) as $course) {
            context_helper::preload_from_record($course);
            if ($context = context_course::instance($course->id, IGNORE_MISSING)) {
                $course->capabilities = array();
                foreach ($capabilities as $cap) {
                    if (has_capability($cap, $context, $userid)) {
                        $course->capabilities[] = $cap;
                    }
                }
                if (!empty($course->capabilities)) {
                    $courses[$course->id] = $course;
                }
            }
        }

        return $courses;
    }
}
