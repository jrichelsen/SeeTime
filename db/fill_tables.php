<?php

/*
TODO:
close statements?

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
*/

// connect to database
$conn = new mysqli(
	'localhost',
	'ozidar',
	'jon',
	'ozidar'
);

// create users
$users = array(
	'bbourdan' => 'bpwd12345678',
	'eonattu' => 'epwd12345678',
	'jrichels' => 'jpwd12345678',
	'ozidar' => 'opwd12345678'
);
foreach ($users as $username => $pwd) {
	if ((!$stmt = $conn->prepare('CALL create_user(?, ?, @error_7, @error_8, @error_9)')) |
		(!$stmt->bind_param('ss', $username, $pwd)) |
		(!$stmt->execute())
	) {
		die("error inserting user $username:\n($stmt->errno) $stmt->error\n");
	}
}

// create calendars
$calendars = array(
	'bbourdan' => 'bcal',
	'eonattu' => 'ecal',
	'jrichels' => 'jcal',
	'ozidar' => 'ocal'
);
$calendar_ids = array();
foreach ($calendars as $admin => $calendar_name) {
	// create calendar
	if ((!$stmt = $conn->prepare('CALL create_calendar(?, ?, @error_20, @calendar_id)')) |
		(!$stmt->bind_param('ss', $calendar_name, $admin)) |
		(!$stmt->execute())
	) {
		die("error inserting calendar '$calendar_name' with admin $admin:\n($stmt->errno) $stmt->error\n");
	}

	// extract calendar ID for later use
	if ((!$result_calendar_id = $conn->query('SELECT @calendar_id')) |
		(!$calendar_ids[$calendar_name] = $result_calendar_id->fetch_assoc()['@calendar_id'])
	) {
		die("error fetching calendar_id of calendar '$calendar_name':\n($stmt->errno) $stmt->error\n");
	}
}

// add other admins to calendars
$other_admins = array(
	'bcal' => array(),
	'ecal' => array('jrichels', 'ozidar'),
	'jcal' => array(),
	'ocal' => array('bbourdan')
);
foreach ($other_admins as $calendar_name => $admins) {
	foreach ($admins as $admin) {
		if ((!$stmt = $conn->prepare('CALL add_admin(?, ?)')) |
			(!$stmt->bind_param('si', $admin, $calendar_ids[$calendar_name])) |
			(!$stmt->execute())
		) {
			die("error adding user $admin as admin of calendar '$calendar_name':\n($stmt->errno) $stmt->error\n");
		}
	}
}

// add viewers to calendars
$other_viewers = array(
	'bcal' => array('jrichels', 'ozidar'),
	'ecal' => array(),
	'jcal' => array('bbourdan', 'eonattu'),
	'ocal' => array('jrichels')
);
foreach ($other_viewers as $calendar_name => $viewers) {
	foreach ($viewers as $viewer) {
		if ((!$stmt = $conn->prepare('CALL add_viewer(?, ?)')) |
			(!$stmt->bind_param('si', $viewer, $calendar_ids[$calendar_name])) |
			(!$stmt->execute())
		) {
			die("error adding user $viewer as viewer of calendar '$calendar_name':\n($stmt->errno) $stmt->error\n");
		}
	}
}

// create events
$events = array(
	'bcal' => array(
		array('flare nostrils', '2015-12-15 12:00:00', 60, null, 'low', '1w', '30m'),
		array('be annoying', '2015-12-15 12:00:00', 60, null, 'low', '1w', '30m')
	),
	'ecal' => array(
		array('office hours 1', '2015-11-02 16:00:00', 180, 'TA office hours for Networks', 'high', '1w', '15m'),
		array('office hours 2', '2015-11-03 15:00:00', 180, 'TA office hours for Networks', 'high', '1w', '15m'),
		array('algorithms HW', '2015-11-03 19:00:00', 240, 'work on HW', 'high', null, '15m'),
		array('databases proj hours', '2015-11-02 19:00:00', 180, 'work on Databases project', 'high', null, '15m')
	),
	'jcal' => array(
		array('software engineering', '2015-11-07 09:25:00', 50, null, 'medium', '2d', '30m'),
		array('beauty sleep', '2015-11-07 23:00:00', 360, 'maintain beauty', 'high', '1w', '30m'),
		array('meeting with Tim', '2015-11-04 16:00:00', 30, 'event details', 'high', null, '30m'),
	),
	'ocal' => array(
		array('office hours', '2015-11-03 15:30:00', 120, 'host office hours for Computer Graphics', 'high', '1w', '15m'),
		array('brush teeth', '2015-11-03 07:00:00', 3, 'scrub those pearly white teeth', 'high', '1d', '5m'),
		array('listen to Edwin ramble', '2015-11-03 12:50:00', 50, 'usually not important information', 'low', '1d', '30m'),
		array('watch football', '2015-11-02 12:00:00', 240, 'fantasy is IMPORTANT', 'low', '1w', '1H')
	),
);
foreach ($events as $calendar_name => $event_tuples) {
	foreach ($event_tuples as $event) {
		if ((!$stmt = $conn->prepare("CALL create_event(?, ?, ?, ?, ?, ?, ?, ?, @event_id)")) |
			(!$stmt->bind_param('ississss', $calendar_ids[$calendar_name], $event[0], $event[1], $event[2], $event[3], $event[4], $event[5], $event[6])) |
			(!$stmt->execute())
		) {
			die("error inserting '$event[0]' into calendar '$calendar_name':\n($stmt->errno) $stmt->error\n");
		}
	}
}

$conn->close();

?>