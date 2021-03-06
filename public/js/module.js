
(function(Icinga) {

    var Director = function(module) {
        this.module = module;

        this.initialize();

        this.openedFieldsets = {};

        this.module.icinga.logger.debug('Director module loaded');
    };

    Director.prototype = {

        initialize: function()
        {
            /**
             * Tell Icinga about our event handlers
             */
            this.module.on('rendered', this.rendered);
            this.module.on('click', 'fieldset > legend', this.toggleFieldset);
            this.module.on('focus', 'form input', this.formElementFocus);
            this.module.on('focus', 'form select', this.formElementFocus);
            this.module.icinga.logger.debug('Director module initialized');
        },

        formElementFocus: function(ev)
        {
            var $input = $(ev.currentTarget);
            var $dd = $input.closest('dd');
            if ($dd.attr('id') && $dd.attr('id').match(/button/)) {
                return;
            }
            var $li = $input.closest('li');
            var $dt = $dd.prev();
            var $form = $dt.closest('form');
            $form.find('dt').removeClass('active');
            $form.find('dd').removeClass('active');
            $form.find('li').removeClass('active');
            $li.addClass('active');
            $dt.addClass('active');
            $dd.addClass('active');
        },

        toggleFieldset: function (ev) {
            ev.stopPropagation();
            var $fieldset = $(ev.currentTarget).closest('fieldset');
            $fieldset.toggleClass('collapsed');
            this.fixFieldsetInfo($fieldset);
            this.openedFieldsets[$fieldset.attr('id')] = ! $fieldset.hasClass('collapsed');
        },

        rendered: function(ev) {
            var $container = $(ev.currentTarget);
            var self = this;
            $container.find('form').each(self.restoreFieldsets.bind(self));

            var $objectType = $container.find('form').find('select[name=object_type]');
            if ($objectType.length) {
                if ($objectType[0].value === '') {
                    $objectType.focus();
                }
            }
        },

        restoreFieldsets: function(idx, form) {
            var $form = $(form);
            var formId = $form.attr('id');
            var self = this;

            $('fieldset', $form).each(function(idx, fieldset) {
                var $fieldset = $(fieldset);
                if ($fieldset.find('.required').length == 0 && (! self.fieldsetWasOpened($fieldset))) {
                    $fieldset.addClass('collapsed');
                    self.fixFieldsetInfo($fieldset);
                }
            });
        },

        fieldsetWasOpened: function($fieldset) {
            var id = $fieldset.attr('id');
            if (typeof this.openedFieldsets[id] === 'undefined') {
                return false;
            }
            return this.openedFieldsets[id];
        },

        fixFieldsetInfo: function($fieldset) {
            if ($fieldset.hasClass('collapsed')) {
                if ($fieldset.find('legend span.element-count').length === 0) {
                    var cnt = $fieldset.find('dt').length;
                    $fieldset.find('legend').append($('<span class="element-count"> (' + cnt + ')</span>'));
                }
            } else {
                $fieldset.find('legend span.element-count').remove();
            }
        }
    };

    Icinga.availableModules.director = Director;

}(Icinga));

