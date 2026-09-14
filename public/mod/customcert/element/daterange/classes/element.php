<?php
// This file is part of the customcert module for Moodle - http://moodle.org/
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
 * This file contains the customcert date range element.
 *
 * @package    customcertelement_daterange
 * @copyright  2018 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_daterange;

use mod_customcert\element as base_element;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\preparable_form_interface;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\restorable_element_interface;
use mod_customcert\element\validatable_element_interface;
use mod_customcert\element_helper;
use mod_customcert\service\certificate_repository;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use restore_customcert_activity_task;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/lib/grade/constants.php');

/**
 * The customcert date range element.
 *
 * @package    customcertelement_daterange
 * @copyright  2018 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends base_element implements
    form_element_interface,
    persistable_element_interface,
    preparable_form_interface,
    renderable_element_interface,
    restorable_element_interface,
    validatable_element_interface
{
    /**
     * Max recurring period in seconds.
     */
    const MAX_RECURRING_PERIOD = 31556926; // 12 months.

    /**
     * Current year placeholder string.
     */
    const CURRENT_YEAR_PLACEHOLDER = '{{current_year}}';

    /**
     * First year in a date range placeholder string.
     */
    const RANGE_FIRST_YEAR_PLACEHOLDER = '{{range_first_year}}';

    /**
     * Last year in a date range placeholder string.
     */
    const RANGE_LAST_YEAR_PLACEHOLDER = '{{range_last_year}}';

    /**
     * First year in a date range placeholder string.
     */
    const RECUR_RANGE_FIRST_YEAR_PLACEHOLDER = '{{recurring_range_first_year}}';

    /**
     * Last year in a date range placeholder string.
     */
    const RECUR_RANGE_LAST_YEAR_PLACEHOLDER = '{{recurring_range_last_year}}';

    /**
     * A year in the user's date.
     */
    const DATE_YEAR_PLACEHOLDER = '{{date_year}}';

    /**
     * Date - Issue
     */
    const DATE_ISSUE = -1;

    /**
     * Date - Completion
     */
    const DATE_COMPLETION = -2;

    /**
     * Date - Course start
     */
    const DATE_COURSE_START = -3;

    /**
     * Date - Course end
     */
    const DATE_COURSE_END = -4;

    /**
     * Date - Course grade date
     */
    const DATE_COURSE_GRADE = -5;

    /**
     * Date - Course current date
     */
    const DATE_CURRENT_DATE = -6;

    /**
     * Build the configuration form for this element.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function build_form(MoodleQuickForm $mform): void {
        global $COURSE;

        // Get the possible date options.
        $dateoptions = [];
        $dateoptions[self::DATE_ISSUE] = get_string('issueddate', 'customcertelement_daterange');
        $dateoptions[self::DATE_CURRENT_DATE] = get_string('currentdate', 'customcertelement_daterange');
        $dateoptions[self::DATE_COMPLETION] = get_string('completiondate', 'customcertelement_daterange');
        $dateoptions[self::DATE_COURSE_START] = get_string('coursestartdate', 'customcertelement_daterange');
        $dateoptions[self::DATE_COURSE_END] = get_string('courseenddate', 'customcertelement_daterange');
        $dateoptions[self::DATE_COURSE_GRADE] = get_string('coursegradedate', 'customcertelement_daterange');

        $dateoptions = $dateoptions + element_helper::get_grade_items($COURSE);

        $mform->addElement('select', 'dateitem', get_string('dateitem', 'customcertelement_daterange'), $dateoptions);
        $mform->addHelpButton('dateitem', 'dateitem', 'customcertelement_daterange');

        element_helper::render_common_form_elements($mform, $this->showposxy);

        $mform->addElement('header', 'dateranges', get_string('dateranges', 'customcertelement_daterange'));
        $mform->addElement('static', 'help', '', get_string('help', 'customcertelement_daterange'));
        $mform->addElement('static', 'placeholders', '', get_string('placeholders', 'customcertelement_daterange'));

        $mform->addElement('text', 'fallbackstring', get_string('fallbackstring', 'customcertelement_daterange'));
        $mform->addHelpButton('fallbackstring', 'fallbackstring', 'customcertelement_daterange');
        $mform->setType('fallbackstring', PARAM_NOTAGS);

        $payload = $this->get_payload();
        $repeats = count($payload['dateranges'] ?? []) ?: 1;

        $ranges = [];

        $ranges[] = $mform->createElement('html', '<hr>');

        $ranges[] = $mform->createElement(
            'date_selector',
            'startdate',
            get_string('start', 'customcertelement_daterange')
        );

        $ranges[] = $mform->createElement(
            'date_selector',
            'enddate',
            get_string('end', 'customcertelement_daterange')
        );

        $ranges[] = $mform->createElement(
            'checkbox',
            'recurring',
            get_string('recurring', 'customcertelement_daterange')
        );

        $ranges[] = $mform->createElement(
            'text',
            'datestring',
            get_string('datestring', 'customcertelement_daterange'),
            ['class' => 'datestring']
        );

        $ranges[] = $mform->createElement(
            'advcheckbox',
            'rangedelete',
            get_string('setdeleted', 'customcertelement_daterange'),
            '',
            [],
            [0, 1]
        );

        $rangeoptions = [];
        $rangeoptions['startdate']['type'] = PARAM_INT;
        $rangeoptions['enddate']['type'] = PARAM_INT;
        $rangeoptions['recurring']['type'] = PARAM_INT;
        $rangeoptions['recurring']['helpbutton'] = ['recurring', 'customcertelement_daterange'];
        $rangeoptions['datestring']['type'] = PARAM_NOTAGS;
        $rangeoptions['rangedelete']['type'] = PARAM_BOOL;

        $addstring = get_string('addrange', 'customcertelement_daterange');
        $this->get_edit_element_form()->repeat_elements($ranges, $repeats, $rangeoptions, 'repeats', 'add', 1, $addstring, true);
    }

    /**
     * A helper function to build consistent form element name.
     *
     * @param string $name
     * @param int|string $num
     *
     * @return string
     */
    protected function build_element_name(string $name, int|string $num): string {
        return $name . '[' . $num . ']';
    }

    /**
     * Normalise daterange element data.
     *
     * @param stdClass $formdata
     * @return array
     */
    public function normalise_data(stdClass $formdata): array {
        return [
            'dateitem' => $formdata->dateitem ?? '',
            'fallbackstring' => $formdata->fallbackstring ?? '',
            'font' => (string)($formdata->font ?? ''),
            'fontsize' => (int)($formdata->fontsize ?? 0),
            'colour' => (string)($formdata->colour ?? ''),
            'width' => (int)($formdata->width ?? 0),
            'dateranges' => array_values(array_filter(array_map(function ($i) use ($formdata) {
                if (!empty($formdata->rangedelete[$i])) {
                    return null;
                }
                return [
                    'startdate' => (int)($formdata->startdate[$i] ?? 0),
                    'enddate' => (int)($formdata->enddate[$i] ?? 0),
                    'datestring' => (string)($formdata->datestring[$i] ?? ''),
                    'recurring' => !empty($formdata->recurring[$i]),
                ];
            }, range(0, (int)($formdata->repeats ?? 0) - 1)))),
        ];
    }

    /**
     * Validate submitted form data for this element.
     *
     * @param array $data
     * @return array
     */
    public function validate(array $data): array {
        $errors = [];
        $repeats = (int)($data['repeats'] ?? 0);

        // Check if at least one range is set.
        $error = get_string('error:atleastone', 'customcertelement_daterange');
        for ($i = 0; $i < $repeats; $i++) {
            if (empty($data['rangedelete'][$i])) {
                $error = '';
            }
        }
        if (!empty($error)) {
            $errors['help'] = $error;
        }

        // Check ranges that are not marked for deletion.
        for ($i = 0; $i < $repeats; $i++) {
            if (!empty($data['rangedelete'][$i])) {
                continue;
            }

            if (empty($data['datestring'][$i])) {
                $errors[$this->build_element_name('datestring', $i)] = get_string(
                    'error:datestring',
                    'customcertelement_daterange'
                );
            }

            if (($data['startdate'][$i] ?? 0) >= ($data['enddate'][$i] ?? 0)) {
                $errors[$this->build_element_name('enddate', $i)] = get_string('error:enddate', 'customcertelement_daterange');
            }

            $rangeperiod = ($data['enddate'][$i] ?? 0) - ($data['startdate'][$i] ?? 0);
            if (!empty($data['recurring'][$i]) && $rangeperiod >= self::MAX_RECURRING_PERIOD) {
                $errors[$this->build_element_name('enddate', $i)] = get_string('error:recurring', 'customcertelement_daterange');
            }
        }

        return $errors;
    }

    /**
     * Prepare the form by populating the fields from stored data.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function prepare_form(MoodleQuickForm $mform): void {
        $payload = $this->get_payload();
        if (empty($payload)) {
            return;
        }

        if (isset($payload['dateitem'])) {
            $mform->getElement('dateitem')->setValue($payload['dateitem']);
        }

        if (isset($payload['fallbackstring'])) {
            $mform->getElement('fallbackstring')->setValue($payload['fallbackstring']);
        }

        if (isset($payload['font'])) {
            $mform->setDefault('font', $payload['font']);
        }

        if (isset($payload['fontsize'])) {
            $mform->setDefault('fontsize', $payload['fontsize']);
        }

        if (isset($payload['colour'])) {
            $mform->setDefault('colour', $payload['colour']);
        }

        if (isset($payload['width'])) {
            $mform->setDefault('width', $payload['width']);
        }

        if (!empty($payload['dateranges']) && is_array($payload['dateranges'])) {
            foreach ($payload['dateranges'] as $key => $range) {
                $range = (object)$range;
                $mform->setDefault($this->build_element_name('startdate', $key), $range->startdate ?? 0);
                $mform->setDefault($this->build_element_name('enddate', $key), $range->enddate ?? 0);
                $mform->setDefault($this->build_element_name('datestring', $key), $range->datestring ?? '');
                $mform->setDefault($this->build_element_name('recurring', $key), !empty($range->recurring));
            }
        }
    }

    /**
     * Handles rendering the element on the pdf.
     *
     * @param pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param stdClass $user the user we are rendering this for
     * @param element_renderer|null $renderer the renderer service
     */
    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        global $DB;

        // If there is no element data, we have nothing to display.
        if (empty($this->get_data())) {
            return;
        }

        $payload = $this->get_payload();
        $dateitem = $payload['dateitem'] ?? '';
        $courseid = element_helper::get_courseid($this->id);
        $date = null;

        // If we are previewing this certificate then just show a demonstration date.
        if ($preview) {
            $date = time();
        } else {
            // Get the page.
            $page = $DB->get_record('customcert_pages', ['id' => $this->get_pageid()], '*', MUST_EXIST);
            // Get the customcert this page belongs to.
            $customcert = (new certificate_repository())->get_by_template_id_or_fail((int)$page->templateid);
            // Now we can get the issue for this user.
            $issue = $DB->get_record(
                'customcert_issues',
                ['userid' => $user->id, 'customcertid' => $customcert->id],
                '*',
                IGNORE_MULTIPLE
            );

            switch ($dateitem) {
                case self::DATE_ISSUE:
                    $date = $issue->timecreated;
                    break;

                case self::DATE_CURRENT_DATE:
                    $date = time();
                    break;

                case self::DATE_COMPLETION:
                    // Get the last completion date.
                    $sql = "SELECT MAX(c.timecompleted) as timecompleted
                          FROM {course_completions} c
                         WHERE c.userid = :userid
                           AND c.course = :courseid";
                    if ($timecompleted = $DB->get_record_sql($sql, ['userid' => $issue->userid, 'courseid' => $courseid])) {
                        if (!empty($timecompleted->timecompleted)) {
                            $date = $timecompleted->timecompleted;
                        }
                    }
                    break;

                case self::DATE_COURSE_START:
                    $date = $DB->get_field('course', 'startdate', ['id' => $courseid]);
                    break;

                case self::DATE_COURSE_END:
                    $date = $DB->get_field('course', 'enddate', ['id' => $courseid]);
                    break;

                case self::DATE_COURSE_GRADE:
                    $grade = element_helper::get_course_grade_info(
                        $courseid,
                        GRADE_DISPLAY_TYPE_DEFAULT,
                        $user->id
                    );
                    if ($grade && !empty($grade->get_dategraded())) {
                        $date = $grade->get_dategraded();
                    }
                    break;

                default:
                    if (strpos((string)$dateitem, 'gradeitem:') === 0) {
                        $gradeitemid = (int)substr((string)$dateitem, 10);
                        $grade = element_helper::get_grade_item_info(
                            $gradeitemid,
                            $dateitem,
                            $user->id
                        );
                    } else {
                        $grade = element_helper::get_mod_grade_info(
                            $dateitem,
                            GRADE_DISPLAY_TYPE_DEFAULT,
                            $user->id
                        );
                    }
                    if ($grade && !empty($grade->get_dategraded())) {
                        $date = $grade->get_dategraded();
                    }
                    break;
            }
        }

        // Ensure that a date has been set.
        if (!empty($date)) {
            $content = $this->get_daterange_string((int)$date);
            if ($renderer) {
                $renderer->render_content($this, $content);
            } else {
                element_helper::render_content($pdf, $this, $content);
            }
        }
    }

    /**
     * Get daterange string.
     *
     * @param int $date Unix stamp date.
     *
     * @return string
     */
    protected function get_daterange_string(int $date): string {
        $matchedrange = null;
        $outputstring = '';
        $formatdata = ['date' => $date];
        $payload = $this->get_payload();
        $ranges = $payload['dateranges'] ?? [];

        foreach ($ranges as $range) {
            $range = (object)$range;
            if ($this->is_recurring_range($range)) {
                if ($matchedrange = $this->get_matched_recurring_range($date, $range)) {
                    $outputstring = $matchedrange->datestring;
                    $formatdata['range'] = $range;
                    $formatdata['recurringrange'] = $matchedrange;
                    break;
                }
            } else if ($this->is_date_in_range($date, $range)) {
                $outputstring = $range->datestring;
                $formatdata['range'] = $range;
                break;
            }
        }

        if (empty($outputstring) && !empty($payload['fallbackstring'])) {
            $outputstring = $payload['fallbackstring'];
        }

        return $this->format_date_string($outputstring, $formatdata);
    }

    /**
     * Returns whether or not a range is recurring.
     *
     * @param \stdClass $range Range object.
     *
     * @return bool
     */
    protected function is_recurring_range(stdClass $range): bool {
        return !empty($range->recurring);
    }

    /**
     * Check if the provided date is in the date range.
     *
     * @param int $date Unix timestamp date to check.
     * @param \stdClass $range Range object.
     *
     * @return bool
     */
    protected function is_date_in_range(int $date, stdClass $range): bool {
        return ($date >= $range->startdate && $date <= $range->enddate);
    }

    /**
     * Check if provided date is in the recurring date range.
     *
     * @param int $date Unix timestamp date to check.
     * @param \stdClass $range Range object.
     *
     * @return bool
     */
    protected function is_date_in_recurring_range(int $date, stdClass $range): bool {
        $intdate = $this->build_number_from_date($date);
        $intstart = $this->build_number_from_date($range->startdate);
        $intend = $this->build_number_from_date($range->enddate);

        if (!$this->has_turn_of_the_year($range)) {
            if ($intdate >= $intstart && $intdate <= $intend) {
                return true;
            }
        } else {
            if ($intdate >= $intstart && $intdate >= $intend) {
                return true;
            }

            if ($intdate <= $intstart && $intdate <= $intend) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if provided recurring range has a turn of the year.
     *
     * @param \stdClass $reccurringrange Range object.
     *
     * @return bool
     */
    protected function has_turn_of_the_year(stdClass $reccurringrange): bool {
        return date('Y', $reccurringrange->startdate) != date('Y', $reccurringrange->enddate);
    }

    /**
     * Check if provided date is in the start year of the recurring range with a turn of the year.
     *
     * @param int $date Unix timestamp date to check.
     * @param \stdClass $range Range object.
     *
     * @return bool
     */
    protected function in_start_year(int $date, stdClass $range): bool {
        $intdate = $this->build_number_from_date($date);
        $intstart = $this->build_number_from_date($range->startdate);
        $intend = $this->build_number_from_date($range->enddate);

        return $intdate >= $intstart && $intdate >= $intend;
    }

    /**
     * Check if provided date is in the end year of the recurring range with a turn of the year.
     *
     * @param int $date Unix timestamp date to check.
     * @param \stdClass $range Range object.
     *
     * @return bool
     */
    protected function in_end_year(int $date, stdClass $range): bool {
        $intdate = $this->build_number_from_date($date);
        $intstart = $this->build_number_from_date($range->startdate);
        $intend = $this->build_number_from_date($range->enddate);

        return $intdate <= $intstart && $intdate <= $intend;
    }

    /**
     * Return matched recurring date range.
     *
     * As recurring date ranges do not depend on the year,
     * we will use a date's year to build a new matched recurring date range with
     * start year and end year. This is required to replace placeholders like range_first_year and range_last_year.
     *
     * @param int $date Unix timestamp date to check.
     * @param \stdClass $range Range object.
     *
     * @return \stdClass || null
     */
    protected function get_matched_recurring_range(int $date, stdClass $range): ?stdClass {
        if (!$this->is_date_in_recurring_range($date, $range)) {
            return null;
        }

        $matchedrage = clone $range;

        if ($this->has_turn_of_the_year($matchedrage)) {
            if ($this->in_start_year($date, $matchedrage)) {
                $startyear = date('Y', $date);
                $endyear = $startyear + 1;
                $matchedrage->startdate = strtotime(date('d.m.', $matchedrage->startdate) . $startyear);
                $matchedrage->enddate = strtotime(date('d.m.', $matchedrage->enddate) . $endyear);

                return $matchedrage;
            }

            if ($this->in_end_year($date, $matchedrage)) {
                $endyear = date('Y', $date);
                $startyear = $endyear - 1;
                $matchedrage->startdate = strtotime(date('d.m.', $matchedrage->startdate) . $startyear);
                $matchedrage->enddate = strtotime(date('d.m.', $matchedrage->enddate) . $endyear);

                return $matchedrage;
            }
        } else {
            $matchedrage->startdate = strtotime(date('d.m.', $matchedrage->startdate) . date('Y', $date));
            $matchedrage->enddate = strtotime(date('d.m.', $matchedrage->enddate) . date('Y', $date));

            return $matchedrage;
        }

        return null;
    }

    /**
     * Build number representation of the provided date.
     *
     * @param int $date Unix timestamp date to check.
     *
     * @return int
     */
    protected function build_number_from_date(int $date): int {
        return (int)date('md', $date);
    }

    /**
     * Format date string based on different types of placeholders.
     *
     * @param string $datestring The date string
     * @param array $formatdata A list of format data.
     *
     * @return string
     */
    protected function format_date_string($datestring, array $formatdata) {
        foreach ($this->get_placeholders() as $search => $replace) {
            $datestring = str_replace($search, $replace, $datestring);
        }

        if (!empty($formatdata['date'])) {
            foreach ($this->get_date_placeholders($formatdata['date']) as $search => $replace) {
                $datestring = str_replace($search, $replace, $datestring);
            }
        }

        if (!empty($formatdata['range'])) {
            foreach ($this->get_range_placeholders($formatdata['range']) as $search => $replace) {
                $datestring = str_replace($search, $replace, $datestring);
            }
        }

        if (!empty($formatdata['recurringrange'])) {
            foreach ($this->get_recurring_range_placeholders($formatdata['recurringrange']) as $search => $replace) {
                $datestring = str_replace($search, $replace, $datestring);
            }
        }

        return $datestring;
    }

    /**
     * Return a list of placeholders to replace in date string as search => $replace pairs.
     *
     * @return array
     */
    protected function get_placeholders(): array {
        return [
            self::CURRENT_YEAR_PLACEHOLDER => date('Y', time()),
        ];
    }

    /**
     * Return a list of user's date related placeholders to replace in date string as search => $replace pairs.

     * @param int $date Unix timestamp date to check.
     *
     * @return array
     */
    protected function get_date_placeholders(int $date): array {
        return [
            self::DATE_YEAR_PLACEHOLDER => date('Y', $date),
        ];
    }

    /**
     * Return a list of range related placeholders to replace in date string as search => $replace pairs.
     *
     * @param \stdClass $range
     *
     * @return array
     */
    protected function get_range_placeholders(stdClass $range): array {
        return [
            self::RANGE_FIRST_YEAR_PLACEHOLDER => date('Y', $range->startdate),
            self::RANGE_LAST_YEAR_PLACEHOLDER => date('Y', $range->enddate),
        ];
    }

    /**
     * Return a list of recurring range s placeholders to replace in date string as search => $replace pairs.
     *
     * @param \stdClass $range
     *
     * @return array
     */
    protected function get_recurring_range_placeholders(stdClass $range): array {
        return [
            self::RECUR_RANGE_FIRST_YEAR_PLACEHOLDER => date('Y', $range->startdate),
            self::RECUR_RANGE_LAST_YEAR_PLACEHOLDER => date('Y', $range->enddate),
        ];
    }

    /**
     * Render the element in html.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     *
     * @param element_renderer|null $renderer the renderer service
     * @return string the html
     */
    public function render_html(?element_renderer $renderer = null): string {
        // If there is no element data, we have nothing to display.
        if (empty($this->get_data())) {
            return '';
        }

        $content = $this->get_daterange_string(time());
        if ($renderer) {
            return (string)$renderer->render_content($this, $content);
        }

        return element_helper::render_html_content($this, $content);
    }

    /**
     * Handle restoring references after backup.
     *
     * @param restore_customcert_activity_task $restore
     * @return void
     */
    public function after_restore_from_backup(restore_customcert_activity_task $restore): void {
        global $DB;

        $data = $this->get_payload();
        if (empty($data['dateitem'])) {
            return;
        }

        $isgradeitem = str_starts_with((string)$data['dateitem'], 'gradeitem:');
        $oldid = $isgradeitem ? substr((string)$data['dateitem'], 10) : $data['dateitem'];
        $newid = $restore->get_mappingid($isgradeitem ? 'grade_item' : 'course_module', (int)$oldid);
        if ($newid) {
            $data['dateitem'] = ($isgradeitem ? 'gradeitem:' : '') . $newid;
            $DB->set_field('customcert_elements', 'data', json_encode($data), ['id' => $this->get_id()]);
        }
    }
}
