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
* investigate closing prepared statements
* check error outputs from procedures
*/

// connect to database
$conn = new mysqli(
	'localhost',
	'ozidar',
	'jon',
	'ozidar'
);
$conn->query("SET SESSION sql_mode = 'strict_all_tables'");

// create users
$users = array(
	'bbourdan' => 'bpwd12345678',
	'eonattu' => 'epwd12345678',
	'jrichels' => 'jpwd12345678',
	'ozidar' => 'opwd12345678'
);
foreach ($users as $username => $pwd) {
	if ((!$stmt = $conn->prepare('CALL create_user(?, ?, @error_8, @error_9, @error_10)')) |
		(!$stmt->bind_param('ss', $username, $pwd)) |
		(!$stmt->execute()) |
		(!$stmt->close())
	) {
		die("error inserting user $username:\n($stmt->errno) $stmt->error\n");
	}
}

// create calendars
$calendars = array(
	'bcal' => array('00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000001', 'bbourdan'),
	'ecal' => array('00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000002', 'eonattu'),
	'jcal' => array('00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000003', 'jrichels'),
	'ocal' => array('00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000004', 'ozidar')
);
$calendar_ids = array();
foreach ($calendars as $calendar_name => $calendar_info) {
	// create calendar
	$calendar_id = $calendar_info[0];
	$admin = $calendar_info[1];
	if ((!$stmt = $conn->prepare('CALL create_calendar(?, ?, ?, @error_28, @error_18, @error_29, @error_30, @calendar_id)')) |
		(!$stmt->bind_param('sss', $admin, $calendar_id, $calendar_name)) |
		(!$stmt->execute()) |
		(!$stmt->close())
	) {
		die("error inserting calendar '$calendar_name' with admin $admin:\n($stmt->errno) $stmt->error\n");
	}

	// extract calendar ID for later use
	if ((!$result_calendar_id = $conn->query('SELECT @calendar_id')) |
		(!$calendar_ids[$calendar_name] = $result_calendar_id->fetch_assoc()['@calendar_id'])
	) {
		die("error fetching calendar_id of calendar '$calendar_name':\n($conn->errno) $conn->error\n");
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
	$original_admin = $calendars[$calendar_name][1];
	$calendar_id = $calendars[$calendar_name][0];
	foreach ($admins as $admin) {
		if ((!$stmt = $conn->prepare("CALL add_member(?, ?, ?, 'admin', @error_28, @error_18, @error_39, @error_40)")) |
			(!$stmt->bind_param('sss', $original_admin, $admin, $calendar_id)) |
			(!$stmt->execute()) |
			(!$stmt->close())
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
	$original_admin = $calendars[$calendar_name][1];
	$calendar_id = $calendars[$calendar_name][0];
	foreach ($viewers as $viewer) {
		if ((!$stmt = $conn->prepare("CALL add_member(?, ?, ?, 'viewer', @error_28, @error_18, @error_39, @error_40)")) |
			(!$stmt->bind_param('sss', $original_admin, $viewer, $calendar_id)) |
			(!$stmt->execute()) |
			(!$stmt->close())
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
			(!$stmt->bind_param('sssssissss', $event[0], $event[1], $calendar_ids[$calendar_name], $event[2], $event[3], $event[4], $event[5], $event[6], $event[7], $event[8])) |
			(!$stmt->execute()) |
			(!$stmt->close())
		) {
			die("error inserting event '$event[2]' into calendar '$calendar_name':\n($stmt->errno) $stmt->error\n");
		}
	}
}

$conn->close();
?>