<?php
/*
See Time API v1.0
Current Bugs:
* /users/:username DELETE can delete user that are sole admins of calendars
* /calendars GET does not correctly return error 20 (errors out with nonexistent username)
* /calendars/:calendar_id GET does not correctly return error 27 (errors out with nonexistent calendar_id)

TODO:
* investigate closing prepared statements
* put database connection into middleware
*/

require 'vendor/autoload.php';

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
);

//TODO: replace with database table
$error_messages = array(
	1 => 'message not valid JSON object',
	2 => '\'username\' field does not exist in message',
	3 => '\'pwd\' field does not exist in message',
	4 => '\'username\' field not of type string',
	5 => '\'pwd\' field not of type string',
	6 => 'JSON object contains extraneous keys',
	7 => 'unknown error creating new user',
	8 => 'username does not meet restrictions ^[[:alpha:]][[:alnum:]]{0,14}$',
	9 => 'password does not meet restrictions ^[[:alnum:]]{8,31}$',
	10 => 'username already exists',
	11 => 'unknown error changing password',
	12 => '\'token\' URL variable does not exist',
	13 => 'invalid authentication token',
	14 => '\'old_pwd\' field does not exist in message',
	15 => '\'new_pwd\' field does not exist in message',
	16 => '\'old_pwd\' field not of type string',
	17 => '\'new_pwd\' field not of type string',
	18 => 'new password does not meet restrictions ^[[:alnum:]]{8,31}$',
	19 => 'invalid permissions',
	20 => 'invalid username-pwd combination',
	21 => 'unknown error deleting user',
	22 => 'cannot remove sole admin of calendar'
);

// different required and possible JSON fields for HTTP methods on routes
$route_method_fields = array(
	'/users' => array(
		'POST' => array(
			'required_fields' => array('username', 'pwd'),
			'possible_fields' => array('username', 'pwd')
		)
	),
	'/users/:username' => array(
		'PUT' => array(
			'required_fields' => array('old_pwd', 'new_pwd'),
			'possible_fields' => array('old_pwd', 'new_pwd')
		),
		'DELETE' => array(
			'required_fields' => array(),
			'possible_fields' => array()
		)
	),
	'/calendars' => array(
		'GET' => array(
			'required_fields' => array('username'),
			'possible_fields' => array('username')
		),
		'POST' => array(
			'required_fields' => array('calendar_name', 'username'),
			'possible_fields' => array('calendar_name', 'username')
		)
	),
	'/calendars/:calendar_id' => array(
		'GET' => array(
			'required_fields' => array(),
			'possible_fields' => array()
		)
	)
);

// converts response body PHP array with errors array containing only numbers to response body JSON with numbers and messages
//TODO: replace with database table
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

// checks if request has valid authentication token, immediately returns error response if not
$check_token_valid = function() {
	$app = \Slim\Slim::getInstance();
	$request = $app->request;
	$response_body_array = array(
		'errors' => array()
	);

	// check that token URL variable exists
	$no_token_error = 12;
	if (!$token = $request->params('token')) {
		$response_body_array['errors'][] = $no_token_error;
		$app->halt(400, prepare_response_body($response_body_array));
		return;
	}

	// check that token exists
	$invalid_token_error = 13;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('SELECT token_exists(?) AS function_return')) |
		(!$stmt->bind_param('s', $token)) |
		(!$stmt->execute()) |
		(!$stmt->get_result()->fetch_assoc()['function_return']) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $invalid_token_error;
		$app->halt(200, prepare_response_body($response_body_array));
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
	$extra_keys_error = 6;

	// attempt to decode JSON
	if (!$request_body_array = json_decode($request->getBody(), true)) {
		$response_body_array['errors'][] = $not_json_error;
		$app->halt(400, prepare_response_body($response_body_array));
		return;
	}

	$pattern = $route->getPattern();
	$method = $request->getMethod();

	global $route_method_fields;
	$required_fields = $route_method_fields[$pattern][$method]['required_fields'];
	$possible_fields = $route_method_fields[$pattern][$method]['possible_fields'];

	// check that required fields are present
	global $field_errors;
	foreach ($required_fields as $required_field) {
		if (!array_key_exists($required_field, $request_body_array)) {
			$response_body_array['errors'][] = $field_errors[$required_field]['exist_error'];
		} else if (gettype($request_body_array[$required_field]) != $field_errors[$required_field]['type']) {
			$response_body_array['errors'][] = $field_errors[$required_field]['type_error'];
		}
	}

	// check if extraneous fields are present
	$fields = array_keys($request_body_array);
	if (array_intersect($fields, $possible_fields) != $fields) {
		$response_body_array['errors'][] = $extra_keys_error;
	}

	// if errors are present, halt route handling and return
	if (!empty($response_body_array['errors'])) {
		$app->halt(400, prepare_response_body($response_body_array));
		return;
	}
};

function create_user() {
	$app = \Slim\Slim::getInstance();
	$request_body_array = json_decode($app->request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);
	$username = $request_body_array['username'];
	$pwd = $request_body_array['pwd'];

	$unknown_error = 7;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL create_user(?, ?, @error_8, @error_9, @error_10)')) |
		(!$stmt->bind_param('ss', $username, $pwd)) |
		(!$stmt->execute()) |
		(!$result_8 = $conn->query('SELECT @error_8')) |
		(!$result_9 = $conn->query('SELECT @error_9')) |
		(!$result_10 = $conn->query('SELECT @error_10')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	if ($result_8->fetch_assoc()['@error_8']) {
		$response_body_array['errors'][] = 8;
	}
	if ($result_9->fetch_assoc()['@error_9']) {
		$response_body_array['errors'][] = 9;
	}
	if ($result_10->fetch_assoc()['@error_10']) {
		$response_body_array['errors'][] = 10;
	}

	echo(prepare_response_body($response_body_array));
	return;
}

function change_pwd($username) {
	$app = \Slim\Slim::getInstance();
	$request = $app->request;
	$request_body_array = json_decode($request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);
	$token = $request->params('token');
	$old_pwd = $request_body_array['old_pwd'];
	$new_pwd = $request_body_array['new_pwd'];

	$unknown_error = 11;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL change_pwd(?, ?, ?, ?, @error_18, @error_19, @error_20)')) |
		(!$stmt->bind_param('ssss', $token, $username, $old_pwd, $new_pwd)) |
		(!$stmt->execute()) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$result_19 = $conn->query('SELECT @error_19')) |
		(!$result_20 = $conn->query('SELECT @error_20')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo($stmt->error);
		return;
	}

	if ($result_18->fetch_assoc()['@error_18']) {
		$response_body_array['errors'][] = 18;
	}
	if ($result_19->fetch_assoc()['@error_19']) {
		$response_body_array['errors'][] = 19;
	}
	if ($result_20->fetch_assoc()['@error_20']) {
		$response_body_array['errors'][] = 20;
	}

	echo(prepare_response_body($response_body_array));
	return;
}

function delete_user($username) {
	$app = \Slim\Slim::getInstance();
	$request = $app->request;
	$request_body_array = json_decode($request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);
	$token = $request->params('token');

	$unknown_error = 21;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL delete_user(?, ?, @error_19, @error_22)')) |
		(!$stmt->bind_param('ss', $token, $username)) |
		(!$stmt->execute()) |
		(!$result_19 = $conn->query('SELECT @error_19')) |
		(!$result_22 = $conn->query('SELECT @error_22')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	if ($result_19->fetch_assoc()['@error_19']) {
		$response_body_array['errors'][] = 19;
	}
	if ($result_22->fetch_assoc()['@error_22']) {
		$response_body_array['errors'][] = 22;
	}

	echo(prepare_response_body($response_body_array));
	return;
}

function get_calendars_roles() {
	$app = \Slim\Slim::getInstance();
	$request_body_array = json_decode($app->request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);

	$unknown_error = 23;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL get_calendars_roles(?, @error_20)')) |
		(!$stmt->bind_param('s', $request_body_array['username'])) |
		(!$stmt->execute()) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	$response_body_array['calendars'] = array();
	$stmt->bind_result($calendar_id, $role);
	while ($stmt->fetch()) {
		$response_body_array['calendars'][] = array(
			'calendar_id' => $calendar_id,
			'role' => $role
		);
	}

	echo(prepare_response_body($response_body_array));
	return;
}

function create_calendar() {
	$app = \Slim\Slim::getInstance();
	$request_body_array = json_decode($app->request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);

	$unknown_error = 24;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL create_calendar(?, ?, @error_20, @calendar_id)')) |
		(!$stmt->bind_param('ss', $request_body_array['calendar_name'], $request_body_array['username'])) |
		(!$stmt->execute()) |
		(!$result_20 = $conn->query('SELECT @error_20')) |
		(!$result_calendar_id = $conn->query('SELECT @calendar_id')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	if ($result_20->fetch_assoc()['@error_20']) {
		$response_body_array['errors'][] = 20;
		echo(prepare_response_body($response_body_array));
		return;
	}

	$response_body_array['calendar_id'] = $result_calendar_id->fetch_assoc()['@calendar_id'];
	echo(prepare_response_body($response_body_array));
	return;
}

function get_calendar($calendar_id) {
	$app = \Slim\Slim::getInstance();
	$request_body_array = json_decode($app->request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);

	$unknown_error = 27;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL get_calendar(?, @error_26)')) |
		(!$stmt->bind_param('i', $request_body_array['calendar_id'])) |
		(!$stmt->execute()) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}
	
	$response_body_array['admins'] = array();
	$response_body_array['viewers'] = array();
	$stmt->bind_result($calendar_name, $ts_modified, $username, $role);
	while ($stmt->fetch()) {
		$response_body_array['calendar_name'] = $calendar_name;
		if ($role == 'admin') {
			$response_body_array['admins'][] = $username;
		} else {
			$response_body_array['viewers'][] = $username;
		}
		$response_body_array['ts_modified'] = $ts_modified;
	}

	$response_body_array['calendar_id'] = $result_calendar_id->fetch_assoc()['@calendar_id'];
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
		global $check_token_valid;
		global $decode_body;
		$app->put('', $check_token_valid, $decode_body, function($username) {
			change_pwd($username);
		});
		$app->delete('', $check_token_valid, function($username) {
			delete_user($username);
		});
	});
});

$app->group('/calendars', function () use ($app) {
	global $decode_body;
	$app->get('', $decode_body, function() {
		get_calendars_roles();
	});
	$app->post('', $decode_body, function() {
		create_calendar();
	});
	$app->group('/:calendar_id', function () use ($app) {
		$app->get('', function($calendar_id) {
			get_calendar($calendar_id);
		});
		$app->put('', function($calendar_id) {
		});
		$app->delete('', function($calendar_id) {
		});
		$app->group('/members', function () use ($app) {
			$app->get('', function($calendar_id) {
			});
			$app->post('', function($calendar_id) {
			});
			$app->group('/:username', function () use ($app) {
				$app->get('', function($calendar_id, $username) {
				});
				$app->put('', function($calendar_id, $username) {
				});
				$app->delete('', function($calendar_id, $username) {
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