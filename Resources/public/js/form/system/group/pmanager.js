"use strict";

define([
    'pim/form',
    'text!pmanager/template/system/group/allconfig',
    'bootstrap.bootstrapswitch',
    'routing'
],
        function (
                BaseForm,
                template
                ) {
            return BaseForm.extend({
                events: {
                    'change textarea[name="textheader"]': 'updateText',
                    'change input[name="logosize"]': 'updateLogosize',
                    'change input[name="titleheader"]': 'updateTitleheader',
                    'change input[name="marginheader"]': 'updateMarginheader',
                    'change input[name="marginfooter"]': 'updateMarginfooter',
                    'change input[name="logoupload"]': 'updateFile'
                },
                isGroup: true,
                label: _.__('ibnab.form.config.group.pmanager.title'),
                template: _.template(template),
                /**
                 * {@inheritdoc}
                 */
                render: function () {
                    this.$el.html(this.template({
                        'textheader': this.getFormData().ibnab_pmanager___textheader.value,
                        'logosize': this.getFormData().ibnab_pmanager___logosize.value,
                        'titleheader': this.getFormData().ibnab_pmanager___titleheader.value,
                        'marginheader': this.getFormData().ibnab_pmanager___marginheader.value,
                        'marginfooter': this.getFormData().ibnab_pmanager___marginfooter.value,
                        'logo_file': this.getFormData().ibnab_pmanager___logoupload.value
                    }));

                    this.delegateEvents();

                    return BaseForm.prototype.render.apply(this, arguments);
                },
                /**
                 * Update model after value change
                 *
                 * @param {Event}
                 */
                updateText: function (event) {
                    var data = this.getFormData();
                    data.ibnab_pmanager___textheader.value = $(event.target).val();
                    this.setData(data);
                },
                updateLogosize: function (event) {
                    var data = this.getFormData();
                    data.ibnab_pmanager___logosize.value = $(event.target).val();
                    this.setData(data);
                },
                updateTitleheader: function (event) {
                    var data = this.getFormData();
                    data.ibnab_pmanager___titleheader.value = $(event.target).val();
                    this.setData(data);
                },
                updateMarginheader: function (event) {
                    var data = this.getFormData();
                    data.ibnab_pmanager___marginheader.value = $(event.target).val();
                    this.setData(data);
                },
                updateMarginfooter: function (event) {
                    var data = this.getFormData();
                    data.ibnab_pmanager___marginfooter.value = $(event.target).val();
                    this.setData(data);
                },
                updateFile: function (event) {
                    $('.ibnab-system-logo-width-field-span').html('');
                    var data = this.getFormData();
                    var self =this;
                    var input = $(event.target).get(0);
                    //console.log(input.files[0]);
                    if (!input || 0 === input.files.length) {
                        return;
                    }
                    var form_data = new FormData();
                    form_data.append('file', input.files[0]);
                    $.ajax({
                        url: Routing.generate('pmanager_template_logoupload'), // point to server-side PHP script 
                        dataType: 'text', // what to expect back from the PHP script, if anything
                        cache: false,
                        contentType: false,
                        processData: false,
                        data: form_data,
                        type: 'post',
                        success: function (response) {
                              data.ibnab_pmanager___logoupload.value = input.files[0].name;
                              self.setData(data);
                        }
                    });

                },
            });
        }
);
