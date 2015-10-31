USE ozidar;

CREATE TABLE person (
	username VARCHAR(255) PRIMARY KEY,
	pwd_hash BINARY(60)
);

CREATE TABLE calendar (
	calendar_id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
	name VARCHAR(127)
);

CREATE TABLE member (
	username VARCHAR(255) NOT NULL,
	calendar_id INT UNSIGNED NOT NULL,
	role ENUM('admin', 'view') NOT NULL,
	PRIMARY KEY(username, calendar_id), -- each user can only have one role per calendar
	FOREIGN KEY(username) REFERENCES person(username),
	FOREIGN KEY(calendar_id) REFERENCES calendar(calendar_id)
);

CREATE TABLE event (
	event_id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
	calendar_id INT UNSIGNED NOT NULL,
	start_date DATETIME NOT NULL,
	duration INT,
	image VARCHAR(63),
	title VARCHAR(63),
	details VARCHAR(510),
	priority VARCHAR(7),
	repitition VARCHAR(7),
	alert BOOL,
	FOREIGN KEY(calendar_id) REFERENCES calendar(calendar_id)
);

DELIMITER $$

CREATE TRIGGER ensure_admin_update BEFORE UPDATE ON member
	FOR EACH ROW
		BEGIN
			IF (OLD.role = 'admin') AND (NEW.role <> 'admin') AND ('admin' NOT IN (SELECT role FROM member WHERE calendar_id = OLD.calendar_id AND username <> OLD.username))
			THEN
				SIGNAL
					SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'cannot remove sole admin from calendar';
			END IF;
		END$$

CREATE TRIGGER ensure_admin_delete BEFORE DELETE ON member
	FOR EACH ROW
		BEGIN
			IF (OLD.role = 'admin') AND ('admin' NOT IN (SELECT role FROM member WHERE calendar_id = OLD.calendar_id AND username <> OLD.username))
			THEN
				SIGNAL
					SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'cannot remove sole admin from calendar';
			END IF;
		END$$

DELIMITER ;