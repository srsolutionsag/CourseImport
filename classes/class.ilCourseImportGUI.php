<?php
require_once './Services/Form/classes/class.ilPropertyFormGUI.php';
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
     * ilCourseImportGUI constructor.
     */
    public function __construct()
    {
        global $ilCtrl, $tpl, $ilTabs, $ilLocator;
        $this->tabs = $ilTabs;
        $this->ctrl = $ilCtrl;
        $this->tpl = $tpl;
        $this->ilLocator = $ilLocator;
        $this->pl = ilCourseImportPlugin::getInstance();

        $this->ctrl->setParameterByClass('ilobjcourseadministrationgui', 'ref_id', $_GET['ref_id']);
        $this->tabs->setBackTarget("back", $ilCtrl->getLinkTargetByClass(array('iladministrationgui', 'ilobjcourseadministrationgui')));

    }

    public function executeCommand() {
        //$this->checkAccess();
        $cmd = $this->ctrl->getCmd('view');
//        $this->tpl->getStandardTemplate();
        $this->prepareOutput();

        switch ($cmd) {
            default:
                $this->$cmd();
                break;
        }
        $this->tpl->getStandardTemplate();
        $this->tpl->show();
    }

    protected function prepareOutput() {

    }

    protected function view() {
        $form = $this->initForm();
        $this->tpl->setContent($form->getHTML());
    }

    protected function initForm() {
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->pl->txt('form_title'));
        $form->setId('crs_import');
        $form->setFormAction($this->ctrl->getFormAction($this));

        $file_input = new ilFileInputGUI($this->pl->txt('file_input'), 'file_input');
        $file_input->setRequired(true);
        $file_input->setSuffixes(array('xml'));

        $form->addItem($file_input);
        $form->addCommandButton('importCourses', $this->pl->txt('import_courses'));

        return $form;
    }

    public function importCourses() {
//        var_dump($_POST);exit;
        $form = $this->initForm();
        $form->setValuesByPost();
        if ($form->checkInput()) {
            $file = $form->getFileUpload('file_input');
            $xml = new DOMDocument();
            $xml->load($file['tmp_name']);
            if ($xml->schemaValidate('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/CourseImport/resources/courses.xsd')
                && $this->validate($xml)) {
                $this->createCourses($xml);
                ilUtil::sendSuccess($this->pl->txt('validation_succeed'), true);
                $this->ctrl->redirect($this, 'view');
            } else {
                ilUtil::sendFailure($this->pl->txt('validation_error') . '<br>' .
                    libxml_get_last_error()->message . '<br>' .
                    libxml_get_last_error()->level . '<br>' .
                    libxml_get_last_error()->line, true);
                $this->ctrl->redirect($this, 'view');
            }
//            var_dump($form->getFileUpload('file_input'));exit;
        } else {
            $this->view();
        }
    }

    protected function validate($file) {
        
    }

    protected function createCourses($xml) {
        include_once("Modules/Course/classes/class.ilObjCourse.php");
        $course = new ilObjCourse();
        $course->setType('crs');
        $course->setTitle('dummy');
        $course->setDescription("");
        $course->create(true); // true for upload
        $course->createReference();
//        $course->putInTree($target_id);
//        $course->setPermissions($target_id);
        
        include_once 'Modules/Course/classes/class.ilCourseXMLParser.php';
        $parser = new ilCourseXMLParser($course, $xml);
        $parser->setXMLContent($xml);
        $parser->startParsing();
    }

}