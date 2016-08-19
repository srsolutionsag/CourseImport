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
		$course = $xml->addChild('ns1:ns1:course', null);
		foreach ($array as $i => $dat) {
			if ($i > 8 || $dat === NULL) {
				continue;
			}
			$course->addChild('ns1:ns1:' . $this->getMapping($i), $dat ? $dat : 0);
		}

		$courseTimeframe = $course->addChild('ns1:ns1:courseTimeframe');
		if ($array[9] || $array[10] || $array[11] || $array[12]) {
			if ($array[9]) {
				$courseTimeframe->addChild('ns1:ns1:' . $this->getMapping(9), date(self::DATE_FORMAT, strtotime($array[9])));
			}
			if ($array[10]) {
				$courseTimeframe->addChild('ns1:ns1:' . $this->getMapping(10), date(self::TIME_FORMAT, strtotime($array[10])));
			}
			if ($array[11]) {
				$courseTimeframe->addChild('ns1:ns1:' . $this->getMapping(11), date(self::DATE_FORMAT, strtotime($array[11])));
			}
			if ($array[12]) {
				$courseTimeframe->addChild('ns1:ns1:' . $this->getMapping(12), date(self::TIME_FORMAT, strtotime($array[12])));
			}
		}

		$courseInscriptionTimeframe = $course->addChild('ns1:ns1:courseInscriptionTimeframe');
		if ($array[13] || $array[14] || $array[15] || $array[16]) {
			if ($array[13]) {
				$courseInscriptionTimeframe->addChild('ns1:ns1:' . $this->getMapping(13), date(self::DATE_FORMAT, strtotime($array[13])));
			}
			if ($array[14]) {
				$courseInscriptionTimeframe->addChild('ns1:ns1:' . $this->getMapping(14), date(self::TIME_FORMAT, strtotime($array[14])));
			}
			if ($array[15]) {
				$courseInscriptionTimeframe->addChild('ns1:ns1:' . $this->getMapping(15), date(self::DATE_FORMAT, strtotime($array[15])));
			}
			if ($array[16]) {
				$courseInscriptionTimeframe->addChild('ns1:ns1:' . $this->getMapping(16), date(self::TIME_FORMAT, strtotime($array[16])));
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
			10 => 'courseBeginningTime',
			11 => 'courseEndDate',
			12 => 'courseEndTime',
			13 => 'courseInscriptionBeginningDate',
			14 => 'courseInscriptionBeginningTime',
			15 => 'courseInscriptionEndDate',
			16 => 'courseInscriptionEndTime',
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
			10 => 'Kurs Startzeit',
			11 => 'Kurs Enddatum',
			12 => 'Kurs Endzeit',
			13 => 'Kurs Einschreibung Startdatum',
			14 => 'Kurs Einschreibung Startzeit',
			15 => 'Kurs Einschreibung Enddatum',
			16 => 'Kurs Einschreibung Endzeit',
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
