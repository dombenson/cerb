<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2015, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerberusweb.com/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://www.cerbweb.com	    http://www.webgroupmedia.com/
***********************************************************************/

class DAO_ContextScheduledBehavior extends Cerb_ORMHelper {
	const ID = 'id';
	const CONTEXT = 'context';
	const CONTEXT_ID = 'context_id';
	const BEHAVIOR_ID = 'behavior_id';
	const RUN_DATE = 'run_date';
	const RUN_RELATIVE = 'run_relative';
	const RUN_LITERAL = 'run_literal';
	const VARIABLES_JSON = 'variables_json';
	const REPEAT_JSON = 'repeat_json';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();

		$sql = "INSERT INTO context_scheduled_behavior () VALUES ()";
		$db->ExecuteMaster($sql);
		$id = $db->LastInsertId();

		self::update($id, $fields);

		return $id;
	}

	static function update($ids, $fields) {
		parent::_update($ids, 'context_scheduled_behavior', $fields);
	}

	static function updateWhere($fields, $where) {
		parent::_updateWhere('context_scheduled_behavior', $fields, $where);
	}

	static function updateRelativeSchedules($context, $context_ids) {
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($context_ids))
			return;
		
		$sql = sprintf("%s = %s AND %s IN (%s) AND %s != ''",
			self::CONTEXT,
			$db->qstr($context),
			self::CONTEXT_ID,
			implode(',', $context_ids),
			self::RUN_RELATIVE
		);
		
		$objects = DAO_ContextScheduledBehavior::getWhere($sql);

		if(is_array($objects))
		foreach($objects as $object) { /* @var $object Model_ContextScheduledBehavior */
			if(null == ($macro = DAO_TriggerEvent::get($object->behavior_id)))
				continue;
			
			if(null == ($event = $macro->getEvent()))
				continue;
			
			$event = $macro->getEvent();
			$event_model = $event->generateSampleEventModel($macro, $object->context_id);
			$event->setEvent($event_model, $macro);
			$values = $event->getValues();
			
			$tpl_builder = DevblocksPlatform::getTemplateBuilder();
			@$run_relative_timestamp = strtotime($tpl_builder->build(sprintf("{{%s|date}}",$object->run_relative), $values));
			
			if(empty($run_relative_timestamp))
				$run_relative_timestamp = time();
			
			$run_date = @strtotime($object->run_literal, $run_relative_timestamp);
			
			DAO_ContextScheduledBehavior::update($object->id, array(
				DAO_ContextScheduledBehavior::RUN_DATE => $run_date,
			));
		}
	}
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_ContextScheduledBehavior[]
	 */
	static function getWhere($where=null, $sortBy=null, $sortAsc=true, $limit=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);

		// SQL
		$sql = "SELECT id, context, context_id, behavior_id, run_date, run_relative, run_literal, variables_json, repeat_json ".
			"FROM context_scheduled_behavior ".
			$where_sql.
			$sort_sql.
			$limit_sql
			;
		$rs = $db->ExecuteSlave($sql);

		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_ContextScheduledBehavior	 */
	static function get($id) {
		if(empty($id))
			return null;
		
		$objects = self::getWhere(sprintf("%s = %d",
			self::ID,
			$id
		));

		if(isset($objects[$id]))
			return $objects[$id];

		return null;
	}

	/**
	 *
	 * Enter description here ...
	 * @param unknown_type $context
	 * @param unknown_type $context_id
	 * @return Model_ContextScheduledBehavior
	 */
	static public function getByContext($context, $context_id) {
		$objects = self::getWhere(
			sprintf("%s = %s AND %s = %d",
				self::CONTEXT,
				Cerb_ORMHelper::qstr($context),
				self::CONTEXT_ID,
				$context_id
			),
			self::RUN_DATE,
			true
		);

		return $objects;
	}
	
	/**
	 * @param resource $rs
	 * @return Model_ContextScheduledBehavior[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		if(!($rs instanceof mysqli_result))
			return false;

		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_ContextScheduledBehavior();
			$object->id = $row['id'];
			$object->context = $row['context'];
			$object->context_id = $row['context_id'];
			$object->behavior_id = $row['behavior_id'];
			$object->run_date = $row['run_date'];
			$object->run_relative = $row['run_relative'];
			$object->run_literal = $row['run_literal'];
			if(!empty($row['variables_json']))
				$object->variables = @json_decode($row['variables_json'], true);
			if(!empty($row['repeat_json']))
				$object->repeat = @json_decode($row['repeat_json'], true);
			$objects[$object->id] = $object;
		}

		mysqli_free_result($rs);

		return $objects;
	}

	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();

		if(empty($ids))
			return;

		$ids_list = implode(',', $ids);

		$db->ExecuteMaster(sprintf("DELETE FROM context_scheduled_behavior WHERE id IN (%s)", $ids_list));

		return true;
	}
	
	static function deleteByContext($context, $context_ids) {
		if(!is_array($context_ids))
			$context_ids = array($context_ids);
		
		if(empty($context_ids))
			return;
		
		$context_ids = DevblocksPlatform::sanitizeArray($context_ids, 'int');
			
		$db = DevblocksPlatform::getDatabaseService();
		
		$db->ExecuteMaster(sprintf("DELETE FROM context_scheduled_behavior WHERE context = %s AND context_id IN (%s) ",
			$db->qstr($context),
			implode(',', $context_ids)
		));
		
		return true;
	}
	
	static function deleteByBehavior($behavior_ids, $only_context=null, $only_context_id=null) {
		if(!is_array($behavior_ids)) $behavior_ids = array($behavior_ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($behavior_ids))
			return;

		DevblocksPlatform::sanitizeArray($behavior_ids, 'integer');
		$ids_list = implode(',', $behavior_ids);
		
		$wheres = array();
		
		$wheres[] = sprintf("behavior_id IN (%s)",
			$ids_list
		);
		
		// Are we limiting this delete to a single context or object?
		if(!empty($only_context)) {
			$wheres[] = sprintf("context = %s",
				$db->qstr($only_context)
			);
			
			if(!empty($only_context_id)) {
				$wheres[] = sprintf("context_id = %d",
					$only_context_id
				);
			}
		}
		
		// Join where clauses
		$where = implode(' AND ', $wheres);
		
		// Query
		$db->ExecuteMaster(sprintf("DELETE FROM context_scheduled_behavior WHERE %s", $where));
		
		return true;
	}

	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_ContextScheduledBehavior::getFields();

		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);

		$select_sql = sprintf("SELECT ".
			"context_scheduled_behavior.id as %s, ".
			"context_scheduled_behavior.context as %s, ".
			"context_scheduled_behavior.context_id as %s, ".
			"context_scheduled_behavior.behavior_id as %s, ".
			"context_scheduled_behavior.run_date as %s, ".
			"context_scheduled_behavior.run_relative as %s, ".
			"context_scheduled_behavior.run_literal as %s, ".
			"context_scheduled_behavior.variables_json as %s, ".
			"context_scheduled_behavior.repeat_json as %s, ".
			"trigger_event.title as %s, ".
			"trigger_event.virtual_attendant_id as %s ",
				SearchFields_ContextScheduledBehavior::ID,
				SearchFields_ContextScheduledBehavior::CONTEXT,
				SearchFields_ContextScheduledBehavior::CONTEXT_ID,
				SearchFields_ContextScheduledBehavior::BEHAVIOR_ID,
				SearchFields_ContextScheduledBehavior::RUN_DATE,
				SearchFields_ContextScheduledBehavior::RUN_RELATIVE,
				SearchFields_ContextScheduledBehavior::RUN_LITERAL,
				SearchFields_ContextScheduledBehavior::VARIABLES_JSON,
				SearchFields_ContextScheduledBehavior::REPEAT_JSON,
				SearchFields_ContextScheduledBehavior::BEHAVIOR_NAME,
				SearchFields_ContextScheduledBehavior::BEHAVIOR_VIRTUAL_ATTENDANT_ID
		);
			
		$join_sql = "FROM context_scheduled_behavior ".
			"INNER JOIN trigger_event ON (context_scheduled_behavior.behavior_id=trigger_event.id) "
			;

		$has_multiple_values = false; // [TODO] Temporary when custom fields disabled

		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = self::_buildSortClause($sortBy, $sortAsc, $fields);

		// Translate virtual fields
		
		$args = array(
			'join_sql' => &$join_sql,
			'where_sql' => &$where_sql,
			'tables' => &$tables,
			'has_multiple_values' => &$has_multiple_values
		);
		
		array_walk_recursive(
			$params,
			array('DAO_ContextScheduledBehavior', '_translateVirtualParameters'),
			$args
		);
		
		return array(
			'primary_table' => 'context_scheduled_behavior',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'has_multiple_values' => $has_multiple_values,
			'sort' => $sort_sql,
		);
	}

	private static function _translateVirtualParameters($param, $key, &$args) {
		if(!is_a($param, 'DevblocksSearchCriteria'))
			return;
			
		$param_key = $param->field;
		settype($param_key, 'string');
		switch($param_key) {
			case SearchFields_ContextScheduledBehavior::VIRTUAL_TARGET:
				break;
		}
	}
	
	/**
	 * Enter description here...
	 *
	 * @param array $columns
	 * @param DevblocksSearchCriteria[] $params
	 * @param integer $limit
	 * @param integer $page
	 * @param string $sortBy
	 * @param boolean $sortAsc
	 * @param boolean $withCounts
	 * @return array
	 */
	static function search($columns, $params, $limit=10, $page=0, $sortBy=null, $sortAsc=null, $withCounts=true) {
		$db = DevblocksPlatform::getDatabaseService();

		// Build search queries
		$query_parts = self::getSearchQueryComponents($columns,$params,$sortBy,$sortAsc);

		$select_sql = $query_parts['select'];
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
		$has_multiple_values = $query_parts['has_multiple_values'];
		$sort_sql = $query_parts['sort'];

		$sql =
			$select_sql.
			$join_sql.
			$where_sql.
			($has_multiple_values ? 'GROUP BY context_scheduled_behavior.id ' : '').
			$sort_sql
			;
			
		if($limit > 0) {
			if(false == ($rs = $db->SelectLimit($sql,$limit,$page*$limit)))
				return false;
		} else {
			if(false == ($rs = $db->ExecuteSlave($sql)))
				return false;
			$total = mysqli_num_rows($rs);
		}

		$results = array();
		
		if(!($rs instanceof mysqli_result))
			return false;

		while($row = mysqli_fetch_assoc($rs)) {
			$object_id = intval($row[SearchFields_ContextScheduledBehavior::ID]);
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					($has_multiple_values ? "SELECT COUNT(DISTINCT context_scheduled_behavior.id) " : "SELECT COUNT(context_scheduled_behavior.id) ").
					$join_sql.
					$where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}

		mysqli_free_result($rs);

		return array($results,$total);
	}

	static function buildVariables($var_keys, $var_vals, $trigger) {
		$vars = array();
		foreach($var_keys as $var) {
			if(isset($var_vals[$var])) {
				@$var_mft = $trigger->variables[$var];
				$val = $var_vals[$var];
				
				if(!empty($var_mft)) {
					// Parse dates
					switch($var_mft['type']) {
						case Model_CustomField::TYPE_DATE:
							@$val = strtotime($val);
							break;
					}
				}
				
				$vars[$var] = $val;
			}
		}
		return $vars;
	}
};

class SearchFields_ContextScheduledBehavior implements IDevblocksSearchFields {
	const ID = 'c_id';
	const CONTEXT = 'c_context';
	const CONTEXT_ID = 'c_context_id';
	const BEHAVIOR_ID = 'c_behavior_id';
	const RUN_DATE = 'c_run_date';
	const RUN_RELATIVE = 'c_run_relative';
	const RUN_LITERAL = 'c_run_literal';
	const VARIABLES_JSON = 'c_variables_json';
	const REPEAT_JSON = 'c_repeat_json';
	
	const BEHAVIOR_NAME = 'b_behavior_name';
	const BEHAVIOR_VIRTUAL_ATTENDANT_ID = 'b_behavior_virtual_attendant_id';
	
	const VIRTUAL_TARGET = '*_target';

	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();

		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'context_scheduled_behavior', 'id', $translate->_('common.id'), null, true),
			self::CONTEXT => new DevblocksSearchField(self::CONTEXT, 'context_scheduled_behavior', 'context', $translate->_('common.context'), null, true),
			self::CONTEXT_ID => new DevblocksSearchField(self::CONTEXT_ID, 'context_scheduled_behavior', 'context_id', $translate->_('common.context_id'), null, true),
			self::BEHAVIOR_ID => new DevblocksSearchField(self::BEHAVIOR_ID, 'context_scheduled_behavior', 'behavior_id', $translate->_('common.behavior'), null, true),
			self::RUN_DATE => new DevblocksSearchField(self::RUN_DATE, 'context_scheduled_behavior', 'run_date', $translate->_('dao.context_scheduled_behavior.run_date'), Model_CustomField::TYPE_DATE, true),
			self::RUN_RELATIVE => new DevblocksSearchField(self::RUN_RELATIVE, 'context_scheduled_behavior', 'run_relative', $translate->_('dao.context_scheduled_behavior.run_relative'), null, false),
			self::RUN_LITERAL => new DevblocksSearchField(self::RUN_LITERAL, 'context_scheduled_behavior', 'run_literal', $translate->_('dao.context_scheduled_behavior.run_literal'), null, false),
			self::VARIABLES_JSON => new DevblocksSearchField(self::VARIABLES_JSON, 'context_scheduled_behavior', 'variables_json', $translate->_('dao.context_scheduled_behavior.variables_json'), null, false),
			self::REPEAT_JSON => new DevblocksSearchField(self::REPEAT_JSON, 'context_scheduled_behavior', 'repeat_json', $translate->_('dao.context_scheduled_behavior.repeat_json'), null, false),
			
			self::BEHAVIOR_NAME => new DevblocksSearchField(self::BEHAVIOR_NAME, 'trigger_event', 'title', $translate->_('common.name'), Model_CustomField::TYPE_SINGLE_LINE, true),
			self::BEHAVIOR_VIRTUAL_ATTENDANT_ID => new DevblocksSearchField(self::BEHAVIOR_VIRTUAL_ATTENDANT_ID, 'trigger_event', 'virtual_attendant_id', $translate->_('common.virtual_attendant'), null, true),

			self::VIRTUAL_TARGET => new DevblocksSearchField(self::VIRTUAL_TARGET, '*', 'target', $translate->_('common.target'), null, false),
		);

		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class Model_ContextScheduledBehavior {
	public $id;
	public $context;
	public $context_id;
	public $behavior_id;
	public $run_date;
	public $run_relative;
	public $run_literal;
	public $variables = array();
	public $repeat = array();
	
	function run() {
		try {
			if(empty($this->context) || empty($this->context_id) || empty($this->behavior_id))
				throw new Exception("Missing properties.");
	
			// Load macro
			if(null == ($macro = DAO_TriggerEvent::get($this->behavior_id))) /* @var $macro Model_TriggerEvent */
				throw new Exception("Invalid macro.");
			
			// Load event manifest
			if(null == ($ext = DevblocksPlatform::getExtension($macro->event_point, false))) /* @var $ext DevblocksExtensionManifest */
				throw new Exception("Invalid event.");
			
		} catch(Exception $e) {
			DAO_ContextScheduledBehavior::delete($this->id);
			return;
		}
		
		// Format variables
		
		foreach($this->variables as $var_key => $var_val) {
			if(!isset($macro->variables[$var_key]))
				continue;
			
			try {
				$this->variables[$var_key] = $macro->formatVariable($macro->variables[$var_key], $var_val);
				
			} catch(Exception $e) {
			}
		}
		
		// Are we going to be rescheduling this behavior?
		$reschedule_date = $this->getNextOccurrence();
	
		if(!empty($reschedule_date)) {
			DAO_ContextScheduledBehavior::update($this->id, array(
				DAO_ContextScheduledBehavior::RUN_DATE => $reschedule_date,
			));
			
		} else {
			DAO_ContextScheduledBehavior::delete($this->id);
		}
		
		// Execute
		call_user_func(array($ext->class, 'trigger'), $macro->id, $this->context_id, $this->variables);
	}
	
	function getNextOccurrence() {
		if(empty($this->repeat) || !isset($this->repeat['freq']))
			return null;
		
		// Do we have end conditions?
		if(isset($this->repeat['end'])) {
			$end = $this->repeat['end'];
			switch($end['term']) {
				// End after a specific date
				case 'date':
					// If we've passed the end date
					$on = intval(@$end['options']['on']);
					if($end['options']['on'] <= time()) {
						// Don't repeat
						return null;
					}
					break;
			}
		}
		
		$next_run_date = null;
		$dates = array();
		
		switch($this->repeat['freq']) {
			case 'interval':
				$now = ($this->run_date <= time()) ? time() : $this->run_date;
				@$next = strtotime($this->repeat['options']['every_n'], $now);
				
				if(!empty($next))
					$next_run_date = $next;
				
				break;
				
			case 'weekly':
				$days = isset($this->repeat['options']['day']) ? $this->repeat['options']['day'] : array();
				$dates = DevblocksCalendarHelper::getWeeklyDates($this->run_date, $days, null, 1);
				break;
				
			case 'monthly':
				$days = isset($this->repeat['options']['day']) ? $this->repeat['options']['day'] : array();
				$dates = DevblocksCalendarHelper::getMonthlyDates($this->run_date, $days, null, 2);
				break;
				
			case 'yearly':
				$months = isset($this->repeat['options']['month']) ? $this->repeat['options']['month'] : array();
				$dates = DevblocksCalendarHelper::getYearlyDates($this->run_date, $months, null, 2);
				break;
		}
		
		if(!empty($dates)) {
			$next_run_date = array_shift($dates);
			$next_run_date = strtotime(date('H:i', $this->run_date), $next_run_date);
		}

		if(empty($next_run_date))
			return false;
		
		return $next_run_date;
	}
};

class View_ContextScheduledBehavior extends C4_AbstractView implements IAbstractView_QuickSearch {
	const DEFAULT_ID = 'contextscheduledbehavior';

	function __construct() {
		$translate = DevblocksPlatform::getTranslationService();

		$this->id = self::DEFAULT_ID;
		$this->name = $translate->_('Scheduled Behavior');
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_ContextScheduledBehavior::RUN_DATE;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_ContextScheduledBehavior::RUN_DATE,
			SearchFields_ContextScheduledBehavior::BEHAVIOR_NAME,
			SearchFields_ContextScheduledBehavior::BEHAVIOR_VIRTUAL_ATTENDANT_ID,
			SearchFields_ContextScheduledBehavior::VIRTUAL_TARGET,
		);
		$this->addColumnsHidden(array(
			SearchFields_ContextScheduledBehavior::BEHAVIOR_ID,
			SearchFields_ContextScheduledBehavior::CONTEXT,
			SearchFields_ContextScheduledBehavior::CONTEXT_ID,
			SearchFields_ContextScheduledBehavior::ID,
			SearchFields_ContextScheduledBehavior::RUN_LITERAL,
			SearchFields_ContextScheduledBehavior::RUN_RELATIVE,
			SearchFields_ContextScheduledBehavior::VARIABLES_JSON,
		));

		$this->addParamsHidden(array(
			SearchFields_ContextScheduledBehavior::BEHAVIOR_ID,
			SearchFields_ContextScheduledBehavior::BEHAVIOR_VIRTUAL_ATTENDANT_ID,
			SearchFields_ContextScheduledBehavior::CONTEXT,
			SearchFields_ContextScheduledBehavior::CONTEXT_ID,
			SearchFields_ContextScheduledBehavior::ID,
			SearchFields_ContextScheduledBehavior::REPEAT_JSON,
			SearchFields_ContextScheduledBehavior::RUN_LITERAL,
			SearchFields_ContextScheduledBehavior::RUN_RELATIVE,
			SearchFields_ContextScheduledBehavior::VARIABLES_JSON,
			SearchFields_ContextScheduledBehavior::VIRTUAL_TARGET,
		));

		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_ContextScheduledBehavior::search(
			$this->view_columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
		return $objects;
	}

	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_ContextScheduledBehavior', $size);
	}

	function getQuickSearchFields() {
		$search_fields = SearchFields_ContextScheduledBehavior::getFields();
		
		$fields = array(
			'_fulltext' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_ContextScheduledBehavior::BEHAVIOR_NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'behavior' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_ContextScheduledBehavior::BEHAVIOR_NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'runDate' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_ContextScheduledBehavior::RUN_DATE),
				),
			/*
			'va' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_VIRTUAL,
					'options' => array('param_key' => SearchFields_ContextScheduledBehavior::BEHAVIOR_VIRTUAL_ATTENDANT_ID),
				),
			*/
		);
		
		// Add is_sortable
		
		$fields = self::_setSortableQuickSearchFields($fields, $search_fields);
		
		// Sort by keys
		
		ksort($fields);
		
		return $fields;
	}	
	
	function getParamsFromQuickSearchFields($fields) {
		$search_fields = $this->getQuickSearchFields();
		$params = DevblocksSearchCriteria::getParamsFromQueryFields($fields, $search_fields);

		// Handle virtual fields and overrides
		if(is_array($fields))
		foreach($fields as $k => $v) {
			switch($k) {
				case 'va':
					$field_keys = array(
						'va' => SearchFields_ContextScheduledBehavior::BEHAVIOR_VIRTUAL_ATTENDANT_ID,
					);
					
					@$field_key = $field_keys[$k];
					
					$oper = DevblocksSearchCriteria::OPER_IN;
					
					$vas = DAO_VirtualAttendant::getAll();
					$patterns = DevblocksPlatform::parseCsvString($v);
					$values = array();
					
					if(is_array($values))
					foreach($patterns as $pattern) {
						foreach($vas as $va_id => $va) {
							if(false !== stripos($va->name, $pattern))
								$values[$va_id] = true;
						}
					}
					
					if(!empty($values)) {
						$params[$field_key] = new DevblocksSearchCriteria(
							$field_key,
							$oper,
							array_keys($values)
						);
					}
					break;
			}
		}
		
		return $params;
	}
	
	function render() {
		$this->_sanitize();

		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);
		
		switch($this->renderTemplate) {
			default:
				$tpl->assign('view_template', 'devblocks:cerberusweb.core::internal/va/scheduled_behavior/view.tpl');
				$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
				break;
		}
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		switch($field) {
			case SearchFields_ContextScheduledBehavior::BEHAVIOR_NAME:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
			case 'placeholder_number':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
			case 'placeholder_bool':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
			case SearchFields_ContextScheduledBehavior::RUN_DATE:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
			// [TODO]
			case SearchFields_ContextScheduledBehavior::BEHAVIOR_VIRTUAL_ATTENDANT_ID:
				break;
		}
	}

	function renderCriteriaParam($param) {
		$field = $param->field;
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		switch($field) {
			// [TODO]
			case SearchFields_ContextScheduledBehavior::BEHAVIOR_VIRTUAL_ATTENDANT_ID:
				break;
				
			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_ContextScheduledBehavior::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_ContextScheduledBehavior::BEHAVIOR_NAME:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case 'placeholder_number':
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;

			case SearchFields_ContextScheduledBehavior::RUN_DATE:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;

			case 'placeholder_bool':
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			// [TODO]
			case SearchFields_ContextScheduledBehavior::BEHAVIOR_VIRTUAL_ATTENDANT_ID:
				break;
		}

		if(!empty($criteria)) {
			$this->addParam($criteria, $field);
			$this->renderPage = 0;
		}
	}

	function doBulkUpdate($filter, $do, $ids=array()) {
		@set_time_limit(600); // 10m

		$change_fields = array();
		$custom_fields = array();

		// Make sure we have actions
		if(empty($do))
			return;

		// Make sure we have checked items if we want a checked list
		if(0 == strcasecmp($filter,"checks") && empty($ids))
			return;
			
		if(is_array($do))
		foreach($do as $k => $v) {
			switch($k) {
				// [TODO] Implement actions
				case 'example':
					//$change_fields[DAO_ContextScheduledBehavior::EXAMPLE] = 'some value';
					break;
			}
		}

		$pg = 0;

		if(empty($ids))
		do {
			list($objects,$null) = DAO_ContextScheduledBehavior::search(
				array(),
				$this->getParams(),
				100,
				$pg++,
				SearchFields_ContextScheduledBehavior::ID,
				true,
				false
			);
			$ids = array_merge($ids, array_keys($objects));

		} while(!empty($objects));

		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
			$batch_ids = array_slice($ids,$x,100);
				
			DAO_ContextScheduledBehavior::update($batch_ids, $change_fields);
	
			unset($batch_ids);
		}

		unset($ids);
	}
};

	