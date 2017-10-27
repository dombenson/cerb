<?php
class DAO_KbCategory extends Cerb_ORMHelper {
	const ID = 'id';
	const NAME = 'name';
	const PARENT_ID = 'parent_id';
	const UPDATED_AT = 'updated_at';
	
	const CACHE_ALL = 'ch_cache_kbcategories_all';
	
	private function __construct() {}

	static function getFields() {
		$validation = DevblocksPlatform::services()->validation();
		
		// int(10) unsigned
		$validation
			->addField(self::ID)
			->id()
			->setEditable(false)
			;
		// varchar(64)
		$validation
			->addField(self::NAME)
			->string()
			->setMaxLength(64)
			->setRequired(true)
			;
		// int(10) unsigned
		$validation
			->addField(self::PARENT_ID)
			->id()
			->addValidator($validation->validators()->contextId(CerberusContexts::CONTEXT_KB_CATEGORY, true))
			;
		// int(10) unsigned
		$validation
			->addField(self::UPDATED_AT)
			->timestamp()
			;

		return $validation->getFields();
	}

	static function create($fields) {
		$db = DevblocksPlatform::services()->database();
		
		$sql = sprintf("INSERT INTO kb_category () ".
			"VALUES ()"
		);
		$db->ExecuteMaster($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields, $check_deltas=true) {
		if(!is_array($ids))
			$ids = array($ids);
			
		if(!isset($fields[self::UPDATED_AT]))
			$fields[self::UPDATED_AT] = time();
		
		// Make a diff for the requested objects in batches
		
		$chunks = array_chunk($ids, 100, true);
		while($batch_ids = array_shift($chunks)) {
			if(empty($batch_ids))
				continue;
				
			// Send events
			if($check_deltas) {
				CerberusContexts::checkpointChanges(CerberusContexts::CONTEXT_KB_CATEGORY, $batch_ids);
			}
			
			// Make changes
			parent::_update($batch_ids, 'kb_category', $fields);
			
			// Send events
			if($check_deltas) {
				// Trigger an event about the changes
				$eventMgr = DevblocksPlatform::services()->event();
				$eventMgr->trigger(
					new Model_DevblocksEvent(
						'dao.kb_category.update',
						array(
							'fields' => $fields,
						)
					)
				);
				
				// Log the context update
				DevblocksPlatform::markContextChanged(CerberusContexts::CONTEXT_KB_CATEGORY, $batch_ids);
			}
		}
		
		self::clearCache();
	}
	
	static function updateWhere($fields, $where) {
		parent::_updateWhere('kb_category', $fields, $where);
		self::clearCache();
	}
	
	static function getTreeMap($prune_empty=false) {
		$db = DevblocksPlatform::services()->database();
		
		$categories = self::getWhere();
		$tree = array();

		// Fake recursion
		foreach($categories as $cat_id => $cat) {
			$pid = $cat->parent_id;
			if(!isset($tree[$pid])) {
				$tree[$pid] = array();
			}
				
			$tree[$pid][$cat_id] = 0;
		}
		
		// Add counts (and bubble up)
		$sql = "SELECT count(*) AS hits, kb_category_id FROM kb_article_to_category GROUP BY kb_category_id";
		$rs = $db->ExecuteSlave($sql);
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		while($row = mysqli_fetch_assoc($rs)) {
			$count_cat = intval($row['kb_category_id']);
			$count_hits = intval($row['hits']);
			
			$pid = $count_cat;
			while($pid) {
				@$parent_id = $categories[$pid]->parent_id;
				$tree[$parent_id][$pid] += $count_hits;
				$pid = $parent_id;
			}
		}
		
		mysqli_free_result($rs);
		
		// Filter out empty categories on public
		if($prune_empty) {
			foreach($tree as $parent_id => $nodes) {
				$tree[$parent_id] = array_filter($nodes, function($count) {
					return !empty($count);
				});
			}
		}
		
		return $tree;
	}

	/**
	 *
	 * @param bool $nocache
	 * @return Model_KbCategory[]
	 */
	static function getAll($nocache=false) {
		$cache = DevblocksPlatform::services()->cache();
		if($nocache || null === ($categories = $cache->load(self::CACHE_ALL))) {
			$categories = self::getWhere(
				null,
				DAO_KbCategory::NAME,
				true,
				null,
				Cerb_ORMHelper::OPT_GET_MASTER_ONLY
			);
			
			if(!is_array($categories))
				return false;
			
			$cache->save($categories, self::CACHE_ALL);
		}
		
		return $categories;
	}
	
	static function getTopics() {
		$categories = self::getAll();
		
		if(is_array($categories))
		foreach($categories as $key => $category) { /* @var $category Model_KbCategory */
			if(0 != $category->parent_id)
				unset($categories[$key]);
		}
		
		return $categories;
	}
	
	static function getTree($root=0) {
		$levels = array();
		$map = self::getTreeMap();
		
		self::_recurseTree($levels,$map,$root);
		
		return $levels;
	}
	
	// [TODO] Move to Model_KbCategoryTree?
	static private function _recurseTree(&$levels,$map,$node=0,$level=-1) {
		if(!isset($map[$node]) || empty($map[$node]))
			return;

		$level++; // we're dropping down a node

		// recurse through children
		foreach($map[$node] as $pid => $children) {
			$levels[$pid] = $level;
			self::_recurseTree($levels,$map,$pid,$level);
		}
	}
	
	static public function getAncestors($root_id, $categories=null) {
		if(empty($categories))
			$categories = DAO_KbCategory::getAll();
		
		if(!isset($categories[$root_id]))
			return false;
			
		$breadcrumb = [];
		
		$pid = $root_id;
		
		while($pid) {
			// Kill infinite loops from poorly constructed trees
			if(isset($breadcrumb[$pid]))
				break;
			
			$breadcrumb[$pid] = $categories[$pid];
			
			if(isset($categories[$pid])) {
				$pid = $categories[$pid]->parent_id;
			} else {
				$pid = 0;
			}
		}
		
		return array_reverse($breadcrumb, true);
	}
	
	static public function getDescendents($root_id) {
		$tree = self::getTree($root_id);
		@$ids = array_merge(array($root_id),array_keys($tree));
		return $ids;
	}
	
	/**
	 * @param string $where
	 * @return Model_KbCategory[]
	 */
	static function getWhere($where=null, $sortBy=DAO_KbCategory::NAME, $sortAsc=true, $limit=null, $options=null) {
		$db = DevblocksPlatform::services()->database();
		
		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, parent_id, name, updated_at ".
			"FROM kb_category ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;

		if($options & Cerb_ORMHelper::OPT_GET_MASTER_ONLY) {
			$rs = $db->ExecuteMaster($sql, _DevblocksDatabaseManager::OPT_NO_READ_AFTER_WRITE);
		} else {
			$rs = $db->ExecuteSlave($sql);
		}
		
		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_KbCategory
	 */
	static function get($id) {
		$objects = self::getAll();
		
		if(empty($id))
			return null;
		
		if(isset($objects[$id]))
			return $objects[$id];
		
		return null;
	}
	
	/**
	 * 
	 * @param array $ids
	 * @return Model_KbCategory[]
	 */
	static function getIds($ids) {
		if(!is_array($ids))
			$ids = array($ids);

		if(empty($ids))
			return [];

		if(!method_exists(get_called_class(), 'getWhere'))
			return [];

		$db = DevblocksPlatform::services()->database();

		$ids = DevblocksPlatform::importVar($ids, 'array:integer');

		$models = [];

		$results = static::getWhere(sprintf("id IN (%s)",
			implode(',', $ids)
		));

		// Sort $models in the same order as $ids
		foreach($ids as $id) {
			if(isset($results[$id]))
				$models[$id] = $results[$id];
		}

		unset($results);

		return $models;
	}
	
	/**
	 * @param resource $rs
	 * @return Model_KbCategory[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_KbCategory();
			$object->id = intval($row['id']);
			$object->parent_id = intval($row['parent_id']);
			$object->name = $row['name'];
			$object->updated_at = intval($row['updated_at']);
			$objects[$object->id] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::services()->database();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM kb_category WHERE id IN (%s)", $ids_list));
		
		$db->ExecuteMaster(sprintf("DELETE FROM kb_article_to_category WHERE kb_category_id IN (%s)", $ids_list));
		
		self::clearCache();
		
		return true;
	}
	
	public static function random() {
		return self::_getRandom('kb_category');
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_KbCategory::getFields();
		
		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, 'SearchFields_KbCategory', $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"kbc.id as %s, ".
			"kbc.name as %s, ".
			"kbc.updated_at as %s, ".
			"kbc.parent_id as %s ",
				SearchFields_KbCategory::ID,
				SearchFields_KbCategory::NAME,
				SearchFields_KbCategory::UPDATED_AT,
				SearchFields_KbCategory::PARENT_ID
			);
			
		$join_sql = "FROM kb_category kbc ";

		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = self::_buildSortClause($sortBy, $sortAsc, $fields, $select_sql, 'SearchFields_KbCategory');
		
		$result = array(
			'primary_table' => 'kbc',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'sort' => $sort_sql,
		);
		
		return $result;
	}
	
	static function search($columns, $params, $limit=10, $page=0, $sortBy=null, $sortAsc=null, $withCounts=true) {
		$db = DevblocksPlatform::services()->database();

		// Build search queries
		$query_parts = self::getSearchQueryComponents($columns,$params,$sortBy,$sortAsc);

		$select_sql = $query_parts['select'];
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
		$sort_sql = $query_parts['sort'];
		
		$sql =
			$select_sql.
			$join_sql.
			$where_sql.
			$sort_sql;
		
		if(false == ($rs = $db->SelectLimit($sql,$limit,$page*$limit)))
			return false;
		
		$results = array();
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		while($row = mysqli_fetch_assoc($rs)) {
			$id = intval($row[SearchFields_KbCategory::ID]);
			$results[$id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					"SELECT COUNT(kbc.id) ".
					$join_sql.
					$where_sql;
				$total = $db->GetOneSlave($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
		return array($results,$total);
	}
	
	static public function clearCache() {
		$cache = DevblocksPlatform::services()->cache();
		$cache->remove(self::CACHE_ALL);
	}
};

class SearchFields_KbCategory extends DevblocksSearchFields {
	// Table
	const ID = 'kbc_id';
	const PARENT_ID = 'kbc_parent_id';
	const NAME = 'kbc_name';
	const UPDATED_AT = 'kbc_updated_at';
	
	const VIRTUAL_CONTEXT_LINK = '*_context_link';
	const VIRTUAL_HAS_FIELDSET = '*_has_fieldset';
	const VIRTUAL_WATCHERS = '*_workers';
	
	static private $_fields = null;
	
	static function getPrimaryKey() {
		return 'kbc.id';
	}
	
	static function getCustomFieldContextKeys() {
		return array(
			CerberusContexts::CONTEXT_KB_CATEGORY => new DevblocksSearchFieldContextKeys('kbc.id', self::ID),
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
			self::ID => new DevblocksSearchField(self::ID, 'kbc', 'id', $translate->_('common.id'), null, true),
			self::PARENT_ID => new DevblocksSearchField(self::PARENT_ID, 'kbc', 'parent_id', $translate->_('common.parent'), null, true),
			self::NAME => new DevblocksSearchField(self::NAME, 'kbc', 'name', $translate->_('common.name'), null, true),
			self::UPDATED_AT => new DevblocksSearchField(self::UPDATED_AT, 'kbc', 'updated_at', $translate->_('common.updated'), null, true),
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

class Context_KbCategory extends Extension_DevblocksContext implements IDevblocksContextProfile, IDevblocksContextPeek {
	static function isReadableByActor($models, $actor) {
		// Everyone can read
		return CerberusContexts::allowEverything($models);
	}
	
	static function isWriteableByActor($models, $actor) {
		// Everyone can modify
		return CerberusContexts::allowEverything($models);
	}
	
	function getRandom() {
		return DAO_KbCategory::random();
	}
	
	function profileGetUrl($context_id) {
		if(empty($context_id))
			return '';
	
		$url_writer = DevblocksPlatform::services()->url();
		$url = $url_writer->writeNoProxy('c=profiles&type=kb_category&id='.$context_id, true);
		return $url;
	}
	
	function getMeta($context_id) {
		$category = DAO_KbCategory::get($context_id);
		$url_writer = DevblocksPlatform::services()->url();
		
		return array(
			'id' => $category->id,
			'name' => $category->name,
			'permalink' => $url_writer->writeNoProxy(sprintf("c=profiles&type=kb_category&id=%d-%s", $category->id, DevblocksPlatform::strToPermalink($category->name), true)),
			'updated' => 0, // [TODO]
		);
	}
	
	function getDefaultProperties() {
		return array(
			'updated_at',
		);
	}
	
	function getContext($category, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Category:';
		
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_KB_CATEGORY);
		
		// Polymorph
		if(is_numeric($category)) {
			$category = DAO_KbCategory::get($category);
		} elseif($category instanceof Model_KbCategory) {
			// It's what we want already.
		} elseif(is_array($category)) {
			$category = Cerb_ORMHelper::recastArrayToModel($category, 'Model_KbCategory');
		} else {
			$category = null;
		}
		/* @var $category Model_KbCategory */
			
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'id' => $prefix.$translate->_('common.id'),
			'name' => $prefix.$translate->_('common.name'),
			'parent_id' => $prefix.$translate->_('common.parent'),
			'updated_at' => $prefix.$translate->_('common.updated'),
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'id' => Model_CustomField::TYPE_NUMBER,
			'name' => Model_CustomField::TYPE_SINGLE_LINE,
			'parent_id' => Model_CustomField::TYPE_NUMBER,
			'updated_at' => Model_CustomField::TYPE_DATE,
		);
		
		// Custom field/fieldset token labels
		if(false !== ($custom_field_labels = $this->_getTokenLabelsFromCustomFields($fields, $prefix)) && is_array($custom_field_labels))
			$token_labels = array_merge($token_labels, $custom_field_labels);
		
		// Custom field/fieldset token types
		if(false !== ($custom_field_types = $this->_getTokenTypesFromCustomFields($fields, $prefix)) && is_array($custom_field_types))
			$token_types = array_merge($token_types, $custom_field_types);
		
		// Token values
		$token_values = array();
		
		$token_values['_context'] = CerberusContexts::CONTEXT_KB_CATEGORY;
		$token_values['_types'] = $token_types;
		
		// Token values
		if(null != $category) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = $category->name;
			$token_values['id'] = $category->id;
			$token_values['name'] = $category->name;
			$token_values['parent_id'] = $category->parent_id;
			$token_values['updated_at'] = $category->updated_at;
			
			// Custom fields
			$token_values = $this->_importModelCustomFieldsAsValues($category, $token_values);
		}
		
		// [TODO] Parent merge
		
		return true;
	}
	
	function getKeyToDaoFieldMap() {
		return [
			'id' => DAO_KbCategory::ID,
			'name' => DAO_KbCategory::NAME,
			'parent_id' => DAO_KbCategory::PARENT_ID,
			'updated_at' => DAO_KbCategory::UPDATED_AT,
		];
	}

	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = CerberusContexts::CONTEXT_KB_CATEGORY;
		$context_id = $dictionary['id'];
		
		@$is_loaded = $dictionary['_loaded'];
		$values = array();
		
		if(!$is_loaded) {
			$labels = array();
			CerberusContexts::getContext($context, $context_id, $labels, $values, null, true, true);
		}
		
		switch($token) {
			case 'links':
				$links = $this->_lazyLoadLinks($context, $context_id);
				$values = array_merge($values, $links);
				break;
			
			case 'watchers':
				$watchers = array(
					$token => CerberusContexts::getWatchers($context, $context_id, true),
				);
				$values = array_merge($values, $watchers);
				break;
				
			default:
				if(DevblocksPlatform::strStartsWith($token, 'custom_')) {
					$fields = $this->_lazyLoadCustomFields($token, $context, $context_id);
					$values = array_merge($values, $fields);
				}
				break;
		}
		
		return $values;
	}
	
	function getChooserView($view_id=null) {
		$active_worker = CerberusApplication::getActiveWorker();

		if(empty($view_id))
			$view_id = 'chooser_'.str_replace('.','_',$this->id).time().mt_rand(0,9999);
		
		// View
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;
		$defaults->is_ephemeral = true;

		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->addParams(array(), true);
		$view->renderSortBy = SearchFields_KbCategory::NAME;
		$view->renderSortAsc = false;
		$view->renderLimit = 10;
		$view->renderTemplate = 'contextlinks_chooser';
		
		return $view;
	}
	
	function getView($context=null, $context_id=null, $options=array(), $view_id=null) {
		$view_id = !empty($view_id) ? $view_id : str_replace('.','_',$this->id);
		
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;

		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		//$view->name = 'Calls';
		
		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				//new DevblocksSearchCriteria(SearchFields_KbCategory::VIRTUAL_CONTEXT_LINK,'in',array($context.':'.$context_id)),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		return $view;
	}
	
	function renderPeekPopup($context_id=0, $view_id='', $edit=false) {
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('view_id', $view_id);
		
		$context = CerberusContexts::CONTEXT_KB_CATEGORY;
		$model = null;
		
		if(!empty($context_id)) {
			$model = DAO_KbCategory::get($context_id);
		}
		
		if(!$model) {
			$model = new Model_KbCategory();
		}
		
		if(empty($context_id) || $edit) {
			if(!empty($edit)) {
				$tokens = explode(' ', trim($edit));
				
				foreach($tokens as $token) {
					@list($k,$v) = explode(':', $token);
					
					if($v)
					switch($k) {
						case 'parent.id':
							$model->parent_id = $v;
							break;
					}
				}
			}
			
			if(isset($model))
				$tpl->assign('model', $model);
			
			// Custom fields
			$custom_fields = DAO_CustomField::getByContext($context, false);
			$tpl->assign('custom_fields', $custom_fields);
	
			$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds($context, $context_id);
			if(isset($custom_field_values[$context_id]))
				$tpl->assign('custom_field_values', $custom_field_values[$context_id]);
			
			$types = Model_CustomField::getTypes();
			$tpl->assign('types', $types);
			
			// View
			$tpl->assign('id', $context_id);
			$tpl->assign('view_id', $view_id);
			$tpl->display('devblocks:cerberusweb.kb::kb/category/peek_edit.tpl');
			
		} else {
			// Counts
			$activity_counts = array(
				//'comments' => DAO_Comment::count($context, $context_id),
			);
			$tpl->assign('activity_counts', $activity_counts);
			
			// Links
			$links = array(
				$context => array(
					$context_id => 
						DAO_ContextLink::getContextLinkCounts(
							$context,
							$context_id,
							array(CerberusContexts::CONTEXT_CUSTOM_FIELDSET)
						),
				),
			);
			$tpl->assign('links', $links);
			
			// Timeline
			if($context_id) {
				$timeline_json = Page_Profiles::getTimelineJson(Extension_DevblocksContext::getTimelineComments($context, $context_id));
				$tpl->assign('timeline_json', $timeline_json);
			}

			// Context
			if(false == ($context_ext = Extension_DevblocksContext::get($context)))
				return;
			
			// Dictionary
			$labels = [];
			$values = [];
			CerberusContexts::getContext($context, $model, $labels, $values, '', true, false);
			$dict = DevblocksDictionaryDelegate::instance($values);
			$tpl->assign('dict', $dict);
			
			$properties = $context_ext->getCardProperties();
			$tpl->assign('properties', $properties);
			
			$ancestors = DAO_KbCategory::getAncestors($dict->id);
			$tpl->assign('breadcrumbs', $ancestors);
			
			$tpl->display('devblocks:cerberusweb.kb::kb/category/peek.tpl');
		}
	}
};

class Model_KbCategory {
	public $id = 0;
	public $parent_id = 0;
	public $name;
	public $updated_at = 0;
};

class View_KbCategory extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
	const DEFAULT_ID = 'kb_categories';

	function __construct() {
		$this->id = self::DEFAULT_ID;
		$this->name = DevblocksPlatform::translateCapitalized('kb.common.knowledgebase_categories');
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_KbCategory::ID;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_KbCategory::NAME,
			SearchFields_KbCategory::PARENT_ID,
			SearchFields_KbCategory::UPDATED_AT,
		);
		$this->addColumnsHidden(array(
			SearchFields_KbCategory::VIRTUAL_CONTEXT_LINK,
			SearchFields_KbCategory::VIRTUAL_HAS_FIELDSET,
			SearchFields_KbCategory::VIRTUAL_WATCHERS,
		));
		
		$this->addParamsHidden(array(
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_KbCategory::search(
			$this->view_columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
		
		$this->_lazyLoadCustomFieldsIntoObjects($objects, 'SearchFields_KbCategory');
		
		return $objects;
	}
	
	function getDataAsObjects($ids=null) {
		return $this->_getDataAsObjects('DAO_KbCategory', $ids);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_KbCategory', $size);
	}

	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
		$fields = [];

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// Fields
//				case SearchFields_KbCategory::EXAMPLE:
//					$pass = true;
//					break;
					
				// Virtuals
				case SearchFields_KbCategory::VIRTUAL_CONTEXT_LINK:
				case SearchFields_KbCategory::VIRTUAL_HAS_FIELDSET:
				case SearchFields_KbCategory::VIRTUAL_WATCHERS:
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
		$counts = [];
		$fields = $this->getFields();
		$context = CerberusContexts::CONTEXT_KB_CATEGORY;

		if(!isset($fields[$column]))
			return [];
		
		switch($column) {
//			case SearchFields_KbCategory::EXAMPLE_BOOL:
//				$counts = $this->_getSubtotalCountForBooleanColumn($context, $column);
//				break;

//			case SearchFields_KbCategory::EXAMPLE_STRING:
//				$counts = $this->_getSubtotalCountForStringColumn($context, $column);
//				break;
				
			case SearchFields_KbCategory::VIRTUAL_CONTEXT_LINK:
				$counts = $this->_getSubtotalCountForContextLinkColumn($context, $column);
				break;

			case SearchFields_KbCategory::VIRTUAL_HAS_FIELDSET:
				$counts = $this->_getSubtotalCountForHasFieldsetColumn($context, $column);
				break;
				
			case SearchFields_KbCategory::VIRTUAL_WATCHERS:
				$counts = $this->_getSubtotalCountForWatcherColumn($context, $column);
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
		$search_fields = SearchFields_KbCategory::getFields();
	
		$fields = array(
			'text' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_KbCategory::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'id' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_KbCategory::ID),
					'examples' => [
						['type' => 'chooser', 'context' => CerberusContexts::CONTEXT_KB_CATEGORY, 'q' => ''],
					]
				),
			'name' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_KbCategory::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'updated' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_KbCategory::UPDATED_AT),
				),
			'watchers' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_WORKER,
					'options' => array('param_key' => SearchFields_KbCategory::VIRTUAL_WATCHERS),
				),
		);
		
		// Add quick search links
		
		$fields = self::_appendVirtualFiltersFromQuickSearchContexts('links', $fields, 'links');
		
		// Add searchable custom fields
		
		$fields = self::_appendFieldsFromQuickSearchContext(CerberusContexts::CONTEXT_KB_CATEGORY, $fields, null);
		
		// Add is_sortable
		
		$fields = self::_setSortableQuickSearchFields($fields, $search_fields);
		
		// Sort by keys
		ksort($fields);
		
		return $fields;
	}	
	
	function getParamFromQuickSearchFieldTokens($field, $tokens) {
		switch($field) {
			default:
				if($field == 'links' || substr($field, 0, 6) == 'links.')
					return DevblocksSearchCriteria::getContextLinksParamFromTokens($field, $tokens);
				
				$search_fields = $this->getQuickSearchFields();
				return DevblocksSearchCriteria::getParamFromQueryFieldTokens($field, $tokens, $search_fields);
				break;
		}
		
		return false;
	}
	
	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		// Custom fields
		$custom_fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_KB_CATEGORY);
		$tpl->assign('custom_fields', $custom_fields);

		$tpl->assign('view_template', 'devblocks:cerberusweb.kb::kb/category/view.tpl');
		$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('id', $this->id);

		switch($field) {
			case SearchFields_KbCategory::NAME:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
				
			case SearchFields_KbCategory::ID:
			case SearchFields_KbCategory::PARENT_ID:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
				
			case 'placeholder_bool':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
				
			case SearchFields_KbCategory::UPDATED_AT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
				
			case SearchFields_KbCategory::VIRTUAL_CONTEXT_LINK:
				$contexts = Extension_DevblocksContext::getAll(false);
				$tpl->assign('contexts', $contexts);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_link.tpl');
				break;
				
			case SearchFields_KbCategory::VIRTUAL_HAS_FIELDSET:
				$this->_renderCriteriaHasFieldset($tpl, CerberusContexts::CONTEXT_KB_CATEGORY);
				break;
				
			case SearchFields_KbCategory::VIRTUAL_WATCHERS:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_worker.tpl');
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
			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function renderVirtualCriteria($param) {
		$key = $param->field;
		
		$translate = DevblocksPlatform::getTranslationService();
		
		switch($key) {
			case SearchFields_KbCategory::VIRTUAL_CONTEXT_LINK:
				$this->_renderVirtualContextLinks($param);
				break;
				
			case SearchFields_KbCategory::VIRTUAL_HAS_FIELDSET:
				$this->_renderVirtualHasFieldset($param);
				break;
			
			case SearchFields_KbCategory::VIRTUAL_WATCHERS:
				$this->_renderVirtualWatchers($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_KbCategory::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_KbCategory::NAME:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case SearchFields_KbCategory::ID:
			case SearchFields_KbCategory::PARENT_ID:
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case SearchFields_KbCategory::UPDATED_AT:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case 'placeholder_bool':
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			case SearchFields_KbCategory::VIRTUAL_CONTEXT_LINK:
				@$context_links = DevblocksPlatform::importGPC($_REQUEST['context_link'],'array',[]);
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$context_links);
				break;
				
			case SearchFields_KbCategory::VIRTUAL_HAS_FIELDSET:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',[]);
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$options);
				break;
				
			case SearchFields_KbCategory::VIRTUAL_WATCHERS:
				@$worker_ids = DevblocksPlatform::importGPC($_REQUEST['worker_id'],'array',[]);
				$criteria = new DevblocksSearchCriteria($field,$oper,$worker_ids);
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