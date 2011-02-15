<?php

class AgileCustomField extends DataObject {
	static $db = array(
		// Name of property in the returned AgileZen object that we want to extract text from
		"FieldName" => "Varchar",
		
		// Heading title we're looking for
		"HeadingTitle" => "Varchar",
		
		// The heading level of that level, so that sub-levels won't confuse us
		"HeadingLevel" => "Int"
	);

	static $has_one = array(
		"ReportPage" => "AgileProjectReportPage"
	);

	function getCMSFields() {
		$fields = parent::getCMSFields();

		return $fields;
	}
}