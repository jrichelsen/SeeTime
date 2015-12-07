<?php
/*
See Time API v1.0
Current Bugs:
* /users/:username+DELETE can delete user that is sole admin of calendar (error 21 not working)

TODO:
* put DB connection into middleware
* ensure JSON array is associative
*/

require 'vendor/autoload.php';

//TODO: replace with DB table
$error_messages = array(
	1 => 'message not valid JSON object',
	2 => '"username" field does not exist in message',
	3 => '"pwd" field does not exist in message',
	4 => '"username" field not of type string',
	5 => '"pwd" field not of type string',
	6 => 'JSON object contains extraneous keys',
	7 => 'unknown error creating new user',
	8 => 'username must be between 1 and 15 alphanumeric characters and must start with a letter',
	9 => 'password must be between 8 and 31 characters and consist solely alphanumeric characters and the following symbols: !@#$%^&*',
	10 => 'username already exists',
	11 => '"token" URL variable does not exist',
	12 => '"old_pwd" field does not exist in message',
	13 => '"new_pwd" field does not exist in message',
	14 => '"old_pwd" field not of type string',
	15 => '"new_pwd" field not of type string',
	16 => 'unknown error changing password',
	17 => 'new password must be between 8 and 31 characters and consist solely alphanumeric characters and the following symbols: !@#$%^&*',
	18 => 'invalid permissions',
	19 => 'invalid username-pwd combination',
	20 => 'unknown error deleting user',
	21 => 'cannot remove sole admin of calendar',
	22 => 'unknown error getting list of all calendars viewable by user',
	23 => '"calendar_id" field does not exist in message',
	24 => '"calendar_name" field does not exist in message',
	25 => '"calendar_id" field not of type string',
	26 => '"calendar_name" field not of type string',
	27 => 'unknown error creating new calendar with user as admin',
	28 => 'calendar ID must be 128-character hexadecimal string',
	29 => 'calendar name must be between 1 and 127 characters',
	30 => 'calendar ID already exists',
	31 => 'unknown error getting calendar information',
	32 => 'unknown error changing calendar name',
	33 => 'unknown error deleting calendar',
	34 => 'cannot delete calendar when other admins exist',
	35 => 'unknown error getting list of all calendar members',
	36 => '"role" field does not exist in message',
	37 => '"role" field not of type string',
	38 => 'unknown error adding new calendar member',
	39 => 'role not a valid role ("admin" or "viewer")',
	40 => 'user already has role in calendar',
	41 => 'unknown error getting member information',
	42 => 'user is not member of calendar',
	43 => 'unknown error changing user role',
	44 => 'cannot downgrade other admin to viewer',
	45 => 'unknown error deleting member',
	46 => 'cannot delete other admin of calendar',
	47 => 'unknown error getting list of events',
	48 => '"event_id" field does not exist in message',
	49 => '"event_title" field does not exist in message',
	50 => '"start_date" field does not exist in message',
	51 => '"duration" field does not exist in message',
	52 => '"details" field does not exist in message',
	53 => '"priority" field does not exist in message',
	54 => '"repetition" field does not exist in message',
	55 => '"alert" field does not exist in message',
	56 => '"event_id" field not of type string',
	57 => '"event_title" field not of type string',
	58 => '"start_date" field not of type string',
	59 => '"duration" field not of type integer',
	60 => '"details" field not of type string',
	61 => '"priority" field not of type string',
	62 => '"repetition" field not of type string',
	63 => '"alert" field not of type string',
	64 => 'unknown error creating event',
	65 => 'event ID must be 128-character hexadecimal string',
	66 => 'event title must be between 1 and 127 characters',
	67 => 'start date not a valid datetime string',
	68 => 'duration cannot be negative',
	69 => 'details cannot be greater than 510 characters',
	70 => 'priority not a valid level ("low", "medium" or "high")',
	71 => 'repetition does not meet restrictions ^[[:digit:]]{1,4}[dwmy]$',
	72 => 'alert does not meet restrictions ^[[:digit:]]{1,4}[MHdwmy]$',
	73 => 'event ID already exists',
	74 => 'unknown error getting event information',
	75 => 'event does not belong to calendar',
	76 => 'unknown error changing event information',
	77 => 'unknown error deleting event'
);

// error numbers and information about different JSON fields
$field_errors = array(
	'username' => array(
		'exist_error' => 2,
		'type' => 'string',
		'type_error' => 4,
		'nullable' => false),
	'pwd' => array(
		'exist_error' => 3,
		'type' => 'string',
		'type_error' => 5,
		'nullable' => false),
	'old_pwd' => array(
		'exist_error' => 12,
		'type' => 'string',
		'type_error' => 14,
		'nullable' => false),
	'new_pwd' => array(
		'exist_error' => 13,
		'type' => 'string',
		'type_error' => 15,
		'nullable' => false),
	'calendar_id' => array(
		'exist_error' => 23,
		'type' => 'string',
		'type_error' => 25,
		'nullable' => false),
	'calendar_name' => array(
		'exist_error' => 24,
		'type' => 'string',
		'type_error' => 26,
		'nullable' => false),
	'role' => array(
		'exist_error' => 36,
		'type' => 'string',
		'type_error' => 37,
		'nullable' => false),
	'event_id' => array(
		'exist_error' => 48,
		'type' => 'string',
		'type_error' => 56,
		'nullable' => false),
	'event_title' => array(
		'exist_error' => 49,
		'type' => 'string',
		'type_error' => 57,
		'nullable' => false),
	'start_date' => array(
		'exist_error' => 50,
		'type' => 'string',
		'type_error' => 58,
		'nullable' => false),
	'duration' => array(
		'exist_error' => 51,
		'type' => 'integer',
		'type_error' => 59,
		'nullable' => true),
	'details' => array(
		'exist_error' => 52,
		'type' => 'string',
		'type_error' => 60,
		'nullable' => true),
	'priority' => array(
		'exist_error' => 53,
		'type' => 'string',
		'type_error' => 61,
		'nullable' => true),
	'repetition' => array(
		'exist_error' => 54,
		'type' => 'string',
		'type_error' => 62,
		'nullable' => true),
	'alert' => array(
		'exist_error' => 55,
		'type' => 'string',
		'type_error' => 63,
		'nullable' => true)
);

// different JSON fields for HTTP methods on routes
$route_method_fields = array(
	'/users' => array(
		'POST' => array('username', 'pwd')
	),
	'/users/:username' => array(
		'PUT' => array('old_pwd', 'new_pwd'),
		'DELETE' => array()
	),
	'/calendars' => array(
		'GET' => array(),
		'POST' => array('calendar_id', 'calendar_name')
	),
	'/calendars/:calendar_id' => array(
		'GET' => array(),
		'PUT' => array('calendar_name'),
		'DELETE' => array()
	),
	'/calendars/:calendar_id/members' => array(
		'GET' => array(),
		'POST' => array('username', 'role')
	),
	'/calendars/:calendar_id/members/:username' => array(
		'GET' => array(),
		'PUT' => array('role'),
		'DELETE' => array(),
	),
	'/calendars/:calendar_id/events' => array(
		'GET' => array(),
		'POST' => array('event_id', 'event_title', 'start_date', 'duration', 'details', 'priority', 'repetition', 'alert')
	),
	'/calendars/:calendar_id/events/:event_id' => array(
		'GET' => array(),
		'PUT' => array('event_title', 'start_date', 'duration', 'details', 'priority', 'repetition', 'alert'),
		'DELETE' => array(),
	)
);

// converts response body PHP array with errors array containing only numbers to response body JSON with numbers and messages
//TODO: replace with DB table
function prepare_response_body($response_body_array) {
	$expanded_errors = array();

	global $error_messages;
	foreach ($response_body_array['errors'] as $error) {
		$expanded_errors[] = array(
			'code' => $error,
			'message' => $error_messages[$error]
		);
	}
	$response_body_array['errors'] = $expanded_errors;

	return json_encode($response_body_array);
}

function get_db_conn() {
	$conn = new mysqli(
		'localhost',
		'ozidar',
		'jon',
		'ozidar');
	if ($conn->connect_errno) {
		return null;
	}
	if (!$conn->query("SET SESSION sql_mode = 'strict_all_tables'")) {
		return null;
	}
	return $conn;
}

// checks if request has authentication token (does NOT check validity), immediately returns error response if not
$check_token_exists = function() {
	$app = \Slim\Slim::getInstance();
	$request = $app->request;
	$response_body_array = array(
		'errors' => array()
	);

	$no_token_error = 11;
	if (!$token = $request->params('token')) {
		$response_body_array['errors'][] = $no_token_error;
		$app->halt(400, prepare_response_body($response_body_array));
		return;
	}
};

// checks if request body JSON has appropriate fields, immediately returns error response if not
$decode_body = function (\Slim\Route $route) {
	$app = \Slim\Slim::getInstance();
	$request = $app->request;
	$response_body_array = array(
		'errors' => array()
	);

	$not_json_error = 1;
	$extra_fields_error = 6;

	// attempt to decode JSON
	if (!$request_body_array = json_decode($request->getBody(), true)) {
		$response_body_array['errors'][] = $not_json_error;
		$app->halt(400, prepare_response_body($response_body_array));
		return;
	}

	// ensure JSON is a dictionary, NOT ARRAY
	if (strcmp(gettype($request_body_array), 'array')) {
		$response_body_array['errors'][] = $not_json_error;
		$app->halt(400, prepare_response_body($response_body_array));
		return;
	}

	$pattern = $route->getPattern();
	$method = $request->getMethod();

	global $route_method_fields;
	$required_fields = $route_method_fields[$pattern][$method];

	// convert empty strings in JSON to null values
	foreach ($request_body_array as $json_key => $json_value) {
		if ($json_value == '') {
			$request_body_array[$json_key] = null;
		}
	}
	
	// check that required fields are present and of the correct type
	global $field_errors;
	foreach ($required_fields as $required_field) {
		if (!array_key_exists($required_field, $request_body_array)) {
			$response_body_array['errors'][] = $field_errors[$required_field]['exist_error'];
		} else if (gettype($request_body_array[$required_field]) != $field_errors[$required_field]['type']) {
			if (!($field_errors[$required_field]['nullable'] && is_null($request_body_array[$required_field]))) {
				$response_body_array['errors'][] = $field_errors[$required_field]['type_error'];
			}
		}
	}

	// check if extraneous fields are present
	$fields_present = array_keys($request_body_array);
	if (array_diff($fields_present, $required_fields)) {
		$response_body_array['errors'][] = $extra_fields_error;
	}

	// if errors are present, halt route handling and return
	if (!empty($response_body_array['errors'])) {
		$app->halt(400, prepare_response_body($response_body_array));
		return;
	}
};

function create_user() {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$request_body_array = json_decode($app->request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);
	$username = $request_body_array['username'];
	$pwd = $request_body_array['pwd'];

	// attempt DB operations, returning on error
	$unknown_error = 7;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL create_user(?, ?, @error_8, @error_9, @error_10)')) |
		(!$stmt->bind_param('ss', $username, $pwd)) |
		(!$stmt->execute()) |
		(!$stmt->close()) |
		(!$result_8 = $conn->query('SELECT @error_8')) |
		(!$result_9 = $conn->query('SELECT @error_9')) |
		(!$result_10 = $conn->query('SELECT @error_10')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	// check for errors
	if ($result_8->fetch_assoc()['@error_8']) {
		$response_body_array['errors'][] = 8;
	}
	$result_8->free();
	if ($result_9->fetch_assoc()['@error_9']) {
		$response_body_array['errors'][] = 9;
	}
	$result_9->free();
	if ($result_10->fetch_assoc()['@error_10']) {
		$response_body_array['errors'][] = 10;
	}
	$result_10->free();

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function change_pwd($username) {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$request = $app->request;
	$request_body_array = json_decode($request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);
	$token = $request->params('token');
	$old_pwd = $request_body_array['old_pwd'];
	$new_pwd = $request_body_array['new_pwd'];

	// attempt DB operations, returning on error
	$unknown_error = 16;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL change_pwd(?, ?, ?, ?, @error_17, @error_18, @error_19)')) |
		(!$stmt->bind_param('ssss', $token, $username, $old_pwd, $new_pwd)) |
		(!$stmt->execute()) |
		(!$stmt->close()) |
		(!$result_17 = $conn->query('SELECT @error_17')) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$result_19 = $conn->query('SELECT @error_19')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo($stmt->error);
		return;
	}

	// check for errors
	if ($result_17->fetch_assoc()['@error_17']) {
		$response_body_array['errors'][] = 17;
	}
	$result_17->free();
	if ($result_18->fetch_assoc()['@error_18']) {
		$response_body_array['errors'][] = 18;
	}
	$result_18->free();
	if ($result_19->fetch_assoc()['@error_19']) {
		$response_body_array['errors'][] = 19;
	}
	$result_19->free();

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function delete_user($username) {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$response_body_array = array(
		'errors' => array()
	);
	$token = $app->request->params('token');

	// attempt DB operations, returning on error
	$unknown_error = 20;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL delete_user(?, ?, @error_18, @error_21)')) |
		(!$stmt->bind_param('ss', $token, $username)) |
		(!$stmt->execute()) |
		(!$stmt->close()) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$result_21 = $conn->query('SELECT @error_21')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	// check for errors
	if ($result_18->fetch_assoc()['@error_18']) {
		$response_body_array['errors'][] = 18;
	}
	$result_18->free();
	if ($result_21->fetch_assoc()['@error_21']) {
		$response_body_array['errors'][] = 21;
	}
	$result_21->free();

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function get_calendars() {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$response_body_array = array(
		'errors' => array()
	);
	$token = $app->request->params('token');

	// attempt DB operations, returning on error
	$unknown_error = 22;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL get_calendars(?, @error_18)')) |
		(!$stmt->bind_param('s', $token)) |
		(!$stmt->execute()) |
		(!$result = $stmt->get_result()) |
		(($calendars = $result->fetch_all(MYSQLI_ASSOC)) && false) |
		($result->free()) |
		(!$conn->next_result()) |
		(!$stmt->close()) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	// check for errors, returning on error
	if ($result_18->fetch_assoc()['@error_18']) {
		$response_body_array['errors'][] = 18;
	}
	$result_18->free();
	if (!empty($response_body_array['errors'])) {
		echo(prepare_response_body($response_body_array));
		return;
	}

	// fill calendars response field
	$response_body_array['calendars'] = array();
	foreach ($calendars as $calendar) {
		$response_body_array['calendars'][] = $calendar['calendar_id'];
	}

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function create_calendar() {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$request = $app->request;
	$request_body_array = json_decode($request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);
	$token = $request->params('token');
	$calendar_id = $request_body_array['calendar_id'];
	$calendar_name = $request_body_array['calendar_name'];

	$unknown_error = 27;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL create_calendar(?, ?, ?, @error_28, @error_18, @error_29, @error_30, @calendar_id)')) |
		(!$stmt->bind_param('sss', $token, $calendar_id, $calendar_name)) |
		(!$stmt->execute()) |
		(!$stmt->close()) |
		(!$result_28 = $conn->query('SELECT @error_28')) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$result_29 = $conn->query('SELECT @error_29')) |
		(!$result_30 = $conn->query('SELECT @error_30')) |
		(!$result_calendar_id = $conn->query('SELECT @calendar_id')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	// check for errors, returning on error
	if ($result_28->fetch_assoc()['@error_28']) {
		$response_body_array['errors'][] = 28;
	}
	$result_28->free();
	if ($result_18->fetch_assoc()['@error_18']) {
		$response_body_array['errors'][] = 18;
	}
	$result_18->free();
	if ($result_29->fetch_assoc()['@error_29']) {
		$response_body_array['errors'][] = 29;
	}
	$result_29->free();
	if ($result_30->fetch_assoc()['@error_30']) {
		$response_body_array['errors'][] = 30;
	}
	$result_30->free();
	if (!empty($response_body_array['errors'])) {
		echo(prepare_response_body($response_body_array));
		return;
	}

	// insert calendar_id into response
	$response_body_array['calendar_id'] = $result_calendar_id->fetch_assoc()['@calendar_id'];
	$result_calendar_id->free();

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function get_calendar($calendar_id) {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$response_body_array = array(
		'errors' => array()
	);
	$token = $app->request->params('token');

	// attempt DB operations, returning on error
	$unknown_error = 31;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL get_calendar(?, ?, @error_28, @error_18)')) |
		(!$stmt->bind_param('ss', $token, $calendar_id)) |
		(!$stmt->execute()) |
		(!$result = $stmt->get_result()) |
		(!$calendar_info = $result->fetch_assoc()) |
		($result->free()) |
		(!$conn->next_result()) |
		(!$stmt->close()) |
		(!$result_28 = $conn->query('SELECT @error_28')) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	// check for errors, returning on error
	if ($result_28->fetch_assoc()['@error_28']) {
		$response_body_array['errors'][] = 28;
	}
	$result_28->free();
	if ($result_18->fetch_assoc()['@error_18']) {
		$response_body_array['errors'][] = 18;
	}
	$result_18->free();
	if (!empty($response_body_array['errors'])) {
		echo(prepare_response_body($response_body_array));
		return;
	}

	// insert calendar information into response
	$response_body_array['calendar_name'] = $calendar_info['calendar_name'];
	$response_body_array['ts_modified'] = $calendar_info['ts_modified'];

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function edit_calendar($calendar_id) {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$request = $app->request;
	$request_body_array = json_decode($request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);
	$token = $request->params('token');
	$calendar_name = $request_body_array['calendar_name'];

	// attempt DB operations, returning on error
	$unknown_error = 32;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL edit_calendar(?, ?, ?, @error_28, @error_18, @error_29)')) |
		(!$stmt->bind_param('sss', $token, $calendar_id, $calendar_name)) |
		(!$stmt->execute()) |
		(!$stmt->close()) |
		(!$result_28 = $conn->query('SELECT @error_28')) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$result_29 = $conn->query('SELECT @error_29')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	// check for errors
	if ($result_28->fetch_assoc()['@error_28']) {
		$response_body_array['errors'][] = 28;
	}
	$result_28->free();
	if ($result_18->fetch_assoc()['@error_18']) {
		$response_body_array['errors'][] = 18;
	}
	$result_18->free();
	if ($result_29->fetch_assoc()['@error_29']) {
		$response_body_array['errors'][] = 29;
	}
	$result_29->free();

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function delete_calendar($calendar_id) {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$response_body_array = array(
		'errors' => array()
	);
	$token = $app->request->params('token');

	// attempt DB operations, returning on error
	$unknown_error = 33;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL delete_calendar(?, ?, @error_28, @error_18, @error_34)')) |
		(!$stmt->bind_param('ss', $token, $calendar_id)) |
		(!$stmt->execute()) |
		(!$stmt->close()) |
		(!$result_28 = $conn->query('SELECT @error_28')) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$result_34 = $conn->query('SELECT @error_34')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	// check for errors
	if ($result_28->fetch_assoc()['@error_28']) {
		$response_body_array['errors'][] = 28;
	}
	$result_28->free();
	if ($result_18->fetch_assoc()['@error_18']) {
		$response_body_array['errors'][] = 18;
	}
	$result_18->free();
	if ($result_34->fetch_assoc()['@error_34']) {
		$response_body_array['errors'][] = 34;
	}
	$result_34->free();

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function get_members($calendar_id) {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$response_body_array = array(
		'errors' => array()
	);
	$token = $app->request->params('token');

	// attempt DB operations, returning on error
	$unknown_error = 35;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL get_members(?, ?, @error_28, @error_18)')) |
		(!$stmt->bind_param('ss', $token, $calendar_id)) |
		(!$stmt->execute()) |
		(!$result = $stmt->get_result()) |
		(!$members = $result->fetch_all(MYSQLI_ASSOC)) |
		($result->free()) |
		(!$conn->next_result()) |
		(!$stmt->close()) |
		(!$result_28 = $conn->query('SELECT @error_28')) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	// check for errors, returning on error
	if ($result_28->fetch_assoc()['@error_28']) {
		$response_body_array['errors'][] = 28;
	}
	$result_28->free();
	if ($result_18->fetch_assoc()['@error_18']) {
		$response_body_array['errors'][] = 18;
	}
	$result_18->free();
	if (!empty($response_body_array['errors'])) {
		echo(prepare_response_body($response_body_array));
		return;
	}

	// fill response fields
	$response_body_array['members'] = array();
	$response_body_array['ts_modified'] = '';
	foreach ($members as $member) {
		$response_body_array['members'][] = $member['username'];
		if (strcmp($member['ts_modified'], $response_body_array['ts_modified']) > 0) {
			$response_body_array['ts_modified'] = $member['ts_modified'];
		}
	}

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function add_member($calendar_id) {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$request = $app->request;
	$request_body_array = json_decode($request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);
	$token = $request->params('token');
	$username = $request_body_array['username'];
	$role = $request_body_array['role'];

	$unknown_error = 38;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL add_member(?, ?, ?, ?, @error_28, @error_18, @error_39, @error_40)')) |
		(!$stmt->bind_param('ssss', $token, $username, $calendar_id, $role)) |
		(!$stmt->execute()) |
		(!$stmt->close()) |
		(!$result_28 = $conn->query('SELECT @error_28')) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$result_39 = $conn->query('SELECT @error_39')) |
		(!$result_40 = $conn->query('SELECT @error_40')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	// check for errors
	if ($result_28->fetch_assoc()['@error_28']) {
		$response_body_array['errors'][] = 28;
	}
	$result_28->free();
	if ($result_18->fetch_assoc()['@error_18']) {
		$response_body_array['errors'][] = 18;
	}
	$result_18->free();
	if ($result_39->fetch_assoc()['@error_39']) {
		$response_body_array['errors'][] = 39;
	}
	$result_39->free();
	if ($result_40->fetch_assoc()['@error_40']) {
		$response_body_array['errors'][] = 40;
	}
	$result_40->free();

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function get_member($username, $calendar_id) {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$response_body_array = array(
		'errors' => array()
	);
	$token = $app->request->params('token');

	// attempt DB operations, returning on error
	$unknown_error = 41;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL get_member(?, ?, ?, @error_28, @error_18, @error_42)')) |
		(!$stmt->bind_param('sss', $token, $username, $calendar_id)) |
		(!$stmt->execute()) |
		(!$result = $stmt->get_result()) |
		(!$member = $result->fetch_assoc()) |
		($result->free()) |
		(!$conn->next_result()) |
		(!$stmt->close()) |
		(!$result_28 = $conn->query('SELECT @error_28')) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$result_42 = $conn->query('SELECT @error_42')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	// check for errors, returning on error
	if ($result_28->fetch_assoc()['@error_28']) {
		$response_body_array['errors'][] = 28;
	}
	$result_28->free();
	if ($result_18->fetch_assoc()['@error_18']) {
		$response_body_array['errors'][] = 18;
	}
	$result_18->free();
	if ($result_42->fetch_assoc()['@error_42']) {
		$response_body_array['errors'][] = 42;
	}
	$result_42->free();
	if (!empty($response_body_array['errors'])) {
		echo(prepare_response_body($response_body_array));
		return;
	}

	// insert calendar information into response
	$response_body_array['role'] = $member['role'];
	$response_body_array['ts_modified'] = $member['ts_modified'];

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function edit_member($username, $calendar_id) {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$request = $app->request;
	$request_body_array = json_decode($request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);
	$token = $request->params('token');
	$role = $request_body_array['role'];

	// attempt DB operations, returning on error
	$unknown_error = 43;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL edit_member(?, ?, ?, ?, @error_28, @error_18, @error_39, @error_42, @error_44, @error_21)')) |
		(!$stmt->bind_param('ssss', $token, $username, $calendar_id, $role)) |
		(!$stmt->execute()) |
		(!$stmt->close()) |
		(!$result_28 = $conn->query('SELECT @error_28')) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$result_39 = $conn->query('SELECT @error_39')) |
		(!$result_42 = $conn->query('SELECT @error_42')) |
		(!$result_44 = $conn->query('SELECT @error_44')) |
		(!$result_21 = $conn->query('SELECT @error_21')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	// check for errors
	if ($result_28->fetch_assoc()['@error_28']) {
		$response_body_array['errors'][] = 28;
	}
	$result_28->free();
	if ($result_18->fetch_assoc()['@error_18']) {
		$response_body_array['errors'][] = 18;
	}
	$result_18->free();
	if ($result_39->fetch_assoc()['@error_39']) {
		$response_body_array['errors'][] = 39;
	}
	$result_39->free();
	if ($result_42->fetch_assoc()['@error_42']) {
		$response_body_array['errors'][] = 42;
	}
	$result_42->free();
	if ($result_44->fetch_assoc()['@error_44']) {
		$response_body_array['errors'][] = 44;
	}
	$result_44->free();
	if ($result_21->fetch_assoc()['@error_21']) {
		$response_body_array['errors'][] = 21;
	}
	$result_21->free();

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function delete_member($username, $calendar_id) {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$response_body_array = array(
		'errors' => array()
	);
	$token = $app->request->params('token');

	// attempt DB operations, returning on error
	$unknown_error = 45;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL delete_member(?, ?, ?, @error_28, @error_18, @error_42, @error_46, @error_21)')) |
		(!$stmt->bind_param('sss', $token, $username, $calendar_id)) |
		(!$stmt->execute()) |
		(!$stmt->close()) |
		(!$result_28 = $conn->query('SELECT @error_28')) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$result_42 = $conn->query('SELECT @error_42')) |
		(!$result_46 = $conn->query('SELECT @error_46')) |
		(!$result_21 = $conn->query('SELECT @error_21')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	// check for errors
	if ($result_28->fetch_assoc()['@error_28']) {
		$response_body_array['errors'][] = 28;
	}
	$result_28->free();
	if ($result_18->fetch_assoc()['@error_18']) {
		$response_body_array['errors'][] = 18;
	}
	$result_18->free();
	if ($result_42->fetch_assoc()['@error_42']) {
		$response_body_array['errors'][] = 42;
	}
	$result_42->free();
	if ($result_46->fetch_assoc()['@error_46']) {
		$response_body_array['errors'][] = 46;
	}
	$result_46->free();
	if ($result_21->fetch_assoc()['@error_21']) {
		$response_body_array['errors'][] = 21;
	}
	$result_21->free();

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function get_events($calendar_id) {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$response_body_array = array(
		'errors' => array()
	);
	$token = $app->request->params('token');

	// attempt DB operations, returning on error
	$unknown_error = 47;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL get_events(?, ?, @error_28, @error_18)')) |
		(!$stmt->bind_param('ss', $token, $calendar_id)) |
		(!$stmt->execute()) |
		(!$result = $stmt->get_result()) |
		(($events = $result->fetch_all(MYSQLI_ASSOC)) && false) |
		($result->free()) |
		(!$conn->next_result()) |
		(!$stmt->close()) |
		(!$result_28 = $conn->query('SELECT @error_28')) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	// check for errors, returning on error
	if ($result_28->fetch_assoc()['@error_28']) {
		$response_body_array['errors'][] = 28;
	}
	$result_28->free();
	if ($result_18->fetch_assoc()['@error_18']) {
		$response_body_array['errors'][] = 18;
	}
	$result_18->free();
	if (!empty($response_body_array['errors'])) {
		echo(prepare_response_body($response_body_array));
		return;
	}

	// fill events response field
	$response_body_array['events'] = array();
	foreach ($events as $event) {
		$response_body_array['events'][] = $event['event_id'];
	}

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function create_event($calendar_id) {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$request = $app->request;
	$request_body_array = json_decode($request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);
	$token = $request->params('token');
	$event_id = $request_body_array['event_id'];
	$event_title = $request_body_array['event_title'];
	$start_date = $request_body_array['start_date'];
	$duration = $request_body_array['duration'];
	$details = $request_body_array['details'];
	$priority = $request_body_array['priority'];
	$repetition = $request_body_array['repetition'];
	$alert = $request_body_array['alert'];

	// attempt DB operations, returning on error
	$unknown_error = 64;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL create_event(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, @error_28, @error_18, @error_65, @error_66, @error_67, @error_68, @error_69, @error_70, @error_71, @error_72, @error_73, @event_id)')) |
		(!$stmt->bind_param('sssssissss', $token, $event_id, $calendar_id, $event_title, $start_date, $duration, $details, $priority, $repetition, $alert)) |
		(!$stmt->execute()) |
		(!$stmt->close()) |
		(!$result_28 = $conn->query('SELECT @error_28')) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$result_65 = $conn->query('SELECT @error_65')) |
		(!$result_66 = $conn->query('SELECT @error_66')) |
		(!$result_67 = $conn->query('SELECT @error_67')) |
		(!$result_68 = $conn->query('SELECT @error_68')) |
		(!$result_69 = $conn->query('SELECT @error_69')) |
		(!$result_70 = $conn->query('SELECT @error_70')) |
		(!$result_71 = $conn->query('SELECT @error_71')) |
		(!$result_72 = $conn->query('SELECT @error_72')) |
		(!$result_73 = $conn->query('SELECT @error_73')) |
		(!$result_event_id = $conn->query('SELECT @event_id')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	// check for errors, returning on error
	if ($result_28->fetch_assoc()['@error_28']) {
		$response_body_array['errors'][] = 28;
	}
	$result_28->free();
	if ($result_18->fetch_assoc()['@error_18']) {
		$response_body_array['errors'][] = 18;
	}
	$result_18->free();
	if ($result_65->fetch_assoc()['@error_65']) {
		$response_body_array['errors'][] = 65;
	}
	$result_65->free();
	if ($result_66->fetch_assoc()['@error_66']) {
		$response_body_array['errors'][] = 66;
	}
	$result_66->free();
	if ($result_67->fetch_assoc()['@error_67']) {
		$response_body_array['errors'][] = 67;
	}
	$result_67->free();
	if ($result_68->fetch_assoc()['@error_68']) {
		$response_body_array['errors'][] = 68;
	}
	$result_68->free();
	if ($result_69->fetch_assoc()['@error_69']) {
		$response_body_array['errors'][] = 69;
	}
	$result_69->free();
	if ($result_70->fetch_assoc()['@error_70']) {
		$response_body_array['errors'][] = 70;
	}
	$result_70->free();
	if ($result_71->fetch_assoc()['@error_71']) {
		$response_body_array['errors'][] = 71;
	}
	$result_71->free();
	if ($result_72->fetch_assoc()['@error_72']) {
		$response_body_array['errors'][] = 72;
	}
	$result_72->free();
	if ($result_73->fetch_assoc()['@error_73']) {
		$response_body_array['errors'][] = 73;
	}
	$result_73->free();
	if (!empty($response_body_array['errors'])) {
		echo(prepare_response_body($response_body_array));
		return;
	}

	// insert event_id into response
	$response_body_array['event_id'] = $result_event_id->fetch_assoc()['@event_id'];
	$result_event_id->free();

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function get_event($calendar_id, $event_id) {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$response_body_array = array(
		'errors' => array()
	);
	$token = $app->request->params('token');

	// attempt DB operations, returning on error
	$unknown_error = 74;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL get_event(?, ?, ?, @error_28, @error_18, @error_65, @error_75)')) |
		(!$stmt->bind_param('sss', $token, $event_id, $calendar_id)) |
		(!$stmt->execute()) |
		(!$result = $stmt->get_result()) |
		(($event_info = $result->fetch_assoc()) && false) |
		($result->free()) |
		(!$conn->next_result()) |
		(!$stmt->close()) |
		(!$result_28 = $conn->query('SELECT @error_28')) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$result_65 = $conn->query('SELECT @error_65')) |
		(!$result_75 = $conn->query('SELECT @error_75')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	// check for errors, returning on error
	if ($result_28->fetch_assoc()['@error_28']) {
		$response_body_array['errors'][] = 28;
	}
	$result_28->free();
	if ($result_18->fetch_assoc()['@error_18']) {
		$response_body_array['errors'][] = 18;
	}
	$result_18->free();
	if ($result_65->fetch_assoc()['@error_65']) {
		$response_body_array['errors'][] = 65;
	}
	$result_65->free();
	if ($result_75->fetch_assoc()['@error_75']) {
		$response_body_array['errors'][] = 75;
	}
	$result_75->free();
	if (!empty($response_body_array['errors'])) {
		echo(prepare_response_body($response_body_array));
		return;
	}

	// insert calendar information into response
	$response_body_array['event_title'] = $event_info['event_title'];
	$response_body_array['start_date'] = $event_info['start_date'];
	$response_body_array['duration'] = $event_info['duration'];
	$response_body_array['details'] = $event_info['details'];
	$response_body_array['priority'] = $event_info['priority'];
	$response_body_array['repetition'] = $event_info['repetition'];
	$response_body_array['alert'] = $event_info['alert'];
	$response_body_array['ts_modified'] = $event_info['ts_modified'];

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function edit_event($calendar_id, $event_id) {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$request = $app->request;
	$request_body_array = json_decode($request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);
	$token = $request->params('token');
	$event_title = $request_body_array['event_title'];
	$start_date = $request_body_array['start_date'];
	$duration = $request_body_array['duration'];
	$details = $request_body_array['details'];
	$priority = $request_body_array['priority'];
	$repetition = $request_body_array['repetition'];
	$alert = $request_body_array['alert'];

	// attempt DB operations, returning on error
	$unknown_error = 76;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL edit_event(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, @error_28, @error_18, @error_65, @error_75, @error_66, @error_67, @error_68, @error_69, @error_70, @error_71, @error_72)')) |
		(!$stmt->bind_param('sssssissss', $token, $event_id, $calendar_id, $event_title, $start_date, $duration, $details, $priority, $repetition, $alert)) |
		(!$stmt->execute()) |
		(!$stmt->close()) |
		(!$result_28 = $conn->query('SELECT @error_28')) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$result_65 = $conn->query('SELECT @error_65')) |
		(!$result_75 = $conn->query('SELECT @error_75')) |
		(!$result_66 = $conn->query('SELECT @error_66')) |
		(!$result_67 = $conn->query('SELECT @error_67')) |
		(!$result_68 = $conn->query('SELECT @error_68')) |
		(!$result_69 = $conn->query('SELECT @error_69')) |
		(!$result_70 = $conn->query('SELECT @error_70')) |
		(!$result_71 = $conn->query('SELECT @error_71')) |
		(!$result_72 = $conn->query('SELECT @error_72')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	// check for errors
	if ($result_28->fetch_assoc()['@error_28']) {
		$response_body_array['errors'][] = 28;
	}
	$result_28->free();
	if ($result_18->fetch_assoc()['@error_18']) {
		$response_body_array['errors'][] = 18;
	}
	$result_18->free();
	if ($result_65->fetch_assoc()['@error_65']) {
		$response_body_array['errors'][] = 65;
	}
	$result_65->free();
	if ($result_75->fetch_assoc()['@error_75']) {
		$response_body_array['errors'][] = 75;
	}
	$result_75->free();
	if ($result_66->fetch_assoc()['@error_66']) {
		$response_body_array['errors'][] = 66;
	}
	$result_66->free();
	if ($result_67->fetch_assoc()['@error_67']) {
		$response_body_array['errors'][] = 67;
	}
	$result_67->free();
	if ($result_68->fetch_assoc()['@error_68']) {
		$response_body_array['errors'][] = 68;
	}
	$result_68->free();
	if ($result_69->fetch_assoc()['@error_69']) {
		$response_body_array['errors'][] = 69;
	}
	$result_69->free();
	if ($result_70->fetch_assoc()['@error_70']) {
		$response_body_array['errors'][] = 70;
	}
	$result_70->free();
	if ($result_71->fetch_assoc()['@error_71']) {
		$response_body_array['errors'][] = 71;
	}
	$result_71->free();
	if ($result_72->fetch_assoc()['@error_72']) {
		$response_body_array['errors'][] = 72;
	}
	$result_72->free();

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function delete_event($calendar_id, $event_id) {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$response_body_array = array(
		'errors' => array()
	);
	$token = $app->request->params('token');

	// attempt DB operations, returning on error
	$unknown_error = 77;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL delete_event(?, ?, ?, @error_28, @error_18, @error_65, @error_75)')) |
		(!$stmt->bind_param('sss', $token, $event_id, $calendar_id)) |
		(!$stmt->execute()) |
		(!$stmt->close()) |
		(!$result_28 = $conn->query('SELECT @error_28')) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$result_65 = $conn->query('SELECT @error_65')) |
		(!$result_75 = $conn->query('SELECT @error_75')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	// check for errors
	if ($result_28->fetch_assoc()['@error_28']) {
		$response_body_array['errors'][] = 28;
	}
	$result_28->free();
	if ($result_18->fetch_assoc()['@error_18']) {
		$response_body_array['errors'][] = 18;
	}
	$result_18->free();
	if ($result_65->fetch_assoc()['@error_65']) {
		$response_body_array['errors'][] = 65;
	}
	$result_65->free();
	if ($result_75->fetch_assoc()['@error_75']) {
		$response_body_array['errors'][] = 75;
	}
	$result_75->free();

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

// create API
$app = new \Slim\Slim(array(
	'mode' => 'development'
));
$app->setName('See Time API');

$app->configureMode('development', function () use ($app) {
	$app->config(array(
		'debug' => true,
		'log.enable' => true,
		'log.level' => \Slim\Log::DEBUG
	));
});
$app->configureMode('production', function () use ($app) {
	$app->config(array(
		'debug' => false,
		'log.enable' => true,
		'log.level' => \Slim\Log::DEBUG
	));
});

$app->group('/users', function () use ($app) {
	global $decode_body;
	$app->post('', $decode_body, function () {
		create_user();
	});
	$app->group('/:username', function () use ($app) {
		global $check_token_exists;
		global $decode_body;
		$app->put('', $check_token_exists, $decode_body, function($username) {
			change_pwd($username);
		});
		$app->delete('', $check_token_exists, function($username) {
			delete_user($username);
		});
	});
});

$app->group('/calendars', function () use ($app) {
	global $check_token_exists;
	global $decode_body;
	$app->get('', $check_token_exists, function() {
		get_calendars();
	});
	$app->post('', $check_token_exists, $decode_body, function() {
		create_calendar();
	});
	$app->group('/:calendar_id', function () use ($app) {
		global $check_token_exists;
		global $decode_body;
		$app->get('', $check_token_exists, function($calendar_id) {
			get_calendar($calendar_id);
		});
		$app->put('', $check_token_exists, $decode_body, function($calendar_id) {
			edit_calendar($calendar_id);
		});
		$app->delete('', $check_token_exists, function($calendar_id) {
			delete_calendar($calendar_id);
		});
		$app->group('/members', function () use ($app) {
			global $check_token_exists;
			global $decode_body;
			$app->get('', $check_token_exists, function($calendar_id) {
				get_members($calendar_id);
			});
			$app->post('', $check_token_exists, $decode_body, function($calendar_id) {
				add_member($calendar_id);
			});
			$app->group('/:username', function () use ($app) {
				global $check_token_exists;
				global $decode_body;
				$app->get('', $check_token_exists, function($calendar_id, $username) {
					get_member($username, $calendar_id);
				});
				$app->put('', $check_token_exists, $decode_body, function($calendar_id, $username) {
					edit_member($username, $calendar_id);
				});
				$app->delete('', $check_token_exists, function($calendar_id, $username) {
					delete_member($username, $calendar_id);
				});
			});
		});
		$app->group('/events', function () use ($app) {
			global $check_token_exists;
			global $decode_body;
			$app->get('', $check_token_exists, function($calendar_id) {
				get_events($calendar_id);
			});
			$app->post('', $check_token_exists, $decode_body, function($calendar_id) {
				create_event($calendar_id);
			});
			$app->group('/:event_id', function () use ($app) {
				global $check_token_exists;
				global $decode_body;
				$app->get('', $check_token_exists, function($calendar_id, $event_id) {
					get_event($calendar_id, $event_id);
				});
				$app->put('', $check_token_exists, $decode_body, function($calendar_id, $event_id) {
					edit_event($calendar_id, $event_id);
				});
				$app->delete('', $check_token_exists, function($calendar_id, $event_id) {
					delete_event($calendar_id, $event_id);
				});
			});
		});
	});
});

$app->run();
?>