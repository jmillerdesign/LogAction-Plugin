<?php
/**
 * LogAction Behavior
 *
 * Will monitor specified fields for changes and log them to the database
 * REQUIRES model log_action.php
 *
 * @author Justin Miller
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
 * - fields: (array) List of fields to monitor. DEFAULTS TO: none,
 * - trackDelete: (boolean) True if row deletions should be monitored. DEFAULTS TO: true
 *
 * @param object $Model Model using the behavior
 * @param array $settings Settings to override for model.
 * @access public
 */
	function setup(&$Model, $settings = array()) {
		if (!isset($this->settings[$Model->alias])) {
			// Set default settings to class variable
			$this->settings[$Model->alias] = array(
				'authSession' => 'Auth',
				'userModel' => 'User',
				'fields' => array(),
				'trackDelete' => true
			);
		}

		// Merge default settings with custom settings
		$this->settings[$Model->alias] = array_merge($this->settings[$Model->alias], $settings);

		if ($this->settings[$Model->alias]['trackDelete']) {
			// Track deletions

			// Track SoftDeletable behavior
			// @link http://github.com/evilbloodydemon/cakephp-softdeletable2
			if (in_array('SoftDeletable', $Model->actsAs)) {
				// Make sure 'deleted' field is being monitored
				// beforeDelete & afterDelete are not called if using SoftDeletable
				$softDeletableField = (!empty($Model->actsAs['SoftDeletable']['field'])) ? $Model->actsAs['SoftDeletable']['field'] : 'deleted';
				if ($Model->hasField($softDeletableField) && !in_array($softDeletableField, $this->settings[$Model->alias]['fields'])) {
					$this->settings[$Model->alias]['fields'][] = $softDeletableField;
				}
			}
		}

		// Set blank default values for changed fields
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
		} else {
			// Creating a new entry
			// Make before match after, but with empty values
			foreach ($this->_changes[$Model->alias]['after'] as $key => $value) {
				$this->_changes[$Model->alias]['before'][$key] = '';
			}
		}

		// Check if any values have changed
		foreach ($this->_changes[$Model->alias]['after'] as $field => $newValue) {
			$oldValue = isset($this->_changes[$Model->alias]['before'][$field]) ? $this->_changes[$Model->alias]['before'][$field] : false;
			if ($newValue == $oldValue) {
				// Values have not changed. Remove them from the arrays.
				unset($this->_changes[$Model->alias]['before'][$field]);
				unset($this->_changes[$Model->alias]['after'][$field]);
			}
		}

		// Determine if any field has changed
		$this->_changes[$Model->alias]['hasChanges'] = (boolean) $this->_changes[$Model->alias]['before'];

		return true;
	}

/**
 * After save method. Called after all saves
 *
 * @param AppModel $Model Model instance.
 * @param boolean $created indicates whether the node just saved was created or updated
 * @return boolean true on success, false on failure
 * @access public
 */
	function afterSave(&$Model, $created) {
		if ($this->_changes[$Model->alias]['hasChanges']) {
			// Initial class for LogAction table
			$logAction = ClassRegistry::init('LogAction.LogAction');

			foreach ($this->_changes[$Model->alias]['before'] as $field => $oldValue) {
				$newValue = $this->_changes[$Model->alias]['after'][$field];

				// Insert record of this change
				$data = array(
					'user_id' => $this->getUserId($Model),
					'row' => $Model->id,
					'model' => $Model->alias,
					'field' => $field,
					'before' => $oldValue,
					'after' => $newValue
				);
				if (!$logAction->insert($data)) {
					// Failed to insert log record
					return false;
				}
			}
		}

		return true;
	}

	function beforeDelete(&$Model, $cascade = true) {
	}

	function afterDelete(&$Model) {
		if ($this->settings[$Model->alias]['trackDelete']) {
			// Insert record of this deletion
			$data = array(
				'user_id' => $this->getUserId($Model),
				'row' => $Model->id,
				'model' => $Model->alias,
				'field' => 'deleted',
				'before' => 0,
				'after' => 1
			);
			if (!ClassRegistry::init('LogAction.LogAction')->insert($data)) {
				// Failed to insert log record
				return false;
			}
		}

		return true;
	}

/**
 * Extract the User.id of the currently logged in user from their Session
 *
 * @param AppModel $Model Model instance
 * @return string id of current User
 * @access private
 */
	private function getUserId(&$Model) {
		if (empty($_SESSION)) {
			return 0;
		}
		$authSession = $this->settings[$Model->alias]['authSession'];
		$userSession = $this->settings[$Model->alias]['userModel'];
		$user = Set::extract($_SESSION, $authSession . '.' . $userSession);
		return (string) $user['id'];
	}

}
