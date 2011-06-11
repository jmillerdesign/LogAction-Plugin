LogAction Behavior
Version 1
by Justin Miller

This will monitor specified fields in your database for changes. When changes occur, it will log the before and after values, as well as the user_id that made the change.


INSTALLATION:

1. Run the .sql script to create the database table log_actions

2. Add the model log_action.php to /app/models/

3. Add the behavior log_action.php to /app/models/behaviors/

4. Add the behavior to the model(s) you want to monitor, and specify the fields to monitor

	var $actsAs = array(
		'LogAction' => array(
			'fields' => array('field1', 'field2')
		)
	);