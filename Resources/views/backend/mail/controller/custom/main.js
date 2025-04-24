//{block name="backend/mail/controller/main" append}
Ext.define('Shopware.apps.Mail.controller.custom.Main', {
    override: 'Shopware.apps.Mail.controller.Main',
    /**
     * @event click
     * @param [object] btn - the btn that fired the event
     * @return void
     */
    onSave: function (btn) {
        var me              = this,
            formPanel       = me.getFormPanel(),
            form            = formPanel.getForm(),
            record          = form.getRecord(),
            treeNeedsReload = false;

        if (!form.isValid()) {
            return;
        }

        if (!record) {
            record = Ext.create('Shopware.apps.Mail.model.Mail');
        }

        var oldName = record.get('name');

        form.updateRecord(record);
        
        // if we insert a new record or name of record changed reload the tree
        if (record.phantom || record.get('name') != oldName) {
            treeNeedsReload = true;
        }

        formPanel.setLoading(true);
        record.save({
            success: function(record, operation) {
                me.getAttributeForm().saveAttribute(record.get('id'), function() {
                    formPanel.setLoading(false);
                    me.loadRecord(record);
                    if (treeNeedsReload) {
                        me.reloadTree();
                    }
                    Shopware.Notification.createGrowlMessage(me.snippets.saveSuccessTitle, me.snippets.saveSuccessMessage, me.snippets.growlMessage);
                });

                Ext.Ajax.request({
                    method: 'POST',
                    url: '{url controller=AttributeData action=saveData}',
                    params: {
                        _foreignKey: record.get('id'),
                        _table: 's_core_config_mails_attributes',
                        __attribute_optivo_enable: form.findField("attribute[optivoEnable]").getValue() ? 1 : 0,
                        __attribute_optivo_authcode: form.findField("attribute[optivoAuthcode]").getValue(),
                        __attribute_optivo_bmmailid: form.findField("attribute[optivoBmMailId]").getValue(),
                        __attribute_optivo_content: form.findField("attribute[optivoContent]").getValue()
                    }
                });
            },
            failure: function() {
                formPanel.setLoading(false);
                Shopware.Notification.createGrowlMessage(me.snippets.saveErrorTitle, me.snippets.saveErrorMessage, me.snippets.growlMessage);
            }
        });
    },
});
//{/block}


