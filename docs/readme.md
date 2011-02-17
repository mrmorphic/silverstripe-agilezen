Introduction
============

Installation
------------

* Add the module code to your project
* In your project's _config.php file, set the API key from Agile Zen: <code>AgileZenService::set_api_key('123456789012345678901234');</code>

Usage
-----

* Create a subclass of AgileProjectReportPage in your site. It can be empty (include an empty controller)
* Create a report output template in your theme with a _report suffix. e.g. if your subclass is called SoftwareSpecification then the template
  should be SoftwareSpecification_report.ss. This sits under template, not Layout, and is the output report.
* In the CMS create a page of this type. You can save the project ID of your Agile Zen project in the page if you want to. You can create custom fields
  in the Custom Fields tab. These are sub-fields that are extracted from the 'details' property of a story card.
* You can use $Stories or $Project in your template. $Stories is a DataObjectSet of all the stories in the project. These can be filtered
  at the front end using tags.
* Visit this page on the front end. You should see the project and it's story cards rendered using your template.

