<?php

class AgileCustomField extends DataObject {
	static $db = array(
		// The reference for your template
		"TemplateFieldName" => "Varchar",

		// Name of property in the returned AgileZen object that we want to extract text from
		"SourceFieldName" => "Varchar",
		
		// Heading title we're looking for
		"HeadingTitle" => "Varchar",
		
		// The heading level of that level, so that sub-levels won't confuse us
		"HeadingLevel" => "Int",

		"ApplyMarkdown" => "Enum('Yes,No','Yes')"
	);

	static $has_one = array(
		"ReportPage" => "AgileProjectReportPage"
	);

	function getCMSFields() {
		$fields = parent::getCMSFields();

		return $fields;
	}
}