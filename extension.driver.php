<?php


	require_once EXTENSIONS . '/activecampaign/vendor/autoload.php';


	Class extension_Activecampaign extends Extension{

		private $ac;
		private $clientId;
		private $clientSecret;
		private $messages = array();

		public function __construct() {
			$this->apiKey = Symphony::Configuration()->get('api-key','activecampaign');
			$this->apiURL = Symphony::Configuration()->get('url','activecampaign');
			$this->datasources = Symphony::Configuration()->get('datasources','activecampaign');
			$this->ac = new ActiveCampaign($this->apiURL,$this->apiKey);
		}

		public function getActiveCampaign(){
			return $this->ac;
		}

		/**
		 * Installation
		 */
		public function install() {
			// A table to keep track of user tokens in relation to the current current user id
			// Symphony::Database()->query("CREATE TABLE IF NOT EXISTS `tbl_paypal_token` (
			// 	`user_id` VARCHAR(255) NOT NULL ,
			// 	`refresh_token` VARCHAR(255) NOT NULL
			// PRIMARY KEY (`user_id`,`system`)
			// )ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
			
			return true;
		}
		
		/**
		 * Update
		 */
		public function update($previousVersion = false) {
			$this->install();
		}

		
		public function getSubscribedDelegates() {
			return array(
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'appendPreferences'
				),
				array(
					'page'		=> '/frontend/',
					'delegate'	=> 'EventPostSaveFilter',
					'callback'	=> 'eventPostSaveFilter'
				),
				array(
					'page'		=> '/blueprints/events/new/',
					'delegate'	=> 'AppendEventFilter',
					'callback'	=> 'appendFilter'
				),
				array(
					'page'		=> '/blueprints/events/edit/',
					'delegate'	=> 'AppendEventFilter',
					'callback'	=> 'appendFilter'
				),
				// array(
				// 	'page' => '/system/preferences/',
				// 	'delegate' => 'AddCustomPreferenceFieldsets',
				// 	'callback' => 'appendPreferences'
				// ),
				// array(
				// 	'page' => '/system/preferences/',
				// 	'delegate' => 'Save',
				// 	'callback' => 'savePreferences'
				// ),
				// array(
				// 	'page' => '/frontend/',
				// 	'delegate' => 'FrontendProcessEvents',
				// 	'callback' => 'appendEventXML'
				// ),
				// array(
				// 	'page' => '/frontend/',
				// 	'delegate' => 'FrontendParamsResolve',
				// 	'callback' => 'appendAccessToken'
				// ),
				// array(
				// 	'page' => '/frontend/',
				// 	'delegate' => 'FrontendPageResolved',
				// 	'callback' => 'frontendPageResolved'
				// ),
			);
		}


		public function appendFilter($context) {
			$selected = !is_array($context['selected']) ? array() : $context['selected'];

			// Add Contact
			$context['options'][] = array(
				'activecampaign-add-contact',
				in_array('activecampaign-add-contact', $selected),
				__('Active Campaign Add Contact')
			);
			// Add Deal
			$context['options'][] = array(
				'activecampaign-add-deal',
				in_array('activecampaign-add-deal', $selected),
				__('Active Campaign Add Deal')
			);
		}

		public function eventPostSaveFilter($context){

			if (in_array('activecampaign-add-contact', $context['event']->eParamFILTERS)) {

				// now generate the data from xPATH 
				$xml = $this->getPostDetailsXML($context['entry'],$context['event']->ROOTELEMENT . '-add-contact.xsl');

				$children = $xml->getChildren();
				$fields = array();
				foreach ($children as $key => $value) {
					if (strlen($value->getValue()) > 0){
						//only keep in array if not empty
						$fields[$value->getName()] = $value->getValue();
					} elseif (is_object($value->getChildren()[0])){
						foreach ($value->getChildren() as $index => $node) {
							if ($node->getName() == 'item'){
								$fields[$value->getName()][$node->getAttribute('name')] = $node->getValue();
							} else {
								$fields[$value->getName()][$node->getName()] = $value->getValue();
							}
						}
					}	
				}

				// here is where we get to submit the stuff

				$ac = $this->getActiveCampaign();
				$apiResult = $ac->api("contact/add", $fields);

				//if contact exists update
				if ($apiResult->success == 1){

					$context['messages'][] = Array('activecampaign-add-contact' , true, null,
							array('method'=>'add')
						);

				} elseif ( $apiResult->success == 0 && isset($apiResult->{'0'}->id) ){

					$fields['id'] = $apiResult->{'0'}->id;

					$apiResult = $ac->api("contact/edit", $fields);
					if ($apiResult->success == 1){

						$context['messages'][] = Array('activecampaign-add-contact' , true, null,
							array('method'=>'update')
						);
					} else {

						$context['errors'][] = Array('activecampaign-add-contact' , false, null,
							array('method'=>'failed')
						);
					}
				}
			}

		}

		private function updateContact($context){

			if ( Symphony::Configuration()->get('section_' . $context['section']->get('id') ,'activecampaign') ){

				$sectionConfig = Symphony::Configuration()->get('section_' . $context['section']->get('id') ,'activecampaign');

				foreach ($sectionConfig['Properties'] as $key => $value) {
					$sectionConfig['Properties'][$key] = $this->compile($context['entry'],$value);
				}

				$body = [
					'Action' => 'addnoforce',
					'Email' => $this->compile($context['entry'],$sectionConfig['Email']),
					'Name' => $this->compile($context['entry'],$sectionConfig['Name']),
					'Properties' => $sectionConfig['Properties']/*[
						'Name' => $this->compile($context['entry'],'{/data/entry/name}'),
						'Surname' => $this->compile($context['entry'],'{/data/entry/surname}'),
					]*/
				];

				$response = $this->ac->post(Resources::$ContactslistManagecontact, [
					'body' => $body,
					'id' => $sectionConfig['id']
				]);
				
			}
		}

		public function entryPostCreate($context){
			// $this->updateContact($context);
		}

		public function entryPostEdit($context){
			// $this->updateContact($context);
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/



		public function getPostDetailsXML($entry, $XSLTfilename = NULL, $fetch_associated_counts = NULL) {
			$entry_xml = new XMLElement('entry');
			$data = $entry->getData();
			$fields = array();

			$entry_xml->setAttribute('id', $entry->get('id'));
			
			//Add date created and edited values
			$date = new XMLElement('system-date');

			$date->appendChild(
				General::createXMLDateObject(
				DateTimeObj::get('U', $entry->get('creation_date')),
				'created'
				)
			);

			$date->appendChild(
				General::createXMLDateObject(
				DateTimeObj::get('U', $entry->get('modification_date')),
				'modified'
				)
			);

			$entry_xml->appendChild($date);


			// Add associated entry counts
			if($fetch_associated_counts == 'yes') {
				$associated = $entry->fetchAllAssociatedEntryCounts();

				if (is_array($associated) and !empty($associated)) {
					foreach ($associated as $section_id => $count) {
						$section = SectionManager::fetch($section_id);

						if(($section instanceof Section) === false) continue;
						$entry_xml->setAttribute($section->get('handle'), (string)$count);
					}
				}
			}

			// Add fields:
			foreach ($data as $field_id => $values) {
				if (empty($field_id)) continue;

				$field = FieldManager::fetch($field_id);
				$field->appendFormattedElement($entry_xml, $values, false, null, $entry->get('id'));
			}

			$xml = new XMLElement('data');
			$xml->appendChild($entry_xml);

			// Build some context
			$section = SectionManager::fetch($entry->get('section_id'));

			//generate parameters such as root and add into dom
			$date = new DateTime();
			$params = array(
				'today' => $date->format('Y-m-d'),
				'current-time' => $date->format('H:i'),
				'this-year' => $date->format('Y'),
				'this-month' => $date->format('m'),
				'this-day' => $date->format('d'),
				'timezone' => $date->format('P'),
				'website-name' => Symphony::Configuration()->get('sitename', 'general'),
				'root' => URL,
				'workspace' => URL . '/workspace',
				'http-host' => HTTP_HOST,
				'entry-id' => $entry->get('id'),
				'section-handle' => $section->get('handle'),
			);


			if (!empty($this->datasources)){
				$datasources = explode(',',$this->datasources);
				$paramPool = array();
				foreach ($datasources as $dsName) {
					$ds = DatasourceManager::create($dsName, array('param'=>$params,'env'=> array('pool'=>$paramPool)));
					$dsXml = $ds->execute($paramPool);
					$xml->appendChild($dsXml);
				}
			}

			//in case there are url params they will also be added in the xml
			$paramsXML = new XMLElement('params');
			foreach ($params as $key => $value) {
				$paramsXML->appendChild(new XMLElement($key,$value));
			}
			$xml->appendChild($paramsXML);

			$dom = new DOMDocument();
			$dom->strictErrorChecking = false;
			$dom->loadXML($xml->generate(true));

			if (!empty($XSLTfilename)) {
				$XSLTfilename = WORKSPACE . '/utilities/activecampaign/'. preg_replace(array('%/+%', '%(^|/)../%'), '/', $XSLTfilename);
				if (file_exists($XSLTfilename)) {
					$XSLProc = new XsltProcessor;

					$xslt = new DomDocument;
					$xslt->load($XSLTfilename);

					$XSLProc->importStyleSheet($xslt);

					// Set some context
					$XSLProc->setParameter('', array(
						'section-handle' => $section->get('handle'),
						'entry-id' => $entry->get('id')
					));

					$temp = $XSLProc->transformToDoc($dom);

					if ($temp instanceof DOMDocument) {
						$dom = $temp;
					}
				}
			}

			return XMLElement::convertFromDOMDocument('data',$dom);
		}


		/**
		* Allows a user to select which section they would like to use as their
		* active members section. This allows developers to build multiple sections
		* for migration during development.
		*
		* @uses AddCustomPreferenceFieldsets
		* @todo Look at how this could be expanded so users can log into multiple sections. This is not in scope for 1.0
		*
		* @param array $context
		*/
		public function appendPreferences($context){
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', 'Active Campaign'));
			$div = new XMLElement('div', NULL, array('class' => 'group'));
			$label = Widget::Label('URL');
			$label->appendChild(Widget::Input('settings[activecampaign][url]',  $this->currency));
			$div->appendChild($label);
			$label = Widget::Label('API Key');
			$label->appendChild(Widget::Input('settings[activecampaign][api-key]',  $this->apiKey, 'password'));
			$div->appendChild($label);
			$group->appendChild($div);


			$selectedDatasources = explode(',',$this->datasources);
			$datasources = DatasourceManager::listAll();
			$options = array();
			foreach ($datasources as $handle => $datasource) {
				$selected = in_array($handle,$selectedDatasources);
				$options[] = array($handle, $selected, $datasource['name']);
			}
			$label = Widget::Label(__('Datasources to include for processing'));
			$label->appendChild(Widget::Select(
				"settings[activecampaign][datasources]",
				$options,
				array('multiple'=>'multiple')
			));			
			$help = new XMLElement('p', __('Add datasources to complete transaction details.'));
			$help->setAttribute('class', 'help');
			$label->appendChild($help);
			$group->appendChild($label);


			$context['wrapper']->appendChild($group);
		}
	}
