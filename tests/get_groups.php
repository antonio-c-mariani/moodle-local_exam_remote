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

include(dirname(__FILE__) . '/config.php');

$courseid = 322;

echo "\nGROUPINGS:\n";
$groupingids = array();
$groupings = call_ws('core_group_get_course_groupings', array('courseid'=>$courseid));
foreach($groupings AS $gr) {
    echo "- Grouping: $gr->id - $gr->name\n";
    $groupingids[] = $gr->id;
}

echo "\nGROUPS:\n";
$groups = call_ws('core_group_get_course_groups', array('courseid'=>$courseid));
foreach($groups AS $g) {
    echo "- Group: $g->id - $g->name\n";
    $gms = call_ws('core_group_get_group_members', array('groupids[0]'=>$g->id));
    foreach($gms AS $gm) {
        foreach($gm->userids AS $uid) {
            echo "     userid: $uid\n";
        }
    }
}

$params = array('returngroups'=>1);
foreach($groupingids AS $i=>$grid) {
    $params["groupingids[{$i}]"] = $grid;
}
$grs = call_ws('core_group_get_groupings', $params);
echo "\nGROUPINGS:\n";
foreach($grs AS $gr) {
    echo "- Grouping: $gr->id - $gr->name\n";
    if(isset($gr->groups)) {
        foreach($gr->groups AS $g) {
            echo "     group: $g->id - $g->name\n";
        }
    }
}
