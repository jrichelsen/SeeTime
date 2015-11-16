<?php
/*
See Time API v1.0
Current Bugs:
* /users/:username+DELETE can delete user that is sole admin of calendar (error 21 not working)

TODO:
* put DB connection into middleware
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
	8 => 'username does not meet restrictions ^[[:alpha:]][[:alnum:]]{0,14}$',
	9 => 'password does not meet restrictions ^[[:alnum:]]{8,31}$',
	10 => 'username already exists',
	11 => 'unknown error changing password',
	12 => '"token" URL variable does not exist',
	13 => '"old_pwd" field does not exist in message',
	14 => '"new_pwd" field does not exist in message',
	15 => '"old_pwd" field not of type string',
	16 => '"new_pwd" field not of type string',
	17 => 'new password does not meet restrictions ^[[:alnum:]]{8,31}$',
	18 => 'invalid permissions',
	19 => 'invalid username-pwd combination',
	20 => 'unknown error deleting user',
	21 => 'cannot remove sole admin of calendar',
	22 => 'unknown error getting list of calendars',
	23 => 'unknown error creating new calendar',
	24 => '"calendar_name" field does not exist in message',
	25 => '"calendar_name" field not of type string',
	26 => 'unknown error getting calendar information',
	27 => 'unknown error changing calendar name',
	28 => 'unknown error deleting calendar',
	29 => 'cannot delete calendar when other admins exist',
	30 => 'unknown error getting members',
	31 => 'unknown error adding member',
	32 => '"role" field does not exist in message',
	33 => '"role" field not of type string',
	34 => '"role" not a valid role ("admin" or "viewer")',
	35 => 'user already has role in calendar',
	36 => 'unknown error getting member information',
	37 => 'user is not member of calendar',
	38 => 'unknown error changing user role',
	39 => 'unknown error deleting member',
	40 => 'cannot delete other admin of calendar'
);

// error numbers and information about different JSON fields
$field_errors = array(
	'username' => array(
		'exist_error' => 2,
		'type' => 'string',
		'type_error' => 4),
	'pwd' => array(
		'exist_error' => 3,
		'type' => 'string',
		'type_error' => 5),
	'old_pwd' => array(
		'exist_error' => 13,
		'type' => 'string',
		'type_error' => 15),
	'new_pwd' => array(
		'exist_error' => 14,
		'type' => 'string',
		'type_error' => 16),
	'calendar_name' => array(
		'exist_error' => 24,
		'type' => 'string',
		'type_error' => 25),
	'role' => array(
		'exist_error' => 32,
		'type' => 'string',
		'type_error' => 33)
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
		'POST' => array('calendar_name')
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
	'/calendars/:calendar_id/members' => array(
		'GET' => array(),
		'POST' => array('username', 'role')
	),
	'/calendars/:calendar_id/members/:username' => array(
		'GET' => array(),
		'PUT' => array('role'),
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
	return $conn;
}

// checks if request has authentication token (does NOT check validity), immediately returns error response if not
$check_token_exists = function() {
	$app = \Slim\Slim::getInstance();
	$request = $app->request;
	$response_body_array = array(
		'errors' => array()
	);

	$no_token_error = 12;
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

	$pattern = $route->getPattern();
	$method = $request->getMethod();

	global $route_method_fields;
	$fields = $route_method_fields[$pattern][$method];

	// check that required fields are present
	global $field_errors;
	foreach ($fields as $field) {
		if (!array_key_exists($field, $request_body_array)) {
			$response_body_array['errors'][] = $field_errors[$field]['exist_error'];
		} else if (gettype($request_body_array[$field]) != $field_errors[$field]['type']) {
			$response_body_array['errors'][] = $field_errors[$field]['type_error'];
		}
	}

	// check if extraneous fields are present
	$fields_present = array_keys($request_body_array);
	if (array_intersect($fields, $fields) != $fields_present) {
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

	// attempt all DB operations, returning on error
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
	$result_8->close();
	if ($result_9->fetch_assoc()['@error_9']) {
		$response_body_array['errors'][] = 9;
	}
	$result_9->close();
	if ($result_10->fetch_assoc()['@error_10']) {
		$response_body_array['errors'][] = 10;
	}
	$result_10->close();

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

	// attempt all DB operations, returning on error
	$unknown_error = 11;
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
	$result_17->close();
	if ($result_18->fetch_assoc()['@error_18']) {
		$response_body_array['errors'][] = 18;
	}
	$result_18->close();
	if ($result_19->fetch_assoc()['@error_19']) {
		$response_body_array['errors'][] = 19;
	}
	$result_19->close();

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function delete_user($username) {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$request = $app->request;
	$request_body_array = json_decode($request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);
	$token = $request->params('token');

	// attempt all DB operations, returning on error
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
	$result_18->close();
	if ($result_21->fetch_assoc()['@error_21']) {
		$response_body_array['errors'][] = 21;
	}
	$result_21->close();

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function get_calendars_roles() {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$request = $app->request;
	$request_body_array = json_decode($request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);
	$token = $request->params('token');

	// attempt beginning DB operations, returning on error
	$unknown_error = 22;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL get_calendars_roles(?, @error_18)')) |
		(!$stmt->bind_param('s', $token)) |
		(!$stmt->execute()) |
		(!$result = $stmt->get_result()) |
		(!$calendars = $result->fetch_all(MYSQLI_ASSOC)) |
		($result->close()) |
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
		$result_18->close();
		$response_body_array['errors'][] = 18;
		echo(prepare_response_body($response_body_array));
		return;
	}
	$result_18->close();

	// fill calendars response field
	$response_body_array['calendars'] = $calendars;

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
	$calendar_name = $request_body_array['calendar_name'];

	$unknown_error = 23;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL create_calendar(?, ?, @error_18, @calendar_id)')) |
		(!$stmt->bind_param('ss', $token, $calendar_name)) |
		(!$stmt->execute()) |
		(!$stmt->close()) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$result_calendar_id = $conn->query('SELECT @calendar_id')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	// check for errors, returning on error
	if ($result_18->fetch_assoc()['@error_18']) {
		$result_18->close();
		$response_body_array['errors'][] = 18;
		echo(prepare_response_body($response_body_array));
		return;
	}
	$result_18->close();

	// insert calendar_id into response
	$response_body_array['calendar_id'] = $result_calendar_id->fetch_assoc()['@calendar_id'];
	$result_calendar_id->close();

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function get_calendar($calendar_id) {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$request = $app->request;
	$request_body_array = json_decode($request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);
	$token = $request->params('token');

	// attempt beginning DB operations, returning on error
	$unknown_error = 26;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL get_calendar(?, ?, @error_18)')) |
		(!$stmt->bind_param('si', $token, $calendar_id)) |
		(!$stmt->execute()) |
		(!$result = $stmt->get_result()) |
		(!$calendar_info = $result->fetch_assoc()) |
		($result->close()) |
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
		$result_18->close();
		$response_body_array['errors'][] = 18;
		echo(prepare_response_body($response_body_array));
		return;
	}
	$result_18->close();

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

	// attempt all DB operations, returning on error
	$unknown_error = 27;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL edit_calendar(?, ?, ?, @error_18)')) |
		(!$stmt->bind_param('sis', $token, $calendar_id, $calendar_name)) |
		(!$stmt->execute()) |
		(!$stmt->close()) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
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
	$result_18->close();

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function delete_calendar($calendar_id) {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$request = $app->request;
	$request_body_array = json_decode($request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);
	$token = $request->params('token');

	// attempt all DB operations, returning on error
	$unknown_error = 28;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL delete_calendar(?, ?, @error_18, @error_29)')) |
		(!$stmt->bind_param('si', $token, $calendar_id)) |
		(!$stmt->execute()) |
		(!$stmt->close()) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$result_29 = $conn->query('SELECT @error_29')) |
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
	$result_18->close();
	if ($result_29->fetch_assoc()['@error_29']) {
		$response_body_array['errors'][] = 29;
	}
	$result_29->close();

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function get_members($calendar_id) {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$request = $app->request;
	$request_body_array = json_decode($request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);
	$token = $request->params('token');

	// attempt beginning DB operations, returning on error
	$unknown_error = 30;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL get_members(?, ?, @error_18)')) |
		(!$stmt->bind_param('si', $token, $calendar_id)) |
		(!$stmt->execute()) |
		(!$result = $stmt->get_result()) |
		(!$members = $result->fetch_all(MYSQLI_ASSOC)) |
		($result->close()) |
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
		$result_18->close();
		$response_body_array['errors'][] = 18;
		echo(prepare_response_body($response_body_array));
		return;
	}
	$result_18->close();

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

	$unknown_error = 31;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL add_member(?, ?, ?, ?, @error_34, @error_18, @error_35)')) |
		(!$stmt->bind_param('ssis', $token, $username, $calendar_id, $role)) |
		(!$stmt->execute()) |
		(!$stmt->close()) |
		(!$result_34 = $conn->query('SELECT @error_34')) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$result_35 = $conn->query('SELECT @error_35')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	// check for errors
	if ($result_34->fetch_assoc()['@error_34']) {
		$response_body_array['errors'][] = 34;
	}
	$result_34->close();
	if ($result_18->fetch_assoc()['@error_18']) {
		$response_body_array['errors'][] = 18;
	}
	$result_18->close();
	if ($result_35->fetch_assoc()['@error_35']) {
		$response_body_array['errors'][] = 35;
	}
	$result_35->close();

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function get_member($username, $calendar_id) {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$request = $app->request;
	$request_body_array = json_decode($request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);
	$token = $request->params('token');

	// attempt beginning DB operations, returning on error
	$unknown_error = 36;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL get_member(?, ?, ?, @error_18, @error_37)')) |
		(!$stmt->bind_param('ssi', $token, $username, $calendar_id)) |
		(!$stmt->execute()) |
		(!$result = $stmt->get_result()) |
		(!$member = $result->fetch_assoc()) |
		($result->close()) |
		(!$conn->next_result()) |
		(!$stmt->close()) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$result_37 = $conn->query('SELECT @error_37')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	// check for errors, returning on error
	if ($result_18->fetch_assoc()['@error_18']) {
		$result_18->close();
		$response_body_array['errors'][] = 18;
		echo(prepare_response_body($response_body_array));
		return;
	}
	$result_18->close();
	if ($result_37->fetch_assoc()['@error_37']) {
		$result_37->close();
		$response_body_array['errors'][] = 37;
		echo(prepare_response_body($response_body_array));
		return;
	}
	$result_37->close();

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

	// attempt all DB operations, returning on error
	$unknown_error = 38;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL edit_member(?, ?, ?, ?, @error_34, @error_18, @error_37, @error_21)')) |
		(!$stmt->bind_param('ssis', $token, $username, $calendar_id, $role)) |
		(!$stmt->execute()) |
		(!$stmt->close()) |
		(!$result_34 = $conn->query('SELECT @error_34')) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$result_37 = $conn->query('SELECT @error_37')) |
		(!$result_21 = $conn->query('SELECT @error_21')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	// check for errors
	if ($result_34->fetch_assoc()['@error_34']) {
		$response_body_array['errors'][] = 34;
	}
	$result_34->close();
	if ($result_18->fetch_assoc()['@error_18']) {
		$response_body_array['errors'][] = 18;
	}
	$result_18->close();
	if ($result_37->fetch_assoc()['@error_37']) {
		$response_body_array['errors'][] = 37;
	}
	$result_37->close();
	if ($result_21->fetch_assoc()['@error_21']) {
		$response_body_array['errors'][] = 21;
	}
	$result_21->close();

	// return response
	echo(prepare_response_body($response_body_array));
	return;
}

function delete_member($username, $calendar_id) {
	// get required objects and variables
	$app = \Slim\Slim::getInstance();
	$request = $app->request;
	$request_body_array = json_decode($request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);
	$token = $request->params('token');

	// attempt all DB operations, returning on error
	$unknown_error = 39;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL delete_member(?, ?, ?, @error_18, @error_37, @error_40, @error_21)')) |
		(!$stmt->bind_param('ssi', $token, $username, $calendar_id)) |
		(!$stmt->execute()) |
		(!$stmt->close()) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$result_37 = $conn->query('SELECT @error_37')) |
		(!$result_40 = $conn->query('SELECT @error_40')) |
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
	$result_18->close();
	if ($result_37->fetch_assoc()['@error_37']) {
		$response_body_array['errors'][] = 37;
	}
	$result_37->close();
	if ($result_40->fetch_assoc()['@error_40']) {
		$response_body_array['errors'][] = 40;
	}
	$result_40->close();
	if ($result_21->fetch_assoc()['@error_21']) {
		$response_body_array['errors'][] = 21;
	}
	$result_21->close();

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
		get_calendars_roles();
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
				$app->get('', function($calendar_id, $username) {
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
			$app->get('', function($calendar_id) {
			});
			$app->post('', function($calendar_id) {
			});
			$app->group('/:event_id', function () use ($app) {
				$app->get('', function($calendar_id, $event_id) {
				});
				$app->put('', function($calendar_id, $event_id) {
				});
				$app->delete('', function($calendar_id, $event_id) {
				});
			});
		});
	});
});

$app->run();
?>