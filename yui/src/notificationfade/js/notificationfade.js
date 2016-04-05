/*global M*/
var CSS = {
    FADEOUT: 'fadeout',
    SHRINK: 'shrink',
    HIDDEN: 'hide'
};
var SELECTOR = {
    NOTIFICATION: '.notification'
};
M.mod_euroaspire = M.mod_euroaspire || {};
M.mod_euroaspire.notificationfade = {
    notifications: null,

    init: function() {
        "use strict";
        var self = this;
        this.notifications = Y.all(SELECTOR.NOTIFICATION);
        setTimeout(function() { self.startFade(); }, 4000);
    },

    startFade: function() {
        "use strict";
        var self = this;
        this.notifications.addClass(CSS.FADEOUT);
        setTimeout(function() { self.shrink(); }, 800);
    },

    shrink: function() {
        "use strict";
        var self = this;
        this.notifications.addClass(CSS.SHRINK);
        setTimeout(function() { self.hide(); }, 500);
    },

    hide: function() {
        "use strict";
        this.notifications.addClass(CSS.HIDDEN);
        this.notifications.removeClass(CSS.FADEOUT);
        this.notifications.removeClass(CSS.SHRINK);
    }
};
