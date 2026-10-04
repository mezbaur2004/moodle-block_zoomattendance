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
 * Upgrade steps for block_zoomattendance.
 *
 * @package    block_zoomattendance
 * @copyright  2026 Mezbaur Are Rafi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the block.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_block_zoomattendance_upgrade($oldversion) {
    if ($oldversion < 2026100400) {
        // The block's own threshold gave way to the thresholds of local_zoomattendance.
        unset_config('threshold', 'block_zoomattendance');
        upgrade_block_savepoint(true, 2026100400, 'zoomattendance', false);
    }
    return true;
}
