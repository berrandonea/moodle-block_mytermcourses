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
 * File : block_mytermcourses.php
 * Block class definition
 */

require_once($CFG->dirroot.'/blocks/mytermcourses/lib.php');

use core_completion\progress;

class block_mytermcourses extends block_base {
	const COURSECAT_SHOW_COURSES_COLLAPSED = 10;
    const COURSECAT_SHOW_COURSES_AUTO = 15; /* will choose between collapsed and expanded automatically */
    const COURSECAT_SHOW_COURSES_EXPANDED = 20;
    const COURSECAT_SHOW_COURSES_EXPANDED_WITH_CAT = 30;

    public function init() {
        $this->title = get_string('mytermcourses', 'block_mytermcourses');
    }

    public function get_content() {
        global $CFG, $DB, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }
        $sitecontext = context_system::instance();

        $this->content = new stdClass;
	    $this->content->text = '';        
        $this->content->text .= "<a href='$CFG->wwwroot/blocks/mytermcourses/oldcourses.php'><button id='oldcoursesbutton' class='btn btn-success' style='margin:5px'>"
            .get_string('myoldcourses', 'block_mytermcourses')."</button></a>&nbsp;&nbsp";
        if (has_capability('block/mytermcourses:createcourse', $sitecontext)) {
			$this->content->text .= "<a href='$CFG->wwwroot/blocks/mytermcourses/addcourse.php'><button id='addcoursebutton' class='btn btn-info' style='margin:5px'>"
			    .get_string('addcourse', 'block_mytermcourses')."</button></a>&nbsp;&nbsp;";
		} else {
			$this->content->text .= "<button class='btn btn-secondary' style='margin:5px' onclick='block_mytermcourses_notfound()'>"
			    .get_string('cantseecourse', 'block_mytermcourses')."</button>&nbsp;&nbsp;";
			$this->content->text .= "<div style='display:none' id='notfounddiv'>".get_string('tryenroldemands', 'block_mytermcourses')."</div>";
		}

        $this->content->text .= '<br><br>';

        // Style for category titles.
        $bgcolor = '#731472';
        $style = "font-weight:bold;padding:5px;padding-left:10px;color:white;background-color:$bgcolor;width:100%";
        $courses = $this->getusercourses();

        if (!$courses) {
            $this->content->text .= get_string('notenrolled', 'block_mytermcourses');
        } else {
            // User's course categories.
            $categoriesid = array();
            foreach ($courses as $course) {
                if (!in_array($course->category, $categoriesid)) {
                    array_push($categoriesid, $course->category);
                }
            }
            $categories = $this->sortcategories($categoriesid);

            // Display categories and courses.
            foreach ($categories as $category) {
				if ($this->config->common) {
                    $commoncategoriesid = explode(';', $this->config->common);
                    if (in_array($category->id, $commoncategoriesid)) {
						$bgcolor = '#A56E9D';
                        $style = "font-weight:bold;padding:5px;padding-left:10px;color:white;background-color:$bgcolor;width:100%";
					}
				}
                $category = $DB->get_record('course_categories', array('id' => $category->id));
                $this->content->text .= "<p style='$style'>$category->name</p>";
                $this->displaycourses($courses, $category);
            }
        }

        $this->content->footer = '';
        return $this->content;
    }

    private function getusercourses() {
		global $DB;
        $courses = enrol_get_my_courses('summary, summaryformat', 'idnumber ASC');
        $courseids = array();
        foreach ($courses as $course) {
			$courseids[] = $course->id;
		}
		reset($courses);
        // Common categories.
        if ($this->config->common) {
            $commoncategoriesid = explode(';', $this->config->common);
            $commoncategories = $this->sortcategories($commoncategoriesid);
            foreach ($commoncategories as $commoncategory) {
                $commoncourses = $DB->get_records('course', array('category' => $commoncategory->id));
                if (!$commoncourses) {
					continue;
				}
                foreach ($commoncourses as $commoncourse) {
					// Only get courses with guest access enabled.
					$guestenrol = $DB->get_record('enrol', array('enrol' => 'guest', 'courseid' => $commoncourse->id));
					if ($guestenrol->status) { // status = 1 means 'disabled method'.
						continue;
					}
					if (!in_array($commoncourse->id, $courseids)) {
						$courses[] = $commoncourse;
					}
				}
            }
        }
        return $courses;
	}

    public function sortcategories($categoriesid) {
        global $DB;
        //~ if ($this->config->idnumber) {
            //~ $criterium = 'idnumber';
        //~ } else {
            //~ $criterium = 'sortorder';
        //~ }
        $categories = array();
        foreach ($categoriesid as $categoryid) {
            $categoriesorder[$categoryid] = $DB->get_field('course_categories', 'idnumber', array('id' => $categoryid));
        }
        asort($categoriesorder);
        $lastcategoryid = '';
        $afterlastcategoryid = '';
        foreach ($categoriesorder as $categoryid => $idnumber) {
            $category = $DB->get_record('course_categories', array('id' => $categoryid));
            /**
             * S'il y a un espace personnel, les espaces collaboratifs ne seront pas affichés (on ne veut pas d'étudiants dans les espaces collaboratifs).
             */
            if (($idnumber == 'COLLAB')||($idnumber == 'PERSO')) {
				if ($lastcategoryid) {
					$afterlastcategoryid = $categoryid;
				} else {
					$lastcategoryid = $categoryid;
				}				
			} else {
				array_push($categories, $category);
			}
        }
        if ($lastcategoryid) {
			$lastcategory = $DB->get_record('course_categories', array('id' => $lastcategoryid));
			array_push($categories, $lastcategory);
		}
        if ($afterlastcategoryid) {
			$afterlastcategory = $DB->get_record('course_categories', array('id' => $afterlastcategoryid));
			array_push($categories, $afterlastcategory);
		}
        return $categories;
    }

    public function preparecolumns($category, $courses) {
        global $DB;
        $columns = array();
        $columns[0] = array();
        $columns[1] = array();
        reset($courses);
        foreach ($courses as $course) {
            if ($course->category == $category->id) {
                $right = $DB->record_exists('block_mytermcourses_col', array('courseid' => $course->id, 'position' => 2));
                if ($right) {
                    array_push($columns[1], $course);
                } else {
                    array_push($columns[0], $course);
                }
            }
        }
        return $columns;
    }

    public function displaycourses($courses, $category) {
		global $PAGE;		
		$this->content->text .= '<div style="overflow:auto">';
        foreach ($courses as $course) {
			if ($course->category == $category->id) {
				$this->content->text .= block_mytermcourses_displaycourse($course);				
			}
		}
		$this->content->text .= '</div>';
		$this->content->text .= '<br><br>';
    }

    public function displaylogo($category) {
		global $DB;
		$logofile = 'logoucp.png';
		$logos = array('7' => 'logoscpo.png', '12' => 'logiut.png', '13' => 'logoespe.png');
		$parentcategory = $DB->get_record('course_categories', array('id' => $category->parent));
		if ($parentcategory) {
			if (isset($logos[$parentcategory->parent])) {
				$logofile = $logos[$parentcategory->parent];
			}
		}
		$this->content->text .=  "<p style='margin-top:-45px;text-align:right'><img src='$CFG->wwwroot/$logofile' style='width:130px'></p>";
	}

    public function courseprogress($course) {
		global $USER;
		$completion = new \completion_info($course);
        if (!$completion->is_enabled()) {
            return null;
        }
        $percentage = progress::get_course_progress_percentage($course);
        if (!is_null($percentage)) {
            $percentage = floor($percentage);
        }
        $courseprogress = array();
        $courseprogress['completed'] = $completion->is_course_complete($USER->id);
        $courseprogress['progress'] = $percentage;
        return $courseprogress;
	}


    public function specialization() {
        if (isset($this->config)) {
            if (empty($this->config->changetitle)) {
                $this->title = get_string('mytermcourses', 'block_mytermcourses');
            } else {
                $this->title = $this->config->changetitle;
            }
        }
    }
    
    public function has_config() {
        return true;
    }
}

