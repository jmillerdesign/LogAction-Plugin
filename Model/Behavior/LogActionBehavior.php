<?php
App::uses('AuthComponent', 'Controller/Component');

/**
 * LogAction Behavior
 *
 * Will monitor specified fields for changes and log them to the database
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
	protected $_changes = array();

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
 * @param Model $model Model using the behavior
 * @param array $config Settings to override for model.
 * @access public
 */
	public function setup(Model $model, $config = array()) {
		if (!isset($this->settings[$model->alias])) {
			// Set default settings to class variable
			$this->settings[$model->alias] = array(
				'authSession' => 'Auth',
				'userModel'   => 'User',
				'fields'      => array(),
				'trackDelete' => true
			);
		}

		// Merge default settings with custom settings
		$this->settings[$model->alias] = array_merge($this->settings[$model->alias], $config);

		if ($this->settings[$model->alias]['trackDelete']) {
			// Track deletions

			// Track SoftDeletable behavior
			// @link http://github.com/evilbloodydemon/cakephp-softdeletable2
			if (in_array('SoftDeletable', $model->actsAs)) {
				// Make sure 'deleted' field is being monitored
				// beforeDelete & afterDelete are not called if using SoftDeletable
				$softDeletableField = (!empty($model->actsAs['SoftDeletable']['field'])) ? $model->actsAs['SoftDeletable']['field'] : 'deleted';
				if ($model->hasField($softDeletableField) && !in_array($softDeletableField, $this->settings[$model->alias]['fields'])) {
					$this->settings[$model->alias]['fields'][] = $softDeletableField;
				}
			}
		}

		// Set blank default values for changed fields
		$this->_changes[$model->alias] = array(
			'before'     => array(),
			'after'      => array(),
			'hasChanges' => false
		);
	}

/**
 * Before save method. Called before all saves
 *
 * Monitors data that is about to be saved. Will read existing data from the database,
 * and if changes have occurred, will set them in the 'changes' key
 *
 * @param Model $model Model instance
 * @return boolean true to continue, false to abort the save
 * @access public
 */
	public function beforeSave(Model $model, $options=>array()) {
		if (!$this->settings[$model->alias]['fields']) {
			// No fields to monitor
			return true;
		}

		// Check if any fields we are monitoring are in $this->data
		foreach ($this->settings[$model->alias]['fields'] as $field) {
			if (in_array($field, array_keys($model->data[$model->alias]))) {
				$this->_changes[$model->alias]['after'][$field] = $model->data[$model->alias][$field];
			}
		}

		if (!$this->_changes[$model->alias]['after']) {
			// No monitored fields are being updated
			return true;
		}

		// Read the existing data before updating
		$oldData = $model->find('first', array(
			'conditions' => array(
				$model->alias.'.id' => $model->id
			),
			'fields' => array_keys($this->_changes[$model->alias]['after'])
		));

		if ($oldData) {
			$this->_changes[$model->alias]['before'] = $oldData[$model->alias];
		} else {
			// Creating a new entry
			// Make before match after, but with empty values
			foreach ($this->_changes[$model->alias]['after'] as $key => $value) {
				$this->_changes[$model->alias]['before'][$key] = '';
			}
		}

		// Check if any values have changed
		foreach ($this->_changes[$model->alias]['after'] as $field => $newValue) {
			$oldValue = isset($this->_changes[$model->alias]['before'][$field]) ? $this->_changes[$model->alias]['before'][$field] : false;
			if ($newValue == $oldValue) {
				// Values have not changed. Remove them from the arrays.
				unset($this->_changes[$model->alias]['before'][$field]);
				unset($this->_changes[$model->alias]['after'][$field]);
			}
		}

		// Determine if any field has changed
		$this->_changes[$model->alias]['hasChanges'] = (boolean) $this->_changes[$model->alias]['before'];

		return true;
	}

/**
 * After save method. Called after all saves
 *
 * @param Model $model Model instance.
 * @param boolean $created indicates whether the node just saved was created or updated
 * @return boolean true on success, false on failure
 * @access public
 */
	public function afterSave(Model $model, $created, $options=>array()) {
		if ($this->_changes[$model->alias]['hasChanges']) {
			// Initial class for LogAction table
			$logAction = ClassRegistry::init('LogAction.LogAction');


			foreach ($this->_changes[$model->alias]['before'] as $field => $oldValue) {
				if (!array_key_exists($field, $this->_changes[$model->alias]['after'])) {
					continue;
				}

				$newValue = $this->_changes[$model->alias]['after'][$field];

				// Insert record of this change
				$data = array(
					'user_id' => AuthComponent::user('id'),
					'row'     => $model->id,
					'model'   => $model->alias,
					'field'   => $field,
					'before'  => $oldValue,
					'after'   => $newValue
				);
				if (!$logAction->insert($data)) {
					// Failed to insert log record
					return false;
				}
			}
		}

		return true;
	}

/**
 * Called before every deletion operation.
 *
 * @param Model $model Model instance.
 * @param boolean $cascade If true records that depend on this record will also be deleted
 * @return boolean True if the operation should continue, false if it should abort
 * @access public
 */
	public function beforeDelete(Model $model, $cascade = true) {}

/**
 * Called after every deletion operation.
 *
 * @param Model $model Model instance.
 * @return void
 * @access public
 */
	public function afterDelete(Model $model) {
		if ($this->settings[$model->alias]['trackDelete']) {
			// Insert record of this deletion
			$data = array(
				'user_id' => AuthComponent::user('id'),
				'row'     => $model->id,
				'model'   => $model->alias,
				'field'   => 'deleted',
				'before'  => 0,
				'after'   => 1
			);
			if (!ClassRegistry::init('LogAction.LogAction')->insert($data)) {
				// Failed to insert log record
				return false;
			}
		}

		return true;
	}

/**
 * Allow changes to be publicly accessible
 *
 * @param Model $model Model instance.
 * @return array Changes that were made
 * @access public
 */
	public function getChanges($model) {
		return $this->_changes[$model];
	}

}
