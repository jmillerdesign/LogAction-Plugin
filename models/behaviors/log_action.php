<?php
/**
 * LogAction Behavior
 *
 * Will monitor specified fields for changes and log them to the database
 * REQUIRES model log_action.php
 *
 * @author Justin Miller
 * @version 1
 */
class LogActionBehavior extends ModelBehavior {

/**
 * Changes that have been made to the data
 *
 * @var array
 * @access protected
 */
	var $_changes = array();

/**
 * Initiate behavior for the model using specified settings.
 *
 * Available settings:
 *
 * - authSession: (string) The name of the Auth session key. DEFAULTS TO: 'Auth'
 * - userModel: (string) The name of the User model. DEFAULTS TO: 'User'
 * - row: (integer) The row id currently being modified. DEFAULTS TO: 0
 * - fields: (array) List of fields to monitor. DEFAULTS TO: none
 *
 * @param object $Model Model using the behavior
 * @param array $settings Settings to override for model.
 * @access public
 */
	function setup(&$Model, $settings = array()) {
		if (!isset($this->settings[$Model->alias])) {
			$this->settings[$Model->alias] = array(
				'authSession' => 'Auth',
				'userModel' => 'User',
				'row' => 0,
				'fields' => array()
			);
		}
		$this->settings[$Model->alias] = array_merge($this->settings[$Model->alias], $settings);

		$this->_changes[$Model->alias] = array(
			'before' => array(),
			'after' => array(),
			'hasChanges' => false
		);
	}

/**
 * Before save method. Called before all saves
 *
 * Monitors data that is about to be saved. Will read existing data from the database,
 * and if changes have occurred, will set them in the 'changes' key
 *
 * @param AppModel $Model Model instance
 * @return boolean true to continue, false to abort the save
 * @access public
 */
	function beforeSave(&$Model) {
		$this->settings[$Model->alias]['row'] = $Model->id;
		pr($Model->data);

		if (!$this->settings[$Model->alias]['fields']) {
			// No fields to monitor
			return true;
		}

		// Check if any fields we are monitoring are in $this->data
		foreach ($this->settings[$Model->alias]['fields'] as $field) {
			if (in_array($field, array_keys($Model->data[$Model->alias]))) {
				$this->_changes[$Model->alias]['after'][$field] = $Model->data[$Model->alias][$field];
			}
		}

		if (!$this->_changes[$Model->alias]['after']) {
			// No monitored fields are being updated
			return true;
		}

		// Read the existing data before updating
		$oldData = $Model->find('first', array(
			'conditions' => array(
				'id' => $Model->id
			),
			'fields' => array_keys($this->_changes[$Model->alias]['after'])
		));

		if ($oldData) {
			$this->_changes[$Model->alias]['before'] = $oldData[$Model->alias];
		}

		foreach ($this->_changes[$Model->alias]['after'] as $field => $newValue) {
			$oldValue = isset($this->_changes[$Model->alias]['before'][$field]) ? $this->_changes[$Model->alias]['before'][$field] : false;
			if ($newValue == $oldValue) {
				// Values have not changed
				unset($this->_changes[$Model->alias]['before'][$field]);
				unset($this->_changes[$Model->alias]['after'][$field]);
			}
		}

		$this->_changes[$Model->alias]['hasChanges'] = (bool) $this->_changes[$Model->alias]['before'];

		return true;
	}

/**
 * After save method. Called after all saves
 *
 * Overriden to transparently manage setting the lft and rght fields if and only if the parent field is included in the
 * parameters to be saved.
 *
 * @param AppModel $Model Model instance.
 * @param boolean $created indicates whether the node just saved was created or updated
 * @return boolean true on success, false on failure
 * @access public
 */
	function afterSave(&$Model, $created) {
		if ($this->_changes[$Model->alias]['hasChanges']) {
			$logAction = ClassRegistry::init('LogAction');

			foreach ($this->_changes[$Model->alias]['before'] as $field => $oldValue) {
				$newValue = $this->_changes[$Model->alias]['after'][$field];

				// Insert record of this change
				$data = array(
					'user_id' => $this->getUserId($Model),
					'row' => $this->settings[$Model->alias]['row'],
					'model' => $Model->alias,
					'field' => $field,
					'before' => $oldValue,
					'after' => $newValue
				);
				if (!$logAction->insert($data)) {
					return false;
				}
			}
		}

		return true;
	}

/**
 * Extract the User.id of the currently logged in user from their Session
 *
 * @param AppModel $Model Model instance
 * @return integer id of current User
 * @access private
 */
	private function getUserId(&$Model) {
		$AuthSession = $this->settings[$Model->alias]['authSession'];
		$UserSession = $this->settings[$Model->alias]['userModel'];
		return Set::extract($_SESSION, $AuthSession . '.' . $UserSession . '.' . 'id');
	}

}