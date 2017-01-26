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
                        array('username'=>new external_value(PARAM_TEXT, 'Username'),
                              'password'=>new external_value(PARAM_RAW, 'Password'))
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

        $params = self::validate_parameters(self::authenticate_parameters(), array('username'=>$username, 'password'=>$password));

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
     * Describes the parameters for get_user_courses.
     *
     * @return external_external_function_parameters
     */
    public static function get_user_courses_parameters() {
        return new external_function_parameters(
                        array('username'=>new external_value(PARAM_TEXT, 'Username'))
                   );
    }

    /**
     * Returns an array of visible courses (id, shortname, fullname, categoryid)
     * and the capabilities (expcept local/exam_remote:take_exam) the user play in the corresponding courses, exc
     *
     * @param String $username
     * @return array of courses
     */
    public static function get_user_courses($username) {
        global $DB;

        $params = self::validate_parameters(self::get_user_courses_parameters(), array('username'=>$username));

        if (!$userid = $DB->get_field('user', 'id', array('username'=>$username, 'deleted'=>0, 'suspended'=>0))) {
            return array();
        }

        $courses = array();
        $course_fields = 'c.shortname, c.fullname, c.category as categoryid';

        list($sql, $params) = self::sql_over_courses($userid, $course_fields);
        self::courses_from_sql($userid, $sql, $params, $courses);

        list($sql, $params) = self::sql_over_categories($userid, $course_fields);
        self::courses_from_sql($userid, $sql, $params, $courses);

        foreach ($courses as $c) {
            $c->capabilities = array_keys($c->capabilities);
        }

        return $courses;
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
                                                                            'Array of user fields', VALUE_DEFAULT, array())
                             )
                    );
    }

    public static function get_students($shortname, $customfields = array()) {
        global $DB, $CFG;

        $params = self::validate_parameters(self::get_students_parameters(),
                                            array('shortname'=>$shortname, 'customfields'=>$customfields));

        if (!$courseid = $DB->get_field('course', 'id', array('shortname'=>$shortname))) {
            return array();
        }
        $context = context_course::instance($courseid);

		$userfields = 'u.id, u.username, u.firstname, u.lastname, u.email, u.city, u.country, u.lang, u.timezone';
		$students = get_enrolled_users($context, 'local/exam_remote:take_exam', 0, $userfields, null, 0, 0, true);

        if (!empty($customfields)) {
            foreach ($students AS $st) {
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
            return get_string('unknown_course', 'local_exam_remote', $shortname);
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

        $rand_backup_path = 'activity_restore_' . date('YmdHis') . '_' . rand();
        $fp = get_file_packer('application/vnd.moodle.backup');

        $extracted = $fp->extract_to_pathname($_FILES['backup_file']['tmp_name'], $tmp_backup_dir.'/'.$rand_backup_path);
        if (!$extracted) {
            throw new backup_helper_exception('missing_moodle_backup_file', $rand_backup_path);
        }

        $adminid = $DB->get_field('user', 'id', array('username'=>'admin'));
        $controller = new restore_controller($rand_backup_path, $course->id, backup::INTERACTIVE_NO, backup::MODE_GENERAL,
                                             $adminid, backup::TARGET_EXISTING_ADDING);
        $controller->execute_precheck();
        $controller->execute_plan();

        return 'OK';

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

    // Private functions

    private static function courses_from_sql($userid, $sql, $params, &$courses) {
        global $DB;

        foreach ($DB->get_recordset_sql($sql, $params) AS $c) {
            if (!isset($courses[$c->id]->capabilities[$c->capability])) {
                $context = context_course::instance($c->id);
                if (has_capability($c->capability, $context, $userid)) {
                    if (!isset($courses[$c->id])) {
                        $c->capabilities = array();
                        $courses[$c->id] = $c;
                    }
                    $courses[$c->id]->capabilities[$c->capability] = true;
                }
            }
        }

        return $courses;
    }

    /**
     * Retorna código sql para obtenção dos cursos visíveis (e correspondentes capabilities) nos quais o usuário possui alguma atribuição explícita
     * de papel que possui uma ou mais das 'capabilities' relacionadas a provas. As capabilities podem ter sido definidas globalmente no papel
     * ou atribuidas localmente no curso.
     *
     * @param int $userid
     * @param String $course_extra_fields
     * @return String sql code
     */
    private static function sql_over_courses($userid, $course_extra_fields='') {
        global $DB;

        $course_extra_fields = empty($course_extra_fields) ? '' : ', ' . $course_extra_fields;

        $sql = "SELECT c.id, rc.capability {$course_extra_fields}
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON (ctx.id = ra.contextid AND ctx.contextlevel = :contextcourselevel1)
                  JOIN {role_capabilities} rc ON (rc.roleid = ra.roleid AND rc.permission = 1 AND (rc.contextid = 1 OR rc.contextid = ra.contextid))
                  JOIN {capabilities} cap ON (cap.name = rc.capability AND cap.component = 'local_exam_remote')
                  JOIN {course} c ON (c.id = ctx.instanceid AND c.visible = 1)
                 WHERE ra.userid = :userid1

                 UNION

                SELECT c.id, rc.capability {$course_extra_fields}
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON (ctx.id = ra.contextid AND ctx.contextlevel = :contextcourselevel2)
                  JOIN {course} c ON (c.id = ctx.instanceid AND c.visible = 1)
                  JOIN {course_categories} cc ON (cc.id = c.category AND cc.visible = 1)
                  JOIN {context} ctxc ON (ctxc.instanceid = cc.id AND ctxc.contextlevel = :contextcoursecatlevel1)
                  JOIN {context} ctxs ON (ctxs.id = ctxc.id OR (ctxs.contextlevel = :contextcoursecatlevel2 AND ctxs.depth < ctxc.depth AND ctxc.path LIKE CONCAT(ctxs.path, '/%')))
                  JOIN {role_capabilities} rc ON (rc.roleid = ra.roleid AND rc.permission = 1 AND rc.contextid > 1 AND rc.contextid = ctxs.id)
                  JOIN {capabilities} cap ON (cap.name = rc.capability AND cap.component = 'local_exam_remote')
                 WHERE ra.userid = :userid2";

        $params = array();
        $params['userid1'] = $userid;
        $params['userid2'] = $userid;
        $params['contextcourselevel1'] = CONTEXT_COURSE;
        $params['contextcourselevel2'] = CONTEXT_COURSE;
        $params['contextcoursecatlevel1'] = CONTEXT_COURSECAT;
        $params['contextcoursecatlevel2'] = CONTEXT_COURSECAT;

        return array($sql, $params);
    }

    private static function sql_over_categories($userid, $course_extra_fields='') {
        global $DB;

        $course_extra_fields = empty($course_extra_fields) ? '' : ', ' . $course_extra_fields;

        list($cap_sql1, $params1) = $DB->get_in_or_equal(array_keys(self::$capabilities), SQL_PARAMS_NAMED);
        list($cap_sql2, $params2) = $DB->get_in_or_equal(array_keys(self::$capabilities), SQL_PARAMS_NAMED);
        $params = array_merge($params1, $params2);

        $coursecatcontext = CONTEXT_COURSECAT;

        $sql = "SELECT c.id, rc.capability {$course_extra_fields}
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON (ctx.id = ra.contextid AND ctx.contextlevel = {$coursecatcontext})
                  JOIN {context} ctxs ON (ctxs.id = ctx.id OR (ctxs.contextlevel = {$coursecatcontext} AND ctxs.depth > ctx.depth AND ctxs.path LIKE CONCAT(ctx.path, '/%')))
                  JOIN {role_capabilities} rc ON (rc.roleid = ra.roleid)
                  JOIN {course_categories} cc ON (cc.id = ctxs.instanceid AND cc.visible)
                  JOIN {course} c ON (c.category = cc.id AND c.visible)
                 WHERE ra.userid = :userid1
                   AND rc.capability {$cap_sql1}
                   AND rc.contextid = 1
                   AND rc.permission = 1

                 UNION

                SELECT c.id, rcc.capability {$course_extra_fields}
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON (ctx.id = ra.contextid AND ctx.contextlevel = {$coursecatcontext})
                  JOIN {context} ctxs ON (ctxs.id = ctx.id OR (ctxs.contextlevel = {$coursecatcontext} AND ctxs.path LIKE CONCAT(ctx.path, '/%')))
                  JOIN (SELECT rc.capability, rc.roleid, ctxs.id as contextid
                          FROM {role_capabilities} rc
                          JOIN {context} ctx ON (ctx.id = rc.contextid AND ctx.contextlevel = {$coursecatcontext})
                          JOIN {context} ctxs ON (ctxs.id = ctx.id OR (ctxs.contextlevel = {$coursecatcontext} AND
                                                  ctxs.depth > ctx.depth AND ctxs.path LIKE CONCAT(ctx.path, '/%')))
                         WHERE rc.capability {$cap_sql2}
                           AND rc.permission = 1
                           AND rc.contextid > 1) rcc
                    ON (rcc.contextid = ctxs.id AND rcc.roleid = ra.roleid)
                  JOIN {course_categories} cc ON (cc.id = ctxs.instanceid AND cc.visible)
                  JOIN {course} c ON (c.category = cc.id AND c.visible)
                 WHERE ra.userid = :userid2";

        $params['userid1'] = $userid;
        $params['userid2'] = $userid;

        return array($sql, $params);
    }
}
