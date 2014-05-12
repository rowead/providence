<?php
/* ----------------------------------------------------------------------
 * relationshipGeneratorPlugin.php
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2014 Whirl-i-Gig
 * This file originally contributed 2014 by Gaia Resources
 *
 * For more information visit http://www.CollectiveAccess.org
 *
 * This program is free software; you may redistribute it and/or modify it under
 * the terms of the provided license as published by Whirl-i-Gig
 *
 * CollectiveAccess is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTIES whatsoever, including any implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * This source code is free and modifiable under the terms of
 * GNU General Public License. (http://www.gnu.org/copyleft/gpl.html). See
 * the "license.txt" file for details, or visit the CollectiveAccess web site at
 * http://www.CollectiveAccess.org
 *
 * ----------------------------------------------------------------------
 */

// For the caProcessTemplateForIDs() function
require_once(__CA_APP_DIR__.'/helpers/displayHelpers.php');

/**
 * The Relationship Generator plugin uses a set of configurable `rules` to automatically manage relationships for any
 * type of model that extends BundlableLabelableBaseModelWithAttributes, but does not extend BaseRelationshipModel
 * (relationships cannot be created from other relationships).
 *
 * Each rule specifies the table(s) (i.e. model type(s)) that the relationship will be created for, and a set of
 * triggers.  Each trigger specifies a field, a match type, and some match type-specific criteria (see below).  Each
 * rule also specifies the target table and record identifier (primary key value or idno), plus the relationship type,
 * which defines the type of relationship to be managed.
 *
 * The plugin combines triggers for multiple fields according to the configured `default_field_combination_operator`,
 * or a per-rule `field_combination_operator` override.  Similarly the plugin combines matches multiple values from
 * trigger fields (where a field for a given record has more than one value) using a per-rule or per-trigger
 * `value_combination_operator`, falling back to a global `default_value_combination_operator` where no override is
 * given.  Finally, the match types can be globally set using `default_match_type`, and then overridden per-rule and
 * per-trigger with a `match_type` setting.  The combination of configurable match types and combination operators
 * gives the ability to generate (and remove) relationships based on a wide range of criteria.
 *
 * If the plugin detects a relationship that does not exist for a record being saved, but should exist according to the
 * defined rules, it will create the relationship.  This behaviour can be disabled by setting the `add_matched` config
 * item to 0.
 *
 * If the plugin detects a relationship that exists for a record being saved, but should not exist according to the
 * defined rules, it will remove the relationship.  This behaviour can be disabled by setting the `remove_unmatched`
 * config item to 0.
 *
 * The plugin will run on both initial creation of the record, and modification of an existing record.  These
 * behaviours can be disabled individually by setting `process_on_insert` and `process_on_update` config items to 0.
 *
 * The plugin will normally notify the user of any added or removed relationships.  This can be disabled by setting the
 * `notify` config item to 0.  The notification text can be overridden using the `default_add_relationship_notification`
 * and `default_remove_relationship_notification` config items for all rules, or `add_relationship_notification` and
 * `remove_relationship_notification` per-rule settings.
 */
class relationshipGeneratorPlugin extends BaseApplicationPlugin {

	/** @var Configuration */
	private $opo_config;

	/** @var NotificationManager */
	private $opo_notifications;

	public function __construct($ps_plugin_path) {
		parent::__construct();
		$this->description = _t('Automatically assigns an object to a collection, based upon rules you specify in the configuration file associated with the plugin');
		$this->opo_config = Configuration::load($ps_plugin_path . DIRECTORY_SEPARATOR . 'conf' . DIRECTORY_SEPARATOR . 'relationshipGenerator.conf');
	}

	public function checkStatus() {
		$errors = array();
		$this->_testConfigurationSection(
			_t('top level'),
			self::_getTopLevelConfigurationRequirements(),
			function ($key) { return $this->opo_config->get($key); },
			$errors
		);
		$rules = $this->opo_config->get('rules');
		if (is_array($rules) && !empty($rules)) {
			foreach ($rules as $ruleIndex => $rule) {
				$this->_testConfigurationSection(
					_t('rule %1', $ruleIndex),
					self::_getRuleConfigurationRequirements(),
					function ($key) use ($rule) { return $rule[$key]; },
					$errors
				);
				$triggers = isset($rule['triggers']) ? $rule['triggers'] : null;
				if (is_array($triggers) && !empty($triggers)) {
					foreach ($triggers as $triggerField => $trigger) {
						$this->_testConfigurationSection(
							_t('trigger field %1 on rule %2', $triggerField, $ruleIndex),
							self::_getTriggerConfigurationRequirements(),
							function ($key) use ($trigger) { return $trigger[$key]; },
							$errors
						);
					}
				}
			}
		}
		return array(
			'description' => $this->getDescription(),
			'errors' => $errors,
			'warnings' => array(),
			'available' => (bool)$this->opo_config->getBoolean('enabled')
		);
	}

	static function getRoleActionList() {
		return array();
	}

	public function hookAfterBundleInsert(&$pa_params) {
		if ($this->opo_config->getBoolean('process_on_insert') && $this->_isRelevantInstance($pa_params['instance'])) {
			$this->_process($pa_params);
		}
	}

	public function hookAfterBundleUpdate(&$pa_params) {
		if ($this->opo_config->getBoolean('process_on_update') && $this->_isRelevantInstance($pa_params['instance'])) {
			$this->_process($pa_params);
		}
	}

	/**
	 * Determine if the given object is an appropriate model to process; specifically exclude relationships as it leads
	 * to infinite recursion.
	 *
	 * @param $po_instance object
	 *
	 * @return bool True if the parameter object is the relevant type of model (i.e. bundlable and labelable, but not
	 *   a relationship model), otherwise false.
	 */
	protected static function _isRelevantInstance($po_instance) {
		return ($po_instance instanceof BundlableLabelableBaseModelWithAttributes)
			&& !($po_instance instanceof BaseRelationshipModel);
	}

	/**
	 * Main processing method, both hooks (insert and update) delegate to this.
	 *
	 * @param $pa_params array As given to the hook method.
	 */
	protected function _process(&$pa_params) {
		/** @var BundlableLabelableBaseModelWithAttributes $vo_instance */
		$vo_instance = $pa_params['instance'];

		// Configuration items used multiple times
		$vb_addMatched = $this->opo_config->getBoolean('add_matched');
		$vb_removeUnmatched = $this->opo_config->getBoolean('remove_unmatched');

		// Process each rule in order specified
		foreach ($this->opo_config->getAssoc('rules') as $va_rule) {
			$vs_relatedTable = $va_rule['related_table'];
			$vs_relatedRecord = $va_rule['related_record'];
			$vs_relationshipType = $va_rule['relationship_type'];

			// Ensure the related model record exists
			/** @var BundlableLabelableBaseModelWithAttributes $vo_relatedModel */
			$vo_relatedModel = new $vs_relatedTable(is_string($vs_relatedRecord) ? array( 'idno' => $vs_relatedRecord ) : $vs_relatedRecord);
			if (sizeof($vo_relatedModel->getFieldValuesArray()) > 0) {
				// Determine whether a relationship already exists, and whether the rule matches the source object
				$vn_relationshipId = self::_getRelationshipId($pa_params['instance'], $vs_relatedTable, $vs_relatedRecord, $vs_relationshipType);
				$vb_matches = $this->_hasMatch($pa_params, $va_rule);

				// Add relationship where one does not already exist, and the rule matches
				if ($vb_addMatched && $vb_matches && is_null($vn_relationshipId)) {
					$vo_instance->addRelationship($vs_relatedTable, $vs_relatedRecord, $vs_relationshipType);
					if ($this->opo_config->getBoolean('notify')) {
						$this->_notifications()->addNotification(
							_t(
								isset($va_rule['add_relationship_notification']) ? $va_rule['add_relationship_notification'] : $this->opo_config->get('default_add_relationship_notification'),
								$vo_relatedModel->getTypeName(),
								$vo_relatedModel->getListName()
							),
							__NOTIFICATION_TYPE_INFO__
						);
					}
				}

				// Remove relationship where one exists, and the rule does not match
				if ($vb_removeUnmatched && !$vb_matches && !is_null($vn_relationshipId)) {
					$vo_instance->removeRelationship($vs_relatedTable, $vn_relationshipId);
					if ($this->opo_config->getBoolean('notify')) {
						$this->_notifications()->addNotification(
							_t(
								isset($va_rule['remove_relationship_notification']) ? $va_rule['remove_relationship_notification'] : $this->opo_config->get('default_remove_relationship_notification'),
								$vo_relatedModel->getTypeName(),
								$vo_relatedModel->getListName()
							),
							__NOTIFICATION_TYPE_INFO__
						);
					}
				}
			}
		}
	}

	/**
	 * @return NotificationManager|null Note this function returns null if `notify` is off in configuration
	 */
	private function _notifications() {
		if (!$this->opo_notifications && $this->opo_config->getBoolean('notify')) {
			$this->opo_notifications = new NotificationManager($this->getRequest());
		}
		return $this->opo_notifications;
	}

	/**
	 * Determine whether the given hook parameters (defining the record being saved) match against the given rule.
	 *
	 * @param $pa_params array As given to the hook method.
	 * @param $pa_rule array Rule from configuration to test against.
	 *
	 * @return bool True if the parameters match against the rule, otherwise false.
	 */
	protected function _hasMatch($pa_params, $pa_rule) {
		// Skip tables that aren't relevant to this rule
		if (!in_array($pa_params['table_name'], $pa_rule['source_tables'])) {
			return false;
		}

		// Settings for the rule, falling back to top-level defaults if not configured
		$vs_fieldCombinationOperator = self::_getOperatorMethodName(isset($pa_rule['field_combination_operator']) ? $pa_rule['field_combination_operator'] : $this->opo_config->get('default_field_combination_operator'));
		$vs_defaultValueCombinationOperator = self::_getOperatorMethodName(isset($pa_rule['value_combination_operator']) ? $pa_rule['value_combination_operator'] : $this->opo_config->get('default_value_combination_operator'));
		$vs_defaultMatchType = self::_getMatchTypeMethodName(isset($pa_rule['match_type']) ? $pa_rule['match_type'] : $this->opo_config->get('default_match_type'));
		$va_defaultMatchOptions = isset($pa_rule['match_options']) ? $pa_rule['match_options'] : $this->opo_config->get('default_match_options');

		$vb_matches = self::$vs_fieldCombinationOperator();
		foreach ($pa_rule['triggers'] as $vs_field => $va_trigger) {
			// Settings for the trigger, falling back to defaults if not specified
			$va_trigger = array_merge($va_defaultMatchOptions, $va_trigger);
			$vs_valueCombinationOperator = isset($va_trigger['value_combination_operator']) ? self::_getOperatorMethodName($va_trigger['value_combination_operator']) : $vs_defaultValueCombinationOperator;
			$vs_matchType = isset($va_trigger['match_type']) ? self::_getMatchTypeMethodName($va_trigger['match_type']) : $vs_defaultMatchType;
			$va_values = self::_getValues($pa_params['table_name'], $pa_params['id'], $vs_field);

			// Track match status
			$vb_fieldMatches = self::$vs_valueCombinationOperator();
			foreach ($va_values as $vm_value) {
				$vb_fieldMatches = self::$vs_valueCombinationOperator($vb_fieldMatches, self::$vs_matchType($vm_value, $va_trigger));
			}
			$vb_matches = self::$vs_fieldCombinationOperator($vb_matches, $vb_fieldMatches);
		}
		return $vb_matches;
	}

	/**
	 * Retrieve the relationship id based on the given source instance, related table name, record identifier (primary
	 * key or idno) and relationship type.  Only relationships that match all of these properties will be returned,
	 * unless relationshipType is null, in which case relationships of any type will be returned.  If there are
	 * multiple relationships of the given type, the id of an arbitrarily selected relationship will be returned.
	 *
	 * @param $po_instance BundlableLabelableBaseModelWithAttributes
	 * @param $ps_relatedTable string
	 * @param $pm_relatedRecord string|int
	 * @param $ps_relationshipType string
	 *
	 * @return int|null
	 */
	protected static function _getRelationshipId($po_instance, $ps_relatedTable, $pm_relatedRecord, $ps_relationshipType) {
		$va_items = $po_instance->getRelatedItems($ps_relatedTable, array(
			'restrict_to_types' => array( $ps_relatedTable ),
			'restrict_to_relationship_types' => array( $ps_relationshipType ),
			'where' => is_array($pm_relatedRecord) ?
					$pm_relatedRecord : (
					is_string($pm_relatedRecord) ?
						array( 'idno' => $pm_relatedRecord ) :
						array( 'id' => $pm_relatedRecord ))
		));
		return sizeof($va_items) > 0 ? array_keys($va_items)[0] : null;
	}

	/**
	 * Get the internal operator method name for the given operator.
	 *
	 * @param $ps_operator string
	 *
	 * @return string
	 */
	protected static function _getOperatorMethodName($ps_operator) {
		return '_' . $ps_operator . 'Operator';
	}

	/**
	 * Get the internal match method name for the given match type.
	 *
	 * @param $ps_matchType string
	 *
	 * @return string
	 */
	protected static function _getMatchTypeMethodName($ps_matchType) {
		return '_' . $ps_matchType . 'Match';
	}

	/**
	 * Get array of values for the given field from the given table's record with the given id.
	 *
	 * @param $ps_table string
	 * @param $pn_id int
	 * @param $ps_field string
	 *
	 * @return array
	 */
	protected static function _getValues($ps_table, $pn_id, $ps_field) {
		$va_result = array();
		/** @var BundlableLabelableBaseModelWithAttributes $vo_object */
		$vo_object = new $ps_table($pn_id);
		$va_values = $vo_object->get($ps_field, array( 'returnAsArray' => true ));
		if (is_array($va_values)) {
			foreach ($va_values as $va_v) {
				$va_result = array_merge($va_result, $va_v);
			}
		}
		return $va_result;
	}

	/**
	 * Operator method implementing "and" operator.
	 *
	 * @param $a null|bool
	 * @param $b null|bool
	 *
	 * @return bool
	 */
	protected static function _andOperator($a = null, $b = null) {
		return (is_null($a) || is_null($b)) ? true : $a && $b;
	}

	/**
	 * Operator method implementing "or" operator.
	 *
	 * @param $a null|bool
	 * @param $b null|bool
	 *
	 * @return bool
	 */
	protected static function _orOperator($a = null, $b = null) {
		return (is_null($a) || is_null($b)) ? false : $a || $b;
	}

	/**
	 * Match method implementing "regex" match method.
	 *
	 * @param $pm_value mixed
	 * @param $pa_trigger array
	 *
	 * @return bool
	 */
	protected static function _regexMatch($pm_value, $pa_trigger) {
		$vs_modifiers = $pa_trigger['case_insensitive'] ? 'i' : '';
		$vb_match = false;
		foreach ($pa_trigger['regexes'] as $vs_pattern) {
			$vs_escapedPattern = str_replace('/', '\\/', $vs_pattern);
			$vb_match = $vb_match || preg_match('/' . $vs_escapedPattern . '/' . $vs_modifiers, strval($pm_value));
		}
		return $vb_match;
	}

	/**
	 * Match method implementing "exact" match method.
	 *
	 * @param $pm_value mixed
	 * @param $pa_trigger array
	 *
	 * @return bool
	 */
	protected static function _exactMatch($pm_value, $pa_trigger) {
		return strcmp($pa_trigger['value'], strval($pm_value)) === 0;
	}

	/**
	 * Match method implementing "caseInsensitive" match method.
	 *
	 * @param $pm_value mixed
	 * @param $pa_trigger array
	 *
	 * @return bool
	 */
	protected static function _caseInsensitiveMatch($pm_value, $pa_trigger) {
		return strcasecmp($pa_trigger['value'], strval($pm_value)) === 0;
	}

	/**
	 * @return array Definition of requirements for top-level configuration items
	 */
	private static function _getTopLevelConfigurationRequirements() {
		return array(
			'default_field_combination_operator' => array(
				'required' => true,
				'type' => 'string',
				'call' => 'operator'
			),
			'default_value_combination_operator' => array(
				'required' => true,
				'type' => 'string',
				'call' => 'operator'
			),
			'default_match_type' => array(
				'required' => true,
				'type' => 'string',
				'call' => 'match type'
			),
			'default_match_options' => array(
				'required' => true,
				'type' => 'array'
			),
			'rules' => array(
				'required' => true,
				'type' => 'array'
			)
		);
	}

	/**
	 * @return array Definition of requirements for per-rule configuration items
	 */
	private static function _getRuleConfigurationRequirements() {
		return array(
			'source_tables' => array(
				'required' => true,
				'type' => 'array'
			),
			'triggers' => array(
				'required' => true,
				'type' => 'array'
			),
			'related_table' => array(
				'required' => true,
				'type' => 'string'
			),
			'related_record' => array(
				'required' => true
			),
			'relationship_type' => array(
				'required' => true,
				'type' => 'string'
			),
			'field_combination_operator' => array(
				'type' => 'string',
				'call' => 'operator'
			),
			'value_combination_operator' => array(
				'type' => 'string',
				'call' => 'operator'
			),
			'match_type' => array(
				'type' => 'string',
				'call' => 'match type'
			)
		);
	}

	/**
	 * @return array Definition of requirements for per-trigger configuration items
	 */
	private static function _getTriggerConfigurationRequirements() {
		return array(
			'value_combination_operator' => array(
				'type' => 'string',
				'call' => 'operator'
			),
			'match_type' => array(
				'type' => 'string',
				'call' => 'match type'
			)
		);
	}

	/**
	 * Test the given configuration section against the given requirements.
	 * @param $section string Name of the section, for error messages
	 * @param $requirements array Key-value array of requirements, keys are passed to the given callback
	 * @param $getValue callback Callback which returns a value for a given key
	 * @param $errors array By-reference array of errors to append to
	 */
	private function _testConfigurationSection($section, $requirements, $getValue, &$errors) {
		foreach ($requirements as $key => $requirement) {
			$value = $getValue($key);
			if (isset($requirement['required']) && $requirement['required'] && is_null($value)) {
				$errors[] = _t('Required configuration item `%1` missing from %2', $key, $section);
			}
			if (isset($requirement['type']) && is_string($requirement['type']) && !is_null($value) && gettype($value) !== $requirement['type']) {
				$errors[] = _t('Configuration item `%1` in %2 has incorrect type %3, expected %4', $key, $section, gettype($value), $requirement['type']);
			}
			if (isset($requirement['call']) && is_string($requirement['call']) && !is_null($value)) {
				$call = '_get' . str_replace(' ', '', ucwords($requirement['call'])) . 'MethodName';
				if (!method_exists($this, $this->$call($value))) {
					$errors[] = _t('Configuration item `%1` in %2 has value "%3", which is an invalid %4', $key, $section, $value, $requirement['call']);
				}
			}
		}
	}
}
