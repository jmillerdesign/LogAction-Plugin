<h2><?php __('Logs');?></h2>

<?php if ($logs): ?>
	<table cellpadding="0" cellspacing="0">
		<tr>
			<th><?php echo $this->Paginator->sort('User', 'user_id');?></th>
			<th><?php echo $this->Paginator->sort('model');?></th>
			<th><?php echo $this->Paginator->sort('field');?></th>
			<th><?php echo $this->Paginator->sort('row');?></th>
			<th><?php echo $this->Paginator->sort('before');?></th>
			<th><?php echo $this->Paginator->sort('after');?></th>
			<th><?php echo $this->Paginator->sort('Date', 'created');?></th>
		</tr>
		<?php
			foreach ($logs as $key => $log):
				$class = array();
				if ($key++ % 2 == 0) {
					$class[] = 'altrow';
				}
				if ($log['LogAction']['field'] == 'deleted') {
					if ($log['LogAction']['after'] == 1) {
						// Row was deleted
						$class[] = 'deleted error';
					} else {
						// Row was un-deleted
						$class[] = 'deleted success';
					}
				}
				if ($log['LogAction']['before'] == '') {
					// Field was created
					$class[] = 'created success';
				}
		?>
			<tr class="<?php echo implode(' ', $class); ?>">
			<td>
				<?php echo $this->Html->link($log['User']['name'], array(
					'action' => 'index',
					$log['User']['id']
				)); ?>
			</td>
			<td><?php echo $log['LogAction']['model']; ?>&nbsp;</td>
			<td><?php echo $log['LogAction']['field']; ?>&nbsp;</td>
			<td><?php echo $log['LogAction']['row']; ?>&nbsp;</td>
			<td><?php echo $log['LogAction']['before']; ?>&nbsp;</td>
			<td><?php echo $log['LogAction']['after']; ?>&nbsp;</td>
			<td><?php echo $this->Time->niceShort($log['LogAction']['created']); ?>&nbsp;</td>
			</tr>
		<?php endforeach; ?>
	</table>

	<?php if ($this->Paginator->hasNext() || $this->Paginator->hasPrev()): ?>
		<p>
			<?php
				echo $this->Paginator->counter(array(
					'format' => __('Page %page% of %pages%, showing %current% records out of %count% total, starting on record %start%, ending on %end%', true)
				));
			?>
		</p>

		<div class="paging">
			<?php echo $this->Paginator->prev('<< ' . __('previous', true), array(), null, array('class'=>'disabled')); ?>
		 | 	<?php echo $this->Paginator->numbers();?>
	|
			<?php echo $this->Paginator->next(__('next', true) . ' >>', array(), null, array('class' => 'disabled')); ?>
		</div>
	<?php endif; ?>
<?php else: ?>
	<p class="empty">
		<?php if ($userId): ?>
			No logs have been recorded for this user.
		<?php else: ?>
			No logs have been recorded.
		<?php endif; ?>
	</p>
<?php endif; ?>
