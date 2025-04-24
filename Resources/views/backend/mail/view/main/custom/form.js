/**
 * optivo(R) broadmail
 *
 * @version   1.1.4
 * @license   https://www.optivo.de/agb
 * @copyright Copyright (c) 2015 optivo GmbH (http://www.optivo.de/)
 * All rights reserved.
 */
//{namespace name=backend/mail/view/form}

//{block name="backend/mail/view/main/form" append}
Ext.override(Shopware.apps.Mail.view.main.Form, {

    newRecord: function() {
        var me   = this;

        me.callParent(arguments);
        me.getComponent('tabpanel').getComponent('optivoContentTab').setDisabled(
            !me.getForm().findField("attribute[optivoEnable]").getValue()
        );
    },

    loadRecord: function() {
        var me = this;

        me.callParent(arguments);

        Ext.Ajax.request({
            url: '{url controller=AttributeData action=loadData}',
            params: {
                _foreignKey: me.getForm().getRecord().get('id'),
                _table: 's_core_config_mails_attributes'
            },
            success: function(responseData, request) {
                var response = Ext.JSON.decode(responseData.responseText);
                me.getForm().findField("attribute[optivoEnable]").setValue(response.data['__attribute_optivo_enable'])
                me.getForm().findField("attribute[optivoAuthcode]").setValue(response.data['__attribute_optivo_authcode'])
                me.getForm().findField("attribute[optivoBmMailId]").setValue(response.data['__attribute_optivo_bmmailid'])
                me.getForm().findField("attribute[optivoContent]").setValue(response.data['__attribute_optivo_content'])
            }
        });
    },

    getItems: function () {
        var me = this;

        var items = me.callParent();

        items[0].items.push({
            xtype: 'checkboxfield',
            inputValue: true,
            uncheckedValue: false,
            name: 'attribute[optivoEnable]',
            fieldLabel: '{s name=label_optivomailBroadmail}optivo® broadmail{/s}',
            boxLabel: '{s name=boxlabel_optivomailBroamail}mit optivo® broadmail versenden{/s}',
            listeners: {
                /**
                 * Fires when field is rendered
                 *
                 * @event afterrender
                 * @param [Ext.form.field.Field]
                 */
                afterrender: function (field) {
                    // @todo: enable or disable fields according to state
                },
                /**
                 * Fires when a user-initiated change is detected in the value of the field.
                 *
                 * @event change
                 * @param [Ext.form.field.Field]
                 * @param [Object] checked
                 */
                change: function (field, newValue) {
                    me.getComponent('tabpanel').getComponent('optivoContentTab').setDisabled(!newValue);
                    me.getForm().findField("isHtml").setDisabled(newValue);
                    me.getForm().findField("attribute[optivoAuthcode]").setDisabled(!newValue);
                    me.getForm().findField("attribute[optivoBmMailId]").setDisabled(!newValue);
                }
            }
        });

        items[0].items.push({
            xtype: 'textfield',
            fieldLabel: 'optivo authcode',
            name: 'attribute[optivoAuthcode]',
            // id: 'attrOptivoAuthCode',
            translationName: 'OptivoAuthcode',
            translatable: true, // Indicates that this field is translatable
            allowBlank: false,
        });

        items[0].items.push({
            xtype: 'textfield',
            fieldLabel: 'optivo bmMailingId',
            name: 'attribute[optivoBmMailId]',
            // id: 'attrOptivoBmMailId',
            translationName: 'OptivoBmMailId',
            translatable: true, // Indicates that this field is translatable
            allowBlank: false,
        });

        items[1].add({
            xtype: 'mail-main-contentEditorOptivo',
            title: '{s name=tab_optivoBroadmail}optivo® broadmail{/s}',
            itemId: 'optivoContentTab',
            id: 'optivoContentTab',
            name: 'optivoTab'
        });

        return items;
    }
});
//{/block}
