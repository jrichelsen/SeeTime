USE ozidar;

CREATE TABLE persons (
	username VARCHAR(255) PRIMARY KEY,
	pwd_hash CHAR(32),
	salt CHAR(36)
);

CREATE TABLE calendars (
	calendar_id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
	calendar_name VARCHAR(127)
);

CREATE TABLE members (
	username VARCHAR(255) NOT NULL,
	calendar_id INT UNSIGNED NOT NULL,
	role ENUM('admin', 'viewer') NOT NULL,
	PRIMARY KEY(username, calendar_id), -- each user can only have one role per calendar
	FOREIGN KEY(username) REFERENCES persons(username),
	FOREIGN KEY(calendar_id) REFERENCES calendars(calendar_id)
);

CREATE TABLE events (
	event_id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
	calendar_id INT UNSIGNED NOT NULL,
	event_title VARCHAR(63) NOT NULL,
	start_date DATETIME NOT NULL,
	duration INT,
	details VARCHAR(510),
	priority ENUM('low', 'medium', 'high'),
	repetition ENUM('day', 'week', 'month', 'year'),
	alert BOOL,
	FOREIGN KEY(calendar_id) REFERENCES calendars(calendar_id)
);

DELIMITER $$

CREATE TRIGGER ensure_admin_update BEFORE UPDATE ON members
FOR EACH ROW BEGIN
	IF (OLD.role = 'admin') AND (NEW.role <> 'admin') AND ('admin' NOT IN (SELECT role FROM members WHERE calendar_id = OLD.calendar_id AND username <> OLD.username))
	THEN
		SIGNAL
			SQLSTATE '45000'
			SET MESSAGE_TEXT = 'cannot remove sole admin from calendar';
	END IF;
END$$

CREATE TRIGGER ensure_admin_delete BEFORE DELETE ON members
FOR EACH ROW BEGIN
	IF (OLD.role = 'admin') AND ('admin' NOT IN (SELECT role FROM members WHERE calendar_id = OLD.calendar_id AND username <> OLD.username))
	THEN
		SIGNAL
			SQLSTATE '45000'
			SET MESSAGE_TEXT = 'cannot remove sole admin from calendar';
	END IF;
END$$

CREATE PROCEDURE create_person (
	IN in_username VARCHAR(255),
	IN in_pwd VARCHAR(31)
)
BEGIN
	SET @my_salt = UUID();
	INSERT INTO persons
	VALUES (
		in_username,
		MD5(CONCAT(in_pwd, @my_salt)),
		@my_salt
	);
END$$

CREATE PROCEDURE create_calendar (
	IN in_username VARCHAR(255),
	IN in_calendar_name VARCHAR(127),
	OUT out_calendar_id INT UNSIGNED
) 
BEGIN 
	INSERT INTO calendars (calendar_name)
	VALUES (in_calendar_name);

	SET out_calendar_id = LAST_INSERT_ID();

	INSERT INTO members
	VALUES (
		in_username,
		out_calendar_id,
		'admin'
	);
END$$

CREATE PROCEDURE create_event (
	IN in_calendar_id INT UNSIGNED,
	IN in_event_title VARCHAR(63),
	IN in_start_date DATETIME,
	IN in_duration INT,
	IN in_details VARCHAR(510),
	IN in_priority ENUM('low', 'medium', 'high'),
	IN in_repetition ENUM('day', 'week', 'month', 'year'),
	IN in_alert BOOL,
	OUT out_event_id INT UNSIGNED
) 
BEGIN 
	INSERT INTO events (
		calendar_id,
		event_title,
		start_date,
		duration,
		details,
		priority,
		repetition,
		alert)
	VALUES (
		in_calendar_id,
		in_event_title,
		in_start_date,
		in_duration,
		in_details,
		in_priority,
		in_repetition,
		in_alert
	);

	SET out_event_id = LAST_INSERT_ID();
END$$

CREATE PROCEDURE add_admin (
	IN in_username VARCHAR(255),
	IN in_calendar_id INT UNSIGNED
) 
BEGIN 
	INSERT INTO members
	VALUES (
		in_username,
		in_calendar_id,
		'admin'
	);
END$$

CREATE PROCEDURE remove_admin (
	IN in_username VARCHAR(255),
	IN in_calendar_id INT UNSIGNED
) 
BEGIN 
	DELETE FROM members
	WHERE
		username = in_username AND
		calendar_id = in_calendar_id AND
		role = 'admin';
END$$

CREATE PROCEDURE add_viewer (
	IN in_username VARCHAR(255),
	IN in_calendar_id INT UNSIGNED
) 
BEGIN 
	INSERT INTO members
	VALUES (
		in_username,
		in_calendar_id,
		'viewer'
	);
END$$

CREATE PROCEDURE remove_viewer (
	IN in_username VARCHAR(255),
	IN in_calendar_id INT UNSIGNED
) 
BEGIN 
	DELETE FROM members
	WHERE
		username = in_username AND
		calendar_id = in_calendar_id AND
		role = 'viewer';
END$$

CREATE PROCEDURE upgrade_viewer_to_admin (
	IN in_username VARCHAR(255),
	IN in_calendar_id INT UNSIGNED
) 
BEGIN 
	UPDATE members
	SET role = 'admin'
	WHERE
		username = in_username AND
		calendar_id = in_calendar_id AND
		role = 'viewer';
END$$

CREATE PROCEDURE downgrade_admin_to_viewer (
	IN in_username VARCHAR(255),
	IN in_calendar_id INT UNSIGNED
) 
BEGIN 
	UPDATE members
	SET role = 'viewer'
	WHERE
		username = in_username AND
		calendar_id = in_calendar_id AND
		role = 'admin';
END$$

CREATE PROCEDURE get_viewable_calendars (
	IN in_username VARCHAR(255)
) 
BEGIN 
	SELECT calendar_id
	FROM members
	WHERE username = in_username;
END$$

CREATE PROCEDURE get_editable_calendars (
	IN in_username VARCHAR(255)
) 
BEGIN 
	SELECT calendar_id
	FROM members
	WHERE
		username = in_username AND
		role = 'admin';
END$$

CREATE PROCEDURE get_role (
	IN in_username VARCHAR(255),
	IN in_calendar_id INT UNSIGNED
) 
BEGIN 
	SELECT role
	FROM members
	WHERE
		username = in_username AND
		calendar_id = in_calendar_id;
END$$

CREATE PROCEDURE get_events (
	IN in_calendar_id INT UNSIGNED
) 
BEGIN 
	SELECT event_id
	FROM calendars
	WHERE calendar_id = in_calendar_id;
END$$

CREATE PROCEDURE describe_calendar (
	IN in_calendar_id INT UNSIGNED
) 
BEGIN 
	SELECT *
	FROM calendars
	WHERE calendar_id = in_calendar_id;
END$$

CREATE PROCEDURE describe_event (
	IN in_event_id INT UNSIGNED
) 
BEGIN 
	SELECT *
	FROM events
	WHERE event_id = in_event_id;
END$$

DELIMITER ;