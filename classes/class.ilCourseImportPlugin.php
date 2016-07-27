<?php
include_once("./Services/UIComponent/classes/class.ilUserInterfaceHookPlugin.php");
/**
 * Class ilCourseImportPlugin
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
class ilCourseImportPlugin extends ilUserInterfaceHookPlugin
{
	/**
	 * @var ilCourseImportPlugin
	 */
	protected static $instance;

	/**
	 * @return ilCourseImportPlugin
	 */
	public static function getInstance() {
		if(is_null(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	function getPluginName()
	{
		return "CourseImport";
	}

}