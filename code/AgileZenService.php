<?php

class AgileZenService extends RestfulService {

	static $base_url = "https://agilezen.com/api/v1/";

	private static $api_key;

	function __construct() {
		parent::__construct(self::$base_url, 10);
		$this->httpHeader('X-Zen-ApiKey: ' . self::$api_key);
	}

	static function set_api_key($key) {
		self::$api_key = $key;
	}

	static function get_api_key($key) {
		return self::$api_key;
	}

	/**
	 * @param String $projectId		Id of the project to retrieve
	 */
	function getProject($projectId, $subFields = null) {
		$r = $this->request('project/' . $projectId . "?with=everything");
		if ($r->isError()) throw new Exception("Project: " . $r->getStatusDescription());

		$proj = $r->xpath_one("/project");
		$result = $this->asArrayData($proj, $subFields);

		return $result;
	}

	/**
	 * @param String $projectId		Id of the project to retrieve
	 * @param array $tags			Array of tag IDs to filter, only retrieve stories that match all tags
	 * @param array subFields		A configuration array that determines which core fields we look in, and what we look for. See
	 *								asArrayData().
	 */
	function getStories($projectId, $tags = null, $subFields = null) {
		$r = $this->request('project/' . $projectId . '/stories?with=everything');
		if ($r->isError()) throw new Exception("Stories: " . $r->getStatusDescription());

		$stories = $r->xpath("/stories/items/story");
//		Debug::show("stories xpath is " . print_r($stories,true));
		$result = new DataObjectSet();
		foreach ($stories as $story) $result->push($this->asArrayData($story, $subFields));
//		Debug::show("stories set is " . print_r($result, true));
		return $result;
	}

	/**
	 * Given a SimpleXMLElement object that represents some domain object like project or story,
	 * return this as array data with the SimpleXMLElement object and sub-objects stripped out,
	 * so that effectively we have an object presentation that is made up of arrays and strings.
	 *
	 * @param SimpleXMLElement	$node
	 * @param array				$subFields		A configuration for extra fields. This lets us use
	 *											content fields in AgileZen as structured fields by
	 *											using headings, for example, and we can extract these
	 *											sub-fields, and return them as regular properties of the objects we return
	 *											array(
	 * 												"Specification" => array(
	 *													"sourceField" => "description",
	 *													"sourceHeadingTitle" => "Detailed Specification",
	 *													"sourceHeadingLevel => "3"
	 *												)
	 *											)
	 *											This creates a new property on the returned object call "Specification"
	 * 											which is read from a heading 3 with a title of "Detailed Specification".
	 *											The property will contain everything after the heading, up to but not including
	 *											the next heading of that level or less, or the end of the document.
	 */
	protected function asArrayData($node, $subFields = null) {
		$result = array();
		foreach ($node as $key => $value) {
//			Debug::show("key is " . print_r($key,true) . " and value is " . print_r($value, true));
			if (is_object($value) && $value instanceof SimpleXMLElement) {
				if ($value->children() && count($value->children())) {
					$r = array();
					foreach ($value->children() as $k => $v) {
						$r[$k] = (string) $v;
					}
					$result[$key] = $r;
				} 
				else $result[$key] = (string) $value;
			}
			else $result[$key] = $value;
		}

// Debug::show("object without subfields is " . print_r($result, true));
		// Process subfields
		if ($subFields) {
			foreach ($subFields as $property => $fieldConf) {
				if (!isset($fieldConf['sourceField']) || !isset($result[$fieldConf['sourceField']]))
					throw new Exception("sub fields are not configured properly. The sourceField is either " . 
								"not specified, or the field {$fieldConf['sourceField']} doesn't exist in this object.");
				$source = $result[$fieldConf['sourceField']];
				$result[$property] = $this->extractSubField($source, $fieldConf);
			}
		}

// Debug::show("final object with subfields is " . print_r($result, true));
		return new ArrayData($result);
	}

	protected function extractSubField($source, $def) {
//		Debug::show("extracting " . print_r($def,true) . " from " . $source);
		$source = html_entity_decode($source);

		// Find the starting element in source.
		$start = "<H{$def['sourceHeadingLevel']}>" . strtoupper($def['sourceHeadingTitle']) . "</H{$def['sourceHeadingLevel']}>";
		$i = strpos(strtoupper($source), $start);
		if ($i !== FALSE) $i += strlen($start);

		if ($i === FALSE) return "";		// start not found

		// chop off the starting bit, it makes find the end easier.
		$source = substr($source, $i); 

		// Find the ending element. This is the next heading style.
		$j = 0;
		while (($j = strpos($source, "<h", $j)) !== FALSE) {
			if ($source[$j+2] > 0 && $source[$j+2] <= (int) $def['sourceHeadingLevel'] && $source[$j+3] == ">") {
				break; // we've found the next section
			}
			$j++; // this is not the heading we're looking for
		}

		// truncate off after this section
		if ($j !== FALSE) $source = substr($source, 0, $j);

		if (isset($def['markdown']) && !$def['markdown']) return $source;

		return $this->convertMarkdown($source);
	}

	// Convert any markdown syntax to HTML.
	protected function convertMarkdown($s) {
	    include_once(Director::baseFolder() . "/agilezen/thirdparty/PHP Markdown Extra 1.2.4/markdown.php");
	    return Markdown($s);
	}
}