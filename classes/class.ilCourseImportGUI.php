<?php
require_once './Services/Form/classes/class.ilPropertyFormGUI.php';
require_once './Modules/Course/classes/class.ilObjCourse.php';
require_once './Modules/CourseReference/classes/class.ilObjCourseReference.php';
require_once './Services/Object/classes/class.ilObject2.php';
require_once './Modules/Course/classes/class.ilCourseParticipants.php';
require_once './Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/CourseImport/classes/class.ilCourseImportValidator.php';

/**
 * Class ilCourseImportGUI
 *
 * @author            Theodor Truffer <tt@studer-raimann.ch>
 *
 * @ilCtrl_IsCalledBy ilCourseImportGUI: ilUIPluginRouterGUI
 * @ilCtrl_Calls      ilCourseImportGUI: ilObjCourseAdministrationGUI
 */
class ilCourseImportGUI {

	const XML_PREFIX = 'ns1';
	const IMPORT_SUCCEEDED = 'import_succeeded';
	const IMPORT_FAILED = 'import_failed';
	const TYPE_XML = 'xml';
	const TYPE_XLSX = 'xlsx';
	/**
	 * @var ilCtrl
	 */
	protected $ctrl;
	/**
	 * @var ilTemplate
	 */
	protected $tpl;
	/**
	 * @var ilCourseImportPlugin
	 */
	protected $pl;
	/**
	 * @var ilTabsGUI
	 */
	protected $tabs;
	/**
	 * @var ilLocatorGUI
	 */
	protected $ilLocator;
	/**
	 * @var ilLanguage
	 */
	protected $lng;
	/**
	 * @var ilTree
	 */
	protected $tree;
	/**
	 * $courses['updated'], $courses['new']
	 *
	 * @var array
	 */
	protected $courses;


	/**
	 * ilCourseImportGUI constructor.
	 */
	public function __construct() {
		global $tree, $ilCtrl, $tpl, $ilTabs, $ilLocator, $lng;
		$this->tree = $tree;
		$this->lng = $lng;
		$this->tabs = $ilTabs;
		$this->ctrl = $ilCtrl;
		$this->tpl = $tpl;
		$this->ilLocator = $ilLocator;
		$this->pl = ilCourseImportPlugin::getInstance();
	}


	/**
	 *
	 */
	public function executeCommand() {
		$this->checkAccess();
		$cmd = $this->ctrl->getCmd('view');
		$this->ctrl->saveParameter($this, 'ref_id');
		$this->prepareOutput();

		switch ($cmd) {
			default:
				$this->$cmd();
				break;
		}

		$this->tpl->getStandardTemplate();
		$this->tpl->show();
	}


	/**
	 * set title, description, icon, backtarget
	 */
	protected function prepareOutput() {
		$this->ctrl->setParameterByClass('ilobjcourseadministrationgui', 'ref_id', $_GET['ref_id']);
		$this->tabs->setBackTarget($this->pl->txt('back'), $this->ctrl->getLinkTargetByClass(array(
			'iladministrationgui',
			'ilobjcourseadministrationgui',
		)));
		$this->setTitleAndIcon();
		$this->setLocator();
	}


	/**
	 * invoked by prepareOutput
	 */
	protected function setTitleAndIcon() {
		$this->tpl->setTitleIcon(ilUtil::getImagePath('icon_crs.svg'));
		$this->tpl->setTitle($this->lng->txt('obj_crss'));
		$this->tpl->setDescription($this->lng->txt('obj_crss_desc'));
	}


	/**
	 * invoked by prepareOutput
	 */
	protected function setLocator() {
		$this->ctrl->setParameterByClass("ilobjsystemfoldergui", "ref_id", SYSTEM_FOLDER_ID);
		$this->ilLocator->addItem($this->lng->txt("administration"), $this->ctrl->getLinkTargetByClass(array(
			"iladministrationgui",
			"ilobjsystemfoldergui",
		), ""));
		$this->ilLocator->addItem($this->lng->txt('obj_crss'), $this->ctrl->getLinkTargetByClass(array(
			'iladministrationgui',
			'ilobjcourseadministrationgui',
		)));
		$this->tpl->setLocator();
	}


	/**
	 * default command
	 */
	protected function view() {
		$form = $this->initForm();
		$this->tpl->setContent($form->getHTML());
	}


	/**
	 * @return ilPropertyFormGUI
	 */
	protected function initForm() {
		$form = new ilPropertyFormGUI();
		$form->setTitle($this->pl->txt('form_title'));
		$form->setId('crs_import');
		$form->setFormAction($this->ctrl->getFormAction($this));

		$file_input = new ilFileInputGUI($this->pl->txt('file_input'), 'file_input');
		$file_input->setRequired(true);
		$file_input->setSuffixes(array( self::TYPE_XML, self::TYPE_XLSX ));

		$form->addItem($file_input);
		$form->addCommandButton('saveForm', $this->pl->txt('import_courses'));

		return $form;
	}


	/**
	 * form action
	 */
	public function saveForm() {
		$form = $this->initForm();
		$form->setValuesByPost();

		if ($form->checkInput()) {
			$file = $form->getFileUpload('file_input');
			$file_suffix = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
			$uploaded_file = $file['tmp_name'];
			switch ($file_suffix) {
				case self::TYPE_XML:
					$this->createCoursesFromXMLFile($uploaded_file);
					break;
				case self::TYPE_XLSX:
					require_once('class.ilCourseImportExcelConverter.php');
					$ilCourseImportExcelConverter = new ilCourseImportExcelConverter($uploaded_file);
					$error = $ilCourseImportExcelConverter->convert();
					if ($error) {
						ilUtil::sendFailure($this->pl->txt($error), true);
						$this->ctrl->redirect($this);
					}
					$this->createCoursesFromXMLString($ilCourseImportExcelConverter->getXmlText());
					break;
			}

			ilUtil::sendSuccess(sprintf($this->pl->txt(self::IMPORT_SUCCEEDED), $this->courses['created'], $this->courses['updated'], $this->courses['refs'], $this->courses['refs_del']));
		}

		$this->view();
	}


	/**
	 * @param $data
	 */
	protected function createCoursesFromXMLString($data) {
		$this->createCoursesFromXMLData(simplexml_load_string($data));
	}


	/**
	 * @param \SimpleXMLElement $data
	 */
	protected function createCoursesFromXMLData(SimpleXMLElement $data) {
		// Validate
		$validator = new ilCourseImportValidator($data);
		$validator->validate();
		if ($last_error = $validator->getLastError()) {
			ilUtil::sendFailure($this->pl->txt(self::IMPORT_FAILED) . $last_error, true);
			$this->ctrl->redirect($this, 'view');
			exit;
		}

		// Run
		foreach ($data->children(self::XML_PREFIX, true) as $item) {
			$ref_id = $item->refId->__toString() ? (int) $item->refId->__toString() : 0;
			$course = new ilObjCourse($ref_id);
			$course->setTitle($item->title->__toString());
			if ($description = $item->description->__toString()) {
				$course->setDescription($description);
			}

			//welcome mail
			if (isset($item->welcomeMail)) {
				if (in_array(strtolower($item->welcomeMail->__toString()), array("true", "1"))) {
					$course->setAutoNotification(true);
				} elseif (in_array(strtolower($item->welcomeMail->__toString()), array("false", "0"))) {
					$course->setAutoNotification(false);
				}
			}

			//create/update
			$hierarchy_id = (int)$item->hierarchy;
			if ($ref_id = $course->getRefId()) {
				$course->update();
				$parent_id = $this->tree->getParentId($ref_id);
				if ($parent_id != $hierarchy_id) {
					//move course
					$this->tree->deleteNode(1, $ref_id);
					$course->putInTree($hierarchy_id);
				}
				$this->courses['updated'] .= ilObject2::_lookupTitle(ilObject2::_lookupObjId($hierarchy_id)) . ' - ' . $course->getTitle() . '<br>';
			} else {
				$course->create();
				$course->createReference();
				$course->putInTree($hierarchy_id);
				$course->setPermissions($hierarchy_id);
				$this->courses['created'] .= ilObject2::_lookupTitle(ilObject2::_lookupObjId($hierarchy_id)) . ' - ' . $course->getTitle() . '<br>';
			}


			//direct registration
			if (in_array(strtolower($item->directRegistration->__toString()), array("true", "1"))) {
				$course->setSubscriptionType(IL_CRS_SUBSCRIPTION_DIRECT);
				$course->setSubscriptionLimitationType(IL_CRS_SUBSCRIPTION_UNLIMITED);
			} elseif (in_array(strtolower($item->directRegistration->__toString()), array("false", "0"))) {
				$course->setSubscriptionType(IL_CRS_SUBSCRIPTION_DIRECT);
				$course->setSubscriptionLimitationType(IL_CRS_SUBSCRIPTION_DEACTIVATED);
			}

			//subscription time range: if there's no timeframe defined, leave the current/default timeframe,
			//if it is defined but empty, unset the timeframe
			if ($item->courseInscriptionTimeframe) {
				if (!empty($item->courseInscriptionTimeframe) && $course->getSubscriptionLimitationType() != IL_CRS_SUBSCRIPTION_DEACTIVATED) {
					$courseInscriptionTimeframe = $item->courseInscriptionTimeframe;
					$course->setSubscriptionLimitationType(ilCourseConstants::SUBSCRIPTION_LIMITED);
					$start = new ilDateTime($courseInscriptionTimeframe->courseInscriptionBeginningDate->__toString() . ' '
						. $courseInscriptionTimeframe->courseInscriptionBeginningTime->__toString(), IL_CAL_DATETIME);
					$course->setSubscriptionStart($start->getUnixTime());
					$end = new ilDateTime($courseInscriptionTimeframe->courseInscriptionEndDate->__toString() . ' '
						. $courseInscriptionTimeframe->courseInscriptionEndTime->__toString(), IL_CAL_DATETIME);
					$course->setSubscriptionEnd($end->getUnixTime());
				}
			}

			//online
			if (isset($item->online)) {
				if ((bool) $item->online->__toString() == false || strtolower($item->online->__toString()) == 'false') {
					$course->setOfflineStatus(true);
				} elseif ((bool) $item->online->__toString() == true || strtolower($item->online->__toString()) == 'true') {
					$course->setOfflineStatus(false);
				}
			}

			//course time range:  if there's no timeframe defined, leave the current/default timeframe,
			//if it is defined but empty, unset the timeframe
			if ($item->courseTimeframe) {
				if (!empty($item->courseTimeframe)) {
					$courseTimeframe = $item->courseTimeframe;
					$start = new ilDate($courseTimeframe->courseBeginningDate->__toString(), IL_CAL_DATE);
					$course->setCourseStart($start);
					$end = new ilDate($courseTimeframe->courseEndDate->__toString(), IL_CAL_DATE);
					$course->setCourseEnd($end);
				} else {
					$course->setCourseStart(null);
					$course->setCourseEnd(null);
				}
			}

			$course->update();


			//set course admins
			$participants = ilCourseParticipants::_getInstanceByObjId($course->getId());
			$admins = $item->courseAdmins->__toString() ? explode(',', $item->courseAdmins->__toString()) : array();
			$admin_ids = array();
			foreach ($admins as $a) {
				$admin_ids[] = ilObjUser::_lookupId($a);
			}
			$existing_admins = $participants->getAdmins();

			foreach (array_diff($admin_ids, $existing_admins) as $add) {
				$participants->add($add, IL_CRS_ADMIN);
			}
			foreach (array_diff($existing_admins, $admin_ids) as $rm) {
				$participants->delete($rm);
			}

			$course->setOwner(ilObjUser::_lookupId($admins[0]));
			$course->updateOwner();


			// create references

			if ($item->references->__toString()) {
				if (strpos($item->references->__toString(), '.')) {
					$new_references = explode('.', $item->references->__toString());
				} else {
					$new_references = explode(',', $item->references->__toString());
				}
			} else {
				$new_references = array();
			}
			// delete existing, not delivered references
			if ($item->refId->__toString()) {
				$existing_references = ilObjCourseReference::_lookupSourceIds($course->getId());
				foreach ($existing_references as $key => $obj_id) {
					$parent_id = $this->tree->getParentId(array_shift(ilObjCourseReference::_getAllReferences($obj_id)));
					if (in_array($parent_id, $new_references)) {
						unset($new_references[array_search($parent_id, $new_references)]);
					} else {
						$course_ref = new ilObjCourseReference($obj_id, false);
						$course_ref->delete();
						$this->courses['refs_del'] .= ilObject2::_lookupTitle(ilObject2::_lookupObjId($parent_id)) . ' - '
							. $course->getTitle() . '<br>';
					}
				}
			}

			foreach ($new_references as $parent_id) {
				$course_ref = new ilObjCourseReference();

				$course_ref->create();
				$course_ref->createReference();

				$course_ref->putInTree($parent_id);

				$course_ref->setTargetid($course->getId());
				$course_ref->update();
				$this->courses['refs'] .= ilObject2::_lookupTitle(ilObject2::_lookupObjId($parent_id)) . ' - '
					. $course->getTitle() . '<br>';
			}
		}
	}


	/**
	 * invoked after positive validation
	 *
	 * @param $path_to_xml
	 */
	protected function createCoursesFromXMLFile($path_to_xml) {
		$this->createCoursesFromXMLData(simplexml_load_file($path_to_xml));
	}


	protected function checkAccess() {
		global $ilAccess, $ilErr;
		if (!$ilAccess->checkAccess("read", "", $_GET['ref_id'])) {
			$ilErr->raiseError($this->lng->txt("no_permission"), $ilErr->WARNING);
		}
	}
}