<?php
require_once './Services/Form/classes/class.ilPropertyFormGUI.php';
require_once './Modules/Course/classes/class.ilObjCourse.php';
require_once './Modules/CourseReference/classes/class.ilObjCourseReference.php';
require_once './Services/Object/classes/class.ilObject2.php';
require_once './Modules/Course/classes/class.ilCourseParticipants.php';
/**
 * Class ilCourseImportGUI
 *
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 *
 * @ilCtrl_IsCalledBy ilCourseImportGUI: ilUIPluginRouterGUI
 * @ilCtrl_Calls ilCourseImportGUI: ilObjCourseAdministrationGUI
 */
class ilCourseImportGUI
{

	const XSD_FILEPATH = './Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/CourseImport/resources/courses.xsd';
	const XML_PREFIX = 'ns1';

	const ERROR_ADMIN_NOT_FOUND = 'error_admin';
	const ERROR_REF_ID_NOT_FOUND = 'error_ref_id';
	const ERROR_WRONG_OBJECT_TYPE = 'error_obj_type';
	const ERROR_PARENT_NOT_FOUND = 'error_parent';
	const ERROR_PARENT_FOR_REFERENCE_NOT_FOUND = 'error_parent_ref';
	const ERROR_TIMEFRAME_INCOMPLETE = 'error_timeframe';
	const ERROR_INSCRIPTION_TIMEFRAME_INCOMPLETE = 'error_inscription';

	const IMPORT_SUCCEEDED = 'import_succeeded';


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
	 * @var String
	 */
	protected $last_error;
	/**
	 * $courses['updated'], $courses['new']
	 * @var array
	 */
	protected $courses;

	/**
	 * ilCourseImportGUI constructor.
	 */
	public function __construct()
	{
		global $tree, $ilCtrl, $tpl, $ilTabs, $ilLocator, $lng;
		$this->tree = $tree;
		$this->lng = $lng;
		$this->tabs = $ilTabs;
		$this->ctrl = $ilCtrl;
		$this->tpl = $tpl;
		$this->ilLocator = $ilLocator;
		$this->pl = ilCourseImportPlugin::getInstance();
		$this->courses = array('new' => '', 'updated' => '');
	}

	/**
	 *
	 */
	public function executeCommand() {
		//$this->checkAccess();
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
		$this->tabs->setBackTarget($this->pl->txt('back'), $this->ctrl->getLinkTargetByClass(array('iladministrationgui', 'ilobjcourseadministrationgui')));
		$this->setTitleAndIcon();
		$this->setLocator();
	}

	/**
	 * invoked by prepareOutput
	 */
	protected function setTitleAndIcon()
	{
		$this->tpl->setTitleIcon(ilUtil::getImagePath('icon_crs.svg'));
		$this->tpl->setTitle($this->lng->txt('obj_crss'));
		$this->tpl->setDescription($this->lng->txt('obj_crss_desc'));
	}

	/**
	 * invoked by prepareOutput
	 */
	protected function setLocator()
	{
		$this->ctrl->setParameterByClass("ilobjsystemfoldergui",
			"ref_id", SYSTEM_FOLDER_ID);
		$this->ilLocator->addItem($this->lng->txt("administration"),
			$this->ctrl->getLinkTargetByClass(array("iladministrationgui", "ilobjsystemfoldergui"), "")
		);
		$this->ilLocator->addItem($this->lng->txt('obj_crss'),
			$this->ctrl->getLinkTargetByClass(array('iladministrationgui', 'ilobjcourseadministrationgui')));
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
		$file_input->setSuffixes(array('xml'));

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
			$xml_file = $file['tmp_name'];
			$this->validate($xml_file);
			$this->createCourses($xml_file);
			ilUtil::sendSuccess(sprintf($this->pl->txt(self::IMPORT_SUCCEEDED),
				$this->courses['created'], $this->courses['updated']));
		}

		$this->view();
	}

	/**
	 * validate fileupload with xsd and ilias specific validation
	 *
	 * @param $xml_file
	 * @return bool
	 * @internal param String $xml
	 */
	protected function validate($xml_file) {
		$this->last_error = '';
		$xml = new DOMDocument();
		$xml->load($xml_file);
		if (! $xml->schemaValidate(self::XSD_FILEPATH)) {
			$this->last_error .= $this->pl->txt('error_xsd_validation') . '<br>' .
				libxml_get_last_error()->message . '<br>' .
				libxml_get_last_error()->level . '<br>' .
				libxml_get_last_error()->line . '<br>';
		}

		$this->validateIliasSpecific($xml_file);

		if ($this->last_error) {
			ilUtil::sendFailure($this->last_error, true);
			$this->ctrl->redirect($this, 'view');
		}

		return true;
	}

	/**
	 * invoked by $this->validate()
	 * check ilias specific invariants which can't be checked by the xsd file
	 *
	 * @param String $xml
	 */
	protected function validateIliasSpecific($xml) {
		$data = simplexml_load_file($xml);
		foreach ($data->children(self::XML_PREFIX, true) as $item) {
			//admin login exists in ilias
			foreach (explode(',', $item->courseAdmins->__toString()) as $admin) {
				if (!ilObjUser::_loginExists($admin)) {
					$this->last_error .= $this->pl->txt(self::ERROR_ADMIN_NOT_FOUND) . $admin . '<br>';
				}
			}

			//existing refId and object type
			if ($ref_id = (int) $item->refId) {
				if (!ilObject2::_exists($ref_id, true)) {
					$this->last_error .= $this->pl->txt(self::ERROR_REF_ID_NOT_FOUND) . $ref_id . '<br>';
				} elseif (ilObject2::_lookupType($ref_id, true) != 'crs') {
					$this->last_error .= $this->pl->txt(self::ERROR_WRONG_OBJECT_TYPE)
						. $ref_id . ', ' . ilObject2::_lookupType($ref_id, true) . '<br>';
				}
			}

			//existing parent id TODO: is container?
			if (!ilObject2::_exists($item->hierarchy, true)) {
				$this->last_error .= $this->pl->txt(self::ERROR_PARENT_NOT_FOUND) . $item->hierarchy . '<br>';
			}

			//existing parent id for references TODO: is container?
			if (isset($item->references)) {
				foreach (explode(',', $item->references->__toString()) as $ref){
					if (!ilObject2::_exists($ref, true)) {
						$this->last_error .= $this->pl->txt(self::ERROR_PARENT_FOR_REFERENCE_NOT_FOUND) . $ref . '<br>';
					}
				}
			}

			//if coursetimeFrame exists, check if beginning/end date/time exist
			if (!empty($item->courseTimeframe)) {
				$courseTimeframe = $item->courseTimeframe;
				if (!$courseTimeframe->courseBeginningDate || !$courseTimeframe->courseBeginningTime ||
					!$courseTimeframe->courseEndDate || !$courseTimeframe->courseEndTime) {
					$this->last_error .= $this->pl->txt(self::ERROR_TIMEFRAME_INCOMPLETE) . $item->title . '<br>';
				}
			}

			if (!empty($item->courseInscriptionTimeframe)) {
				$courseInscriptionTimeframe = $item->courseInscriptionTimeframe;
				if (!$courseInscriptionTimeframe->courseInscriptionBeginningDate || !$courseInscriptionTimeframe->courseInscriptionBeginningTime ||
					!$courseInscriptionTimeframe->courseInscriptionEndDate || !$courseInscriptionTimeframe->courseInscriptionEndTime) {
					$this->last_error .= $this->pl->txt(self::ERROR_INSCRIPTION_TIMEFRAME_INCOMPLETE) . $item->title . '<br>';
				}
			}

		}
		//TODO: dates/times -> check start before end
	}

	/**
	 * invoked after positive validation
	 *
	 * @param $xml
	 */
	protected function createCourses($xml) {
		$data = simplexml_load_file($xml);
		foreach ($data->children(self::XML_PREFIX, true) as $item) {
			$course = new ilObjCourse($item->refId);
			$course->setTitle($item->title->__toString());
			if ($description = $item->description->__toString()) {
				$course->setDescription($description);
			}

			//online
			if (isset($item->online)) {
				$course->setOfflineStatus(!(bool) $item->online);
			}

			//direct registration
			if ($reg = (bool) $item->directRegistration) {
				$course->setSubscriptionType(IL_CRS_SUBSCRIPTION_DIRECT);
			}
			//welcome mail
			if (isset($item->welcomeMail)) {
				$course->setAutoNotification((bool) $item->welcomeMail);
			}

			//activation time range
			if (!empty($item->courseTimeframe)) {
				$courseTimeframe = $item->courseTimeframe;
				$course->setActivationType(IL_CRS_ACTIVATION_LIMITED);
				$start = new ilDateTime(
					$courseTimeframe->courseBeginningDate->__toString() . ' ' .
					$courseTimeframe->courseBeginningTime->__toString(),
					IL_CAL_DATETIME);
				$course->setActivationStart($start->getUnixTime());
				$end = new ilDateTime(
					$courseTimeframe->courseEndDate->__toString() . ' ' .
					$courseTimeframe->courseEndTime->__toString(),
					IL_CAL_DATETIME);
				$course->setActivationEnd($end->getUnixTime());
			}

			//subscription time range
			if (!empty($item->courseInscriptionTimeframe)) {
				$courseInscriptionTimeframe = $item->courseInscriptionTimeframe;
				$course->setSubscriptionLimitationType(ilCourseConstants::SUBSCRIPTION_LIMITED);
				$start = new ilDateTime(
					$courseInscriptionTimeframe->courseInscriptionBeginningDate->__toString() . ' ' .
					$courseInscriptionTimeframe->courseInscriptionBeginningTime->__toString(),
					IL_CAL_DATETIME);
				$course->setSubscriptionStart($start->getUnixTime());
				$end = new ilDateTime(
					$courseInscriptionTimeframe->courseInscriptionEndDate->__toString() . ' ' .
					$courseInscriptionTimeframe->courseInscriptionEndTime->__toString(),
					IL_CAL_DATETIME);
				$course->setSubscriptionEnd($end->getUnixTime());
			}

			//create/update
			if ($ref_id = $course->getRefId()) {
				$course->update();
				$parent_id = $this->tree->getParentId($ref_id);
				if ($parent_id != $item->hierarchy) {
					//move course
					$this->tree->deleteNode(1, $ref_id);
					$course->putInTree($item->hierarchy);
				}
				$this->courses['updated'] .= $course->getTitle() . '<br>';
			} else {
				$course->create();
				$course->createReference();
				$course->putInTree((int) $item->hierarchy);
				$course->setPermissions((int) $item->hierarchy);
				$this->courses['created'] = $course->getTitle() . '<br>';
			}


			//add course admins
			$participants = ilCourseParticipants::_getInstanceByObjId($course->getId());
			if($existing_admins = $participants->getAdmins()) {
				//import has higher priority, so delete existing admins first
				/** @var ilCourseParticipants $participants */
				$participants->deleteParticipants($existing_admins);
			}
			$admins = explode(',', $item->courseAdmins->__toString());
			$course->setOwner(ilObjUser::_lookupId($admins[0]));
			$course->updateOwner();
			foreach ($admins as $admin) {
				$participants->add(ilObjUser::_lookupId($admin), IL_CRS_ADMIN);
			}

			//create references
			if ($item->references) {
				foreach (explode(',', $item->references->__toString()) as $parent_id) {
					$course_ref = new ilObjCourseReference();

					$course_ref->create();
					$course_ref->createReference();

					$course_ref->putInTree($parent_id);

					$course_ref->setTargetid($course->getId());
					$course_ref->update();
				}
			}

		}
	}

}