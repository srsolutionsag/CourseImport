<?php
require_once('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/CourseImport/vendor/autoload.php');

/**
 * Class ilCourseImportExcelConverter
 *
 * @author Fabian Schmid <fs@studer-raimann.ch>
 */
class ilCourseImportExcelConverter {

	const DATE_FORMAT = 'Y-m-d';
	const TIME_FORMAT = 'H:i:s';
	const ERROR_FILESIZE = 'error_large_file';
	/**
	 * @var string
	 */
	protected $uploaded_file = '';
	/**
	 * @var SimpleXMLElement
	 */
	protected $xml = null;
	/**
	 * @var string
	 */
	protected $xml_text = '';


	/**
	 * ilCourseImportExcelConverter constructor.
	 *
	 * @param string $uploaded_file
	 */
	public function __construct($uploaded_file) {
		$this->uploaded_file = $uploaded_file;
	}


	public function convert() {
		if (!$this->uploaded_file || !is_file($this->uploaded_file)) {
			throw new ilException('no valid file');
		}

		if (filesize($this->uploaded_file) > 120000) {
			return self::ERROR_FILESIZE;
		}

		$objPHPExcel = PHPExcel_IOFactory::load($this->uploaded_file);
		$data = $objPHPExcel->getSheet()->toArray();

		if (array_slice($data[1], 0, count($this->getValidHeaders())) != $this->getValidHeaders()) {
			throw new ilException('no valid file');
		}
		$xml = $this->getBaseXML();
		foreach ($data as $i => $dat) {
			if ($i < 2) {
				continue;
			}
			$this->appendCourseArrayAsElement($xml, $dat);
		}

		$this->setXml($xml);

		$dom = dom_import_simplexml($xml)->ownerDocument;
		$dom->formatOutput = true;
		$this->setXmlText($dom->saveXML());
	}


	/**
	 * @return \SimpleXMLElement
	 */
	protected function getBaseXML() {
		$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><ns1:courses></ns1:courses>', LIBXML_NOERROR, false, 'ns1', true);
		$xml->addAttribute('xmlns:xmlns:ns1', 'http://www.studer-raimann.ch/CourseImport');

		return $xml;
	}


	/**
	 * @param \SimpleXMLElement $xml
	 * @param array $array
	 */
	protected function appendCourseArrayAsElement(SimpleXMLElement &$xml, array $array) {
		if (!$array[1]) {
			return;
		}

		$course = $xml->addChild('ns1:ns1:course', null);
		foreach ($array as $i => $dat) {
			if ($i > 8 || $dat === NULL) {
				continue;
			}
			$course->addChild('ns1:ns1:' . $this->getMapping($i), $dat ? htmlspecialchars($dat) : 0);
		}

		$courseTimeframe = $course->addChild('ns1:ns1:courseTimeframe');
		if ($array[9] || $array[10]) {
			if ($array[9]) {
				$courseTimeframe->addChild('ns1:ns1:' . $this->getMapping(9), date(self::DATE_FORMAT, strtotime(str_replace('/', '.', $array[9]))));
			}
			if ($array[10]) {
				$courseTimeframe->addChild('ns1:ns1:' . $this->getMapping(10), date(self::DATE_FORMAT, strtotime(str_replace('/', '.', $array[10]))));
			}
		}

		$courseInscriptionTimeframe = $course->addChild('ns1:ns1:courseInscriptionTimeframe');
		if ($array[11] || $array[12] || $array[13] || $array[14]) {
			if ($array[11]) {
				$courseInscriptionTimeframe->addChild('ns1:ns1:' . $this->getMapping(11), date(self::DATE_FORMAT, strtotime(str_replace('/', '.', $array[11]))));
			}
			if ($array[12]) {
				$courseInscriptionTimeframe->addChild('ns1:ns1:' . $this->getMapping(12), date(self::TIME_FORMAT, strtotime(str_replace('/', '.', $array[12]))));
			}
			if ($array[13]) {
				$courseInscriptionTimeframe->addChild('ns1:ns1:' . $this->getMapping(13), date(self::DATE_FORMAT, strtotime(str_replace('/', '.', $array[13]))));
			}
			if ($array[14]) {
				$courseInscriptionTimeframe->addChild('ns1:ns1:' . $this->getMapping(14), date(self::TIME_FORMAT, strtotime(str_replace('/', '.', $array[14]))));
			}
		}
	}


	/**
	 * @return \SimpleXMLElement
	 */
	public function getXml() {
		return $this->xml;
	}


	/**
	 * @param \SimpleXMLElement $xml
	 */
	public function setXml($xml) {
		$this->xml = $xml;
	}


	/**
	 * @param $i
	 * @return string
	 */
	protected function getMapping($i) {
		$map = array(
			0  => 'refId',
			1  => 'title',
			2  => 'description',
			3  => 'hierarchy',
			4  => 'references',
			5  => 'courseAdmins',
			6  => 'online',
			7  => 'directRegistration',
			8  => 'welcomeMail',
			9  => 'courseBeginningDate',
			10 => 'courseEndDate',
			11 => 'courseInscriptionBeginningDate',
			12 => 'courseInscriptionBeginningTime',
			13 => 'courseInscriptionEndDate',
			14 => 'courseInscriptionEndTime',
		);

		return $map[$i];
	}


	/**
	 * @return array
	 */
	protected function getValidHeaders() {
		return array(
			0  => 'Kurs',
			1  => 'Titel',
			2  => 'Beschreibung',
			3  => 'Hierarchie',
			4  => 'Kurs-Referenzen',
			5  => 'Kursadmin(s)',
			6  => 'Online?',
			7  => 'Direkte Registration?',
			8  => 'Willkommens-Mail?',
			9  => 'Kurs Startdatum',
			10 => 'Kurs Enddatum',
			11 => 'Kurs Einschreibung Startdatum',
			12 => 'Kurs Einschreibung Startzeit',
			13 => 'Kurs Einschreibung Enddatum',
			14 => 'Kurs Einschreibung Endzeit',
		);
	}


	/**
	 * @return string
	 */
	public function getUploadedFile() {
		return $this->uploaded_file;
	}


	/**
	 * @param string $uploaded_file
	 */
	public function setUploadedFile($uploaded_file) {
		$this->uploaded_file = $uploaded_file;
	}


	/**
	 * @return string
	 */
	public function getXmlText() {
		return $this->xml_text;
	}


	/**
	 * @param string $xml_text
	 */
	public function setXmlText($xml_text) {
		$this->xml_text = $xml_text;
	}
}
