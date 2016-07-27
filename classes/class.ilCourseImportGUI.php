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
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 *
 * @ilCtrl_IsCalledBy ilCourseImportGUI: ilUIPluginRouterGUI
 * @ilCtrl_Calls ilCourseImportGUI: ilObjCourseAdministrationGUI
 */
class ilCourseImportGUI
{

	const XML_PREFIX = 'ns1';
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

			$validator = new ilCourseImportValidator($xml_file);
			$validator->validate();
			if ($last_error = $validator->getLastError()) {
				ilUtil::sendFailure($last_error, true);
				$this->ctrl->redirect($this, 'view');
			}

			$this->createCourses($xml_file);
			ilUtil::sendSuccess(sprintf($this->pl->txt(self::IMPORT_SUCCEEDED),
				$this->courses['created'], $this->courses['updated'], $this->courses['refs']));
		}

		$this->view();
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


			//subscription time range: if there's no timeframe defined, leave the current/default timeframe,
			//if it is defined but empty, unset the timeframe
			if ($item->courseInscriptionTimeframe) {
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
				} else {

				}
			}


			//create/update
			$hierarchy_id = (int) $item->hierarchy;
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

			//course time range:  if there's no timeframe defined, leave the current/default timeframe,
			//if it is defined but empty, unset the timeframe
			if (!empty($item->courseTimeframe)) {
				$courseTimeframe = $item->courseTimeframe;
				$start = new ilDate(
					$courseTimeframe->courseBeginningDate->__toString(),
					IL_CAL_DATE);
				$course->setCourseStart($start);
				$end = new ilDate(
					$courseTimeframe->courseEndDate->__toString(),
					IL_CAL_DATE);
				$course->setCourseEnd($end);
				$course->update();
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
					$this->courses['refs'] .= ilObject2::_lookupTitle(ilObject2::_lookupObjId($parent_id)) . ' - ' . ilObject2::_lookupTitle($course_ref->getTargetId()) . '<br>';
				}
			}

		}
	}

}