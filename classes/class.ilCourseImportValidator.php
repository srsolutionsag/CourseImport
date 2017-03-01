<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class ilCourseImportValidator
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
class ilCourseImportValidator {

	const XSD_FILEPATH = './Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/CourseImport/resources/courses.xsd';
	const XML_PREFIX = 'ns1';
	const ERROR_XSD_VALIDATION = 'error_xsd';
	const ERROR_ILIAS_VALIDATION = 'error_ilias';
	const ERROR_ADMIN_NOT_FOUND = 'error_admin';
	const ERROR_REF_ID_NOT_FOUND = 'error_ref_id';
	const ERROR_WRONG_OBJECT_TYPE = 'error_obj_type';
	const ERROR_PARENT_NOT_FOUND = 'error_parent';
	const ERROR_PARENT_NOT_CATEGORY = 'error_not_category';
	const ERROR_PARENT_FOR_REFERENCE_NOT_FOUND = 'error_parent_ref';
	const ERROR_PARENT_FOR_REFERENCE_NOT_CONTAINER = 'error_container_ref';
	const ERROR_TIMEFRAME_INCOMPLETE = 'error_timeframe';
	const ERROR_BEGINNING_BEFORE_END = 'error_beginning_end';
	const ERROR_INSCRIPTION_TIMEFRAME_INCOMPLETE = 'error_inscription';
	const ERROR_INSCRIPTION_BEGINNING_BEFORE_END = 'error_inscription_beginning_end';
	/**
	 * @var ilCtrl
	 */
	protected $ctrl;
	/**
	 * @var ilCourseImportPlugin
	 */
	protected $pl;
	/**
	 * @var ilLanguage
	 */
	protected $lng;
	/**
	 * @var String
	 */
	protected $last_error;
	/**
	 * @var ilObjectDefinition
	 */
	protected $objDefinition;
	/**
	 * @var SimpleXMLElement
	 */
	protected $xml;


	/**
	 * ilCourseImportGUI constructor.
	 */
	public function __construct(SimpleXMLElement $xml) {
		global $ilCtrl, $lng, $objDefinition;
		$this->lng = $lng;
		$this->ctrl = $ilCtrl;
		$this->pl = ilCourseImportPlugin::getInstance();
		$this->objDefinition = $objDefinition;
		$this->xml = $xml;
	}


	/**
	 * validate fileupload with xsd and ilias specific validation
	 *
	 * @return bool
	 * @internal param $xml_file
	 * @internal param String $xml
	 */
	public function validate() {
		$this->last_error = '';
		$xml = new DOMDocument();
		$xml->loadXML($this->xml->asXML());
		if (!$xml->schemaValidate(self::XSD_FILEPATH)) {
			$this->last_error .= sprintf($this->pl->txt(self::ERROR_XSD_VALIDATION), libxml_get_last_error()->message, libxml_get_last_error()->line);
			$this->last_error .= $this->pl->txt(self::ERROR_ILIAS_VALIDATION);
		}

		$this->validateIliasSpecific();
	}


	/**
	 * invoked by $this->validate()
	 * check ilias specific invariants which can't be checked by the xsd file
	 *
	 * @internal param String $xml
	 */
	protected function validateIliasSpecific() {
		$data = $this->xml;
		foreach ($data->children(self::XML_PREFIX, true) as $item) {
			//admin login exists in ilias
			if ($item->courseAdmins->__toString()) {
				foreach (explode(',', $item->courseAdmins->__toString()) as $admin) {
					if (!ilObjUser::_loginExists($admin)) {
						$this->last_error .= sprintf($this->pl->txt(self::ERROR_ADMIN_NOT_FOUND), $admin);
					}
				}
			}

			//existing refId and object type
			if ($ref_id = (int)$item->refId) {
				if (!ilObject2::_exists($ref_id, true)) {
					$this->last_error .= sprintf($this->pl->txt(self::ERROR_REF_ID_NOT_FOUND), $ref_id);
				} elseif (ilObject2::_lookupType($ref_id, true) != 'crs') {
					$this->last_error .= sprintf($this->pl->txt(self::ERROR_WRONG_OBJECT_TYPE), $ref_id, ilObject2::_lookupType($ref_id, true));
				}
			}

			//existing parent id
			$hierarchy = (int)$item->hierarchy;
			if (!ilObject2::_exists($hierarchy, true)) {
				$this->last_error .= sprintf($this->pl->txt(self::ERROR_PARENT_NOT_FOUND), $hierarchy);
			} else {
				//parent is category
				if ($hierarchy != 1 && ilObject2::_lookupType($hierarchy, true) != 'cat') {
					$this->last_error .= sprintf($this->pl->txt(self::ERROR_PARENT_NOT_CATEGORY), $hierarchy);
				}
			}

			//existing parent id for references
			if (isset($item->references)) {
				if (strpos($item->references->__toString(), '.')) {
					$references = explode('.', $item->references->__toString());
				} else {
					$references = explode(',', $item->references->__toString());
				}
				foreach ($references as $ref) {
					if (!ilObject2::_exists($ref, true)) {
						$this->last_error .= sprintf($this->pl->txt(self::ERROR_PARENT_FOR_REFERENCE_NOT_FOUND), $ref);
					} else {
						//parent for reference is container
						if (!$this->objDefinition->isContainer(ilObject2::_lookupType($ref, true))) {
							$this->last_error .= sprintf($this->pl->txt(self::ERROR_PARENT_FOR_REFERENCE_NOT_CONTAINER), $ref);
						}
					}
				}
			}

			//if coursetimeFrame exists..
			if (!empty($item->courseTimeframe)) {
				$courseTimeframe = $item->courseTimeframe;
				//check if beginning/end date/time exist
				if (!$courseTimeframe->courseBeginningDate || !$courseTimeframe->courseEndDate) {
					$this->last_error .= sprintf($this->pl->txt(self::ERROR_TIMEFRAME_INCOMPLETE), $item->title);
				} else {
					//check if beginning is before end
					$beginning = new ilDate($courseTimeframe->courseBeginningDate->__toString(), IL_CAL_DATE);
					$end = new ilDate($courseTimeframe->courseEndDate->__toString(), IL_CAL_DATE);
					if (ilDate::_after($beginning, $end)) {
						$this->last_error .= sprintf($this->pl->txt(self::ERROR_BEGINNING_BEFORE_END), $item->title);
					}
				}
			}

			if (!empty($item->courseInscriptionTimeframe)) {
				$courseInscriptionTimeframe = $item->courseInscriptionTimeframe;
				if (!$courseInscriptionTimeframe->courseInscriptionBeginningDate || !$courseInscriptionTimeframe->courseInscriptionBeginningTime
				    || !$courseInscriptionTimeframe->courseInscriptionEndDate
				    || !$courseInscriptionTimeframe->courseInscriptionEndTime
				) {
					$this->last_error .= sprintf($this->pl->txt(self::ERROR_INSCRIPTION_TIMEFRAME_INCOMPLETE), $item->title);
				} else {
					//check if beginning is before end
					$beginning = new ilDateTime($courseInscriptionTimeframe->courseInscriptionBeginningDate->__toString() . ' '
					                            . $courseInscriptionTimeframe->courseInscriptionBeginningTime->__toString(), IL_CAL_DATETIME);
					$end = new ilDateTime($courseInscriptionTimeframe->courseInscriptionEndDate->__toString() . ' '
					                      . $courseInscriptionTimeframe->courseInscriptionEndTime->__toString(), IL_CAL_DATETIME);
					if (!ilDateTime::_before($beginning, $end)) {
						$this->last_error .= sprintf($this->pl->txt(self::ERROR_INSCRIPTION_BEGINNING_BEFORE_END), $item->title);
					}
				}
			}
		}
	}


	/**
	 * @return String
	 */
	public function getLastError() {
		return $this->last_error;
	}
}