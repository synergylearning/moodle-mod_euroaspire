/*global M*/
var CSS = {
    URLERROR: 'urlerror',
    SPINNER: 'spinner'
};
var SELECTOR = {
    LINK: 'input.evidenceurl',
    FORM: 'form.mform',
    URLERROR: 'form.mform .urlerror',
    FORMITEM: '.fitem',
    SPINNER: '.spinner'
};
M.mod_euroaspire = M.mod_euroaspire || {};
M.mod_euroaspire.addevidence = {
    currentValues: {},
    url: '',
    cmid: null,

    init: function(opts) {
        "use strict";
        var form;

        this.url = M.cfg.wwwroot + '/mod/euroaspire/assessment/ajax_getlinktitle.php';
        this.cmid = opts.cmid;
        this.storeCurrentValues();

        form = Y.one(SELECTOR.FORM);
        form.delegate('blur', this.updateTitle, SELECTOR.LINK, this);
    },

    storeCurrentValues: function() {
        "use strict";
        Y.all(SELECTOR.LINK).each(function(link) {
            this.currentValues[link.get('id')] = link.get('value').trim();
        }, this);
    },

    updateTitle: function(e) {
        "use strict";
        var currentVal, linkNode, linkId, linkVal, titleNode;

        this.removeErrors();

        linkNode = e.currentTarget;
        linkId = linkNode.get('id');
        linkVal = linkNode.get('value').trim();
        currentVal = this.currentValues[linkId];
        if (currentVal === linkVal) {
            return;
        }
        this.currentValues[linkId] = linkVal;

        titleNode = this.getTitleNode(linkNode);
        if (!titleNode) {
            return;
        }
        if (!linkVal) {
            titleNode.set('value', '');
            return;
        }

        this.addSpinner(titleNode);

        Y.io(this.url, {
            data: {
                id: this.cmid,
                url: linkVal,
                sesskey: M.cfg.sesskey
            },
            on: {
                success: function(id, resp) {
                    var details;
                    try {
                        details = JSON.parse(resp.responseText);
                    } catch (Exception) {

                    }
                    if (!details || details.error || !details.title) {
                        this.addError(titleNode);
                        return;
                    }
                    titleNode.set('value', details.title);
                },
                complete: function() {
                    this.removeSpinner(titleNode);
                }
            },
            context: this
        });
    },

    removeErrors: function() {
        "use strict";
        Y.all(SELECTOR.URLERROR).remove(true);
    },

    getTitleNode: function(linkNode) {
        "use strict";
        var id;
        id = linkNode.get('id');
        id = id.replace('url', 'urltitle');
        return Y.one('#' + id);
    },

    addError: function(titleNode) {
        "use strict";
        var errNode, beforeNode, withinNode, errOuter;

        beforeNode = titleNode.ancestor(SELECTOR.FORMITEM);
        if (!beforeNode) {
            return;
        }
        withinNode = beforeNode.ancestor();

        errNode = Y.Node.create('<div>' + M.util.get_string('titleerror', 'mod_euroaspire') + '</div>');
        errNode.addClass(CSS.URLERROR);
        errNode.addClass('felement');
        errOuter = Y.Node.create('<div class="fitem"></div>');
        errOuter.appendChild(errNode);

        withinNode.insertBefore(errOuter, beforeNode);
    },

    addSpinner: function(titleNode) {
        "use strict";
        var parent, spinner;

        this.removeSpinner(titleNode);

        spinner = Y.Node.create('<span class="spinner"></span>');
        parent = titleNode.ancestor();
        parent.appendChild(spinner);
        titleNode.set('disabled', 'disabled');
    },

    removeSpinner: function(titleNode) {
        "use strict";
        var parent;
        parent = titleNode.ancestor();
        parent.all(SELECTOR.SPINNER).remove(true);
        titleNode.removeAttribute('disabled');
    }
};
