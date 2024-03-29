<?php
require_once dirname(__FILE__) . "/./helpers/constants.php";
require_once dirname(__FILE__) . "/../bootstrap.php";
require_once dirname(__FILE__) . '/./helpers/db_utils.php';
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

$announcements = [];

if ($source === SOURCE_GOOGLE_CLASSROOM) {

    $google_announcements = get_google_announcements($user, $course_id);
    foreach ($google_announcements as $google_announcement) {

        $announcement = parse_announcement($google_announcement, SOURCE_GOOGLE_CLASSROOM, $course_id);
        array_push($announcements, $announcement);
    }

} else if ($source === SOURCE_CANVAS) {

    $canvas_announcements = get_canvas_announcements($user, $course_id);
    foreach ($canvas_announcements as $canvas_announcement) {

        $announcement = parse_announcement($canvas_announcement, SOURCE_CANVAS, $course_id);
        array_push($announcements, $announcement);
    }

}

echo json_encode($announcements);


function get_google_announcements(User $user, $course_id)
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
    }

    $service = new Google\Service\Classroom($client);
    $announcements = $service->courses_announcements->listCoursesAnnouncements($course_id);

    return $announcements;
}

function get_canvas_announcements(User $user, $course_id)
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

    $response = Requests::get('https://canvas.instructure.com/api/v1/announcements?context_codes[]=' . $course_id, $headers);
    $announcements = json_decode($response->body);

    return $announcements;
}
