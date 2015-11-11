<?php
/*
See Time API v1.0
Current Bugs:
* /users/:username DELETE can delete user that are sole admins of calendars
* /calendars/:username GET does not correctly return error 20 (errors out with nonexistent username)

TODO:
* investigate closing statements
* put database connection into middleware
*/

require 'vendor/autoload.php';

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
		'exist_error' => 11,
		'type' => 'string',
		'type_error' => 13),
	'new_pwd' => array(
		'exist_error' => 12,
		'type' => 'string',
		'type_error' => 14),
	'calendar_name' => array(
		'exist_error' => 23,
		'type' => 'string',
		'type_error' => 24)
);

$error_messages = array(
	1 => 'message not valid JSON object',
	2 => '\'username\' field does not exist in message',
	3 => '\'pwd\' field does not exist in message',
	4 => '\'username\' field not of type string',
	5 => '\'pwd\' field not of type string',
	6 => 'JSON object contains extraneous keys',
	7 => 'unknown error creating new user',
	8 => 'username does not meet restrictions ^[[:alnum:]]{1,15}$',
	9 => 'password does not meet restrictions ^[[:alnum:]]{8,31}$',
	10 => 'username already exists',
	11 => '\'old_pwd\' field does not exist in message',
	12 => '\'new_pwd\' field does not exist in message',
	13 => '\'old_pwd\' field not of type string',
	14 => '\'new_pwd\' field not of type string',
	15 => 'unknown error changing password',
	16 => 'old password does not meet restrictions ^[[:alnum:]]{8,15}$',
	17 => 'new password does not meet restrictions ^[[:alnum:]]{8,15}$',
	18 => 'invalid username-pwd combination',
	19 => 'unknown error deleting user',
	20 => 'user does not exist',
	21 => 'cannot remove sole admin of calendar',
	22 => 'unknown error getting list of calendars',
	23 => '\'calendar_name\' field does not exist in message',
	24 => '\'calendar_name\' field not of type string',
	25 => 'unknown error creating new calendar'
);

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
	'/calendars/:username' => array(
		'GET' => array(
			'required_fields' => array(),
			'possible_fields' => array()
		),
		'POST' => array(
			'required_fields' => array('calendar_name'),
			'possible_fields' => array('calendar_name')
		)
	)
);

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

$decode_body = function (\Slim\Route $route) {
	$app = \Slim\Slim::getInstance();
	$request = $app->request;
	$response_body_array = array(
		'errors' => array()
	);

	// attempt to decode JSON
	if (!$request_body_array = json_decode($request->getBody(), true)) {
		$response_body_array['errors'][] = 1;
		$app->halt(400, prepare_response_body($response_body_array)); 
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
		} else if (strcmp(gettype($request_body_array[$required_field]), $field_errors[$required_field]['type'])) {
			$response_body_array['errors'][] = $field_errors[$required_field]['type_error'];
		}
	}

	// check if extraneous fields are present
	if (array_keys($request_body_array) != $possible_fields) {
		$response_body_array['errors'][] = 6;
	}

	// if errors are present, halt route handling and return
	if (!empty($response_body_array['errors'])) {
		$app->halt(400, prepare_response_body($response_body_array)); 
	}
};

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

function create_user() {
	$app = \Slim\Slim::getInstance();
	$request_body_array = json_decode($app->request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);

	$unknown_error = 7;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL create_user(?, ?, @error_8, @error_9, @error_10)')) |
		(!$stmt->bind_param('ss', $request_body_array['username'], $request_body_array['pwd'])) |
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
	$request_body_array = json_decode($app->request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);

	$unknown_error = 15;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL change_pwd(?, ?, ?, @error_8, @error_16, @error_17, @error_18)')) |
		(!$stmt->bind_param('sss', $username, $request_body_array['old_pwd'], $request_body_array['new_pwd'])) |
		(!$stmt->execute()) |
		(!$result_8 = $conn->query('SELECT @error_8')) |
		(!$result_16 = $conn->query('SELECT @error_16')) |
		(!$result_17 = $conn->query('SELECT @error_17')) |
		(!$result_18 = $conn->query('SELECT @error_18')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	if ($result_8->fetch_assoc()['@error_8']) {
		$response_body_array['errors'][] = 8;
	}
	if ($result_16->fetch_assoc()['@error_16']) {
		$response_body_array['errors'][] = 16;
	}
	if ($result_17->fetch_assoc()['@error_17']) {
		$response_body_array['errors'][] = 17;
	}
	if ($result_18->fetch_assoc()['@error_18']) {
		$response_body_array['errors'][] = 18;
	}

	echo(prepare_response_body($response_body_array));
	return;
}

function delete_user($username) {
	$app = \Slim\Slim::getInstance();
	$request_body_array = json_decode($app->request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);

	$unknown_error = 19;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL delete_user(?, @error_20, @error_21)')) |
		(!$stmt->bind_param('s', $username)) |
		(!$stmt->execute()) |
		(!$result_20 = $conn->query('SELECT @error_20')) |
		(!$result_21 = $conn->query('SELECT @error_21')) |
		(!$conn->close())
	) {
		$response_body_array['errors'][] = $unknown_error;
		echo(prepare_response_body($response_body_array));
		return;
	}

	if ($result_20->fetch_assoc()['@error_20']) {
		$response_body_array['errors'][] = 20;
	}
	if ($result_21->fetch_assoc()['@error_21']) {
		$response_body_array['errors'][] = 21;
	}

	echo(prepare_response_body($response_body_array));
	return;
}

function get_calendars_roles($username) {
	$app = \Slim\Slim::getInstance();
	$request_body_array = json_decode($app->request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);

	$unknown_error = 22;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL get_calendars_roles(?, @error_20)')) |
		(!$stmt->bind_param('s', $username)) |
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

function create_calendar($username) {
	$app = \Slim\Slim::getInstance();
	$request_body_array = json_decode($app->request->getBody(), true);
	$response_body_array = array(
		'errors' => array()
	);

	$unknown_error = 25;
	if ((!$conn = get_db_conn()) |
		(!$stmt = $conn->prepare('CALL create_calendar(?, ?, @error_20, @calendar_id)')) |
		(!$stmt->bind_param('ss', $request_body_array['calendar_name'], $username)) |
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
		global $decode_body;
		$app->put('', $decode_body, function($username) {
			change_pwd($username);
		});
		$app->delete('', function($username) {
			delete_user($username);
		});
	});
});

$app->group('/calendars', function () use ($app) {
	$app->group('/:username', function () use ($app) {
		global $decode_body;
		$app->get('', function($username) {
			get_calendars_roles($username);
		});
		$app->post('', $decode_body, function($username) {
			create_calendar($username);
		});
	});
	$app->group('/:calendar_id', function () use ($app) {
		$app->get('', function($calendar_id) {
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