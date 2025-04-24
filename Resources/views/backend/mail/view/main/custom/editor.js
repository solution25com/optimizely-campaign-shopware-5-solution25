/**
 * optivo(R) broadmail
 *
 * @version   1.1.4
 * @license   https://www.optivo.de/agb
 * @copyright Copyright (c) 2015 optivo GmbH (http://www.optivo.de/)
 * All rights reserved.
 */
//{block name="backend/mail/view/main/contentEditor" append}
//{namespace name=backend/mail/view/contentEditor}
Ext.define('Shopware.apps.Mail.view.main.ContentEditorOptivo', {
    extend: 'Ext.Panel',
    alias: 'widget.mail-main-contentEditorOptivo',
    bodyPadding: 10,

    layout: 'fit',

    isHtml: false,

    /**
     * Defines additional events which will be fired
     *
     * @return void
     */
    registerEvents: function () {
        this.addEvents(
            /**
             * Event will be fired when the user clicks the show preview button
             *
             * @event showPreview
             * @param [string] content of the textarea
             * @param [boolean]
             */
            'showPreview',

            /**
             * Event will be fired when the user clicks the send testmail button
             *
             * @event sendTestMail
             * @param [string] content of the textarea
             * @param [boolean]
             */
            'sendTestMail'
        );
    },

    /**
     * Initializes the component and builds up the main interface
     *
     * @public
     * @return void
     */
    initComponent: function () {
        var me = this;

        me.items = me.getItems();

        me.callParent(arguments);
    },

    /**
     * Creates items shown in form panel
     *
     * @return array
     */
    getItems: function () {
        var me = this;

        me.editorField = Ext.create('Shopware.form.field.CodeMirror', {
            xtype: 'codemirrorfield',
            mode: 'smarty',
            name: 'attribute[optivoContent]',
            translationName: 'Optivo-Content',
            translationLabel: '{s name=codemirrorOptivo_translationLabel}Optivo-Content{/s}',
            translatable: true // Indicates that this field is translatable
        });


        me.editorField.on('editorready', function (editorField, editor) {
            var scroller, size;

            if (!editor || !editor.hasOwnProperty('display')) {
                return false;
            }

            scroller = editor.display.scroller;
            size = editorField.getSize();
            editor.setSize('100%', size.height);
            Ext.get(scroller).setSize(size);
        });

        me.on('resize', function (cmp, width, height) {
            var editorField = me.editorField,
                editor = editorField.editor,
                scroller;

            if (!editor || !editor.hasOwnProperty('display')) {
                return false;
            }

            scroller = editor.display.scroller;

            width -= me.bodyPadding * 2;
            // We need to remove the bodyPadding, the padding on the field itself and the scrollbars
            height -= me.bodyPadding * 5;

            editor.setSize(width, height);
            Ext.get(scroller).setSize(
                {
                    width: width, height: height
                }
            );

        });

        return me.editorField;
    }
});
//{/block}
