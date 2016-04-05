/*global M*/
var CSS = {
    HOVER: 'hover',
    SELECTED: 'selected'
};
var SELECTOR = {
    FORM: '#mapitemform',
    CELL: 'td',
    ADDITEM: '.addmap',
    REMOVEITEM: '.removemap',
    ADDITEMBUTTON: '.selectitem',
    CURRENTITEMSINPUT: 'input.currentitems',
    CURRENTITEMSLIST: 'ul.mappeditems',
    ITEMCONTENT: '.itemcontent',
    ITEMSELECTLIST: '.itemselect',
    SUBMITCOMMENT: 'textarea'
};
M.mod_euroaspire = M.mod_euroaspire || {};
M.mod_euroaspire.mapevidence = {
    dialogue: null,
    dialogueInner: null,
    selectedCell: null,
    hasChanges: false,

    init: function(opts) {
        "use strict";
        var form, self;

        // Highlight 'add' buttons when hovering over the cells.
        form = Y.one(SELECTOR.FORM);
        form.addClass('jsinit');
        form.delegate('mouseenter', this.enterCell, SELECTOR.CELL, this);
        form.delegate('mouseleave', this.leaveCell, SELECTOR.CELL, this);

        // Prepare the 'add item' popup dialog.
        this.dialogueInner = Y.Node.create('<div>'+opts.itemselect+'</div>');
        this.dialogue = new M.core.dialogue({
            bodyContent: this.dialogueInner,
            width: '550px',
            modal: true,
            visible: false,
            render: true,
            draggable: true
        });

        // Show the dialog as required.
        form.delegate('click', this.showSelect, SELECTOR.ADDITEM, this);
        this.dialogue.after('visibleChange', this.selectHidden, this);
        this.dialogueInner.delegate('click', this.selectItem, SELECTOR.ADDITEMBUTTON, this);

        // Handle removing items.
        form.delegate('click', this.removeItem, SELECTOR.REMOVEITEM, this);

        // Warn about navigating without saving.
        self = this;
        window.addEventListener('beforeunload', function(e) {
            return self.unsavedWarning(e);
        });
        form.on('submit', function() { this.hasChanges = false; }, this);
        form.delegate('valuechange', function() { this.hasChanges = true; }, SELECTOR.SUBMITCOMMENT, this);
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

    /**
     * Show the 'select items' pop-up dialogue.
     * @param e
     */
    showSelect: function(e) {
        "use strict";
        var addIcon;

        // Highlight the cell that is being updated.
        addIcon = e.currentTarget;
        this.selectedCell = addIcon.ancestor(SELECTOR.CELL);
        this.selectedCell.addClass(CSS.SELECTED);

        // Update and show the dialogue.
        this.dialogue.set('headerContent', addIcon.get('title'));
        this.updateDialogueMaxHeight();
        this.refreshAlreadySelected();
        this.dialogue.show();
    },

    /**
     * The 'select items' pop-up dialogue has been closed.
     */
    selectHidden: function() {
        "use strict";
        if (this.dialogue.get('visible')) {
            return; // Dialogue visible - do nothing.
        }
        // Dialogue hidden.
        Y.one(SELECTOR.FORM).all(SELECTOR.CELL).removeClass(CSS.SELECTED);
        this.selectedCell = null;
    },

    /**
     * An item in the pop-up dialogue has been selected.
     * @param e
     */
    selectItem: function(e) {
        "use strict";
        var itemId, itemContent;

        if (!this.selectedCell) {
            return; // Just in case.
        }

        itemId = parseInt(e.currentTarget.getData('itemid'), 10);
        itemContent = e.currentTarget.next(SELECTOR.ITEMCONTENT);

        if (this.cellHasItem(this.selectedCell, itemId)) {
            return; // Already present - don't add it again.
        }
        this.cellAddItem(this.selectedCell, itemId, itemContent);

        this.refreshAlreadySelected();

        this.hasChanges = true;
    },

    /**
     * Clicked on the icon to remove one of the items that was previously mapped.
     * @param e
     */
    removeItem: function(e) {
        "use strict";
        var cellNode, itemId;

        cellNode = e.currentTarget.ancestor(SELECTOR.CELL);
        itemId = parseInt(e.currentTarget.getData('itemid'), 10);
        this.cellRemoveItem(cellNode, itemId);

        this.hasChanges = true;
    },

    /**
     * Get the currently selected itemids from the given cell.
     * @param cellNode Y.Node
     * @returns Integer[]
     */
    cellGetSelected: function(cellNode) {
        "use strict";
        var items;
        items = cellNode.one(SELECTOR.CURRENTITEMSINPUT).get('value');
        if (!items) {
            return [];
        }
        items = items.split(',');
        return items.map(function (el) { return parseInt(el, 10); });
    },

    /**
     * Set the currently selected itemids in the given cell.
     * @param cellNode Y.Node
     * @param items Integer[]
     */
    cellSetSelected: function(cellNode, items) {
        "use strict";
        cellNode.one(SELECTOR.CURRENTITEMSINPUT).set('value', items.join(','));
    },

    /**
     * Insert an extra selected itemid to the end of the list in the cell.
     * @param cellNode Y.Node
     * @param itemId Integer
     */
    cellAddSelected: function(cellNode, itemId) {
        "use strict";
        var items;
        items = this.cellGetSelected(cellNode);
        items.push(itemId);
        this.cellSetSelected(cellNode, items);
    },

    /**
     * Remove the selected itemid from the list in the cell.
     * @param cellNode Y.Node
     * @param itemId Integer
     */
    cellRemoveSelected: function(cellNode, itemId) {
        "use strict";
        var items, idx;
        items = this.cellGetSelected(cellNode);
        idx = items.indexOf(itemId);
        if (idx !== -1) {
            items.splice(idx, 1);
            this.cellSetSelected(cellNode, items);
        }
    },

    /**
     * Check if the selected itemid is already in the cell.
     * @param cellNode Y.Node
     * @param itemId Integer
     * @returns Boolean
     */
    cellHasItem: function(cellNode, itemId) {
        "use strict";
        var items;
        items = this.cellGetSelected(cellNode);
        return (items.indexOf(itemId) !== -1);
    },

    /**
     * Add the selected itemid + content into the specified cell.
     * @param cellNode Y.Node
     * @param itemId Integer
     * @param itemContent Y.Node
     */
    cellAddItem: function(cellNode, itemId, itemContent) {
        "use strict";
        var cloneNode, itemList;

        // Update the hidden input field.
        this.cellAddSelected(cellNode, itemId);

        // Update the displayed list.
        cloneNode = Y.Node.create('<li>'+itemContent.getHTML()+'</li>');
        cloneNode.setData('itemid', itemId);
        itemList = cellNode.one(SELECTOR.CURRENTITEMSLIST);
        itemList.appendChild(cloneNode);
    },

    /**
     * Remove the selected itemid + content from the specified cell.
     * @param cellNode Y.Node
     * @param itemId Integer
     */
    cellRemoveItem: function(cellNode, itemId) {
        "use strict";
        cellNode.one(SELECTOR.CURRENTITEMSLIST).all('li').each(function(listItem) {
            var listItemId;
            listItemId = parseInt(listItem.getData('itemid'), 10);
            if (listItemId === itemId) {
                listItem.remove(true);
            }
        });
        this.cellRemoveSelected(cellNode, itemId);
    },

    /**
     * In the pop-up select dialogue, highlight any items already linked to the currently selected cell.
     */
    refreshAlreadySelected: function() {
        "use strict";
        var items, i, itemId, selectItem;

        if (!this.selectedCell) {
            return;
        }
        this.dialogueInner.all('li').removeClass(CSS.SELECTED);
        this.dialogueInner.all('button').removeAttribute('disabled');

        items = this.cellGetSelected(this.selectedCell);
        for (i in items) {
            if (!items.hasOwnProperty(i)) {
                continue;
            }
            itemId = items[i];
            selectItem = Y.one('#selectitem-'+itemId);
            if (selectItem) {
                selectItem.addClass(CSS.SELECTED);
                selectItem.one('button').set('disabled', 'disabled');
            }
        }
    },

    updateDialogueMaxHeight: function() {
        "use strict";
        var winHeight, innerHeight;

        winHeight = Y.one('body').get('winHeight');
        innerHeight = winHeight - 140;
        this.dialogueInner.one(SELECTOR.ITEMSELECTLIST).setStyle('maxHeight', innerHeight + 'px');
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
