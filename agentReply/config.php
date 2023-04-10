<?php

/**
 * Description of plugin
 * @author Joseph Philbert <joe@philbertphotos.com>
 * @license http://opensource.org/licenses/MIT
 * @version 0.1 beta
 */
 
require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.forms.php');

class agentReplyConfig extends PluginConfig
{
	function translate()
	{
		if (!method_exists('Plugin', 'translate')) {
			return array(
				function($x) { return $x; },
				function($x, $y, $n) { return $n != 1 ? $y : $x; },
			);
		}
		return Plugin::translate('alert-reply');
	}
	function getDeptList()
	{
		$dept = array();
		$sql = "SELECT `id`, `name` FROM " . TABLE_PREFIX . "department ORDER BY `name`";
		$result = db_query($sql);
		while ($row = db_fetch_array($result)) {
			$dept[$row['id']] = $row['name'];
		}
		return $dept;
	}
	
	function getOptions()
	{
		list($__, $_N) = self::translate();
		foreach ($this->getDeptList() as $id => $name) 
			$deptlist[$id] = $name;	

		$options = array();
		
		$options['Info'] = new SectionBreakField(
			array(
				'hint' => 'Configure Agent Reply Settings',
			)
		);
		$options['auto-assign'] = new BooleanField(
		array(
			'label' => $__('Auto Assign') ,
			'default' => false,
			'hint' => $__('Auto Assign to first responding agent?'),
			'configuration' => array(
				'desc' => $__('Yes/No')
			))
		);
		$options['assign-dept'] = new ChoiceField(
			array(
				'label' => $__('Choose Department'),
				'configuration' => array('multiselect' => true),
				'choices' => $deptlist,
				'default' => array_values($deptlist)[0],
				'hint' => $__('Select departments that will be affected by plug-in')
			)
		);

		$options['agent-debug-msg'] = new SectionBreakField(
			array(
				'label' => $__('Debug Mode'),
				'hint' => $__('Turns debugging on or off check the "System Logs" for entires'),
			)
		);
		$options['agent-debug'] = new BooleanField(
			array(
				'label' => $__('Debug'),
				'default' => false,
				'configuration' => array(
					'desc' => $__('Enable debugging')
				)
			)
		);

		return $options;
	}

	function pre_save(&$config, &$errors)
	{
		global $msg;
		if (!$errors) $msg = 'Configuration updated successfully';
		return true;
	}
}

