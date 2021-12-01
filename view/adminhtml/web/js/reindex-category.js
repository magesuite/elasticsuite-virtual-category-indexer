define(
    [
        'jquery',
        'mage/url',
        'Magento_Ui/js/modal/alert'
    ],
    function ($, url, alert) {
        'use strict';

        var mixin = {

            reindexVirtualCategory: function() {

                var self = this;
                var dialog = alert;

                $.ajax({
                    url: self.data.reindex_url,
                    type: 'POST',
                    context: this,
                    data: {
                        'form_key': window.FORM_KEY
                    },
                    dataType: 'JSON',
                    showLoader: true
            }).done(function (response) {
                    dialog({
                        title: '',
                        content: response.message,
                    });
                });
            }
        };

        return function (target) {
            return target.extend(mixin);
        };
    }
);
