<?php
/**
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for xt:Commerce. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

defined('_VALID_CALL') or die('Direct Access is not allowed.');

require_once _SRV_WEBROOT . _SRV_WEB_PLUGINS . 'ly_payzen/classes/class.ly_payzen.php';

class PayzenExtAdminHandler extends ExtAdminHandler
{
    private $_master_key = 'id';

    function setPosition($position)
    {
        $this->position = $position;
    }

    function _getParams()
    {
        $params = array();
        $params['header'] = array();
        $params['master_key'] = $this->_master_key;
        $params['display_deleteBtn'] = false;
        $params['display_resetBtn'] = false;
        $params['display_editBtn'] = false;
        $params['display_newBtn'] = false;
        $params['display_searchPanel'] = false;

        return $params;
    }

    function __construct($extAdminHandler)
    {
        if (is_array($extAdminHandler)) {
            // Clone default admin handler to ours.
            foreach ($extAdminHandler as $key => $value) {
                $this->$key = $value;
            }
        }
    }

    function _switchFormField($line_data, $lang_data = array())
    {
        $label = $line_data['text'];
        $name = $line_data['name'];

        if (! ly_payzen::$payzen_plugin_features['multi'] && (strpos($name, 'conf_LY_PAYZEN_MULTI') === 0)) {
            $data = PhpExt_Form_Hidden::createHidden('', '');
            $data->setDisabled(true);
            return $data;
        }

        switch ($line_data['type']) {
            case 'payzen_dropdown_ms':
                if (empty($line_data['url'])) {
                    // Default dropdown status.
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
                $store->setData(json_decode(stripslashes($line_data['value']), true));
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
                    '   var grid = Ext.ComponentMgr.get("id_grid_' . $name . '");

                        var options = [];
                        grid.getStore().each(function(rec) {
                            options.push(rec.data);
                        });

                        var hidden = Ext.getCmp("id_' . $name . '");
                        hidden.setValue(Ext.util.JSON.encode(options));',
                    array('store')
                );
                $store->attachListener('add', new PhpExt_Listener($onChangeFnc, null /* scope */, 10 /* delay */));
                $store->attachListener('remove', new PhpExt_Listener($onChangeFnc, null /* scope */, 10 /* delay */));
                $store->attachListener('update', new PhpExt_Listener($onChangeFnc, null /* scope */, 10 /* delay */));

                $labelPanel = new PhpExt_Panel();
                $labelPanel->setHtml('<label style="width: 200px;" class="x-form-item-label">' . $label . '</label>');
                $labelPanel->setBaseCssClass('x-form-item');

                $data = new PhpExt_Panel();
                $data->setBorder(false)
                    ->setCssClass('x-form-item x-tab-item')
                    ->setButtonAlign(PhpExt_Ext::HALIGN_CENTER);

                $data->addItem($labelPanel);
                $data->addItem($gridPanel);
                $data->addItem(PhpExt_Form_Hidden::createHidden($name, html_entity_decode($line_data['value']), 'id_' . $name));

                $addBtn = PhpExt_Button::createTextButton(
                    TEXT_PAYZEN_ADD,
                    new PhpExt_Handler(
                        PhpExt_Javascript::stm('
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
                            grid.startEditing(grid.getStore().getCount() - 1, 0);'
                        )
                    )
                );

                $addBtn->setType(PhpExt_Button::BUTTON_TYPE_BUTTON);
                $addBtn->setCssClass('x-btn-text-icon');
                $addBtn->setIcon('images/icons/add.png');
                $data->addButton($addBtn);

                $deleteBtn = PhpExt_Button::createTextButton(
                    TEXT_PAYZEN_DELETE,
                    new PhpExt_Handler(
                        PhpExt_Javascript::stm('
                            var grid = Ext.ComponentMgr.get("id_grid_' . $name . '");

                            var sel = grid.getSelectionModel().getSelections();
                            Ext.each(sel, function(item) {
                                grid.getStore().remove(item);
                            });'
                        )
                    )
                );

                $deleteBtn->setType(PhpExt_Button::BUTTON_TYPE_BUTTON);
                $deleteBtn->setIconCssClass('delete');
                $data->addButton($deleteBtn);
                break;

            case 'payzen_multilang_textfield':
                global $language ;
                $value = json_decode(stripslashes($line_data['value']), true);
                $data = new PhpExt_Panel();
                $data->setBorder(false)
                    ->setLayout(new PhpExt_Layout_ColumnLayout())
                    ->setCssClass('x-form-item x-tab-item');

                $labelPanel = new PhpExt_Panel();
                $labelPanel->setHtml('<label style="width: 200px;" class="x-form-item-label">' . $label . '</label>');
                $labelPanel->setBaseCssClass('x-form-item');
                $data->addItem($labelPanel);

                $onChangeFnc = PhpExt_Javascript::functionDef(
                    null,
                    '   var combo = Ext.getCmp("id_lang_' . $name . '");
                        var id = combo.getValue();

                        var hidden = Ext.getCmp("id_' . $name . '");
                        var texts = Ext.util.JSON.decode(hidden.getValue());
                        texts[id] = newValue ;

                        hidden.setValue(Ext.util.JSON.encode(texts));',
                    array('textfield', 'newValue', 'oldValue')
                );

                $textfield = PhpExt_Form_TextField::createTextField('text_' . $name, null, 'id_text_' . $name);
                $textfield->setValue($value[$language->code] ? addslashes($value[$language->code]) : '');
                $textfield->setMaskRegEx(PhpExt_Javascript::inlineStm('/[^"]/'));
                $textfield->attachListener('change', new PhpExt_Listener($onChangeFnc, null /* scope */, 10 /* delay */));
                $data->addItem($textfield, new PhpExt_Layout_ColumnLayoutData(.45));

                // Dropdown with checkboxes.
                $store = new PhpExt_Data_SimpleStore();
                $store->addField(new PhpExt_Data_FieldConfigObject('id'));
                $store->addField(new PhpExt_Data_FieldConfigObject('name'));

                $datalanguages = array();
                foreach ($language->_getLanguageList() as $val) {
                    $datalanguages[] = array($val['code'], $val['text']);
                }

                $store->setData($datalanguages);

                $onSelectFnc = PhpExt_Javascript::functionDef(
                    null,
                    '   var hidden = Ext.getCmp("id_' . $name . '");
                        var texts = Ext.util.JSON.decode(hidden.getValue());

                        var textfield = Ext.ComponentMgr.get("id_text_' . $name . '");
                        textfield.setValue(texts[record.get("id")] || "");
                    ',
                    array('combo', 'record', 'index')
                );

                $combobox = PhpExt_Form_ComboBox::createComboBox('lang_' . $name, null, 'id_lang_' . $name, null);
                $combobox->setMode(PhpExt_Form_ComboBox::MODE_LOCAL);
                $combobox->setStore($store);
                $combobox->setValueField('id');
                $combobox->setDisplayField('name');
                $combobox->setEditable(false);
                $combobox->setTriggerAction(PhpExt_Form_ComboBox::TRIGGER_ACTION_ALL);
                $combobox->setValue($language->code);
                $combobox->attachListener('select', new PhpExt_Listener($onSelectFnc, null /* scope */, 10 /* delay */));
                $data->addItem($combobox, new PhpExt_Layout_ColumnLayoutData(.1));

                $data->addItem(PhpExt_Form_Hidden::createHidden($name, html_entity_decode($line_data['value']), 'id_' . $name));
                break;

            case 'payzen_key_test_textfield':
                // Show key test field based on qualif feature.
                if (ly_payzen::$payzen_plugin_features['qualif']) {
                    $data = PhpExt_Form_Hidden::createHidden('', '');
                    $data->setDisabled(true);
                } else {
                    $data = parent::_switchFormField($line_data, $lang_data);
                }

                break;

            case 'payzen_ctx_mode_dropdown':

                $line_data['type'] = 'dropdown';
                $line_data['url'] = 'DropdownData.php?get=' . $line_data['url'];
                $data = parent::_switchFormField($line_data, $lang_data);

                // Change ctx_mode based on qualif feature.
                if (ly_payzen::$payzen_plugin_features['qualif']) {
                    $data->setDisabled(true);
                }

                break;

            case 'payzen_description':// Add description field type.

                // Change sign algo field description based on shatwo feature.
                if (ly_payzen::$payzen_plugin_features['shatwo'] && (strpos($name, 'conf_LY_PAYZEN_SIGN_ALGO_DESC2') === 0)) {
                    $data = PhpExt_Form_Hidden::createHidden('', '');
                    $data->setDisabled(true);
                } elseif (ly_payzen::$payzen_plugin_features['qualif'] && (strpos($name, 'conf_LY_PAYZEN_KEY_TEST_DESC') === 0)) {
                    $data = PhpExt_Form_Hidden::createHidden('', '');
                    $data->setDisabled(true);
                } else {
                    $data = new PhpExt_Panel();
                    $data->setBorder(false);
                    $color = '#565454';
                    if (strpos($name, 'conf_LY_PAYZEN_CHECK_URL_DESC') === 0) {
                        $color = 'red';
                    }

                    $data->setHtml('<div class="control-group"><p style="color:' . $color . '; margin-left: 205px;">' . $label . '</p></div>');
                }

                break;

            case 'payzen_doc':
                // Get documentation links.
                $docs = '' ;
                $DOC_PATTERN = '${doc.pattern}';
                $filenames = glob(_SRV_WEBROOT . _SRV_WEB_PLUGINS . 'ly_payzen/installation_doc/' . $DOC_PATTERN);

                if (! empty($filenames)) {
                    $languages = array(
                        'fr' => 'Français',
                        'en' => 'English',
                        'es' => 'Español',
                        'de' => 'Deutsch'
                        // Complete when other languages are managed.
                    );
                    foreach ($filenames as $filename) {
                        $base_filename = basename($filename, '.pdf');
                        $lang = substr($base_filename, -2); // Extract language code.

                        $docs .= ' <a target="_blank" href="' . _SYSTEM_BASE_URL . str_replace('/xtAdmin/', '', _SRV_WEB)
                            . '/plugins/ly_payzen/installation_doc/' . $base_filename . '.pdf" >' . $languages[$lang] . '</a>';
                    }

                    $html = $label . $docs;
                    $data = new PhpExt_Panel();
                    $data->setBorder(false);
                    $data->setHtml('<div class="control-group"><span style="color: red; font-weight: bold; text-transform: uppercase;">' . $html . '</span></div>');
                } else {
                    $data = PhpExt_Form_Hidden::createHidden('', '');
                    $data->setDisabled(true);
                }

                break;

            case 'payzen_multi_restriction_warn':
                // Add restrict multi feature.
                if (ly_payzen::$payzen_plugin_features['restrictmulti']) {
                    $data = new PhpExt_Panel();
                    $data->setHtml($label);
                    $data->setBodyStyle('border: 1px solid #f9c59d; color:#f38733; background-color: #fef8f4; padding: 3px; margin: 15px 0px 5px;');
                } else {
                    $data = PhpExt_Form_Hidden::createHidden('', '');
                    $data->setDisabled(true);
                }

                break;

            default:
                $data = parent::_switchFormField($line_data, $lang_data);
                break;
        }

        return $data;
    }

    function _getCallbackLink()
    {
        global $xtLink;

        $checkURL = $xtLink->_link(array('page' => 'callback', 'paction' => 'ly_payzen', 'conn' => 'SSL'), 'xtAdmin/');
        return html_entity_decode($checkURL);
    }

    function _payzenMultiComboBox($label, $name, $value, $url)
    {
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

        // Dropdown with checkboxes.
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
