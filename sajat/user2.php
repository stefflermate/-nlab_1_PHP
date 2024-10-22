<?php
require_once(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

// Alapvető jogosultság ellenőrzés
require_login();

$courseid = optional_param('courseid', 0, PARAM_INT);
$quizid = optional_param('quizid', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/quizanswers/index.php', array('courseid' => $courseid, 'quizid' => $quizid)));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Quiz Answers');
$PAGE->set_heading('Quiz Answers');

echo $OUTPUT->header();

// Kurzus kiválasztása
if (!$courseid) {
    $courses = get_courses('all', 'c.fullname ASC', 'c.id,c.fullname');
    $courseoptions = array();
    foreach ($courses as $course) {
        $courseoptions[$course->id] = format_string($course->fullname);
    }
    echo $OUTPUT->single_select(
        new moodle_url('/local/quizanswers/index.php'),
        'courseid',
        $courseoptions,
        '',
        array('' => 'Select a course'),
        'courseselector'
    );
} 
// Teszt kiválasztása
elseif (!$quizid) {
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    require_login($course);
    $quizzes = get_all_instances_in_course('quiz', $course);
    $quizlist = array();
    foreach ($quizzes as $quiz) {
        $quizlist[$quiz->id] = format_string($quiz->name);
    }
    echo $OUTPUT->single_select(
        new moodle_url('/local/quizanswers/index.php', array('courseid' => $courseid)),
        'quizid',
        $quizlist,
        '',
        array('' => 'Select a quiz'),
        'quizselector'
    );
} 
// Válaszok megjelenítése
else {
    $quiz = $DB->get_record('quiz', array('id' => $quizid), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);

    // Jogosultság ellenőrzése
    require_capability('mod/quiz:viewreports', context_module::instance($cm->id));

    echo $OUTPUT->heading("Quiz Answers: " . format_string($quiz->name));

    // Válaszok lekérdezése
    $attempts = quiz_get_user_attempts($quiz->id, 0, 'finished');
    
    foreach ($attempts as $attempt) {
        $user = $DB->get_record('user', array('id' => $attempt->userid), '*', MUST_EXIST);
        echo $OUTPUT->heading(fullname($user), 3);

        $attemptobj = quiz_attempt::create($attempt->id);
        $slots = $attemptobj->get_slots();

        echo html_writer::start_tag('ul');
        foreach ($slots as $slot) {
            $qa = $attemptobj->get_question_attempt($slot);
            $question = $qa->get_question();
            $response = $qa->get_response_summary();
            echo html_writer::tag('li', format_string($question->name) . ': ' . s($response));
        }
        echo html_writer::end_tag('ul');
    }

    // Navigációs gombok
    $prevlink = new moodle_url('/local/quizanswers/index.php', array('courseid' => $courseid));
    $nextquiz = $DB->get_records_select('quiz', 'course = ? AND id > ?', array($courseid, $quizid), 'id ASC', 'id', 0, 1);
    $nextlink = !empty($nextquiz) ? new moodle_url('/local/quizanswers/index.php', array('courseid' => $courseid, 'quizid' => reset($nextquiz)->id)) : null;

    echo html_writer::start_div('navigation');
    echo $OUTPUT->single_button($prevlink, 'Previous Quiz', 'get');
    if ($nextlink) {
        echo $OUTPUT->single_button($nextlink, 'Next Quiz', 'get');
    }
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
?>