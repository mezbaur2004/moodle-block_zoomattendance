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
 * Zoom attendance block.
 *
 * @package    block_zoomattendance
 * @copyright  2026 Mezbaur Are Rafi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_zoomattendance\local\content;

/**
 * Shows each user their Zoom attendance at a glance: their own course overall, students below
 * the threshold, their teaching attendance and the teachers with the lowest attendance, as far
 * as their capabilities in local_zoomattendance allow.
 */
class block_zoomattendance extends block_base {
    /**
     * Set the title.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_zoomattendance');
    }

    /**
     * Where the block can be added.
     *
     * @return array
     */
    public function applicable_formats() {
        return ['my' => true, 'site-index' => true, 'course-view' => false, 'mod' => false];
    }

    /**
     * One block per page is enough.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * The block has site settings.
     *
     * @return bool
     */
    public function has_config() {
        return true;
    }

    /**
     * Build the content. Empty content hides the block from users with nothing to see.
     *
     * @return stdClass
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = (object) ['text' => '', 'footer' => ''];
        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }
        $data = content::get();
        if (content::is_empty($data)) {
            if ($this->page->user_is_editing()) {
                $this->content->text = html_writer::div(get_string('nothing', 'block_zoomattendance'), 'text-muted small');
            }
            return $this->content;
        }
        /** @var \block_zoomattendance\output\renderer $renderer */
        $renderer = $this->page->get_renderer('block_zoomattendance');
        $this->content->text = $renderer->overview($data);
        $this->content->footer = html_writer::div(
            get_string('updatedhourly', 'block_zoomattendance'),
            'text-muted small'
        );
        return $this->content;
    }
}
