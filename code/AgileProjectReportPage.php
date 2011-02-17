<?php

/**
 * This is a page that can generate report from a project in Agile Zen. It's basic behaviour is to prompt the user for filtering
 * options that are used to determine the project and/or user stories that are reported on, and then displays the report in
 * a new browser window without navigation, in a way that can be generated to PDF and used as a printed report to a client.
 * The report can also be customised to extract bits from markdown fields in the agile zen object. This is particularly for the
 * detail field. This is done using AgileCustomField objects.
 */

class AgileProjectReportPage extends Page {
	static $db = array(
		"DefaultProjectID" => "Varchar",
		"DefaultTags" => "Varchar"
	);

	static $has_many = array(
		"CustomFields" => "AgileCustomField"
	);

	function getCMSFields() {
		$fields = parent::getCMSFields();

		$fields->addFieldToTab('Root.Content.Main', new TextField('DefaultProjectID', 'Default Project ID in AgileZen'));
		$fields->addFieldToTab(
			'Root.Content.CustomFields',
			new AgileCustomFieldTableField(
				"CustomFields",
				"Custom fields",
				"AgileCustomField",
				array(
					"TemplateFieldName" => "Story Card Name for Template",
					"SourceFieldName" => "Story Card Field Name",
					"HeadingTitle" => "Heading Title",
					"HeadingLevel" => "Heading Level",
					"ApplyMarkdown" => "Apply Markdown to HTML translation on value"
				)
			)
		);
		return $fields;
	}
}

class AgileProjectReportPage_Controller extends Page_Controller {

	static $allowed_actions = array(
		"showreport"
	);

	function SearchForm() {
		$form = new Form(
			$this,
			"SearchForm",
			new FieldSet(
				new TextField("tags", "tags (comma delimited)", $this->DefaultTags)
			),
			new FieldSet(
				new FormAction("showreport", "Show Report")
			));
		$form->setTarget("_blank");
		$form->setFormMethod("get");
		return $form;
	}

	// This is the target of the search form.
	function showreport() {
		return $this->renderWith($this->ClassName . "_report");
	}

	// Return a set of the criteria for presentation. At this this stage, just a list of the tags
	function StoriesCriteria() {
		$result = new DataObjectSet();

		$tags = isset($_REQUEST['tags']) ? strtolower($_REQUEST['tags']) : "";
		$tags = explode(",", $tags);

		if ($tags) foreach ($tags as $tag) $result->push(new ArrayData(array("CriteriaType" => "tag", "Detail" => $tag)));

		return $result;
	}

	// Returns a renderable object that contains properties of the project
	function Project() {
		return $this->getService()->getProject($this->DefaultProjectID);
	}

	// Returns a dataobjectset of stories for the project
	function Stories() {
		// Get custom fields from the page and assemble that into the structure that getStories needs.
		$customFieldsSrc = array();
		$CustomFields = $this->CustomFields()->items;
		foreach($CustomFields as $CustomField){
			$customFieldsSrc[$CustomField->TemplateFieldName] = array(
				"sourceField" => $CustomField->SourceFieldName,
				"sourceHeadingTitle" => $CustomField->HeadingTitle,
				"sourceHeadingLevel" => $CustomField->HeadingLevel,
				"markdown" => $CustomField->ApplyMarkdown == "Yes"
			);
		}

		// Get the tags we're filtering on, which is a comma separated list of values which we convert to an array
		$tags = isset($_REQUEST['tags']) ? strtolower($_REQUEST['tags']) : "";
		$tags = explode(",", $tags);
		return $this->getService()->getStories(
			$this->DefaultProjectID,
			$tags,
			$customFieldsSrc
		);
	}

	var $service = null;
	protected function getService() {
		if (!$this->service) $this->service = new AgileZenService();
		return $this->service;
	}
}

class AgileCustomFieldTableField extends ComplexTableField {
	protected $template = 'AgileCustomFieldTableField';

	public $popupClass = 'AgileCustomFieldTableField_Popup';
	
	public $itemClass = 'AgileCustomFieldTableField_Item';
	
	static $data_class = 'AgileCustomField';

	function __construct($controller, $name, $sourceClass, $fieldList, $detailFormFields = null, $sourceFilter = "", $sourceSort = "Created", $sourceJoin = "") {
		parent::__construct($controller, $name, $sourceClass, $fieldList, $detailFormFields, $sourceFilter, $sourceSort, $sourceJoin);

		$this->Markable = true;
	}

	function Items() {
		$this->sourceItems = $this->sourceItems();

		if(!$this->sourceItems) {
			return null;
		}

		$pageStart = (isset($_REQUEST['ctf'][$this->Name()]['start']) && is_numeric($_REQUEST['ctf'][$this->Name()]['start'])) ? $_REQUEST['ctf'][$this->Name()]['start'] : 0;
		$this->sourceItems->setPageLimits($pageStart, $this->pageSize, $this->totalCount);

		$output = new DataObjectSet();
		foreach($this->sourceItems as $pageIndex=>$item) {
			$output->push(Object::create('AgileCustomFieldTableField_Item',$item, $this, $pageStart+$pageIndex));
		}
		return $output;
	}

	function handleItem($request) {
		return new CommentTableField_ItemRequest($this, $request->param('ID'));
	}
}

/**
 * Popup window for {@link MemberTableField}.
 * @package cms
 * @subpackage security
 */
class AgileCustomFieldTableField_Popup extends ComplexTableField_Popup {

	function __construct($controller, $name, $fields, $validator, $readonly, $dataObject) {
		parent::__construct($controller, $name, $fields, $validator, $readonly, $dataObject);
	}
}

/**
 * Single row of a {@link CommentTableField}
 * @package cms
 * @subpackage comments
 */
class AgileCustomFieldTableField_Item extends ComplexTableField_Item {

	function Actions() {
		$actions = parent::Actions();

		foreach($actions as $action) {
			if($action->Name == 'delete') {
				$action->TitleText = 'Delete custom field';
			}
		}

		return $actions;
	}
}

/**
 * @package cms
 * @subpackage comments
 */
class AgileCustomFieldTableField_ItemRequest extends ComplexTableField_ItemRequest {
	/**
	 * Deleting an item from a member table field should just remove that member from the group
	 */
	function delete($request) {
		// Protect against CSRF on destructive action
		$token = $this->ctf->getForm()->getSecurityToken();
		if(!$token->checkRequest($request)) return $this->httpError('400');
		
		if($this->ctf->Can('delete') !== true) {
			return false;
		}

		// if a group limitation is set on the table, remove relation.
		// otherwise remove the record from the database
		if($this->ctf->getGroup()) {
			$groupID = $this->ctf->sourceID();
			$group = DataObject::get_by_id('Group', $groupID);
			
			// Remove from group and all child groups
			foreach($group->getAllChildren() as $subGroup) {
				$this->dataObj()->Groups()->remove($subGroup);
			}
			$this->dataObj()->Groups()->remove($groupID);
		} else {
			$this->dataObj()->delete();
		}	
	}
	
	function getParent() {
		return $this->ctf;
	}
}