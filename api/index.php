<?php

require 'vendor/autoload.php';

\Slim\Slim::registerAutoloader();

$api = new \Slim\Slim();

$api->post('/users', add_user());

$api->put('/user/:user_id', change_password($user_id));
$api->delete('/user/:user_id', delete_user($user_id));

$api->get('/calendars', get_calendars());
$api->post('/calendars', add_calendar());

$api->get('/calendar/:calendar_id', get_calendar_info($calendar_id));
$api->put('/calendar/:calendar_id', rename_calendar($calendar_id));
$api->delete('/calendar/:calendar_id', delete_calendar($calendar_id));

$api->get('/calendar/:calendar_id/members', get_members($calendar_id));
$api->post('/calendar/:calendar_id/members', add_member($calendar_id));

$api->put('/calendar/:calendar_id/member/:username', edit_member($calendar_id, $username));
$api->delete('/calendar/:calendar_id/member/:username', delete_member($calendar_id, $username));

$api->get('/calendar/:calendar_id/events', get_events($calendar_id));
$api->post('/calendar/:calendar_id/events', add_event($calendar_id));

$api->get('/calendar/:calendar_id/event/:event_id', get_event_info($calendar_id, $event_id));
$api->put('/calendar/:calendar_id/event/:event_id', edit_event($calendar_id, $event_id));
$api->delete('/calendar/:calendar_id/event/:event_id', delete_event($calendar_id, $event_id));

$app->run();

?>