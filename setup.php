<?php
require_once dirname(__FILE__) . '/./vendor/autoload.php';

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function get_client()
{
    $client = new Google\Client();
    $client->setAuthConfig(__DIR__ . './client_secret.json');

    $client->addScope(Google\Service\Classroom::CLASSROOM_COURSES_READONLY);
    $client->addScope(Google\Service\Classroom::CLASSROOM_COURSEWORK_ME_READONLY);
    $client->addScope(Google\Service\Classroom::CLASSROOM_COURSEWORK_STUDENTS_READONLY);
    $client->addScope(Google\Service\Classroom::CLASSROOM_ANNOUNCEMENTS_READONLY);

    $client->setAccessType('offline');
    $client->setPrompt('consent');

    $client->setIncludeGrantedScopes(true);

    return $client;
}
