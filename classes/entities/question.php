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
 * Entity class for the "question" activity.
 *
 * @package    local_ezxlate
 * @subpackage entities
 * @copyright  2025 CBCD EURL & Ezxlate
 * @author     Christophe Blanchot <cblanchot@cbcd.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ezxlate\entities;

use local_ezxlate\entity;

/**
 * Represents a question entity.
 */
class question extends entity {

    /**
     * The main DB table for the question entity.
     *
     * @var string
     */
    protected $maintable = 'question';

    /**
     * Define the fields and relationships for the entity.
     *
     * @return void
     */
    protected function define_fields(): void {
        if (!is_numeric($this->record('questiontext'))) {
            $this->addField('questiontext');
        }
        $this->addField('generalfeedback');

        // Detailed feedback.
        $type = $this->record('qtype');
        $fbfields = ['correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'];

        if ($type === 'multichoice') {
            $this->linkTable('qtype_multichoice_options', 'questionid', $fbfields);
        } else if ($type === 'match') {
            $this->linkTable('qtype_match_options', 'questionid', $fbfields);
        } else if ($type === 'ordering') {
            $this->linkTable('qtype_ordering_options', 'questionid', $fbfields);
        } else if ($type === 'randomsamatch') {
            $this->linkTable('qtype_randomsamatch_options', 'questionid', $fbfields);
        } else if ($type === 'calculated') {
            $this->linkTable('question_calculated_options', 'question', $fbfields);
        } else if (!in_array($type, ['multianswer', 'numerical', 'truefalse', 'essay', 'shortanswer'])) {
            $record = $this->linkTable('qtype_' . $type, 'questionid', $fbfields);
            if (empty($record)) {
                $this->linkTable('question_' . $type, 'question', $fbfields);
            }
        }

        // Answers.
        $this->addEntitiesFromTable('answers', ['answer', 'feedback'], 'question_answers', 'question');

        // Subquestions.
        if ($type === 'match') {
            $this->addEntitiesFromTable('subquestions', ['questiontext', 'answertext'], 'qtype_match_subquestions', 'questionid');
        }

        // Hints.
        $this->addEntitiesFromTable('hints', ['hint'], 'question_hints', 'questionid');
    }
}
