YUI.add("moodle-mod_euroaspire-mapevidence",function(e,t){var n={HOVER:"hover",SELECTED:"selected"},r={FORM:"#mapitemform",CELL:"td",ADDITEM:".addmap",REMOVEITEM:".removemap",ADDITEMBUTTON:".selectitem",CURRENTITEMSINPUT:"input.currentitems",CURRENTITEMSLIST:"ul.mappeditems",ITEMCONTENT:".itemcontent",ITEMSELECTLIST:".itemselect",SUBMITCOMMENT:"textarea"};M.mod_euroaspire=M.mod_euroaspire||{},M.mod_euroaspire.mapevidence={dialogue:null,dialogueInner:null,selectedCell:null,hasChanges:!1,init:function(t){"use strict";var n,i;n=e.one(r.FORM),n.addClass("jsinit"),n.delegate("mouseenter",this.enterCell,r.CELL,this),n.delegate("mouseleave",this.leaveCell,r.CELL,this),this.dialogueInner=e.Node.create("<div>"+t.itemselect+"</div>"),this.dialogue=new M.core.dialogue({bodyContent:this.dialogueInner,width:"550px",modal:!0,visible:!1,render:!0,draggable:!0}),n.delegate("click",this.showSelect,r.ADDITEM,this),this.dialogue.after("visibleChange",this.selectHidden,this),this.dialogueInner.delegate("click",this.selectItem,r.ADDITEMBUTTON,this),n.delegate("click",this.removeItem,r.REMOVEITEM,this),i=this,window.addEventListener("beforeunload",function(e){return i.unsavedWarning(e)}),n.on("submit",function(){this.hasChanges=!1},this),n.delegate("valuechange",function(){this.hasChanges=!0},r.SUBMITCOMMENT,this)},enterCell:function(e){"use strict";e.currentTarget.addClass(n.HOVER)},leaveCell:function(e){"use strict";e.currentTarget.removeClass(n.HOVER)},showSelect:function(e){"use strict";var t;t=e.currentTarget,this.selectedCell=t.ancestor(r.CELL),this.selectedCell.addClass(n.SELECTED),this.dialogue.set("headerContent",t.get("title")),this.updateDialogueMaxHeight(),this.refreshAlreadySelected(),this.dialogue.show()},selectHidden:function(){"use strict";if(this.dialogue.get("visible"))return;e.one(r.FORM).all(r.CELL).removeClass(n.SELECTED),this.selectedCell=null},selectItem:function(e){"use strict";var t,n;if(!this.selectedCell)return;t=parseInt(e.currentTarget.getData("itemid"),10),n=e.currentTarget.next(r.ITEMCONTENT);if(this.cellHasItem(this.selectedCell,t))return;this.cellAddItem(this.selectedCell,t,n),this.refreshAlreadySelected(),this.hasChanges=!0},removeItem:function(e){"use strict";var t,n;t=e.currentTarget.ancestor(r.CELL),n=parseInt(e.currentTarget.getData("itemid"),10),this.cellRemoveItem(t,n),this.hasChanges=!0},cellGetSelected:function(e){"use strict";var t;return t=e.one(r.CURRENTITEMSINPUT).get("value"),t?(t=t.split(","),t.map(function(e){return parseInt(e,10)})):[]},cellSetSelected:function(e,t){"use strict";e.one(r.CURRENTITEMSINPUT).set("value",t.join(","))},cellAddSelected:function(e,t){"use strict";var n;n=this.cellGetSelected(e),n.push(t),this.cellSetSelected(e,n)},cellRemoveSelected:function(e,t){"use strict";var n,r;n=this.cellGetSelected(e),r=n.indexOf(t),r!==-1&&(n.splice(r,1),this.cellSetSelected(e,n))},cellHasItem:function(e,t){"use strict";var n;return n=this.cellGetSelected(e),n.indexOf(t)!==-1},cellAddItem:function(t,n,i){"use strict";var s,o;this.cellAddSelected(t,n),s=e.Node.create("<li>"+i.getHTML()+"</li>"),s.setData("itemid",n),o=t.one(r.CURRENTITEMSLIST),o.appendChild(s)},cellRemoveItem:function(e,t){"use strict";e.one(r.CURRENTITEMSLIST).all("li").each(function(e){var n;n=parseInt(e.getData("itemid"),10),n===t&&e.remove(!0)}),this.cellRemoveSelected(e,t)},refreshAlreadySelected:function(){"use strict";var t,r,i,s;if(!this.selectedCell)return;this.dialogueInner.all("li").removeClass(n.SELECTED),this.dialogueInner.all("button").removeAttribute("disabled"),t=this.cellGetSelected(this.selectedCell);for(r in t){if(!t.hasOwnProperty(r))continue;i=t[r],s=e.one("#selectitem-"+i),s&&(s.addClass(n.SELECTED),s.one("button").set("disabled","disabled"))}},updateDialogueMaxHeight:function(){"use strict";var t,n;t=e.one("body").get("winHeight"),n=t-140,this.dialogueInner.one(r.ITEMSELECTLIST).setStyle("maxHeight",n+"px")},unsavedWarning:function(e){"use strict";var t;if(!this.hasChanges)return;return t=M.util.get_string("changesmadereallygoaway","core"),(e||window.event).returnValue=t,t}}},"@VERSION@",{requires:["node","event-mouseenter","event-valuechange","moodle-core-notification-dialogue"]});