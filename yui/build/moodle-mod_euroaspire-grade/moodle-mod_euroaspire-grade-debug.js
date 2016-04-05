YUI.add('moodle-mod_euroaspire-grade', function (Y, NAME) {

/*global M*/
var CSS = {
    HOVER: 'hover',
    SELECTED: 'selected'
};
var SELECTOR = {
    FORM: '#gradeitemform',
    CELL: 'td',
    PASS: '.gradepass',
    FAIL: '.gradefail',
    GRADEINPUT: 'input[type="hidden"]',
    GRADECOMMENT: 'input[type="text"]'
};
M.mod_euroaspire = M.mod_euroaspire || {};
M.mod_euroaspire.grade = {
    hasChanges: false,

    init: function() {
        "use strict";
        var form, self;

        // Highlight 'add' buttons when hovering over the cells.
        form = Y.one(SELECTOR.FORM);
        form.addClass('jsinit');
        form.delegate('mouseenter', this.enterCell, SELECTOR.CELL, this);
        form.delegate('mouseleave', this.leaveCell, SELECTOR.CELL, this);

        // Handle clicking on pass/fail buttons.
        form.delegate('click', this.clickPass, SELECTOR.PASS, this);
        form.delegate('click', this.clickFail, SELECTOR.FAIL, this);

        // Warn about navigating without saving.
        self = this;
        window.addEventListener('beforeunload', function(e) {
            return self.unsavedWarning(e);
        });
        form.on('submit', function() { this.hasChanges = false; }, this);
        form.delegate('valuechange', function() { this.hasChanges = true; }, SELECTOR.GRADECOMMENT, this);
    },

    /**
     * Highlight add/remove buttons when hovering over a table cell.
     * @param e
     */
    enterCell: function(e) {
        "use strict";
        e.currentTarget.addClass(CSS.HOVER);
    },

    /**
     * Highlight add/remove buttons when hovering over a table cell.
     * @param e
     */
    leaveCell: function(e) {
        "use strict";
        e.currentTarget.removeClass(CSS.HOVER);
    },

    clickPass: function(e) {
        "use strict";
        var cell;
        cell = e.currentTarget.ancestor(SELECTOR.CELL);
        if (e.currentTarget.hasClass(CSS.SELECTED)) {
            this.updateCellGrade(cell, 'none');
        } else {
            this.updateCellGrade(cell, 'pass');
        }
    },

    clickFail: function(e) {
        "use strict";
        var cell;
        cell = e.currentTarget.ancestor(SELECTOR.CELL);
        if (e.currentTarget.hasClass(CSS.SELECTED)) {
            this.updateCellGrade(cell, 'none');
        } else {
            this.updateCellGrade(cell, 'fail');
        }
    },

    updateCellGrade: function(cell, grade) {
        "use strict";
        var passIcon, failIcon, input;

        passIcon = cell.one(SELECTOR.PASS);
        failIcon = cell.one(SELECTOR.FAIL);
        input = cell.one(SELECTOR.GRADEINPUT);

        if (grade === 'pass') {
            passIcon.addClass(CSS.SELECTED);
        } else {
            passIcon.removeClass(CSS.SELECTED);
        }
        if (grade === 'fail') {
            failIcon.addClass(CSS.SELECTED);
        } else {
            failIcon.removeClass(CSS.SELECTED);
        }
        input.set('value', grade);

        this.hasChanges = true;
    },

    unsavedWarning: function(e) {
        "use strict";
        var msg;

        if (!this.hasChanges) {
            return;
        }

        msg = M.util.get_string('changesmadereallygoaway', 'core');
        (e || window.event).returnValue = msg;
        return msg;
    }
};


}, '@VERSION@', {"requires": ["node", "event-mouseenter", "event-valuechange", "moodle-core-notification-dialogue"]});
