<?php

require 'vendor/autoload.php';

function add_user() {
	echo('TODO: add_user()');
}

function change_password($user_id) {
	echo("TODO: change_password($user_id)");
}

function delete_user($user_id) {
	echo("TODO: delete_user($user_id)");
}

function get_calendars() {
	echo('TODO: get_calendars()');
}

function add_calendar() {
	echo('TODO: add_calendar()');
}

function get_calendar_info($calendar_id) {
	echo("TODO: get_calendar_info($calendar_id)");
}

function rename_calendar($calendar_id) {
	echo("TODO: rename_calendar($calendar_id)");
}

function delete_calendar($calendar_id) {
	echo("TODO: delete_calendar($calendar_id)");
}

function get_members($calendar_id) {
	echo("TODO: get_members($calendar_id)");
}

function add_member($calendar_id) {
	echo("TODO: add_member($calendar_id)");
}

function edit_member($calendar_id, $user_id) {
	echo("TODO: edit_member($calendar_id, $user_id)");
}

function delete_member($calendar_id, $user_id) {
	echo("TODO: delete_member($calendar_id, $user_id)");
}

function get_events($calendar_id) {
	echo("TODO: get_events($calendar_id)");
}

function add_event($calendar_id) {
	echo("TODO: add_event($calendar_id)");
}

function get_event_info($calendar_id, $event_id) {
	echo("TODO: get_event_info($calendar_id, $event_id)");
}

function edit_event($calendar_id, $event_id) {
	echo("TODO: edit_event($calendar_id, $event_id)");
}

function delete_event($calendar_id, $event_id) {
	echo("TODO: delete_event($calendar_id, $event_id)");
}

\Slim\Slim::registerAutoloader();
$api = new \Slim\Slim();

$api->post('/users', add_user()); {
	add_user();
});


$api->put('/user/:user_id', function($user_id) {
	change_password($user_id);
});
$api->delete('/user/:user_id', function($user_id) {
	delete_user($user_id);
});


$api->get('/calendars', function() {
	get_calendars();
});
$api->post('/calendars', function() {
	add_calendar();
});

$api->get('/calendar/:calendar_id', function($calendar_id) {
	get_calendar_info($calendar_id);
});
$api->put('/calendar/:calendar_id', function($calendar_id) {
	rename_calendar($calendar_id);
});
$api->delete('/calendar/:calendar_id', function($calendar_id) {
	delete_calendar($calendar_id);
});


$api->get('/calendar/:calendar_id/members', function($calendar_id) {
	get_members($calendar_id);
});
$api->post('/calendar/:calendar_id/members', function($calendar_id) {
	add_member($calendar_id);
});


$api->put('/calendar/:calendar_id/member/:username', function($calendar_id, $username) {
	edit_member($calendar_id, $username);
});
$api->delete('/calendar/:calendar_id/member/:username', function($calendar_id, $username) {
	delete_member($calendar_id, $username);
});


$api->get('/calendar/:calendar_id/events', function($calendar_id) {
	get_events($calendar_id);
});
$api->post('/calendar/:calendar_id/events', function($calendar_id) {
	add_event($calendar_id);
});


$api->get('/calendar/:calendar_id/event/:event_id', function($calendar_id, $event_id) {
	get_event_info($calendar_id, $event_id);
});
$api->put('/calendar/:calendar_id/event/:event_id', function($calendar_id, $event_id) {
	edit_event($calendar_id, $event_id);
});
$api->delete('/calendar/:calendar_id/event/:event_id', function($calendar_id, $event_id) {
	delete_event($calendar_id, $event_id);
});

$api->run();

?>