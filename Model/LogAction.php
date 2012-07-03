<?php
/**
 * LogAction Model
 *
 * Used with LogAction Behavior to log when changes to the database occurs.
 *
 * @author Justin Miller
 */
class LogAction extends LogActionAppModel {
/**
 * Name of the model.
 *
 * @var string
 * @access public
 * @link http://book.cakephp.org/view/1057/Model-Attributes#name-1068
 */
	var $name = 'LogAction';

	var $belongsTo = 'User';

/**
 * Insert a new record into the database
 *
 * @param array $data Data array to save to database
 * @return boolean true on successful save, false on error
 * @access public
 */
	function insert($data) {
		$this->create($data);
		return $this->save($data);
	}
}
