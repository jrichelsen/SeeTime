<?php

// error checking for prepare statements?
// error checking for binding?

/*
Calendar:	bcal		ecal		jcal		ocal
Admins:		bbourdan	eonattu		jrichels	ozidar
						jrichels				bbourdan
						ozidar
Viewers:	jrichels				bbourdan	jrichels
			ozidar					eonattu		
Events:		event00		event04		event07		event08
			event01		event05					event09
			event02		event06					event10
			event03								event11
												event12
												event13
*/

// connect to database
$conn = new mysqli(
	'localhost',
	'ozidar',
	'jon',
	'ozidar');
if ($conn->connect_errno) {
    die("failed to connect to MySQL: ($conn->connect_errno) $conn->connect_error");
}
echo("connected successfully\n");
echo("\n");

// insert test users
$users = array(
	'bbourdan' => 'bpwd',
	'eonattu' => 'epwd',
	'jrichels' => 'jpwd',
	'ozidar' => 'opwd'
);
foreach ($users as $username => $pwd) {
	$stmt = $conn->prepare('CALL create_person(?, ?)');
	$stmt->bind_param('ss', $username, $pwd);
	if (!$stmt->execute()) {
		die("CALL to create_person failed: ($stmt->errno) $stmt->error");
	}
	echo("inserted user '" . $username . "'\n");
}
echo("\n");

// create test calendars
$calendars = array(
	'bbourdan' => 'bcal',
	'eonattu' => 'ecal',
	'jrichels' => 'jcal',
	'ozidar' => 'ocal'
);
$calendar_ids = array();
foreach ($calendars as $admin => $calendar_name) {
	// create calendar
	$stmt = $conn->prepare('CALL create_calendar(?, ?, @calendar_id)');
	$stmt->bind_param('ss', $admin, $calendar_name);
	if (!$conn->query("SET @calendar_id = NULL") | !$stmt->execute()) {
		die("CALL to create_calendar failed: ($stmt->errno) $stmt->error");
	}
	// extract calendar ID for later user
	if (!($res = $conn->query('SELECT @calendar_id'))) {
		die("fetch of calendar_id failed: ($conn->errno) $conn->error");
	}
	$calendar_ids[$calendar_name] = $res->fetch_assoc()['@calendar_id'];
	echo("inserted calendar '$calendar_name' with admin $admin and ID $calendar_ids[$calendar_name]\n");
}
echo("\n");

// add other admins to calendars
$other_admins = array(
	'bcal' => array(),
	'ecal' => array('jrichels', 'ozidar'),
	'jcal' => array(),
	'ocal' => array('bbourdan')
);
foreach ($other_admins as $calendar_name => $admins) {
	foreach ($admins as $admin) {
		$stmt = $conn->prepare('CALL add_admin(?, ?)');
		$stmt->bind_param('ss', $admin, $calendar_ids[$calendar_name]);
		if (!$stmt->execute()) {
			die("CALL to add_admin failed: ($stmt->errno) $stmt->error");
		}
		echo("added $admin as admin of $calendar_name\n");
	}
}
echo("\n");

// add viewers to calendars
$other_viewers = array(
	'bcal' => array('jrichels', 'ozidar'),
	'ecal' => array(),
	'jcal' => array('bbourdan', 'eonattu'),
	'ocal' => array('jrichels')
);
foreach ($other_viewers as $calendar_name => $viewers) {
	foreach ($viewers as $viewer) {
		$stmt = $conn->prepare('CALL add_viewer(?, ?)');
		$stmt->bind_param('ss', $viewer, $calendar_ids[$calendar_name]);
		if (!$stmt->execute()) {
			die("CALL to add_viewer failed: ($stmt->errno) $stmt->error");
		}
		echo("added $viewer as viewer of $calendar_name\n");
	}
}
echo("\n");

// add events to calendars
$events = array(
	'bcal' => array('event01', 'event02', 'event03'),
	'ecal' => array('event04', 'event05', 'event06'),
	'jcal' => array('event07'),
	'ocal' => array('event08', 'event09', 'event10', 'event11', 'event12', 'event13')
);
$event_ids = array();
foreach ($events as $calendar_name => $event_names) {
	foreach ($event_names as $event_name) {
		$stmt = $conn->prepare("CALL create_event(?, ?, '2015-12-25 12:34:56', NULL, NULL, NULL, NULL, NULL, @event_id)");
		$stmt->bind_param('ss', $calendar_ids[$calendar_name], $event_name);
		if (!$conn->query("SET @event_id = NULL") | !$stmt->execute()) {
			die("CALL to create_event failed: ($stmt->errno) $stmt->error");
		}
		// extract calendar ID for later user
		if (!($res = $conn->query('SELECT @event_id'))) {
			die("fetch of event_id failed: ($conn->errno) $conn->error");
		}
		$event_ids[$event_name] = $res->fetch_assoc()['@event_id'];
		echo("added $event_name to $calendar_name with ID $event_ids[$event_name]\n");
	}
}

mysqli_close($conn);

?>