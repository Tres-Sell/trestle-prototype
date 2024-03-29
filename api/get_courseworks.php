<?php
require_once dirname(__FILE__) . "/./helpers/constants.php";
require_once dirname(__FILE__) . "/../bootstrap.php";
require_once dirname(__FILE__) . './helpers/db_utils.php';
require_once dirname(__FILE__) . '/../setup.php';
require_once dirname(__FILE__) . "/./helpers/parsers.php";

session_start();

$errors = array("errors" => []);

if (!isset($_GET['course_id'])) {
    array_push($errors["errors"], ERROR_MISSING_VALUE . ': Course ID');
    echo json_encode($errors);
    exit;
}

if (!isset($_GET['source'])) {
    array_push($errors["errors"], ERROR_MISSING_VALUE . ': Source');
    echo json_encode($errors);
    exit;
}

$user = get_logged_in_user($entityManager);

if (is_null($user)) {
    array_push($errors["errors"], ERROR_MISSING_LOGGED_IN_USER);
    echo json_encode($errors);
    exit;
}

$course_id = $_GET['course_id'];
$source = $_GET['source'];

$courseworks = [];

if ($source === SOURCE_GOOGLE_CLASSROOM) {

    $google_courseworks = get_google_courseworks($user, $course_id);
    foreach ($google_courseworks as $google_coursework) {
        $coursework = parse_coursework($google_coursework, SOURCE_GOOGLE_CLASSROOM, TYPE_COURSEWORK);
        array_push($courseworks, $coursework);
    }

} else if ($source === SOURCE_CANVAS) {

    $canvas_assignments = get_canvas_assignments($user, $course_id);
    if(isset($canvas_assignments->message)){

        array_push($courseworks,
            array_merge(
                json_decode(json_encode($canvas_assignments), true), 
                array("source" => SOURCE_CANVAS, "type" => TYPE_ASSIGNMENT)
            )
        );

    } else {

        foreach ($canvas_assignments as $canvas_assignment) {
            $coursework = parse_coursework($canvas_assignment, SOURCE_CANVAS, TYPE_ASSIGNMENT);
            array_push($courseworks, $coursework);
        }
    }
    

    $canvas_quizzes = get_canvas_quizzes($user, $course_id);
    if (isset($canvas_quizzes->message)) {

        array_push($courseworks, 
            array_merge(
                json_decode(json_encode($canvas_quizzes), true), 
                array("source" => SOURCE_CANVAS, "type" => TYPE_QUIZ)
            )
        );

    } else {

        foreach ($canvas_quizzes as $canvas_quiz) {
            $coursework = parse_coursework($canvas_quiz, SOURCE_CANVAS, TYPE_QUIZ);
            array_push($courseworks, $coursework);
        }
    }

}

echo json_encode($courseworks);
exit;

function get_google_courseworks(User $user, $course_id)
{
    $client = get_client();
    $token = $user->get_google_token();

    global $errors;
    if (is_null($token)) {
        array_push($errors["errors"], ERROR_GOOGLE_TOKEN_NOT_SET . ": Reminder");
        echo json_encode($errors);
        exit;
    }

    $client->setAccessToken($token);

    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            $user->set_google_token($client->getAccessToken());
        }
        else{
            echo "wooo";
        }
    }
    
    $client->setAccessToken($token);
    $service = new Google\Service\Classroom($client);
    $courseworks = $service->courses_courseWork->listCoursesCourseWork($course_id);

    return $courseworks;
    
}

function get_canvas_assignments(User $user, $course_id)
{
    $token = $user->get_canvas_token();

    global $errors;
    if (is_null($token)) {
        array_push($errors["errors"], ERROR_CANVAS_TOKEN_NOT_SET . ": Reminder");
        echo json_encode($errors);
        exit;
    }

    $headers = array(
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $token
    );

    $response = Requests::get('https://canvas.instructure.com/api/v1/courses/' . $course_id . '/assignments', $headers);
    $assignments = json_decode($response->body);

    return $assignments;
}

function get_canvas_quizzes(User $user, $course_id)
{
    $token = $user->get_canvas_token();

    global $errors;
    if (is_null($token)) {
        array_push($errors["errors"], ERROR_CANVAS_TOKEN_NOT_SET . ": Reminder");
        echo json_encode($errors);
        exit;
    }

    $headers = array(
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $token
    );

    $response = Requests::get('https://canvas.instructure.com/api/v1/courses/' . $course_id . '/quizzes', $headers);
    $quizzes = json_decode($response->body);

    return $quizzes;
}
