<?php
/*
Calendar:	bcal			ecal					jcal					ocal
Admins:		bbourdan		eonattu					jrichels				ozidar
							jrichels										bbourdan
							ozidar
Viewers:	jrichels								bbourdan				jrichels
			ozidar									eonattu
Events:		flare nostrils	office hours 1			software engineering	office hours
			be annoying		office hours 2			beauty sleep			brush teeth
							algorithms HW			meeting with 			listen to Edwin ramble
							databases proj hours							watch football

TODO:
* check error outputs from procedures
*/

// connect to database
$conn = new mysqli(
	'localhost',
	'eonattu',
	'z',
	'eonattu'
);
$conn->query("SET SESSION sql_mode = 'strict_all_tables'");

// create users
$users = array(
	'bbourdan' => array('pwd' => 'bpwd12345678'),
	'eonattu' => array('pwd' => 'epwd12345678'),
	'jrichels' => array('pwd' => 'jpwd12345678'),
	'ozidar' => array('pwd' => 'opwd12345678')
);
foreach ($users as $username => $user_info) {
	if ((!$stmt = $conn->prepare('CALL create_user(?, ?, @error_8, @error_9, @error_10)')) |
		(!$stmt->bind_param('ss', $username, $user_info['pwd'])) |
		(!$stmt->execute())
	) {
		die("error inserting user $username:\n($stmt->errno) $stmt->error\n");
	}
}

// get authentication tokens
foreach ($users as $username => $user_info) {
	if ((!$stmt = $conn->prepare('CALL get_token(?, ?, @error_19, @token)')) |
		(!$stmt->bind_param('ss', $username, $user_info['pwd'])) |
		(!$stmt->execute())
	) {
		die("error creating token for user $username:\n($stmt->errno) $stmt->error\n");
	}

	// extract token for later use
	if ((!$result_token = $conn->query('SELECT @token')) |
		(!$users[$username]['token'] = $result_token->fetch_assoc()['@token'])
	) {
		die("error fetching token of user $username:\n($conn->errno) $conn->error\n");
	}
}

// create calendars
$calendars = array(
	'bcal' => array(
		'id' => '00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000001',
		'creator' => 'bbourdan',
		'other_admins' => array(),
		'viewers' => array('jrichels', 'ozidar')),
	'ecal' => array(
		'id' => '00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000002',
		'creator' => 'eonattu',
		'other_admins' => array('jrichels', 'ozidar'),
		'viewers' => array()),
	'jcal' => array(
		'id' => '00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000003',
		'creator' => 'jrichels',
		'other_admins' => array(),
		'viewers' => array('bbourdan', 'eonattu')),
	'ocal' => array(
		'id' => '00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000004',
		'creator' => 'ozidar',
		'other_admins' => array('bbourdan'),
		'viewers' => array('jrichels')),
);
foreach ($calendars as $calendar_name => $calendar_info) {
	// create calendar
	if ((!$stmt = $conn->prepare('CALL create_calendar(?, ?, ?, @error_28, @error_18, @error_29, @error_30, @calendar_id)')) |
		(!$stmt->bind_param('sss', $users[$calendar_info['creator']]['token'], $calendar_info['id'], $calendar_name)) |
		(!$stmt->execute())
	) {
		die("error inserting calendar '$calendar_name' with creator ${calendar_info['creator']}:\n($stmt->errno) $stmt->error\n");
	}
}

// add other admins to calendars
foreach ($calendars as $calendar_name => $calendar_info) {
	foreach ($calendar_info['other_admins'] as $admin) {
		if ((!$stmt = $conn->prepare("CALL add_member(?, ?, ?, 'admin', @error_28, @error_18, @error_39, @error_40)")) |
			(!$stmt->bind_param('sss', $users[$calendar_info['creator']]['token'], $admin, $calendar_info['id'])) |
			(!$stmt->execute())
		) {
			die("error adding user $admin as admin of calendar '$calendar_name':\n($stmt->errno) $stmt->error\n");
		}
	}
}

// add other viewers to calendars
foreach ($calendars as $calendar_name => $calendar_info) {
	foreach ($calendar_info['viewers'] as $viewer) {
		if ((!$stmt = $conn->prepare("CALL add_member(?, ?, ?, 'viewer', @error_28, @error_18, @error_39, @error_40)")) |
			(!$stmt->bind_param('sss', $users[$calendar_info['creator']]['token'], $viewer, $calendar_info['id'])) |
			(!$stmt->execute())
		) {
			die("error adding user $viewer as viewer of calendar '$calendar_name':\n($stmt->errno) $stmt->error\n");
		}
	}
}

// create events
$events = array(
	'bcal' => array(
		array('bbourdan','00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000011', 'flare nostrils', '2015-12-15 12:00:00', 60, null, 'low', '1w', '30m'),
		array('bbourdan', '00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000012', 'be annoying', '2015-12-15 12:00:00', 60, null, 'low', '1w', '30m')
	),
	'ecal' => array(
		array('eonattu', '00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000013', 'office hours 1', '2015-11-02 16:00:00', 180, 'TA office hours for Networks', 'high', '1w', '15m'),
		array('eonattu', '00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000014', 'office hours 2', '2015-11-03 15:00:00', 180, 'TA office hours for Networks', 'high', '1w', '15m'),
		array('eonattu', '00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000015', 'algorithms HW', '2015-11-03 19:00:00', 240, 'work on HW', 'high', null, '15m'),
		array('eonattu', '00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000016', 'databases proj hours', '2015-11-02 19:00:00', 180, 'work on Databases project', 'high', null, '15m')
	),
	'jcal' => array(
		array('jrichels', '00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000017', 'software engineering', '2015-11-07 09:25:00', 50, null, 'medium', '2d', '30m'),
		array('jrichels', '00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000018', 'beauty sleep', '2015-11-07 23:00:00', 360, 'maintain beauty', 'high', '1w', '30m'),
		array('jrichels', '00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000019', 'meeting with Tim', '2015-11-04 16:00:00', 30, 'event details', 'high', null, '30m'),
	),
	'ocal' => array(
		array('ozidar', '00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000020', 'office hours', '2015-11-03 15:30:00', 120, 'host office hours for Computer Graphics', 'high', '1w', '15m'),
		array('ozidar', '00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000021', 'brush teeth', '2015-11-03 07:00:00', 3, 'scrub those pearly white teeth', 'high', '1d', '5m'),
		array('ozidar', '00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000022', 'listen to Edwin ramble', '2015-11-03 12:50:00', 50, 'usually not important information', 'low', '1d', '30m'),
		array('ozidar', '00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000023', 'watch football', '2015-11-02 12:00:00', 240, 'fantasy is IMPORTANT', 'low', '1w', '1H')
	),
);
foreach ($events as $calendar_name => $event_tuples) {
	foreach ($event_tuples as $event) {
		if ((!$stmt = $conn->prepare("CALL create_event(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, @error_28, @error_18, @error_65, @error_66, @error_67, @error_68, @error_69, @error_70, @error_71, @error_72, @error_73, @event_id)")) |
			(!$stmt->bind_param('sssssissss', $users[$event[0]]['token'], $event[1], $calendars[$calendar_name]['id'], $event[2], $event[3], $event[4], $event[5], $event[6], $event[7], $event[8])) |
			(!$stmt->execute())
		) {
			die("error inserting event '$event[2]' into calendar '$calendar_name':\n($stmt->errno) $stmt->error\n");
		}
	}
}

$conn->close();
?>