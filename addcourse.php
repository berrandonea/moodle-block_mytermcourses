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
 * Initially developped for :
 * Université de Cergy-Pontoise
 * 33, boulevard du Port
 * 95011 Cergy-Pontoise cedex
 * FRANCE
 *
 * Displays the user's courses for the current term. Courses open to all users are also displayed.
 * Teachers can add courses.
 *
 * @package    block_mytermcourses
 * @author     Brice Errandonea <brice.errandonea@u-cergy.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * File : addcourse.php
 * Page where teachers can create new courses.
 * 
 */

require_once('../../config.php');
require_once("$CFG->dirroot/blocks/mytermcourses/lib.php");

$categoryid = optional_param('category', 0, PARAM_INT);
$courseidnumber = optional_param('idnumber', '', PARAM_TEXT);
$coursename = optional_param('coursename', '', PARAM_TEXT);
$format = optional_param('format', '', PARAM_ALPHA);
$categoryparentid = optional_param('categoryparent', 0, PARAM_INT);
$courseparentid = optional_param('courseparent', 0, PARAM_INT);
$newcategoryname = optional_param('newcategoryname', '', PARAM_TEXT);
$newcoursename = optional_param('newcoursename', '', PARAM_TEXT);

// Header code.
$moodlefilename = '/blocks/mytermcourses/addcourse.php';
$sitecontext = context_system::instance();
$PAGE->set_context($sitecontext);
$PAGE->set_url($moodlefilename);
$title = get_string('addcourse', 'block_mytermcourses');
$PAGE->set_title($title);
$PAGE->set_pagelayout('standard');
$PAGE->set_heading($title);
$PAGE->navbar->add(get_string('pluginname', 'block_mytermcourses'));
$PAGE->navbar->add($title);

require_login();
require_capability('block/mytermcourses:createcourse', $sitecontext);

// New category creation.
if ($categoryparentid && $newcategoryname && confirm_sesskey()) {
	$newcategoryidnumber = block_mytermcourses_idnumbercounter('category', $categoryparentid);
	$newcategory = block_mytermcourses_createcategory($newcategoryname, $newcategoryidnumber, 'id', $categoryparentid);
	$categoryid = $newcategory->id;
}

// New course creation (via pedagooffer).
if ($courseparentid && $newcoursename && confirm_sesskey()) {
	$courseidnumber = block_mytermcourses_idnumbercounter('course', $courseparentid);
	$categoryid = $courseparentid;
	$coursename = $newcoursename;
}

// New course creation (via availablecourses or pedagooffer).
if ($courseidnumber && confirm_sesskey()) {
	$category = $DB->get_record('course_categories', array('id' => $categoryid));
	$newcourse = block_mytermcourses_createcourse($coursename, $courseidnumber, $categoryid);
    header("Location: cohorts.php?id=$newcourse->id");
}

echo $OUTPUT->header();

// Failed category creation.
if ($categoryparentid && !$newcategoryname) {
	echo "<p style='text-align:center;color:red'>".get_string('missingcategoryname', 'block_mytermcourses')."</p>";
	$categoryid = $categoryparentid;
}

// Failed course creation.
if ($courseparentid && !$newcoursename) {
	echo "<p style='text-align:center;color:red'>".get_string('missingcoursename', 'block_mytermcourses')."</p>";
	$categoryid = $courseparentid;
}

// Display a given course category.
if ($categoryid) {
	$category = $DB->get_record('course_categories', array('id' => $categoryid));
	block_mytermcourses_choosecategory($category);
	echo $OUTPUT->footer();
	exit;
}

// Select initial cohorts for a new course.
//~ if ($selectcohorts) {
	//~ echo $OUTPUT->heading(get_string('creatingcourse', 'block_mytermcourses')."$coursename ($courseidnumber)");
	//~ $category = $DB->get_record('course_categories', array('id' => $categoryid));
	//~ echo " dans la catégorie $category->name<br><br>";
	//~ $newcourse = block_mytermcourses_createcourse($coursename, $courseidnumber, $categoryid);
        //~ $formdata = array();
	    //~ $formdata['courseidnumber'] = $courseidnumber;
	    //~ $mform = new block_mytermcourses_cohorts_form();
        //~ $mform->set_data($formdata); 
        //~ if ($submitteddata = $mform->get_data()) {
			//~ //TODO
	    //~ } else {
            //~ echo '<p style="text-align:justify">'.get_string('choosecohorts', 'block_mytermcourses').'</p>';
            //~ $mform->display();		
	    //~ }
	
	//~ echo $OUTPUT->footer();
	//~ exit;
//~ }

 //find . -name "*.txt" -type f -exec sed -i "s/mytermcourses/ucpcoursecreation/g" {} \;
  
$availablevets = block_mytermcourses_availablevets();

echo '<p></p>';
//~ echo "<div id='step1' style='font-size:18;text-align:center'>";
//~ echo "<p>Aviez-vous, en 2017-2018, utilisé la plateforme CoursUCP pour ce même cours (celui que vous voulez créer) ?</p>";
//~ echo "<a href='oldcourses.php'><button class='btn btn-primary'>Oui</button></a>&nbsp;&nbsp;";
//~ echo "<button class='btn btn-secondary' onclick='javascript:block_mytermcourses_availables()'>Non</button>";
//~ echo "</div>";

echo "<div id='availables' style='text-align:center'>";
echo "<p style='font-size:18'>".get_string('createwhichcourse', 'block_mytermcourses')."</p>";
$categorystyle = "text-align:left;font-weight:bold;padding:5px;color:white;background-color:gray;width:100%";
foreach ($availablevets as $availablevet) {
	echo "<br><p style='$categorystyle'>$availablevet->vetname";
	echo "&nbsp; &nbsp;<span style='font-size:10'>($availablevet->vetcodeyear)</span></p>";	
	block_mytermcourses_showavailablecourses($availablevet);
}
echo '<br>';

if (isset ($CFG->yearprefix)) {
    $maincoursecategory = $DB->get_record('course_categories', array('idnumber' => $CFG->yearprefix));
    if ($maincoursecategory) {
	    echo "<a class='btn btn-secondary' href='addcourse.php?category=$maincoursecategory->id'>Il n'est pas dans cette liste</a>";
    }
}

echo "</div>";

echo $OUTPUT->footer();
