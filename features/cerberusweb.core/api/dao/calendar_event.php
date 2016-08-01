<?php
/***********************************************************************
 | Cerb(tm) developed by Webgroup Media, LLC.
 |-----------------------------------------------------------------------
 | All source code & content (c) Copyright 2002-2016, Webgroup Media LLC
 |   unless specifically noted otherwise.
 |
 | This source code is released under the Devblocks Public License.
 | The latest version of this license can be found here:
 | http://cerb.io/license
 |
 | By using this software, you acknowledge having read this license
 | and agree to be bound thereby.
 | ______________________________________________________________________
 |	http://cerb.io	    http://webgroup.media
 ***********************************************************************/

class DAO_CalendarEvent extends Cerb_ORMHelper {
	const ID = 'id';
	const NAME = 'name';
	const CALENDAR_ID = 'calendar_id';
	const IS_AVAILABLE = 'is_available';
	const DATE_START = 'date_start';
	const DATE_END = 'date_end';

	static function create($fields) {
		if(
			!isset($fields[DAO_CalendarEvent::CALENDAR_ID])
			|| false == ($calendar = DAO_Calendar::get($fields[DAO_CalendarEvent::CALENDAR_ID]))
			)
			return false;
		
		$db = DevblocksPlatform::getDatabaseService();

		$sql = "INSERT INTO calendar_event () VALUES ()";
		$db->ExecuteMaster($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		/*
		 * Log the activity of a new event being created
		 */
		
		$entry = array(
			//{{actor}} created event {{event}} on calendar {{target}}
			'message' => 'activities.calendar_event.created',
			'variables' => array(
				'event' => $fields[DAO_CalendarEvent::NAME],
				'target' => $calendar->name,
				),
			'urls' => array(
				'event' => sprintf("ctx://%s:%d", CerberusContexts::CONTEXT_CALENDAR_EVENT, $id),
				'target' => sprintf("ctx://%s:%d", CerberusContexts::CONTEXT_CALENDAR, $calendar->id),
				)
		);
		CerberusContexts::logActivity('calendar_event.created', CerberusContexts::CONTEXT_CALENDAR, $calendar->id, $entry, null, null);
		
		return $id;
	}
	
	static function update($ids, $fields, $check_deltas=true) {
		if(!is_array($ids))
			$ids = array($ids);
		
		// Make a diff for the requested objects in batches
		
		$chunks = array_chunk($ids, 100, true);
		while($batch_ids = array_shift($chunks)) {
			if(empty($batch_ids))
				continue;
			
			// Send events
			if($check_deltas) {
				CerberusContexts::checkpointChanges(CerberusContexts::CONTEXT_CALENDAR_EVENT, $batch_ids);
			}
			
			// Make changes
			parent::_update($batch_ids, 'calendar_event', $fields);
			
			// Send events
			if($check_deltas) {
				// Trigger an event about the changes
				$eventMgr = DevblocksPlatform::getEventService();
				$eventMgr->trigger(
					new Model_DevblocksEvent(
						'dao.calendar_event.update',
						array(
							'fields' => $fields,
						)
					)
				);
				
				// Log the context update
				DevblocksPlatform::markContextChanged(CerberusContexts::CONTEXT_CALENDAR_EVENT, $batch_ids);
			}
		}
	}
	
	static function updateWhere($fields, $where) {
		parent::_updateWhere('calendar_event', $fields, $where);
	}
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_CalendarEvent[]
	 */
	static function getWhere($where=null, $sortBy=null, $sortAsc=true, $limit=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, name, calendar_id, is_available, date_start, date_end ".
			"FROM calendar_event ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;
		$rs = $db->ExecuteSlave($sql);
		
		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_CalendarEvent	 */
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
	 * @param resource $rs
	 * @return Model_CalendarEvent[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_CalendarEvent();
			$object->id = $row['id'];
			$object->name = $row['name'];
			$object->calendar_id = $row['calendar_id'];
			$object->is_available = $row['is_available'];
			$object->date_start = intval($row['date_start']);
			$object->date_end = intval($row['date_end']);
			$objects[$object->id] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	static function random() {
		return self::_getRandom('calendar_event');
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM calendar_event WHERE id IN (%s)", $ids_list));
		
		// Fire event
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.delete',
				array(
					'context' => 'cerberusweb.contexts.calendar_event',
					'context_ids' => $ids
				)
			)
		);
		
		return true;
	}
	
	static function deleteByCalendarIds($ids) {
		if(!is_array($ids))
			$ids = array($ids);
		
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM calendar_event WHERE calendar_id IN (%s)", $ids_list));
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_CalendarEvent::getFields();
		
		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, 'SearchFields_CalendarEvent', $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"calendar_event.id as %s, ".
			"calendar_event.name as %s, ".
			"calendar_event.calendar_id as %s, ".
			"calendar_event.is_available as %s, ".
			"calendar_event.date_start as %s, ".
			"calendar_event.date_end as %s ",
				SearchFields_CalendarEvent::ID,
				SearchFields_CalendarEvent::NAME,
				SearchFields_CalendarEvent::CALENDAR_ID,
				SearchFields_CalendarEvent::IS_AVAILABLE,
				SearchFields_CalendarEvent::DATE_START,
				SearchFields_CalendarEvent::DATE_END
			);
			
		$join_sql = "FROM calendar_event ".
			(isset($tables['context_link']) ? "INNER JOIN context_link ON (context_link.to_context = 'cerberusweb.contexts.calendar_event' AND context_link.to_context_id = calendar_event.id) " : " ")
			;
		
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = self::_buildSortClause($sortBy, $sortAsc, $fields, $select_sql, 'SearchFields_CalendarEvent');
	
		$args = array(
			'join_sql' => &$join_sql,
			'where_sql' => &$where_sql,
			'tables' => &$tables,
			'has_multiple_values' => &$has_multiple_values
		);
		
		array_walk_recursive(
			$params,
			array('DAO_CalendarEvent', '_translateVirtualParameters'),
			$args
		);
		
		return array(
			'primary_table' => 'calendar_event',
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
			
		$from_context = CerberusContexts::CONTEXT_CALENDAR_EVENT;
		$from_index = 'calendar_event.id';
		
		$param_key = $param->field;
		settype($param_key, 'string');
		
		switch($param_key) {
			case SearchFields_CalendarEvent::VIRTUAL_CONTEXT_LINK:
				$args['has_multiple_values'] = true;
				self::_searchComponentsVirtualContextLinks($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
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
			($has_multiple_values ? 'GROUP BY calendar_event.id ' : '').
			$sort_sql;
			
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
			$object_id = intval($row[SearchFields_CalendarEvent::ID]);
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					($has_multiple_values ? "SELECT COUNT(DISTINCT calendar_event.id) " : "SELECT COUNT(calendar_event.id) ").
					$join_sql.
					$where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
		return array($results,$total);
	}
};

class SearchFields_CalendarEvent extends DevblocksSearchFields {
	const ID = 'c_id';
	const NAME = 'c_name';
	const CALENDAR_ID = 'c_calendar_id';
	const IS_AVAILABLE = 'c_is_available';
	const DATE_START = 'c_date_start';
	const DATE_END = 'c_date_end';
	
	// Virtuals
	const VIRTUAL_CONTEXT_LINK = '*_context_link';
	
	// Context Links
	const CONTEXT_LINK = 'cl_context_from';
	const CONTEXT_LINK_ID = 'cl_context_from_id';
	
	static private $_fields = null;
	
	static function getPrimaryKey() {
		return 'calendar_event.id';
	}
	
	static function getCustomFieldContextKeys() {
		return array(
			CerberusContexts::CONTEXT_CALENDAR_EVENT => new DevblocksSearchFieldContextKeys('calendar_event.id', self::ID),
			CerberusContexts::CONTEXT_CALENDAR => new DevblocksSearchFieldContextKeys('calendar_event.calendar_id', self::CALENDAR_ID),
		);
	}
	
	static function getWhereSQL(DevblocksSearchCriteria $param) {
		if('cf_' == substr($param->field, 0, 3)) {
			return self::_getWhereSQLFromCustomFields($param);
		} else {
			return $param->getWhereSQL(self::getFields(), self::getPrimaryKey());
		}
	}
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		if(is_null(self::$_fields))
			self::$_fields = self::_getFields();
		
		return self::$_fields;
	}
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function _getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'calendar_event', 'id', $translate->_('common.id'), Model_CustomField::TYPE_NUMBER, true),
			self::NAME => new DevblocksSearchField(self::NAME, 'calendar_event', 'name', $translate->_('common.name'), Model_CustomField::TYPE_SINGLE_LINE, true),
			self::CALENDAR_ID => new DevblocksSearchField(self::CALENDAR_ID, 'calendar_event', 'calendar_id', $translate->_('common.calendar'), null, true),
			self::IS_AVAILABLE => new DevblocksSearchField(self::IS_AVAILABLE, 'calendar_event', 'is_available', $translate->_('dao.calendar_event.is_available'), Model_CustomField::TYPE_CHECKBOX, true),
			self::DATE_START => new DevblocksSearchField(self::DATE_START, 'calendar_event', 'date_start', $translate->_('dao.calendar_event.date_start'), Model_CustomField::TYPE_DATE, true),
			self::DATE_END => new DevblocksSearchField(self::DATE_END, 'calendar_event', 'date_end', $translate->_('dao.calendar_event.date_end'), Model_CustomField::TYPE_DATE, true),

			self::VIRTUAL_CONTEXT_LINK => new DevblocksSearchField(self::VIRTUAL_CONTEXT_LINK, '*', 'context_link', $translate->_('common.links'), null, false),
				
			self::CONTEXT_LINK => new DevblocksSearchField(self::CONTEXT_LINK, 'context_link', 'from_context', null, null, false),
			self::CONTEXT_LINK_ID => new DevblocksSearchField(self::CONTEXT_LINK_ID, 'context_link', 'from_context_id', null, null, false),
		);
		
		// Custom fields with fieldsets
		
		$custom_columns = DevblocksSearchField::getCustomSearchFieldsByContexts(array_keys(self::getCustomFieldContextKeys()));
		
		if(is_array($custom_columns))
			$columns = array_merge($columns, $custom_columns);
		
		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class Model_CalendarEvent {
	public $id;
	public $name;
	public $calendar_id;
	public $is_available;
	public $date_start;
	public $date_end;
};

class View_CalendarEvent extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
	const DEFAULT_ID = 'calendar_events';

	function __construct() {
		$translate = DevblocksPlatform::getTranslationService();
	
		$this->id = self::DEFAULT_ID;
		$this->name = $translate->_('Calendar Events');
		$this->renderLimit = 10;
		$this->renderSortBy = SearchFields_CalendarEvent::ID;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_CalendarEvent::CALENDAR_ID,
			SearchFields_CalendarEvent::DATE_START,
			SearchFields_CalendarEvent::DATE_END,
			SearchFields_CalendarEvent::IS_AVAILABLE,
		);
		
		$this->addColumnsHidden(array(
			SearchFields_CalendarEvent::ID,
			SearchFields_CalendarEvent::CONTEXT_LINK,
			SearchFields_CalendarEvent::CONTEXT_LINK_ID,
			SearchFields_CalendarEvent::VIRTUAL_CONTEXT_LINK,
		));
		
		$this->addParamsHidden(array(
			SearchFields_CalendarEvent::ID,
			SearchFields_CalendarEvent::CONTEXT_LINK,
			SearchFields_CalendarEvent::CONTEXT_LINK_ID,
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_CalendarEvent::search(
			$this->view_columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
		
		$this->_lazyLoadCustomFieldsIntoObjects($objects, 'SearchFields_CalendarEvent');
		
		return $objects;
	}
	
	function getDataAsObjects($ids=null) {
		return $this->_getDataAsObjects('DAO_CalendarEvent', $ids);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_CalendarEvent', $size);
	}
	
	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
		$fields = array();

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// Fields
				case SearchFields_CalendarEvent::CALENDAR_ID:
				case SearchFields_CalendarEvent::IS_AVAILABLE:
					$pass = true;
					break;
					
				// Virtuals
				case SearchFields_CalendarEvent::VIRTUAL_CONTEXT_LINK:
					$pass = true;
					break;
					
				// Valid custom fields
				default:
					if('cf_' == substr($field_key,0,3))
						$pass = $this->_canSubtotalCustomField($field_key);
					break;
			}
			
			if($pass)
				$fields[$field_key] = $field_model;
		}
		
		return $fields;
	}
	
	function getSubtotalCounts($column) {
		$counts = array();
		$fields = $this->getFields();
		$context = CerberusContexts::CONTEXT_CALENDAR_EVENT;

		if(!isset($fields[$column]))
			return array();
		
		switch($column) {
			case SearchFields_CalendarEvent::CALENDAR_ID:
				$calendars = DAO_Calendar::getAll();
				$label_map = array();
				
				if(is_array($calendars))
				foreach($calendars as $calendar_id => $calendar) {
					$label_map[$calendar_id] = $calendar->name;
				}
				
				$counts = $this->_getSubtotalCountForStringColumn($context, $column, $label_map, DevblocksSearchCriteria::OPER_IN, 'context_id[]');
				break;
				
			case SearchFields_CalendarEvent::IS_AVAILABLE:
				$counts = $this->_getSubtotalCountForBooleanColumn($context, $column);
				break;
				
			case SearchFields_CalendarEvent::VIRTUAL_CONTEXT_LINK:
				$counts = $this->_getSubtotalCountForContextLinkColumn($context, $column);
				break;
				
			default:
				// Custom fields
				if('cf_' == substr($column,0,3)) {
					$counts = $this->_getSubtotalCountForCustomColumn($context, $column);
				}
				
				break;
		}
		
		return $counts;
	}
	
	function getQuickSearchFields() {
		$search_fields = SearchFields_CalendarEvent::getFields();
		
		$fields = array(
			'text' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_CalendarEvent::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'calendar' =>
				array(
					'type' => DevblocksSearchCriteria::TYPE_VIRTUAL,
					'options' => array('param_key' => SearchFields_CalendarEvent::CALENDAR_ID),
				),
			'endDate' =>
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_CalendarEvent::DATE_END),
				),
			'name' =>
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_CalendarEvent::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'startDate' =>
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_CalendarEvent::DATE_START),
				),
			'status' =>
				array(
					'type' => DevblocksSearchCriteria::TYPE_VIRTUAL,
					'options' => array('param_key' => SearchFields_CalendarEvent::IS_AVAILABLE),
					'examples' => array(
						'available',
						'busy',
					),
				),
		);
		
		// Add searchable custom fields
		
		$fields = self::_appendFieldsFromQuickSearchContext(CerberusContexts::CONTEXT_CALENDAR_EVENT, $fields, null);
		
		// Add is_sortable
		
		$fields = self::_setSortableQuickSearchFields($fields, $search_fields);
		
		// Sort by keys
		
		ksort($fields);
		
		return $fields;
	}
	
	function getParamFromQuickSearchFieldTokens($field, $tokens) {
		switch($field) {
			case 'calendar':
				$field_key = SearchFields_CalendarEvent::CALENDAR_ID;
				$oper = null;
				$terms = array();
				
				if(false == CerbQuickSearchLexer::getOperArrayFromTokens($tokens, $oper, $terms, false))
					return false;
				
				if(empty($terms))
					return false;
				
				$calendars = DAO_Calendar::getAll();
				$value = array();;
				
				if(is_array($terms))
				foreach($terms as $term) {
					foreach($calendars as $calendar_id => $calendar) {
						if(false !== stripos($calendar->name, $term))
							$values[$calendar_id] = true;
					}
				}
				
				return new DevblocksSearchCriteria(
					$field_key,
					$oper,
					array_keys($values)
				);
				break;
				
			case 'status':
				$field_key = SearchFields_CalendarEvent::IS_AVAILABLE;
				$oper = null;
				$term = null;
				$value = 0;
				
				if(false == CerbQuickSearchLexer::getOperStringFromTokens($tokens, $oper, $term, false))
					return false;
				
				// Normalize status labels
				switch(substr(strtolower($term), 0, 1)) {
					case 'a':
					case 'y':
					case '1':
						$value = 1;
						break;
				}
				
				return new DevblocksSearchCriteria(
					$field_key,
					$oper,
					$value
				);
				break;
				
			default:
				$search_fields = $this->getQuickSearchFields();
				return DevblocksSearchCriteria::getParamFromQueryFieldTokens($field, $tokens, $search_fields);
				break;
		}
		
		return false;
	}
	
	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		// Calendars
		$calendars = DAO_Calendar::getAll();
		$tpl->assign('calendars', $calendars);
		
		// Custom fields
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_CALENDAR_EVENT);
		$tpl->assign('custom_fields', $custom_fields);

		switch($this->renderTemplate) {
			default:
				$tpl->assign('view_template', 'devblocks:cerberusweb.core::internal/calendar_event/view.tpl');
				$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
				break;
		}
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		switch($field) {
			case SearchFields_CalendarEvent::NAME:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
				
			case SearchFields_CalendarEvent::ID:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
				
			case SearchFields_CalendarEvent::IS_AVAILABLE:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
				
			case SearchFields_CalendarEvent::DATE_START:
			case SearchFields_CalendarEvent::DATE_END:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
				
			case SearchFields_CalendarEvent::CALENDAR_ID:
				$tpl->assign('context', CerberusContexts::CONTEXT_CALENDAR);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__chooser.tpl');
				break;
				
			case SearchFields_CalendarEvent::VIRTUAL_CONTEXT_LINK:
				$contexts = Extension_DevblocksContext::getAll(false);
				$tpl->assign('contexts', $contexts);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_link.tpl');
				break;
				
			default:
				// Custom Fields
				if('cf_' == substr($field,0,3)) {
					$this->_renderCriteriaCustomField($tpl, substr($field,3));
				} else {
					echo ' ';
				}
				break;
		}
	}

	function renderCriteriaParam($param) {
		$field = $param->field;
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		switch($field) {
			case SearchFields_CalendarEvent::CALENDAR_ID:
				$calendars = DAO_Calendar::getAll();
				$label_map = array();
				
				if(is_array($calendars))
				foreach($calendars as $calendar_id => $calendar) {
					$label_map[$calendar_id] = $calendar->name;
				}
				
				$this->_renderCriteriaParamString($param, $label_map);
				break;
				
			case SearchFields_CalendarEvent::IS_AVAILABLE:
				$this->_renderCriteriaParamBoolean($param);
				break;
				
			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function renderVirtualCriteria($param) {
		$key = $param->field;
		
		$translate = DevblocksPlatform::getTranslationService();
		
		switch($key) {
			case SearchFields_CalendarEvent::VIRTUAL_CONTEXT_LINK:
				$this->_renderVirtualContextLinks($param);
				break;
		}
	}
	
	function getFields() {
		return SearchFields_CalendarEvent::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_CalendarEvent::NAME:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case SearchFields_CalendarEvent::ID:
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case SearchFields_CalendarEvent::DATE_START:
			case SearchFields_CalendarEvent::DATE_END:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case SearchFields_CalendarEvent::IS_AVAILABLE:
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			case SearchFields_CalendarEvent::CALENDAR_ID:
				@$context_ids = DevblocksPlatform::sanitizeArray(DevblocksPlatform::importGPC($_REQUEST['context_id'],'array',array()), 'integer', array('nonzero','unique'));
				$criteria = new DevblocksSearchCriteria($field,$oper,$context_ids);
				break;
				
			case SearchFields_CalendarEvent::VIRTUAL_CONTEXT_LINK:
				@$context_links = DevblocksPlatform::importGPC($_REQUEST['context_link'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$context_links);
				break;
				
			default:
				// Custom Fields
				if(substr($field,0,3)=='cf_') {
					$criteria = $this->_doSetCriteriaCustomField($field, substr($field,3));
				}
				break;
		}

		if(!empty($criteria)) {
			$this->addParam($criteria, $field);
			$this->renderPage = 0;
		}
	}
};

class Context_CalendarEvent extends Extension_DevblocksContext implements IDevblocksContextPeek, IDevblocksContextProfile {
	function profileGetUrl($context_id) {
		if(empty($context_id))
			return '';
	
		$url_writer = DevblocksPlatform::getUrlService();
		$url = $url_writer->writeNoProxy('c=profiles&type=calendar_event&id='.$context_id, true);
		return $url;
	}
	
	function getMeta($context_id) {
		$calendar_event = DAO_CalendarEvent::get($context_id);
		$url_writer = DevblocksPlatform::getUrlService();
		
		$url = $this->profileGetUrl($context_id);
		$friendly = DevblocksPlatform::strToPermalink($calendar_event->name);
		
		if(!empty($friendly))
			$url .= '-' . $friendly;
		
		return array(
			'id' => $calendar_event->id,
			'name' => $calendar_event->name,
			'permalink' => $url,
			'updated' => 0, // [TODO]
		);
	}
	
	function getRandom() {
		return DAO_CalendarEvent::random();
	}
	
	function getPropertyLabels(DevblocksDictionaryDelegate $dict) {
		$labels = $dict->_labels;
		$prefix = $labels['_label'];
		
		if(!empty($prefix)) {
			array_walk($labels, function(&$label, $key) use ($prefix) {
				$label = preg_replace(sprintf("#^%s #", preg_quote($prefix)), '', $label);
				
				// [TODO] Use translations
				switch($key) {
				}
				
				$label = mb_convert_case($label, MB_CASE_LOWER);
				$label[0] = mb_convert_case($label[0], MB_CASE_UPPER);
			});
		}
		
		asort($labels);
		
		return $labels;
	}
	
	function getDefaultProperties() {
		return array(
			'calendar__label',
			'calendar_owner__label',
			'date_start',
			'date_end',
			'is_available',
		);
	}
	
	function getContext($calendar_event, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Calendar Event:';
		
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_CALENDAR_EVENT);

		// Polymorph
		if(is_numeric($calendar_event)) {
			$calendar_event = DAO_CalendarEvent::get($calendar_event);
		} elseif($calendar_event instanceof Model_CalendarEvent) {
			// It's what we want already.
		} elseif(is_array($calendar_event)) {
			$calendar_event = Cerb_ORMHelper::recastArrayToModel($calendar_event, 'Model_CalendarEvent');
		} else {
			$calendar_event = null;
		}
		
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'date_end' => $prefix.$translate->_('dao.calendar_event.date_end'),
			'date_start' => $prefix.$translate->_('dao.calendar_event.date_start'),
			'id' => $prefix.$translate->_('common.id'),
			'is_available' => $prefix.$translate->_('dao.calendar_event.is_available'),
			'name' => $prefix.$translate->_('common.name'),
			'record_url' => $prefix.$translate->_('common.url.record'),
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'date_end' => Model_CustomField::TYPE_DATE,
			'date_start' => Model_CustomField::TYPE_DATE,
			'id' => Model_CustomField::TYPE_NUMBER,
			'is_available' => Model_CustomField::TYPE_CHECKBOX,
			'name' => Model_CustomField::TYPE_SINGLE_LINE,
			'record_url' => Model_CustomField::TYPE_URL,
		);
		
		// Custom field/fieldset token labels
		if(false !== ($custom_field_labels = $this->_getTokenLabelsFromCustomFields($fields, $prefix)) && is_array($custom_field_labels))
			$token_labels = array_merge($token_labels, $custom_field_labels);
		
		// Custom field/fieldset token types
		if(false !== ($custom_field_types = $this->_getTokenTypesFromCustomFields($fields, $prefix)) && is_array($custom_field_types))
			$token_types = array_merge($token_types, $custom_field_types);
		
		// Token values
		$token_values = array();
		
		$token_values['_context'] = CerberusContexts::CONTEXT_CALENDAR_EVENT;
		$token_values['_types'] = $token_types;
		
		if($calendar_event) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = $calendar_event->name;
			$token_values['calendar_id'] = $calendar_event->calendar_id;
			$token_values['date_end'] = $calendar_event->date_end;
			$token_values['date_start'] = $calendar_event->date_start;
			$token_values['id'] = $calendar_event->id;
			$token_values['is_available'] = $calendar_event->is_available;
			$token_values['name'] = $calendar_event->name;

			// Custom fields
			$token_values = $this->_importModelCustomFieldsAsValues($calendar_event, $token_values);
			
			// Calendar
			$merge_token_labels = array();
			$merge_token_values = array();
			CerberusContexts::getContext(CerberusContexts::CONTEXT_CALENDAR, null, $merge_token_labels, $merge_token_values, '', true);
	
			CerberusContexts::merge(
				'calendar_',
				$prefix.'Calendar:',
				$merge_token_labels,
				$merge_token_values,
				$token_labels,
				$token_values
			);
			
			// URL
			$url_writer = DevblocksPlatform::getUrlService();
			$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=profiles&type=calendar_event&id=%d-%s",$calendar_event->id, DevblocksPlatform::strToPermalink($calendar_event->name)), true);
		}

		return true;
	}

	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = CerberusContexts::CONTEXT_CALENDAR_EVENT;
		$context_id = $dictionary['id'];
		
		@$is_loaded = $dictionary['_loaded'];
		$values = array();
		
		if(!$is_loaded) {
			$labels = array();
			CerberusContexts::getContext($context, $context_id, $labels, $values, null, true);
		}
		
		switch($token) {
			case 'watchers':
				$watchers = array(
					$token => CerberusContexts::getWatchers($context, $context_id, true),
				);
				$values = array_merge($values, $watchers);
				break;
				
			default:
				if(substr($token,0,7) == 'custom_') {
					$fields = $this->_lazyLoadCustomFields($token, $context, $context_id);
					$values = array_merge($values, $fields);
				}
				break;
		}
		
		return $values;
	}
	
	function getChooserView($view_id=null) {
		if(empty($view_id))
			$view_id = 'chooser_'.str_replace('.','_',$this->id).time().mt_rand(0,9999);

		$active_worker = CerberusApplication::getActiveWorker();
		
		// View
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;
		$defaults->is_ephemeral = true;
		
		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Calendar Events';
		$view->addParams(array(
			new DevblocksSearchCriteria(SearchFields_CalendarEvent::DATE_START,'between',array('now','+1 month')),
		), true);
		
		$required_params = array();

		// [TODO] This should still filter out on VAs
		
		$view->addParamsRequired($required_params, true);
		$view->renderSortBy = SearchFields_CalendarEvent::DATE_START;
		$view->renderSortAsc = true;
		$view->renderLimit = 10;
		$view->renderFilters = false;
		$view->renderTemplate = 'contextlinks_chooser';
		return $view;
	}
	
	function getView($context=null, $context_id=null, $options=array(), $view_id=null) {
		$view_id = !empty($view_id) ? $view_id : str_replace('.','_',$this->id);
		
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;
		
		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Calendar Events';
		
		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_CalendarEvent::CONTEXT_LINK,'=',$context),
				new DevblocksSearchCriteria(SearchFields_CalendarEvent::CONTEXT_LINK_ID,'=',$context_id),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		return $view;
	}

	function renderPeekPopup($context_id=0, $view_id='', $edit=false) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);
		
		// [TODO] Check calendar+event ownership
		
		if(!empty($context_id)) {
			if(null != ($event = DAO_CalendarEvent::get($context_id))) {  /* @var $event Model_CalendarEvent */
				$tpl->assign('event', $event);
				
				$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_CALENDAR_EVENT, false);
				$tpl->assign('custom_fields', $custom_fields);
				
				$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds(CerberusContexts::CONTEXT_CALENDAR_EVENT, $context_id);
				if(isset($custom_field_values[$context_id]))
					$tpl->assign('custom_field_values', $custom_field_values[$context_id]);
			}
		}
		
		if(empty($context_id) || is_null($event)) {
			@$calendar_id = DevblocksPlatform::importGPC($_REQUEST['calendar_id'],'integer');
			
			$event = new Model_CalendarEvent();
			$event->id = 0;
			$event->calendar_id = $calendar_id;
			$event->is_available = 0;
			$tpl->assign('event', $event);
			
			if(empty($calendar_id)) {
				$active_worker = CerberusApplication::getActiveWorker();
				$calendars = DAO_Calendar::getWriteableByActor($active_worker);
				$tpl->assign('calendars', $calendars);
			}
			
			$tpl->assign('workers', DAO_Worker::getAllActive());
		}
		
		$tpl->display('devblocks:cerberusweb.core::internal/calendar_event/peek.tpl');
	}
};
