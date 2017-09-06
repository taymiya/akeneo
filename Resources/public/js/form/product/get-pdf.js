'use strict';
define(
        [
            'jquery',
            'underscore',
            'backbone',
            'pim/form',
            'pim/user-context',
            'text!pmanager/product/get-pdf',
            'routing',
            'oro/messenger',
            'pim/dialog'
        ],
        function ($, _, Backbone, BaseForm, UserContext, template, Routing, messenger, Dialog) {
            return BaseForm.extend({
                template: _.template(template),
                className: 'panel-pane',
                pdfTemplates: [],
                events: {
                    'click .pdf-buttons .send-pdf': 'savePDF'
                },
                initialize: function () {
                    //this.pdfTemplate = new Backbone.Model();

                    //BaseForm.prototype.initialize.apply(this, arguments);
                },
                configure: function () {
                    this.trigger('panel:register', {
                        code: this.code,
                        label: _.__('ibnab.pmanager.pdftemplate.getpdf.title')
                    });

                    return BaseForm.prototype.configure.apply(this, arguments);
                },
                render: function () {
                    if (!this.configured || this.code !== this.getParent().getCurrentPanelCode()) {
                        return this;
                    }

                    this.loadData().done(function (data) {
                        this.pdfTemplates = data;

                        this.$el.html(
                                this.template({
                                    pdfTemplates: this.pdfTemplates,
                                    currentUser: UserContext.toJSON(),
                                    code: this.code
                                })
                                );
                        this.delegateEvents();
                    }.bind(this));

                    return this;
                },
                loadData: function () {
                    return $.get(
                            Routing.generate(
                                    'pmanager_template_jsontemplate',
                                    {
                                        id: this.getFormData().meta.id
                                    }
                            )
                            );
                },
                savePDF: function () {
                    if ($('#' + this.code).find('option:selected').val() === undefined) {
                      alert(_.__('No PDF Template Selected'))
                    } else {
                        var params = "?template_id=" + $('#' + this.code).find('option:selected').val() + "&id=" + this.getFormData().meta.id + "&dataLocale=" + UserContext.get('catalogLocale') + "&dataScope=" + UserContext.get('catalogScope');
                        window.location = Routing.generate('pmanager_default_index') + params;


                    }
                    /*
                     $.ajax({
                     type: 'POST',
                     url: Routing.generate('pmanager_default_index'),
                     data: { 'template_id': $('#'+this.code).find('option:selected').val(),id: this.getFormData().meta.id }
                     }).done(function (data) {
                     //console.log(data);
                     //messenger.notificationFlashMessage('success', _.__('flash.comment.create.success'));
                     }.bind(this)).fail(function () {
                     // messenger.notificationFlashMessage('error', _.__('flash.comment.create.error'));
                     });*/
                }
            });
        }
);
