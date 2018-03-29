/* jce - 2.6.28 | 2018-03-21 | https://www.joomlacontenteditor.net | Copyright (C) 2006 - 2018 Ryan Demmer. All rights reserved | GNU/GPL Version 2 or later - http://www.gnu.org/licenses/gpl-2.0.html */
!function(){var each=tinymce.each;tinymce.create("tinymce.plugins.AdvListPlugin",{init:function(ed,url){function buildFormats(str){var formats=[];return each(str.split(/,/),function(type){var title=type.replace(/-/g,"_");"default"===type&&(title="def"),formats.push({title:"advlist."+title,styles:{listStyleType:"default"===type?"":type}})}),formats}var t=this;t.editor=ed;var numlist=ed.getParam("advlist_number_styles","default,lower-alpha,lower-greek,lower-roman,upper-alpha,upper-roman");numlist&&(t.numlist=buildFormats(numlist));var bullist=ed.getParam("advlist_bullet_styles","default,circle,disc,square");bullist&&(t.bullist=buildFormats(bullist)),tinymce.isIE&&/MSIE [2-7]/.test(navigator.userAgent)&&(t.isIE7=!0)},createControl:function(name,cm){function hasFormat(node,format){var state=!0;return each(format.styles,function(value,name){if(editor.dom.getStyle(node,name)!=value)return state=!1,!1}),state}function applyListFormat(){var list,dom=editor.dom,sel=editor.selection;list=dom.getParent(sel.getNode(),"ol,ul"),list&&list.nodeName!=("bullist"==name?"OL":"UL")&&format&&!hasFormat(list,format)||editor.execCommand("bullist"==name?"InsertUnorderedList":"InsertOrderedList"),format&&(list=dom.getParent(sel.getNode(),"ol,ul"),list&&(dom.setStyles(list,format.styles),list.removeAttribute("data-mce-style"))),editor.focus()}var btn,format,t=this,editor=t.editor;if("numlist"==name||"bullist"==name)return t[name]&&"advlist.def"===t[name][0].title&&(format=t[name][0]),t[name]?(btn=cm.createSplitButton(name,{title:"advanced."+name+"_desc",class:"mce_"+name,onclick:function(){applyListFormat()}}),btn.onRenderMenu.add(function(btn,menu){menu.onHideMenu.add(function(){t.bookmark&&(editor.selection.moveToBookmark(t.bookmark),t.bookmark=0)}),menu.onShowMenu.add(function(){var fmtList,dom=editor.dom,list=dom.getParent(editor.selection.getNode(),"ol,ul");(list||format)&&(fmtList=t[name],each(menu.items,function(item){var state=!0;item.setSelected(0),list&&!item.isDisabled()&&(each(fmtList,function(fmt){if(fmt.id==item.id&&!hasFormat(list,fmt))return state=!1,!1}),state&&item.setSelected(1))}),list||menu.items[format.id].setSelected(1)),editor.focus(),tinymce.isIE&&(t.bookmark=editor.selection.getBookmark(1))}),menu.add({id:editor.dom.uniqueId(),title:"advlist.types",class:"mceMenuItemTitle",titleItem:!0}).setDisabled(1),each(t[name],function(item){t.isIE7&&"lower-greek"==item.styles.listStyleType||(item.id=editor.dom.uniqueId(),menu.add({id:item.id,title:item.title,onclick:function(){format=item,applyListFormat()}}))})}),btn):btn=cm.createButton(name,{title:"advanced."+name+"_desc",class:"mce_"+name,onclick:function(){applyListFormat()}})},getInfo:function(){return{longname:"Advanced lists",author:"Moxiecode Systems AB",authorurl:"http://tinymce.moxiecode.com",infourl:"http://wiki.moxiecode.com/index.php/TinyMCE:Plugins/advlist",version:tinymce.majorVersion+"."+tinymce.minorVersion}}}),tinymce.PluginManager.add("advlist",tinymce.plugins.AdvListPlugin)}();