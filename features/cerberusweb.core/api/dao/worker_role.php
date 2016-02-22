<?php
/************************************************************************
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

class DAO_WorkerRole extends Cerb_ORMHelper {
	const _CACHE_ROLES_ALL = 'ch_roles_all';
	const _CACHE_WORKER_PRIVS_PREFIX = 'ch_privs_worker_';
	const _CACHE_WORKER_ROLES_PREFIX = 'ch_roles_worker_';
	
	const ID = 'id';
	const NAME = 'name';
	const PARAMS_JSON = 'params_json';
	
	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("INSERT INTO worker_role () ".
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
		
		// Make a diff for the requested objects in batches
		
		$chunks = array_chunk($ids, 100, true);
		while($batch_ids = array_shift($chunks)) {
			if(empty($batch_ids))
				continue;
			
			// Send events
			if($check_deltas) {
				CerberusContexts::checkpointChanges(CerberusContexts::CONTEXT_ROLE, $batch_ids);
			}
			
			// Make changes
			parent::_update($batch_ids, 'worker_role', $fields);
			
			// Send events
			if($check_deltas) {
				
				// Trigger an event about the changes
				$eventMgr = DevblocksPlatform::getEventService();
				$eventMgr->trigger(
					new Model_DevblocksEvent(
						'dao.role.update',
						array(
							'fields' => $fields,
						)
					)
				);
				
				// Log the context update
				DevblocksPlatform::markContextChanged(CerberusContexts::CONTEXT_ROLE, $batch_ids);
			}
		}
		
		// Clear cache
		self::clearCache();
	}
	
	static function getRolesByWorker($worker_id, $nocache=false) {
		$cache = DevblocksPlatform::getCacheService();
		
		if($nocache || null === ($roles = $cache->load(self::_CACHE_WORKER_ROLES_PREFIX.$worker_id))) {
			$worker = DAO_Worker::get($worker_id);
			$memberships = $worker->getMemberships();
			$all_roles = DAO_WorkerRole::getAll();
			$roles = array();

			foreach($all_roles as $role_id => $role) {
				if(
					// If this applies to everyone
					'all' == $role->params['who'] ||
					(
						// ... or any group this worker is in
						'groups' == $role->params['who'] &&
						($in_groups = array_intersect(array_keys($memberships), $role->params['who_list'])) &&
						!empty($in_groups)
					) ||
					(
						// ... or this worker is on the list
						'workers' == $role->params['who'] &&
						in_array($worker_id, $role->params['who_list'])
					)
				) {
					$roles[$role_id] = $role;
				}
			}
			
			if(!is_array($roles))
				return false;

			$cache->save($roles, self::_CACHE_WORKER_ROLES_PREFIX.$worker_id);
		}
		
		return $roles;
	}
	
	static function getCumulativePrivsByWorker($worker_id, $nocache=false) {
		$cache = DevblocksPlatform::getCacheService();

		if($nocache || null === ($privs = $cache->load(self::_CACHE_WORKER_PRIVS_PREFIX.$worker_id))) {
			if(false === ($worker = DAO_Worker::get($worker_id)))
				return false;
			
			if(false === ($memberships = $worker->getMemberships()))
				return false;
			
			if(false === ($roles = DAO_WorkerRole::getRolesByWorker($worker_id)))
				return false;
			
			$privs = array();
			
			foreach($roles as $role_id => $role) {
				switch($role->params['what']) {
					case 'all':
						$privs = array('*' => array());
						$cache->save($privs, self::_CACHE_WORKER_PRIVS_PREFIX.$worker_id);
						return;
						break;
						
					case 'itemized':
						$privs = array_merge($privs, DAO_WorkerRole::getRolePrivileges($role_id));
						break;
				}
			}
			
			$cache->save($privs, self::_CACHE_WORKER_PRIVS_PREFIX.$worker_id);
		}
		
		return $privs;
	}
	
	static function getAll($nocache=false) {
		$cache = DevblocksPlatform::getCacheService();
		if($nocache || null === ($roles = $cache->load(self::_CACHE_ROLES_ALL))) {
			$roles = DAO_WorkerRole::getWhere(
				null,
				DAO_WorkerRole::NAME,
				true,
				null,
				Cerb_ORMHelper::OPT_GET_MASTER_ONLY
			);
			
			if(!is_array($roles))
				return false;
			
			$cache->save($roles, self::_CACHE_ROLES_ALL);
		}
		
		return $roles;
	}
	
	/**
	 * @param string $where
	 * @return Model_WorkerRole[]
	 */
	static function getWhere($where=null, $sortBy=DAO_WorkerRole::NAME, $sortAsc=true, $limit=null, $options=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, name, params_json ".
			"FROM worker_role ".
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
	 * @return Model_WorkerRole	 */
	static function get($id) {
		if(empty($id))
			return null;
		
		$objects = DAO_WorkerRole::getAll();
		
		if(isset($objects[$id]))
			return $objects[$id];
		
		return null;
	}
	
	/**
	 * @param resource $rs
	 * @return Model_WorkerRole[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		if(!($rs instanceof mysqli_result))
			return false;
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_WorkerRole();
			$object->id = $row['id'];
			$object->name = $row['name'];
			
			@$params = json_decode($row['params_json'], true) or array();
			$object->params = $params;
			
			$objects[$object->id] = $object;
		}
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	static function random() {
		return self::_getRandom('worker_role');
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->ExecuteMaster(sprintf("DELETE FROM worker_role WHERE id IN (%s)", $ids_list));
		$db->ExecuteMaster(sprintf("DELETE FROM worker_role_acl WHERE role_id IN (%s)", $ids_list));

		self::clearCache();
		self::clearWorkerCache();
		
		// Fire event
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.delete',
				array(
					'context' => CerberusContexts::CONTEXT_ROLE,
					'context_ids' => $ids
				)
			)
		);
		
		return true;
	}
	
	static function getRolePrivileges($role_id) {
		// [TODO] Cache all?
		
		$db = DevblocksPlatform::getDatabaseService();
		$acl = DevblocksPlatform::getAclRegistry();
		
		$privs = array();
		
		$results = $db->GetArraySlave(sprintf("SELECT priv_id FROM worker_role_acl WHERE role_id = %d", $role_id));

		foreach($results as $row) {
			@$priv = $row['priv_id'];
			$privs[$priv] = isset($acl[$priv]) ? $acl[$priv] : array();
		}
		
		return $privs;
	}
	
	/**
	 * @param integer $role_id
	 * @param array $privileges
	 * @param boolean $replace
	 */
	static function setRolePrivileges($role_id, $privileges) {
		if(!is_array($privileges)) $privileges = array($privileges);
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($role_id))
			return;
		
		// Wipe all privileges on blank replace
		$sql = sprintf("DELETE FROM worker_role_acl WHERE role_id = %d", $role_id);
		$db->ExecuteMaster($sql);

		// Set ACLs according to the new list
		if(!empty($privileges)) {
			foreach($privileges as $priv) { /* @var $priv DevblocksAclPrivilege */
				$sql = sprintf("INSERT INTO worker_role_acl (role_id, priv_id) ".
					"VALUES (%d, %s)",
					$role_id,
					$db->qstr($priv)
				);
				$db->ExecuteMaster($sql);
			}
		}
	}
	
	static function clearCache() {
		$cache = DevblocksPlatform::getCacheService();
		$cache->remove(self::_CACHE_ROLES_ALL);
	}
	
	static function clearWorkerCache($worker_id=null) {
		$cache = DevblocksPlatform::getCacheService();
		
		if(!empty($worker_id)) {
			$cache->remove(self::_CACHE_WORKER_PRIVS_PREFIX.$worker_id);
			$cache->remove(self::_CACHE_WORKER_ROLES_PREFIX.$worker_id);
		} else {
			$workers = DAO_Worker::getAll();
			foreach($workers as $worker_id => $worker) {
				$cache->remove(self::_CACHE_WORKER_PRIVS_PREFIX.$worker_id);
				$cache->remove(self::_CACHE_WORKER_ROLES_PREFIX.$worker_id);
			}
		}
	}
};

class Model_WorkerRole {
	public $id;
	public $name;
	public $params = array();
};

class Context_WorkerRole extends Extension_DevblocksContext {
	function authorize($context_id, Model_Worker $worker) {
		// Security
		try {
			if(empty($worker))
				throw new Exception();
			
			if($worker->is_superuser)
				return TRUE;
				
		} catch (Exception $e) {
			// Fail
		}
		
		return FALSE;
	}
	
	function getRandom() {
		return DAO_WorkerRole::random();
	}
	
	function getMeta($context_id) {
		$url_writer = DevblocksPlatform::getUrlService();
		
		if(null == ($worker_role = DAO_WorkerRole::get($context_id)))
			return false;
		
		$who = sprintf("%d-%s",
			$worker_role->id,
			DevblocksPlatform::strToPermalink($worker_role->name)
		);
		
		return array(
			'id' => $worker_role->id,
			'name' => $worker_role->name,
			'permalink' => $url_writer->writeNoProxy('c=profiles&type=role&who='.$who, true),
			'updated' => 0, // [TODO]
		);
	}
	
	// [TODO] Interface
	function getDefaultProperties() {
		return array(
		);
	}
	
	function getContext($role, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Role:';
			
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext(CerberusContexts::CONTEXT_ROLE);
		
		// Polymorph
		if(is_numeric($role)) {
			$role = DAO_WorkerRole::get($role);
		} elseif($role instanceof Model_WorkerRole) {
			// It's what we want already.
		} elseif(is_array($role)) {
			$role = Cerb_ORMHelper::recastArrayToModel($role, 'Model_WorkerRole');
		} else {
			$role = null;
		}
			
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'id' => $prefix.$translate->_('common.id'),
			'name' => $prefix.$translate->_('common.name'),
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'id' => Model_CustomField::TYPE_NUMBER,
			'name' => Model_CustomField::TYPE_SINGLE_LINE,
		);
		
		// Custom field/fieldset token labels
		if(false !== ($custom_field_labels = $this->_getTokenLabelsFromCustomFields($fields, $prefix)) && is_array($custom_field_labels))
			$token_labels = array_merge($token_labels, $custom_field_labels);
		
		// Custom field/fieldset token types
		if(false !== ($custom_field_types = $this->_getTokenTypesFromCustomFields($fields, $prefix)) && is_array($custom_field_types))
			$token_types = array_merge($token_types, $custom_field_types);
		
		// Token values
		$token_values = array();
		
		$token_values['_context'] = CerberusContexts::CONTEXT_ROLE;
		$token_values['_types'] = $token_types;
		
		// Worker token values
		if(null != $role) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = $role->name;
			$token_values['id'] = $role->id;
			$token_values['name'] = $role->name;
			
			// Custom fields
			$token_values = $this->_importModelCustomFieldsAsValues($role, $token_values);
			
			// URL
// 			$url_writer = DevblocksPlatform::getUrlService();
// 			$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=profiles&type=worker&id=%d-%s",$worker->id, DevblocksPlatform::strToPermalink($worker->getName())), true);
		}
		
		return true;
	}

	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = CerberusContexts::CONTEXT_ROLE;
		$context_id = $dictionary['id'];
		
		@$is_loaded = $dictionary['_loaded'];
		$values = array();
		
		if(!$is_loaded) {
			$labels = array();
			CerberusContexts::getContext($context, $context_id, $labels, $values, null, true);
		}
		
		switch($token) {
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
	
		// View
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;
		$defaults->is_ephemeral = true;

		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Roles';
		$view->addParams(array(
			//SearchFields_Worker::IS_DISABLED => new DevblocksSearchCriteria(SearchFields_Worker::IS_DISABLED,'=',0),
		), true);
		$view->renderLimit = 10;
		$view->renderTemplate = 'contextlinks_chooser';
		
		return $view;
	}
	
	function getView($context=null, $context_id=null, $options=array(), $view_id=null) {
		$view_id = !empty($view_id) ? $view_id : str_replace('.','_',$this->id);
		
		$defaults = C4_AbstractViewModel::loadFromClass($this->getViewClass());
		$defaults->id = $view_id;

		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Roles';
		
		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_Worker::CONTEXT_LINK,'=',$context),
				new DevblocksSearchCriteria(SearchFields_Worker::CONTEXT_LINK_ID,'=',$context_id),
			);
		}

		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		return $view;
	}
}