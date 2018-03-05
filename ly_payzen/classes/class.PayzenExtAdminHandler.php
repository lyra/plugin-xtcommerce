<?php
/**
  * PayZen V2-Payment Module version 1.0.1 for xtCommerce 4.1.x. Support contact : support@payzen.eu.
 *
 * The MIT License (MIT)
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @category  payment
 * @package   payzen
 * @author    Lyra Network (http://www.lyra-network.com/)
 * @copyright 2014-2016 Lyra Network and contributors
 * @license   http://www.opensource.org/licenses/mit-license.html  The MIT License (MIT)
 */

defined('_VALID_CALL') or die('Direct Access is not allowed.');

class PayzenExtAdminHandler extends ExtAdminHandler {

	private $_master_key = 'id';

	function setPosition($position) {
		$this->position = $position;
	}

	function _getParams() {
		$params = array();
		$params['header'] = array();
		$params['master_key'] = $this->_master_key;
		$params['display_deleteBtn'] = false;
		$params['display_resetBtn'] = false;
		$params['display_editBtn'] = false;
		$params['display_newBtn'] = false;
		$params['display_searchPanel']  = false;

		return $params;
	}

	function __construct($extAdminHandler) {
		// clone default admin handler to ours
		foreach ($extAdminHandler as $key => $value) {
			$this->$key = $value;
		}
	}

	function _switchFormField($line_data, $lang_data = array()) {
		$label = $line_data['text'];
		$name = $line_data['name'];
		$tmpData = $this->tmpData['data'][$name];

		switch ($line_data['type']) {

			case 'payzen_dropdown_ms':
				if (empty($line_data['url'])) {
					// default dropdownstatus
					$url = 'DropdownData.php?get=status_truefalse';
				} else {
					$url = 'DropdownData.php?get=' . $line_data['url'];
				}

				$data = $this->_payzenMultiComboBox($label, $name, $line_data['value'], $url);
				break;

			case 'payzen_label':
				$data = PhpExt_Form_TextField::createTextField($name, $label);
				$data->setWidth($line_data['width'] ? $line_data['width'] : '300');
				$data->setReadOnly(true);
				$data->setCssStyle('border: none; background: none;');

				$data->setValue($line_data['value']);
				break;

			case 'payzen_callback_url':
				$data = PhpExt_Form_TextField::createTextField($name, $label);
				$data->setWidth('600');
				$data->setReadOnly(true);
				$data->setCssStyle('border: none; background: none;');

				$data->setValue($this->_getCallbackLink());
				break;

			case 'payzen_section_title':
				$data = new PhpExt_Panel();
				$data->setHtml($label);
				$data->setBodyStyle('border: none; font-size: 16px; font-weight: bold; background-color: #ABADAF; padding: 3px; margin: 15px 0px 5px;');
				break;

			case 'payzen_multi_options':
				$store = new PhpExt_Data_SimpleStore();
				$store->setData(json_decode(html_entity_decode($line_data['value']), true));
				$store->addField(new PhpExt_Data_FieldConfigObject('id', 'id'));
				$store->addField(new PhpExt_Data_FieldConfigObject('label', 'label'));
				$store->addField(new PhpExt_Data_FieldConfigObject('min_amount', 'min_amount', PhpExt_Data_FieldConfigObject::TYPE_FLOAT));
				$store->addField(new PhpExt_Data_FieldConfigObject('max_amount', 'max_amount', PhpExt_Data_FieldConfigObject::TYPE_FLOAT));
				$store->addField(new PhpExt_Data_FieldConfigObject('contract', 'contract'));
				$store->addField(new PhpExt_Data_FieldConfigObject('count', 'count', PhpExt_Data_FieldConfigObject::TYPE_INT));
				$store->addField(new PhpExt_Data_FieldConfigObject('period', 'period', PhpExt_Data_FieldConfigObject::TYPE_INT));
				$store->addField(new PhpExt_Data_FieldConfigObject('first', 'first', PhpExt_Data_FieldConfigObject::TYPE_FLOAT));

				$colModel = new PhpExt_Grid_ColumnModel();

				$column = PhpExt_Grid_ColumnConfigObject::createColumn(TEXT_PAYZEN_LABEL, 'label', 'id_grid_label_' . $name, 150);
				$editor = new PhpExt_Form_TextField();
				$editor->setAllowBlank(false);
				$column->setEditor($editor);
				$colModel->addColumn($column);

				$column = PhpExt_Grid_ColumnConfigObject::createColumn(TEXT_PAYZEN_MIN_AMOUNT, 'min_amount', null, 80);
				$column->setEditor(new PhpExt_Form_NumberField());
				$colModel->addColumn($column);

				$column = PhpExt_Grid_ColumnConfigObject::createColumn(TEXT_PAYZEN_MAX_AMOUNT, 'max_amount', null, 80);
				$column->setEditor(new PhpExt_Form_NumberField());
				$colModel->addColumn($column);

				$column = PhpExt_Grid_ColumnConfigObject::createColumn(TEXT_PAYZEN_CONTRACT, 'contract', null, 90);
				$column->setEditor(new PhpExt_Form_TextField());
				$colModel->addColumn($column);

				$column = PhpExt_Grid_ColumnConfigObject::createColumn(TEXT_PAYZEN_COUNT, 'count', null, 65);
				$editor = new PhpExt_Form_NumberField();
				$editor->setAllowBlank(false);
				$column->setEditor($editor);
				$colModel->addColumn($column);

				$column = PhpExt_Grid_ColumnConfigObject::createColumn(TEXT_PAYZEN_PERIOD, 'period', null, 65);
				$editor = new PhpExt_Form_NumberField();
				$editor->setAllowBlank(false);
				$column->setEditor($editor);
				$colModel->addColumn($column);

				$column = PhpExt_Grid_ColumnConfigObject::createColumn(TEXT_PAYZEN_FIRST, 'first', null, 70);
				$column->setEditor(new PhpExt_Form_NumberField());
				$colModel->addColumn($column);

				$gridPanel = new PhpExt_Grid_EditorGridPanel();
				$gridPanel->setStore($store)
						->setId('id_grid_' . $name)
						->setClicksToEdit(2)
						->setColumnModel($colModel)
						->setSelectionModel(new PhpExt_Grid_RowSelectionModel())
						->setEnableHeaderMenu(false)
						->setEnableDragDrop(false)
						->setAutoExpandColumn('id_grid_label_' . $name)
						->setAutoExpandMax(400)
						->setAutoHeight(true)
						->setAutoWidth(true)
						->setCssStyle('padding-left: 205px;')
						->setBorder(false);

				$onChangeFnc = PhpExt_Javascript::functionDef(
						null,
						'
							var grid = Ext.ComponentMgr.get("id_grid_' . $name . '");

							var options = [];
							grid.getStore().each(function(rec) {
								options.push(rec.data);
							});

							var hidden = Ext.getCmp("id_' . $name . '");
							hidden.setValue(Ext.util.JSON.encode(options));
						',
						array('store')
				);
				$store->attachListener('add', new PhpExt_Listener($onChangeFnc, null /* scope */, 10 /* delay */));
				$store->attachListener('remove', new PhpExt_Listener($onChangeFnc, null /* scope */, 10 /* delay */));
				$store->attachListener('update', new PhpExt_Listener($onChangeFnc, null /* scope */, 10 /* delay */));

				$labelPanel = new PhpExt_Panel();
				$labelPanel->setHtml('<label style="width: 200px;" class="x-form-item-label">' . $label . ': </label>');
				$labelPanel->setBaseCssClass('x-form-item');

				$data = new PhpExt_Panel();
				$data->setBorder(false)
					->setCssClass('x-form-item x-tab-item')
					->setButtonAlign(PhpExt_Ext::HALIGN_CENTER);
				$data->addItem($labelPanel);
				$data->addItem($gridPanel);
				$data->addItem(PhpExt_Form_Hidden::createHidden($name, html_entity_decode($line_data['value']), 'id_' . $name));

				$addBtn = PhpExt_Button::createTextButton(
						TEXT_PAYZEN_ADD, new PhpExt_Handler(PhpExt_Javascript::stm('
								var grid = Ext.ComponentMgr.get("id_grid_' . $name . '");
								var d = new Date();

								var Option = grid.getStore().recordType;
								var opt = new Option({
									id: d.getTime() + "_" + d.getMilliseconds(),
									label: "New option",
									min_amount: "",
									max_amount: "",
									contract: "",
									count: "3",
									period: "30",
									first: ""
								});

								grid.stopEditing();
								grid.getStore().add(opt);
								grid.startEditing(grid.getStore().getCount() - 1, 0);
						'))
				);
				$addBtn->setType(PhpExt_Button::BUTTON_TYPE_BUTTON);
				$addBtn->setCssClass('x-btn-text-icon');
				$addBtn->setIcon('images/icons/add.png');
				$data->addButton($addBtn);

				$deleteBtn = PhpExt_Button::createTextButton(
						TEXT_PAYZEN_DELETE, new PhpExt_Handler(PhpExt_Javascript::stm('
								var grid = Ext.ComponentMgr.get("id_grid_' . $name . '");

								var sel = grid.getSelectionModel().getSelections();
								Ext.each(sel, function(item) {
									grid.getStore().remove(item);
								});
						'))
				);
				$deleteBtn->setType(PhpExt_Button::BUTTON_TYPE_BUTTON);
				$deleteBtn->setIconCssClass('delete');
				$data->addButton($deleteBtn);
				break;

			default:
				$data = parent::_switchFormField($line_data, $lang_data);

				break;
		}

		return $data;
	}

	function _getCallbackLink() {
		global $xtLink;

		$checkURL = $xtLink->_link(array('page' => 'callback', 'paction' => 'ly_payzen', 'conn' => 'SSL'), 'xtAdmin/');
		return html_entity_decode($checkURL);
	}

	function _payzenMultiComboBox($label, $name, $value, $url) {
		$store = new PhpExt_Data_JsonStore();

		$store->addField(new PhpExt_Data_FieldConfigObject('id'));
		$store->addField(new PhpExt_Data_FieldConfigObject('name'));

		$store->setUrl($url);
		$store->setRoot('topics');
		$store->setTotalProperty('totalCount');
		$store->setId('id');
		$store->setBaseParams(array('query' => ''));
		$store->setAutoLoad(true);

		$store->attachListener('load',
				new PhpExt_Listener(
						PhpExt_Javascript::functionDef(
								null,
								"var combo = Ext.getCmp('id_$name'); combo.lastQuery = ''; combo.setValue('$value');",
								array('s', 'recs', 'o')
						),
						null /* anonymous function */,
						10 /* delay */
				)
		);

		// dropdown with checkboxes
		$data = PhpExt_Form_LovCombo::createLovCombo(null, $label, 'id_' . $name, $name);
		$data->setMode(PhpExt_Form_ComboBox::MODE_REMOTE);
		$data->setStore($store);
		$data->setValueField('id');
		$data->setDisplayField('name');
		$data->setTriggerAction(PhpExt_Form_ComboBox::TRIGGER_ACTION_ALL);
		$data->setEditable(false);
		$data->setWidth(400);

		return $data;
	}
}