<?php
class LogsController extends LogActionAppController {
	var $name = 'Logs';
	var $uses = array('LogAction.LogAction');
	var $helpers = array('Time');

	function admin_index($userId = null) {
		$this->LogAction->recursive = 0;
		$conditions = array();
		if ($userId) {
			$conditions['LogAction.user_id'] = $userId;
		}
		$this->paginate = array(
			'conditions' => $conditions,
			'order' => 'LogAction.id DESC',
			'limit' => 25
		);
		$this->set('logs', $this->paginate());
		$this->set('userId', $userId);
	}

}
