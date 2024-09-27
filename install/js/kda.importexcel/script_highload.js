var kdaIEModuleName = 'kda.importexcel';
var kdaIEModuleFilePrefix = 'kda_import_excel';
var kdaIEModuleAddPath = '';
var EList = {
	cfields: {},
	
	Init: function()
	{
		this.InitLines();
		
		$('.kda-ie-tbl input[type=checkbox][name^="SETTINGS[CHECK_ALL]"]').bind('change', function(){
			var inputs = $(this).closest('tbody').find('input[type=checkbox]').not(this);
			if(this.checked)
			{
				inputs.prop('checked', true);
			}
			else
			{
				inputs.prop('checked', false);
			}
		});
		
		/*Bug fix with excess jquery*/
		var anySelect = $('select:eq(0)');
		if(typeof anySelect.chosen!='function')
		{
			var jQuerySrc = $('script[src*="/bitrix/js/main/jquery/"]').attr('src');
			if(jQuerySrc)
			{
				$.getScript(jQuerySrc, function(){
					$.getScript('/bitrix/js/'+kdaIEModuleName+'/chosen/chosen.jquery.min.js');
				});
			}
		}
		/*/Bug fix with excess jquery*/
		this.SetFieldValues();
		
		/*$('.kda-ie-tbl select[name^="SETTINGS[FIELDS_LIST]"]').bind('change', function(){
			EList.OnChangeFieldHandler(this);
		}).trigger('change');*/
		
		$('.kda-ie-tbl input[type=checkbox][name^="SETTINGS[LIST_ACTIVE]"]').bind('change', function(){
			var tr = $(this).closest('.kda-ie-tbl').find('tr.settings');
			if(this.checked)
			{
				tr.show();
			}
			else
			{
				tr.hide();
			}
			$(window).trigger('resize');
		});
		
		$('.kda-ie-tbl:not(.empty) tr.heading input[name^="SETTINGS[LIST_ACTIVE]"]:checked:eq(0)').closest('tr.heading').find('.showlist').trigger('click');
		//$('.kda-ie-tbl:not(.empty) tr.heading .showlist:eq(0)').trigger('click');
		
		
		$('.kda-ie-tbl:not(.empty) div.set').bind('scroll', function(){
			$('#kda_select_chosen').remove();
			$(this).prev('.set_scroll').scrollLeft($(this).scrollLeft());
		});
		$('.kda-ie-tbl:not(.empty) div.set_scroll').bind('scroll', function(){
			$('#kda_select_chosen').remove();
			$(this).next('.set').scrollLeft($(this).scrollLeft());
		});
		$(window).bind('resize', function(){
			EList.SetWidthList();
		});
		BX.addCustomEvent("onAdminMenuResize", function(json){
			$(window).trigger('resize');
		});
		$(window).trigger('resize');
	},
	
	InitLines: function(list)
	{
		var obj = this;
		$('.kda-ie-tbl .list input[name^="SETTINGS[IMPORT_LINE]"]').click(function(e){
			if(typeof obj.lastChb != 'object') obj.lastChb = {};
			var arKeys = this.name.substr(0, this.name.length - 1).split('][');
			var chbKey = arKeys.pop();
			var sheetKey = arKeys.pop();
			if(e.shiftKey && obj.lastChb[sheetKey] && obj.lastChb[sheetKey].checked==this.checked)
			{
				var arKeys2 = obj.lastChb[sheetKey].name.substr(0, obj.lastChb[sheetKey].name.length - 1).split('][');
				var chbKey2 = arKeys2.pop();
				var kFrom = Math.min(chbKey, chbKey2);
				var kTo = Math.max(chbKey, chbKey2);
				for(var i=kFrom+1; i<kTo; i++)
				{
					$('.kda-ie-tbl .list input[name="SETTINGS[IMPORT_LINE]['+sheetKey+']['+i+']"]').prop('checked', this.checked);
				}
			}
			obj.lastChb[sheetKey] = this;
		});
	},
	
	SetFieldValues: function(gParent)
	{
		if(!gParent) gParent = $('.kda-ie-tbl');
		$('select[name^="FIELDS_LIST["]', gParent).each(function(){
			var pSelect = this;
			var parent = $(pSelect).closest('tr');
			var arVals = [];
			var arValParents = [];
			for(var i=0; i<pSelect.options.length; i++)
			{
				arVals[pSelect.options.item(i).value] = pSelect.options.item(i).text;
				arValParents[pSelect.options.item(i).value] = pSelect.options.item(i).parentNode.getAttribute('label');
			}
			$('.kda-ie-field-select', parent).each(function(index){
				$('.field_insert', this).remove();
				var input = $('input[name^="SETTINGS[FIELDS_LIST]"]:eq(0)', this);
				//var inputShow = $('input[name^="FIELDS_LIST_SHOW"]:eq(0)', this);
				var inputShow = $('span.fieldval:eq(0)', this);
				//var firstVal = $(this).closest('table').find('tr:gt(0) > td:nth-child('+(index+2)+') .cell_inner:not(:empty):eq(0)').text().toLowerCase();
				var inputVals = $(this).closest('table').find('tr:gt(0) > td:nth-child('+(index+2)+') .cell_inner:not(:empty):lt(10)');
				var ind = false;
				var length = -1;
				for(var j=0; j<inputVals.length; j++)
				{
					var firstVal = $.trim(inputVals[j].innerHTML.toLowerCase()).replace(/(&nbsp;|\s)+/g, ' ');
					for(var i in arVals)
					{
						if(i.indexOf('ISECT')==0 && !i.match(/ISECT\d+_NAME/)) continue;
						var lowerVal = $.trim(arVals[i].toLowerCase()).replace(/(&nbsp;|\s)+/g, ' ');
						var lowerValCorrected = lowerVal.replace(/^[^"]*"/, '').replace(/"[^"]*$/, '').replace(/\s+\[[\d\w_]*\]$/, '');
						if(
							(firstVal.indexOf(lowerVal)!=-1) 
							|| (firstVal.indexOf(lowerValCorrected)!=-1) 
							|| (i.indexOf('ISECT')==0 && firstVal.indexOf(arValParents[i].replace(/^.*(\d+\D{5}).*$/, '$1'))!=-1))
						{
							if(length < 0)
							{
								length = firstVal.replace(lowerVal, '').replace(lowerValCorrected, '').length;
								ind = i;
							}
							else if(firstVal.replace(lowerVal, '').replace(lowerValCorrected, '').length < length)
							{
								length = firstVal.replace(lowerVal, '').replace(lowerValCorrected, '').length;
								ind = i;
							}
						}
					}
				}
				
				if(ind)
				{
					$('a.field_settings:eq(0)', this).after('<a href="javascript:void(0)" class="field_insert" title=""></a>');
					$('a.field_insert', this).attr('title', arVals[ind]+' ('+BX.message("KDA_IE_INSERT_FIND_FIELD")+')')
						.attr('data-value', ind)
						.attr('data-show-value', arVals[ind])
						.bind('click', function(){
							/*input.val(this.getAttribute('data-value'));
							inputShow.val(this.getAttribute('data-show-value'));*/
							EList.SetHiddenFieldVal(input, this.getAttribute('data-value'));
							EList.SetShowFieldVal(inputShow, this.getAttribute('data-show-value'), this.getAttribute('data-show-group'));
					});
				}
			});
			$('input[name^="SETTINGS[FIELDS_LIST]"]', parent).each(function(index){
				var input = this;
				/*var inputShow = $('input[name="'+input.name.replace('SETTINGS[FIELDS_LIST]', 'FIELDS_LIST_SHOW')+'"]', parent)[0];				
				inputShow.setAttribute('placeholder', arVals['']);*/
				var inputShow = EList.GetShowInputFromValInput(input);
				inputShow.setAttribute('placeholder', arVals['']);
				
				if(!input.value || !arVals[input.value])
				{
					//input.value = '';
					//inputShow.value = '';
					EList.SetHiddenFieldVal(input, '');
					EList.SetShowFieldVal(inputShow, '');
					return;
				}
				/*inputShow.value = arVals[input.value];
				inputShow.title = arVals[input.value];*/
				EList.SetHiddenFieldVal(input, input.value);
				EList.SetShowFieldVal(inputShow, arVals[input.value], arValParents[input.value]);
			});
			
			EList.OnFieldFocus($('span.fieldval', parent));
		});
	},
	
	GetShowInputColIndex: function(input)
	{
		input = $(input);
		var arIdx = input.attr('name').replace(/SETTINGS\[FIELDS_LIST\]\[[\d_]*\]\[([\d_]*)\]/, '$1').split('_');
		if(arIdx.length > 1) return arIdx[1];
		else return 0;
	},
	
	GetShowInputNameFromValInputName: function(name)
	{
		return name.replace(/SETTINGS\[FIELDS_LIST\]\[([\d_]*)\]\[([\d_]*)\]/, 'field-list-show-$1-$2');
	},
	
	GetShowInputFromValInput: function(input)
	{
		return $('#'+this.GetShowInputNameFromValInputName(input.name))[0];
	},
	
	GetValInputFromShowInput: function(input)
	{
		return  $(input).closest('td').find('input[name="'+input.id.replace(/field-list-show-([\d_]*)-([\d_]*)$/, 'SETTINGS[FIELDS_LIST][$1][$2]')+'"]')[0];
	},
	
	InArray: function(val, arr)
	{
		for(var i=0; i<arr.length; i++)
		{
			if(arr[i]==val) return true;
		}
		return false;
	},
	
	SetHiddenFieldVal: function(input, val)
	{
		input = $(input);
		var arKeys = input.attr('name').replace(/SETTINGS\[FIELDS_LIST\]\[([\d_]*)\]\[([\d_]*)\]/, '$1|$2').split('|');
		if(!this.cfields[arKeys[0]]) this.cfields[arKeys[0]] = {};
		
		/*var oldVal = input.val();
		if(oldVal!=val && oldVal.length > 0 && this.cfields[arKeys[0]][oldVal] && this.cfields[arKeys[0]][oldVal].length > 1)
		{
			var fkeys = this.cfields[arKeys[0]][oldVal];
			this.cfields[arKeys[0]][oldVal] = [];
			for(var i=0; i<fkeys.length; i++)
			{
				if(arKeys[1]==fkeys[i])
				{
					$('#field-list-show-'+arKeys[0]+'-'+fkeys[i]).removeClass('fieldval_duplicated');
					continue;
				}
				this.cfields[arKeys[0]][oldVal].push(fkeys[i]);
			}
			var fkeys = this.cfields[arKeys[0]][oldVal];
			if(fkeys.length == 1)
			{
				for(var i=0; i<fkeys.length; i++)
				{
					$('#field-list-show-'+arKeys[0]+'-'+fkeys[i]).removeClass('fieldval_duplicated');
				}
			}
		}
		
		if(val.length > 0)
		{
			if(!this.cfields[arKeys[0]][val]) this.cfields[arKeys[0]][val] = [];
			if(!this.InArray(arKeys[1], this.cfields[arKeys[0]][val])) this.cfields[arKeys[0]][val].push(arKeys[1]);
			var fkeys = this.cfields[arKeys[0]][val];
			if(fkeys.length > 1 && $.inArray(val, $('#multiple_fields_'+arKeys[0]).val().split(';'))==-1)
			{
				for(var i=0; i<fkeys.length; i++)
				{
					$('#field-list-show-'+arKeys[0]+'-'+fkeys[i]).addClass('fieldval_duplicated');
				}
			}
		}*/
			
		input.val(val);
	},
	
	SetShowFieldVal: function(input, val, group)
	{
		input = $(input);
		var jsInput = input[0];
		var placeholder = jsInput.getAttribute('placeholder');
		var parentDiv = input.closest('div');
		if(val.length > 0 && val!=placeholder)
		{
			jsInput.innerHTML = val;
			input.removeClass('fieldval_empty');
			parentDiv.addClass('selected');
		}
		else
		{
			jsInput.innerHTML = placeholder;
			input.addClass('fieldval_empty');
			parentDiv.removeClass('selected');
		}
		jsInput.title = /*(group ? group+' - ' : '')+*/val;
	},
	
	OnFieldFocus: function(objInput)
	{
		$(objInput).unbind('click').bind('click', function(){
			var input = this;
			/*var parentTd = $(input).closest('td');
			parentTd.css('width', parentTd[0].offsetWidth - 6);*/
			var parent = $(input).closest('tr');
			var pSelect = parent.find('select[name^="FIELDS_LIST["]');
			//var inputVal = $('input[name="'+input.name.replace('FIELDS_LIST_SHOW', 'SETTINGS[FIELDS_LIST]')+'"]', parent)[0];
			var inputVal = EList.GetValInputFromShowInput(input);
			//$(input).css('visibility', 'hidden');
			var select = $(pSelect).clone();
			var options = select[0].options;
			for(var i=0; i<options.length; i++)
			{
				if(inputVal.value==options.item(i).value) options.item(i).selected = true;
			}
			
			var chosenId = 'kda_select_chosen';
			$('#'+chosenId).remove();
			var offset = $(input).offset();
			var div = $('<div></div>');
			div.attr('id', chosenId);
			div.css({
				position: 'absolute',
				left: offset.left,
				top: offset.top,
				width: $(input).width() + 27
			});
			div.append(select);
			$('body').append(div);
			
			//select.insertBefore($(input));
			select.chosen({search_contains: true});
			select.bind('change', function(){
				if(this.value=='new_prop')
				{
					var ind = this.name.replace(/^.*\[(\d+)\]$/, '$1');
					var ptable = $('.kda-ie-tbl:eq('+ind+')');
					var option = options.item(0);
					var dialog = new BX.CAdminDialog({
						'title':BX.message("KDA_IE_POPUP_NEW_FIELD"),
						'content_url':'/bitrix/admin/'+kdaIEModuleFilePrefix+'_new_hlbl_field.php?lang='+BX.message('LANGUAGE_ID')+'&PARENT_FIELD_NAME='+encodeURIComponent(input.id)+'&HLBL_ID='+ptable.attr('data-iblock-id'),
						'width':'900',
						'height':'400',
						'resizable':true});
					EList.newPropDialog = dialog;
					EList.NewPropDialogButtonsSet();
						
					BX.addCustomEvent(dialog, 'onWindowRegister', function(){
						$('input[type=checkbox]', this.DIV).each(function(){
							BX.adminFormTools.modifyCheckbox(this);
						});
					});
						
					dialog.Show();
				}
				else
				{
					var option = options.item(select[0].selectedIndex);
				}
				if(option.value)
				{
					/*input.value = option.text;
					input.title = option.text;
					inputVal.value = option.value;*/
					EList.SetHiddenFieldVal(inputVal, option.value);
					EList.SetShowFieldVal(input, option.text, option.parentNode.getAttribute('label'));
				}
				else
				{
					/*input.value = '';
					input.title = '';
					inputVal.value = '';*/
					EList.SetHiddenFieldVal(inputVal, '');
					EList.SetShowFieldVal(input, '');
				}
				select.chosen('destroy');
				//select.remove();
				$('#'+chosenId).remove();
				//$(input).css('visibility', 'visible');
				//parentTd.css('width', 'auto');
			});
			
			$('body').one('click', function(e){
				e.stopPropagation();
				return false;
			});
			var chosenDiv = select.next('.chosen-container')[0];
			$('a:eq(0)', chosenDiv).trigger('mousedown');
			
			var lastClassName = chosenDiv.className;
			var interval = setInterval( function() {   
				   var className = chosenDiv.className;
					if (className !== lastClassName) {
						select.trigger('change');
						lastClassName = className;
						clearInterval(interval);
					}
				},30);
		});
	},
	
	NewPropDialogChangeType: function(select)
	{
		var form = $(select).closest('form');
		$('input[name="action"]', form).val('change_type');
		var dialog = this.newPropDialog;
		dialog.PostParameters();
	},
	
	NewPropDialogButtonsSet: function(fireEvents)
	{
		var dialog = this.newPropDialog;
		dialog.SetButtons([
			dialog.btnCancel,
			new BX.CWindowButton(
			{
				title: BX.message('JS_CORE_WINDOW_SAVE'),
				id: 'savebtn',
				name: 'savebtn',
				className: BX.browser.IsIE() && BX.browser.IsDoctype() && !BX.browser.IsIE10() ? '' : 'adm-btn-save',
				action: function () {					
					this.disableUntilError();
					this.parentWindow.PostParameters();
					//this.parentWindow.Close();
				}
			})
		]);
		
		if(fireEvents)
		{
			BX.onCustomEvent(dialog, 'onWindowRegister');
		}
	},
	
	SetWidthList: function()
	{
		$('.kda-ie-tbl:not(.empty) div.set').each(function(){
			var div = $(this);
			div.css('width', 0);
			div.prev('.set_scroll').css('width', 0);
			var timer = setInterval(function(){
				var width = div.parent().width();
				if(width > 0)
				{
					div.css('width', width);
					div.prev('.set_scroll').css('width', width).find('>div').css('width', div.find('>table.list').width());
					clearInterval(timer);
					//$('select[name^="SETTINGS[FIELDS_LIST]"]', div).chosen();
				}
			}, 100);
			setTimeout(function(){clearInterval(timer);}, 3000);
		});
	},
	
	ToggleSettings: function(btn)
	{
		var tr = $(btn).closest('.kda-ie-tbl').find('tr.settings');
		if(tr.is(':visible'))
		{
			tr.hide();
			$(btn).removeClass('open');
		}
		else
		{
			tr.show();
			$(btn).addClass('open');
		}
		$(window).trigger('resize');		
	},

	ShowFull: function(btn)
	{
		var tbl = $(btn).closest('.kda-ie-tbl');
		var list = tbl.attr('data-list-index');
		var colCount = Math.max(1, $('table.list tr:eq(0) > td', tbl).length - 1);
		var post = $(btn).closest('form').serialize() + '&ACTION=SHOW_FULL_LIST&LIST_NUMBER=' + list + '&COUNT_COLUMNS=' + colCount;
		var wait = BX.showWait();
		$.post(window.location.href, post, function(data){
			data = $(data);
			var chb = $('input[type=checkbox][name^="SETTINGS[CHECK_ALL]"]', tbl);
			/*if(chb.length > 0)
			{
				if(chb[0].checked)
				{
					data.find('input[type=checkbox]').prop('checked', true);
				}
				else
				{
					data.find('input[type=checkbox]').prop('checked', false);
				}
			}*/
			$('table.list', tbl).append(data);
			/*$('table.list input[type=checkbox]', tbl).each(function(){
				BX.adminFormTools.modifyCheckbox(this);
			});*/
			EList.InitLines(list);
			$(window).trigger('resize');
			BX.closeWait(null, wait);
		});
		$(btn).hide();
	},
	
	ApplyToAllLists: function(link)
	{
		var tbl = $(link).closest('.kda-ie-tbl');
		var tbls = tbl.parent().find('.kda-ie-tbl').not(tbl);
		var form = tbl.closest('form')[0];
		
		/*var post = {
			'MODE': 'AJAX',
			'ACTION': 'APPLY_TO_LISTS',
			'PROFILE_ID': form.PROFILE_ID.value,
			'LIST_FROM': tbl.attr('data-list-index')
		}
		post.LIST_TO = [];
		for(var i=0; i<tbls.length; i++)
		{
			post.LIST_TO.push($(tbls[i]).attr('data-list-index'));
		}
		$.post(window.location.href, post, function(data){});*/
		
		var ts = tbl.find('.kda-ie-field-select');
		var cha = tbl.find('input[type="checkbox"][name^="SETTINGS[CHECK_ALL]"]');
		var chl = tbl.find('input[type="checkbox"][name^="SETTINGS[IMPORT_LINE]"]');
		for(var i=0; i<tbls.length; i++)
		{
			var tss = $('.kda-ie-field-select', tbls[i]);
			for(var j=0; j<ts.length; j++)
			{
				if(!tss[j]) continue;
				/*var c1 = $('input.fieldval', ts[j]).length;
				var c2 = $('input.fieldval', tss[j]).length;*/
				var c1 = $('span.fieldval', ts[j]).length;
				var c2 = $('span.fieldval', tss[j]).length;
				if(c2 < c1)
				{
					for(var k=0; k<c1-c2; k++)
					{
						$('.kda-ie-add-load-field', tss[j]).trigger('click');
					}
				}
				else if(c2 > c1)
				{
					for(var k=0; k<c2-c1; k++)
					{
						$('.field_delete:last', tss[j]).trigger('click');
					}
				}
				
				var fts = $('input[name^="SETTINGS[FIELDS_LIST]"]', ts[j]);
				//var fts2 = $('input[name^="FIELDS_LIST_SHOW"]', ts[j]);
				var fts2 = $('span.fieldval', ts[j]);
				var fts2s = $('a.field_settings', ts[j]);
				var fts2si = $('input[name^="EXTRASETTINGS"]', fts2s);
				var ftss = $('input[name^="SETTINGS[FIELDS_LIST]"]', tss[j]);
				//var ftss2 = $('input[name^="FIELDS_LIST_SHOW"]', tss[j]);
				var ftss2 = $('span.fieldval', tss[j]);
				var ftss2s = $('a.field_settings', tss[j]);
				var ftss2si = $('input[name^="EXTRASETTINGS"]', ftss2s);
				for(var k=0; k<ftss.length; k++)
				{
					if(fts[k])
					{
						//ftss[k].value = fts[k].value;
						//ftss2[k].value = fts2[k].value;
						EList.SetHiddenFieldVal(ftss[k], fts[k].value);
						EList.SetShowFieldVal(ftss2[k], fts2[k].innerHTML);
						ftss2si[k].value = fts2si[k].value;
						if($(fts2s[k]).hasClass('inactive')) $(ftss2s[k]).addClass('inactive');
						else $(ftss2s[k]).removeClass('inactive');
					}
				}
			}
			
			var chas = $('input[type="checkbox"][name^="SETTINGS[CHECK_ALL]"]', tbls[i]);
			if(cha[0] && chas[0])
			{
				chas[0].checked = cha[0].checked;
			}
			var chls = $('input[type="checkbox"][name^="SETTINGS[IMPORT_LINE]"]', tbls[i]);
			for(var j=0; j<chl.length; j++)
			{
				if(!chls[j]) continue;
				chls[j].checked = chl[j].checked;
			}
		}
	},
	
	OnAfterAddNewProperty: function(fieldName, propId, propName, hlblId)
	{
		//var field = $('input[name="'+fieldName+'"]');
		var field = $('#'+fieldName);
		var form = field.closest('form')[0];
		var post = {
			'MODE': 'AJAX',
			'ACTION': 'GET_SECTION_LIST',
			'HLBL_ID': hlblId,
			'PROFILE_ID': form.PROFILE_ID.value
		}
		var ptable = $(field).closest('.kda-ie-tbl');
		$.post(window.location.href, post, function(data){			
			ptable.find('select[name^="FIELDS_LIST["]').each(function(){
				var fields = $(data).find('select[name=fields]');
				fields.attr('name', this.name);
				$(this).replaceWith(fields);
			});
		});
		/*field.val(propName);
		$('input[name="'+fieldName.replace('FIELDS_LIST_SHOW', 'SETTINGS[FIELDS_LIST]')+'"]', ptable).val(propId);*/
		EList.SetHiddenFieldVal(EList.GetValInputFromShowInput(field[0]), propId);
		EList.SetShowFieldVal(field, propName);
		
		BX.WindowManager.Get().Close();
		
		var td = field.closest('td');
		td.addClass('kda-ie-field-select-highligth');
		setTimeout(function(){
			td.removeClass('kda-ie-field-select-highligth');
		}, 2000);
	},
	
	ChooseIblock: function(select)
	{
		var form = $(select).closest('form')[0];
		var post = {
			'MODE': 'AJAX',
			'ACTION': 'GET_SECTION_LIST',
			'HLBL_ID': select.value,
			'PROFILE_ID': form.PROFILE_ID.value
		}
		$.post(window.location.href, post, function(data){
			var sections = $(data).find('select[name=sections]');
			var sectSelect = $(select).closest('table').find('select[name="'+select.name.replace('[IBLOCK_ID]', '[SECTION_ID]')+'"]');
			sections.attr('name', sectSelect.attr('name'));
			sectSelect.replaceWith(sections);
			
			var ptable = $(select).closest('.kda-ie-tbl');
			ptable.find('select[name^="FIELDS_LIST["]').each(function(){
				var fields = $(data).find('select[name=fields]');
				fields.attr('name', this.name);
				$(this).replaceWith(fields);
				EList.SetFieldValues(ptable);
			});
			
			ptable.find('table.list tbody, table.list tfoot').show();
			ptable.attr('data-iblock-id', select.value);
		});
	},
	
	OnChangeFieldHandler: function(select)
	{
		var val = select.value;
		var link = $(select).next('a.field_settings');
		/*if(val.indexOf("ICAT_PRICE")===0 || val=="ICAT_PURCHASING_PRICE")
		{
			link.removeClass('inactive');
		}
		else
		{
			link.addClass('inactive');
		}*/
	},
	
	AddUploadField: function(link)
	{
		var parent = $(link).closest('.kda-ie-field-select-btns');
		var div = parent.prev('div').clone();
		var input = $('input[name^="SETTINGS[FIELDS_LIST]"]', div)[0];
		//var inputShow = $('input[name^="FIELDS_LIST_SHOW"]', div)[0];
		var inputShow = $('span.fieldval', div)[0];
		var a = $('a.field_settings', div)[0];
		var inputExtra = $('input', a)[0];
		$('.field_insert', div).remove();
		
		var sname = input.name;
		var index = sname.substr(0, sname.length-1).split('][').pop();
		var arIndex = index.split('_');
		if(arIndex.length==1) arIndex[1] = 1;
		else arIndex[1] = parseInt(arIndex[1]) + 1;
		
		input.name = input.name.replace(/\[[\d_]+\]$/, '['+arIndex.join('_')+']');
		//inputShow.name = input.name.replace('SETTINGS[FIELDS_LIST]', 'FIELDS_LIST_SHOW');
		inputShow.id = this.GetShowInputNameFromValInputName(input.name);
		if(arIndex[1] > 1) a.id = a.id.replace(/\_\d+_\d+$/, '_'+arIndex.join('_'));
		else a.id = a.id.replace(/\_\d+$/, '_'+arIndex.join('_'));
		$(a).addClass('inactive');
		inputExtra.name = inputExtra.name.replace(/\[[\d_]+\]$/, '['+arIndex.join('_')+']');
		inputExtra.value = '';
		
		div.insertBefore(parent);
		EList.OnFieldFocus(inputShow);
	},
	
	DeleteUploadField: function(link)
	{
		var parent = $(link).closest('div');
		EList.SetHiddenFieldVal($('input[name^="SETTINGS[FIELDS_LIST]"]', parent), '');
		if(this.GetShowInputColIndex($('input[name^="SETTINGS[FIELDS_LIST]"]', parent)) > 0)
		{
			parent.remove();
		}
		else
		{
			EList.SetShowFieldVal($('span.fieldval', parent), '');
			$('.field_settings', parent).addClass('inactive');
			$('.field_settings input[name^="EXTRASETTINGS"]', parent).val('');
		}

	},
	
	ShowFieldSettings: function(btn, name, val)
	{
		//if($(btn).hasClass('inactive')) return;
		if(!name || !val)
		{
			var input = $(btn).prevAll('input[name^="SETTINGS[FIELDS_LIST]"]');
			val = input.val();
			name = input[0].name;
		}
		//var input2 = $(btn).prevAll('input[name^="FIELDS_LIST_SHOW["]');
		var input2 = $(btn).closest('div').find('span.fieldval');
		var input2Val = input2.html();
		if(input2Val==input2.attr('placeholder')) input2Val = '';
		var ptable = $(btn).closest('.kda-ie-tbl');
		var form = $(btn).closest('form')[0];
		var countCols = $(btn).closest('tr').find('.kda-ie-field-select').length;
		
		var dialogParams = {
			//'title':BX.message("KDA_IE_POPUP_FIELD_SETTINGS_TITLE") + (input2.val() ? ' "'+input2.val()+'"' : ''),
			'title':BX.message("KDA_IE_POPUP_FIELD_SETTINGS_TITLE") + (input2Val ? ' "'+input2Val+'"' : ''),
			'content_url':'/bitrix/admin/'+kdaIEModuleFilePrefix+'_field_settings_highload.php?lang='+BX.message('LANGUAGE_ID')+'&field='+val+'&field_name='+name+'&HIGHLOADBLOCK_ID='+ptable.attr('data-iblock-id')+'&PROFILE_ID='+form.PROFILE_ID.value+'&count_cols='+countCols,
			'width': '930',
			'height': '420',
			'resizable':true
		};
		if($('input', btn).length > 0)
		{
			dialogParams['content_url'] += '&return_data=1';
			dialogParams['content_post'] = {'POSTEXTRA': $('input', btn).val()};
		}
		var dialog = new BX.CAdminDialog(dialogParams);
			
		dialog.SetButtons([
			dialog.btnCancel,
			new BX.CWindowButton(
			{
				title: BX.message('JS_CORE_WINDOW_SAVE'),
				id: 'savebtn',
				name: 'savebtn',
				className: BX.browser.IsIE() && BX.browser.IsDoctype() && !BX.browser.IsIE10() ? '' : 'adm-btn-save',
				action: function () {
					this.disableUntilError();
					this.parentWindow.PostParameters();
					//this.parentWindow.Close();
				}
			})/*,
			dialog.btnSave*/
		]);
			
		BX.addCustomEvent(dialog, 'onWindowRegister', function(){
			$('input[type=checkbox]', this.DIV).each(function(){
				BX.adminFormTools.modifyCheckbox(this);
			});
			ESettings.BindConversionEvents();
			$('select.kda-ie-select2text').each(function(){
				var s = $(this);
				s.wrap('<div class="kda-ie-select2text-wrap"></div>');
				new Select2Text(s.closest('div.kda-ie-select2text-wrap'), s);
			});
		});
			
		dialog.Show();
	},
	
	ShowListSettings: function(btn)
	{
		var tbl = $(btn).closest('.kda-ie-tbl');
		var post = 'list_index='+tbl.attr('data-list-index');
		var inputs = tbl.find('input[name^="SETTINGS[FIELDS_LIST]"], input[name^="SETTINGS[ADDITIONAL_SETTINGS]"]');
		for(var i in inputs)
		{
			post += '&'+inputs[i].name+'='+inputs[i].value;
		}
		
		var abtns = tbl.find('a.field_insert');
		var findFields = [];
		for(var i=0; i<abtns.length; i++)
		{
			findFields.push('FIND_FIELDS[]='+$(abtns[i]).attr('data-value'));
		}
		if(findFields.length > 0)
		{
			post += '&'+findFields.join('&');
		}
		
		var dialog = new BX.CAdminDialog({
			'content_url':'/bitrix/admin/'+kdaIEModuleFilePrefix+'_list_settings_highload.php?lang='+BX.message('LANGUAGE_ID'),
			'content_post': post,
			'width':'900',
			'height':'400',
			'resizable':true});
			
		dialog.SetButtons([
			dialog.btnCancel,
			new BX.CWindowButton(
			{
				title: BX.message('JS_CORE_WINDOW_SAVE'),
				id: 'savebtn',
				name: 'savebtn',
				className: BX.browser.IsIE() && BX.browser.IsDoctype() && !BX.browser.IsIE10() ? '' : 'adm-btn-save',
				action: function () {
					this.disableUntilError();
					this.parentWindow.PostParameters();
					//this.parentWindow.Close();
				}
			})/*,
			dialog.btnSave*/
		]);
			
		BX.addCustomEvent(dialog, 'onWindowRegister', function(){
			$('input[type=checkbox]', this.DIV).each(function(){
				BX.adminFormTools.modifyCheckbox(this);
			});
			$('select.kda-chosen-multi').chosen();
		});
			
		dialog.Show();
	},
	
	SetExtraParams: function(oid, returnJson)
	{
		if(typeof returnJson == 'object') returnJson = JSON.stringify(returnJson);
		if(returnJson.length > 0) $('#'+oid).removeClass("inactive");
		else $('#'+oid).addClass("inactive");
		$('#'+oid+' input').val(returnJson);
		if(BX.WindowManager.Get()) BX.WindowManager.Get().Close();
	},
	
	ToggleAddSettings: function(input)
	{
		var display = (input.checked ? '' : 'none');
		var tr = $(input).closest('tr');
		var next;
		while((next = tr.next('tr.subfield')) && next.length > 0)
		{
			tr = next;
			if(display=='' && $('select', tr).length > 0 && $('select option', tr).length==0) continue;
			tr.css('display', display);
		}
	},
	
	ToggleAddSettingsBlock: function(link)
	{
		if($(link).hasClass('open'))
		{
			$(link).removeClass('open');
			$(link).next('div').slideUp();
		}
		else
		{
			$(link).addClass('open');
			$(link).next('div').slideDown();
		}
	}
}

var EProfile = {
	Init: function()
	{
		var select = $('select#PROFILE_ID');
		if(select.length > 0)
		{
			if(typeof select.chosen == 'function')
			{
				setTimeout(function(){$('select#PROFILE_ID').chosen({search_contains: true})}, 500);
			}
			if(select.val().length > 0)
			{
				$.post(window.location.href, {'MODE': 'AJAX', 'ACTION': 'DELETE_TMP_DIRS'}, function(data){});
			}
			
			select = select[0]
			/*this.Choose(select[0]);*/
			$('#new_profile_name').css('display', (select.value=='new' ? '' : 'none'));
		
			$('select.adm-detail-iblock-list').bind('change', function(){
				$.post(window.location.href, {'MODE': 'AJAX', 'HIGHLOADBLOCK_ID': this.value, 'ACTION': 'GET_UID'}, function(data){
					var fields = $(data).find('select[name="fields[]"]');
					var select = $('select[name="SETTINGS_DEFAULT[ELEMENT_UID][]"]');
					fields.val(select.val());
					fields.attr('name', select.attr('name'));
					//$('select.chosen').chosen('destroy');
					select.replaceWith(fields);
					//$('select.chosen').chosen();
				});
			});
			
			var select = $('select[name="SETTINGS_DEFAULT[ELEMENT_UID][]"]');
			if(select.length > 0 && !select.val())
			{
				if(select[0].options.length > 0)
				{
					select[0].options[0].selected = true;
				}
			}
			/*$('select.chosen').chosen();*/
			$('select.kda-chosen-multi').chosen({width: '300px'});
			this.ToggleAdditionalSettings();
			
			$('#dataload input[type="checkbox"][data-confirm]').bind('change', function(){
				if(this.checked && !confirm(this.getAttribute('data-confirm')))
				{
					this.checked = false;
				}
			});
		}
	},
	
	ChangeSelectViewMode: function(a)
	{
		var select = $(a).parent().find('select:eq(0)');
		if(select.length > 0 && typeof select.chosen == 'function')
		{
			var minput = select.prevAll('input[type="hidden"][name*="SHOW_MODE_"]');
			if($(a).attr('mode')!='chosen')
			{
				select.chosen({search_contains: true, placeholder_text: BX.message("KDA_IE_SELECT_NOT_CHOSEN")});
				$(a).attr('title', BX.message("KDA_IE_SELECT_STANDARD_VIEW"));
				$(a).attr('mode', 'chosen');
				minput.val('chosen');
			}
			else
			{
				select.chosen('destroy');
				$(a).attr('title', BX.message("KDA_IE_SELECT_FAST_VIEW"));
				$(a).attr('mode', '');
				minput.val('');
			}
		}
	},
	
	Choose: function(select)
	{
		/*if(select.value=='new')
		{
			$('#new_profile_name').css('display', '');
		}
		else
		{
			$('#new_profile_name').css('display', 'none');
		}*/
		$('form#dataload input[name="submit_btn"], form#dataload input[name="saveConfigButton"]').prop('disabled', true);
		var id = (typeof select == 'object' ? select.value : select);
		var query = window.location.search.replace(/PROFILE_ID=[^&]*&?/, '');
		if(query.length < 2) query = '?';
		if(query.length > 1 && query.substr(query.length-1)!='&') query += '&';
		query += 'PROFILE_ID=' + id;
		window.location.href = query;
	},
	
	Delete: function()
	{
		var obj = this;
		var select = $('select#PROFILE_ID');
		var option = select[0].options[select[0].selectedIndex];
		var id = option.value;
		$.post(window.location.href, {'MODE': 'AJAX', 'ID': id, 'ACTION': 'DELETE_PROFILE'}, function(data){
			obj.Choose('');
		});
	},
	
	Copy: function()
	{
		var obj = this;
		var select = $('select#PROFILE_ID');
		var option = select[0].options[select[0].selectedIndex];
		var id = option.value;
		$.post(window.location.href, {'MODE': 'AJAX', 'ID': id, 'ACTION': 'COPY_PROFILE'}, function(data){
			eval('var res = '+data+';');
			obj.Choose(res.id);
		});
	},
	
	ShowRename: function()
	{
		var select = $('select#PROFILE_ID');
		var option = select[0].options[select[0].selectedIndex];
		var name = option.innerHTML;
		var prefix = '['+option.value+'] ';
		if(name.indexOf(prefix)==0) name = name.substr(prefix.length);
		
		var tr = $('#new_profile_name');
		var input = $('input[type=text]', tr);
		input.val(name);
		if(!input.attr('init_btn'))
		{
			input.after('&nbsp;<input type="button" onclick="EProfile.Rename();" value="OK">');
			input.attr('init_btn', 1);
		}
		tr.css('display', '');
	},
	
	Rename: function()
	{
		var select = $('select#PROFILE_ID');
		var option = select[0].options[select[0].selectedIndex];
		var id = option.value;
		
		var tr = $('#new_profile_name');
		var input = $('input[type=text]', tr);
		var value = $.trim(input.val());
		if(value.length==0) return false;
		
		tr.css('display', 'none');
		option.innerHTML = '['+id+'] '+value;
		
		$.post(window.location.href, {'MODE': 'AJAX', 'ID': id, 'NAME': value, 'ACTION': 'RENAME_PROFILE'}, function(data){});
	},
	
	ToggleAvailStatOption: function(available)
	{
		var statChb = $('#dataload input[type="checkbox"][name="SETTINGS_DEFAULT[STAT_SAVE]"]');
		if(statChb.length==0) return;
		if(available)
		{
			$('#dataload input[type="hidden"][name="SETTINGS_DEFAULT[STAT_SAVE]"]').remove();
			statChb.prop('disabled', false);
			if(statChb.attr('data-oldval'))
			{
				statChb.prop('checked', statChb.attr('data-oldval')=='1');
			}
		}
		else
		{
			statChb.attr('data-oldval', (statChb.prop('checked') ? '1' : '0'));
			statChb.prop('checked', true);
			statChb.prop('disabled', true);
			statChb.before('<input type="hidden" name="SETTINGS_DEFAULT[STAT_SAVE]" value="Y">');
		}
	},
	
	ShowCron: function()
	{
		var dialog = new BX.CAdminDialog({
			'title':BX.message("KDA_IE_POPUP_CRON_TITLE"),
			'content_url':'/bitrix/admin/'+kdaIEModuleFilePrefix+'_cron_settings.php?lang='+BX.message('LANGUAGE_ID')+'&suffix=highload',
			'width':'800',
			'height':'350',
			'resizable':true});
			
		dialog.SetButtons([
			dialog.btnCancel/*,
			new BX.CWindowButton(
			{
				title: BX.message('JS_CORE_WINDOW_SAVE'),
				id: 'savebtn',
				name: 'savebtn',
				className: BX.browser.IsIE() && BX.browser.IsDoctype() && !BX.browser.IsIE10() ? '' : 'adm-btn-save',
				action: function () {
					this.disableUntilError();
					this.parentWindow.PostParameters();
					//this.parentWindow.Close();
				}
			})*/
		]);
		
		BX.addCustomEvent(dialog, 'onWindowRegister', function(){
			$('input[type=checkbox]', this.DIV).each(function(){
				BX.adminFormTools.modifyCheckbox(this);
			});
			if(typeof $('select.kda-chosen-multi').chosen == 'function')
			{
				$('select.kda-chosen-multi').chosen({search_contains: true, placeholder_text: BX.message("KDA_IE_CRON_CHOOSE_PROFILE")});
			}
		});
			
		dialog.Show();
	},
	
	SaveCron: function(btn)
	{
		var obj = this;
		var form = $(btn).closest('form');
		var action = form[0].getAttribute('action');
		$.post(action, form.serialize()+'&subaction='+btn.name, function(data){
			$('#kda-ie-cron-result').html(data);
			obj.UpdateCronRecords(action);
			if($('input[name="recordkey"]', form).val().length > 0)
			{
				$('input[name="recordkey"]', form).val('');
				var addBtn = $('input[name="add"]', form);
				addBtn.val(addBtn.attr('data-name-add'));
				$('select[name="PROFILE_ID[]"]', form).val('').trigger('chosen:updated').trigger('change');
				$('select[name="agent_period_type"]', form).val('daily').trigger('chosen:updated').trigger('change');
			}
		});
	},
	
	EditCronRecord: function(btn, key)
	{
		$('#kda-ie-cron-result').html('');
		btn = $(btn);
		var obj = this;
		var form = btn.closest('form');
		$('select[name="PROFILE_ID[]"]', form).val(btn.attr('data-profiles').split(',')).trigger('chosen:updated').trigger('change');
		$('select[name="agent_period_type"]', form).val('expert').trigger('chosen:updated').trigger('change');
		$('input[name="agent_period_expert"]', form).val(btn.attr('data-time'));
		$('input[name="agent_php_path"]', form).val(btn.attr('data-phppath'));
		$('input[name="recordkey"]', form).val(key);
		var addBtn = $('input[name="add"]', form);
		addBtn.val(addBtn.attr('data-name-change'));
		form.closest('.bx-core-adm-dialog-content').animate({scrollTop: 0}, 500);
	},
	
	DeleteFromCron: function(btn, key)
	{
		var obj = this;
		var form = $(btn).closest('form');
		var action = form[0].getAttribute('action');
		$.post(action, 'action=deleterecord&key='+encodeURIComponent(key), function(data){
			$('#kda-ie-cron-result').html('');
			obj.UpdateCronRecords(action);
		});
	},
	
	UpdateCronRecords: function(action)
	{
		$.get(action, function(data){
			$('#kda-ie-cron-records_wrap').html($(data).find('#kda-ie-cron-records_wrap').html());
		});
	},
	
	ShowMassUploader: function()
	{
		var dialog = new BX.CAdminDialog({
			'title':BX.message("KDA_IE_TOOLS_IMG_LOADER_TITLE"),
			'content_url':'/bitrix/admin/'+kdaIEModuleFilePrefix+'_mass_uploader.php?lang='+BX.message('LANGUAGE_ID')+'&suffix=highload',
			'width':'900',
			'height':'450',
			'resizable':true});
			
		this.massUploaderDialog = dialog;
		this.MassUploaderSetButtons();
			
		dialog.Show();
	},
	
	MassUploaderSetButtons: function()
	{
		var dialog = this.massUploaderDialog;
		dialog.SetButtons([
			dialog.btnCancel,
			new BX.CWindowButton(
			{
				title: BX.message('JS_CORE_WINDOW_SAVE'),
				id: 'savebtn',
				name: 'savebtn',
				className: BX.browser.IsIE() && BX.browser.IsDoctype() && !BX.browser.IsIE10() ? '' : 'adm-btn-save',
				action: function () {
					this.disableUntilError();
					this.parentWindow.PostParameters();
					//this.parentWindow.Close();
				}
			})
		]);
	},
	
	RemoveProccess: function(link, id)
	{
		var post = {
			'MODE': 'AJAX',
			'PROCCESS_PROFILE_ID': id,
			'ACTION': 'REMOVE_PROCESS_PROFILE'
		};
		
		$.ajax({
			type: "POST",
			url: window.location.href,
			data: post,
			success: function(data){
				var parent = $(link).closest('.kda-proccess-item');
				if(parent.parent().find('.kda-proccess-item').length <= 1)
				{
					parent.closest('.adm-info-message-wrap').hide();
				}
				parent.remove();
			}
		});
	},
	
	ContinueProccess: function(link, id)
	{
		var parent = $(link).closest('div');
		parent.append('<form method="post" action="" style="display: none;">'+
						'<input type="hidden" name="PROFILE_ID" value="'+id+'">'+
						'<input type="hidden" name="STEP" value="3">'+
						'<input type="hidden" name="PROCESS_CONTINUE" value="Y">'+
						'<input type="hidden" name="sessid" value="'+$('#sessid').val()+'">'+
					  '</form>');
		parent.find('form')[0].submit();
	},
	
	ToggleAdditionalSettings: function(link)
	{
		if(link) link = $(link);
		else link = $('.kda-head-more');
		if(link.length==0) return;
		$(link).each(function(){
			var tr = $(this).closest('tr');
			var show = $(this).hasClass('show');
			while((tr = tr.next('tr:not(.heading)')) && tr.length > 0)
			{
				if(show) tr.hide();
				else tr.show();
			}
			if(show) $(this).removeClass('show');
			else $(this).addClass('show');
		});
	},
	
	RadioChb: function(chb1, chb2name, confirmMessage)
	{
		if(chb1.checked)
		{
			if(!confirmMessage || confirm(confirmMessage))
			{
				var form = $(chb1).closest('form');
				if(typeof chb2name=='object')
				{
					for(var i=0; i<chb2name.length; i++)
					{
						if(form[0][chb2name[i]]) form[0][chb2name[i]].checked = false;
					}
				}
				if(form[0][chb2name]) form[0][chb2name].checked = false;
			}
			else
			{
				chb1.checked = false;
			}
		}
	},
	
	ShowEmailForm: function()
	{
		var pid = $('#PROFILE_ID').val();
		var dialog = new BX.CAdminDialog({
			'title':BX.message("KDA_IE_POPUP_SOURCE_EMAIL"),
			'content_url':'/bitrix/admin/'+kdaIEModuleFilePrefix+'_source_email.php?lang='+BX.message('LANGUAGE_ID')+'&PROFILE_ID='+pid,
			'content_post': {EMAIL_SETTINGS: $('.kda-ie-file-choose input[name="SETTINGS_DEFAULT[EMAIL_DATA_FILE]"]').val()},
			'width':'900',
			'height':'450',
			'resizable':true});
			
		BX.addCustomEvent(dialog, 'onWindowRegister', function(){
			
		});
		
		dialog.SetButtons([
			dialog.btnCancel,
			new BX.CWindowButton(
			{
				title: BX.message('JS_CORE_WINDOW_SAVE'),
				id: 'savebtn',
				name: 'savebtn',
				className: BX.browser.IsIE() && BX.browser.IsDoctype() && !BX.browser.IsIE10() ? '' : 'adm-btn-save',
				action: function () {
					this.disableUntilError();
					this.parentWindow.PostParameters();
					//this.parentWindow.Close();
				}
			})
		]);
			
		dialog.Show();
	},
	
	CheckEmailConnectData: function(link)
	{
		var form = $(link).closest('form');
		var post = form.serialize()+'&action=checkconnect';
		$.ajax({
			type: "POST",
			url: form.attr('action'),
			data: post,
			success: function(data){
				eval('var res = '+data+';');
				if(res.result=='success') $('#connect_result').html('<div class="success">'+BX.message("KDA_IE_SOURCE_EMAIL_SUCCESS")+'</div>');
				else $('#connect_result').html('<div class="fail">'+BX.message("KDA_IE_SOURCE_EMAIL_FAIL")+'</div><div class="fail_note">'+BX.message("KDA_IE_SOURCE_EMAIL_FAIL_NOTE")+'</div>');
				
				if(res.folders)
				{
					var select = $('select[name="EMAIL_SETTINGS[FOLDER]"]', form);
					var oldVal = select.val();
					$('option', select).remove();
					for(var i in res.folders)
					{
						var option = $('<option>'+res.folders[i]+'</option>');
						option.attr('value', i);
						select.append(option);
					}
					select.val(oldVal);
				}
			},
			error: function(){
				$('#connect_result').html('<div class="fail">'+BX.message("KDA_IE_SOURCE_EMAIL_FAIL")+'</div>');
			},
			timeout: 15000
		});
	},
	
	ShowFileAuthForm: function()
	{
		var pid = $('#PROFILE_ID').val();
		var post = '';
		var json = $('.kda-ie-file-choose input[name="EXT_DATA_FILE"]').val();
		if(json && json.substr(0,1)=='{')
		{
			eval('post = {AUTH_SETTINGS: '+json+'};');
		}
		var dialog = new BX.CAdminDialog({
			'title':BX.message("KDA_IE_POPUP_SOURCE_LINKAUTH"),
			'content_url':'/bitrix/admin/'+kdaIEModuleFilePrefix+'_source_linkauth.php?lang='+BX.message('LANGUAGE_ID')+'&PROFILE_ID='+pid,
			'content_post': post,
			'width':'900',
			'height':'450',
			'resizable':true});
			
		BX.addCustomEvent(dialog, 'onWindowRegister', function(){
			
		});
		
		dialog.SetButtons([
			dialog.btnCancel,
			new BX.CWindowButton(
			{
				title: BX.message('JS_CORE_WINDOW_SAVE'),
				id: 'savebtn',
				name: 'savebtn',
				className: BX.browser.IsIE() && BX.browser.IsDoctype() && !BX.browser.IsIE10() ? '' : 'adm-btn-save',
				action: function () {
					this.disableUntilError();
					this.parentWindow.PostParameters();
					//this.parentWindow.Close();
				}
			})
		]);
			
		dialog.Show();
	},
	
	SetLinkAuthParams: function(jData)
	{
		if($('.kda-ie-file-choose input[name="EXT_DATA_FILE"]').length == 0)
		{
			$(".kda-ie-file-choose").prepend('<input type="hidden" name="EXT_DATA_FILE" value="">');
		}
		$('.kda-ie-file-choose input[name="EXT_DATA_FILE"]').val(JSON.stringify(jData));
		$('.kda-ie-file-choose input[name="SETTINGS_DEFAULT[EMAIL_DATA_FILE]"]').val('');
		BX.WindowManager.Get().Close();
	},
	
	LauthAddVar: function(link)
	{
		var tr = $(link).closest('tr').prev('tr.kda-ie-lauth-var');
		var newTr = tr.clone();
		newTr.find('input').val('');
		tr.after(newTr);
	},
	
	CheckLauthConnectData: function(link)
	{
		var form = $(link).closest('form');
		var post = form.serialize()+'&action=checkconnect';
		$.ajax({
			type: "POST",
			url: form.attr('action'),
			data: post,
			success: function(data){
				eval('var res = '+data+';');
				if(res.result=='success') $('#connect_result').html('<div class="success">'+BX.message("KDA_IE_SOURCE_LAUTH_SUCCESS")+'</div>');
				else $('#connect_result').html('<div class="fail">'+BX.message("KDA_IE_SOURCE_LAUTH_FAIL")+'</div>');
			},
			error: function(){
				$('#connect_result').html('<div class="fail">'+BX.message("KDA_IE_SOURCE_LAUTH_FAIL")+'</div>');
			},
			timeout: 30000
		});
	},
	
	LauthLoadParams: function(link)
	{
		var form = $(link).closest('form');
		var post = form.serialize()+'&action=loadparams';
		$.ajax({
			type: "POST",
			url: form.attr('action'),
			data: post,
			success: function(data){
				if(data.length==0) return;
				eval('var res = '+data+';');
				if(typeof res!='object') return;
				
				var varInputs = $('input[name="vars[]"]', form);
				var emptyVals = true;
				for(var i=0; i<varInputs.length; i++)
				{
					if($.trim($(varInputs[i]).val()).length > 0) emptyVals = false;
				}
				if(emptyVals && typeof res.VARS=='object')
				{
					var countVars = varInputs.length;
					while(countVars < res.VARS.length)
					{
						$('td.kda-ie-lauth-addvar a', form).trigger('click');
						countVars++;
					}
					varInputs = $('input[name="vars[]"]', form);
					for(var i=0; i<varInputs.length; i++)
					{
						if(res.VARS[i]) $(varInputs[i]).val(res.VARS[i]);
					}
				}
				var postAuthInput = $('input[name="AUTH_SETTINGS[POSTPAGEAUTH]"]', form);
				if($.trim(postAuthInput.val()).length == 0 && res.LOC)
				{
					postAuthInput.val(res.LOC);
				}
			},
			timeout: 8000
		});
	},
	
	OpenMissignElementFields: function(link)
	{
		var form = $(link).closest('form');
		var hlblId = $('select[name="SETTINGS_DEFAULT[HIGHLOADBLOCK_ID]"]', form).val();
		var input = $(link).prev('input[type=hidden]');
		
		var dialogParams = {
			'title':BX.message(input.attr('id').indexOf('OFFER_')==0 ? "KDA_IE_POPUP_MISSINGOFFER_FIELDS_TITLE" : "KDA_IE_POPUP_MISSINGELEM_FIELDS_TITLE"),
			'content_url':'/bitrix/admin/'+kdaIEModuleFilePrefix+'_missignelem_fields_hl.php?lang='+BX.message('LANGUAGE_ID')+'&HLBL_ID='+hlblId+'&INPUT_ID='+input.attr('id'),
			'content_post': {OLDDEFAULTS: input.val()},
			'width':'800',
			'height':'400',
			'resizable':true
		};
		var dialog = new BX.CAdminDialog(dialogParams);
			
		dialog.SetButtons([
			dialog.btnCancel,
			new BX.CWindowButton(
			{
				title: BX.message('JS_CORE_WINDOW_SAVE'),
				id: 'savebtn',
				name: 'savebtn',
				className: BX.browser.IsIE() && BX.browser.IsDoctype() && !BX.browser.IsIE10() ? '' : 'adm-btn-save',
				action: function () {
					this.disableUntilError();
					this.parentWindow.PostParameters();
					//this.parentWindow.Close();
				}
			})
		]);
			
		BX.addCustomEvent(dialog, 'onWindowRegister', function(){
			$('input[type=checkbox]', this.DIV).each(function(){
				BX.adminFormTools.modifyCheckbox(this);
			});
			$('select.kda-ie-chosen-multi').chosen();
		});
			
		dialog.Show();
		
		return false;
	},
	
	OpenMissignElementFilter: function(link)
	{
		var obj = this;
		var form = $(link).closest('form');
		var hlblockId = $('select[name="SETTINGS_DEFAULT[HIGHLOADBLOCK_ID]"]', form).val();
		
		var dialogParams = {
			'title':BX.message("KDA_IE_POPUP_MISSINGELEM_FILTER_TITLE"),
			'content_url':'/bitrix/admin/'+kdaIEModuleFilePrefix+'_missignelem_filter_hl.php?lang='+BX.message('LANGUAGE_ID')+'&HIGHLOADBLOCK_ID='+hlblockId+'&PROFILE_ID='+$('#PROFILE_ID').val(),
			'content_post': {OLDFILTER: $('#ELEMENT_MISSING_FILTER').val()},
			'width':'800',
			'height':'400',
			'resizable':true
		};
		var dialog = new BX.CAdminDialog(dialogParams);
			
		dialog.SetButtons([
			dialog.btnCancel,
			new BX.CWindowButton(
			{
				title: BX.message('JS_CORE_WINDOW_SAVE'),
				id: 'savebtn',
				name: 'savebtn',
				className: BX.browser.IsIE() && BX.browser.IsDoctype() && !BX.browser.IsIE10() ? '' : 'adm-btn-save',
				action: function () {
					$('#kda-ie-filter').find('tr[id*="_filter_row_"]:hidden').find('input,select,textarea').val('').trigger('change');
					$.post('/bitrix/admin/'+kdaIEModuleFilePrefix+'_missignelem_filter_hl.php?lang='+BX.message('LANGUAGE_ID'), $('#kda-ie-filter').serialize(), function(data){
						$('#ELEMENT_MISSING_FILTER').val($.trim(data));
						BX.WindowManager.Get().Close();
					});
				}
			})
		]);
		
		BX.addCustomEvent(dialog, 'onWindowRegister', function(){
			setTimeout(function(){
				$('.find_form_inner select[name*="find_el_vtype_"]').bind('change', function(){
					var div = $(this.parentNode).next();
					if(this.value.length > 0 && this.value.indexOf('empty')!=-1) div.hide();
					else div.show();
				}).trigger('change');
			}, 500);
		});
			
		dialog.Show();
		
		return false;
	}
}

var EImport = {
	params: {},

	Init: function(post, params)
	{
		BX.scrollToNode($('#resblock .adm-info-message')[0]);
		this.wait = BX.showWait();
		this.post = post;
		if(typeof params == 'object') this.params = params;
		this.SendData();
		this.pid = post.PROFILE_ID;
		this.idleCounter = 0;
		this.errorStatus = false;
		var obj = this;
		setTimeout(function(){obj.SetTimeout();}, 3000);
	},
	
	SetTimeout: function()
	{
		if($('#progressbar').hasClass('end')) return;
		var obj = this;
		this.timer = setTimeout(function(){obj.GetStatus();}, 2000);
	},
	
	GetStatus: function()
	{
		var obj = this;
		$.ajax({
			type: "GET",
			url: '/upload/tmp/'+kdaIEModuleName+'/'+kdaIEModuleAddPath+this.pid+'_highload.txt?hash='+(new Date()).getTime(),
			success: function(data){
				var finish = false;
				if(data && data.substr(0, 1)=='{' && data.substr(data.length-1)=='}')
				{
					try {
						eval('var result = '+data+';');
					} catch (err) {
						var result = false;
					}
				}
				else
				{
					var result = false;
				}
				
				if(typeof result == 'object')
				{
					if(result.action!='finish')
					{
						obj.UpdateStatus(result);
					}
					else
					{
						obj.UpdateStatus(result, true);
						var finish = true;
					}
				}
				if(!finish) obj.SetTimeout();
			},
			error: function(){
				obj.SetTimeout();
			},
			timeout: 5000
		});
	},
	
	UpdateStatus: function(result, end)
	{
		if($('#progressbar').hasClass('end')) return;
		if(end && this.timer) clearTimeout(this.timer);
		
		if(typeof result == 'object')
		{
			if(end && (parseInt(result.total_read_line) < parseInt(result.total_file_line)))
			{
				result.total_read_line = result.total_file_line;
			}
			
			$('#total_line').html(result.total_line);
			$('#correct_line').html(result.correct_line);
			$('#error_line').html(result.error_line);
			$('#element_added_line').html(result.element_added_line);
			$('#element_updated_line').html(result.element_updated_line);
			$('#element_removed_line').html(result.element_removed_line);
			$('#sku_added_line').html(result.sku_added_line);
			$('#sku_updated_line').html(result.sku_updated_line);
			$('#section_added_line').html(result.section_added_line);
			$('#section_updated_line').html(result.section_updated_line);
			$('#killed_line').html(result.killed_line);
			$('#zero_stock_line').html(result.zero_stock_line);
			
			var span = $('#progressbar .presult span');
			//span.html(span.attr('data-prefix')+': '+result.total_read_line+'/'+result.total_file_line);
			//span.css('visibility', 'hidden');
			if(result.curstep && span.attr('data-'+result.curstep))
			{
				span.html(span.attr('data-'+result.curstep));
			}
			if(end)
			{
				span.css('visibility', 'hidden');
				$('#progressbar .presult').removeClass('load');
				$('#progressbar').addClass('end');
			}
			var percent = Math.round((result.total_read_line / result.total_file_line) * 100);
			if(percent >= 100)
			{
				if(end) percent = 100;
				else percent = 99;
			}
			$('#progressbar .presult b').html(percent+'%');
			$('#progressbar .pline').css('width', percent+'%');
			
			if(this.tmpparams && this.tmpparams.total_read_line==result.total_read_line)
			{
				this.idleCounter++;
			}
			else
			{
				this.idleCounter = 0;
			}
			this.tmpparams = result;
		}
		
		/*if(this.idleCounter > 10 && this.errorStatus)
		{
			var obj = this;
			for(var i in obj.tmpparams)
			{
				obj.params[i] = obj.tmpparams[i];
			}
			obj.SendData();
		}*/
	},
	
	SendData: function()
	{
		var post = this.post;
		post.ACTION = 'DO_IMPORT';
		post.stepparams = this.params;
		var obj = this;
		
		$.ajax({
			type: "POST",
			url: window.location.href,
			data: post,
			success: function(data){
				obj.errorStatus = false;
				obj.OnLoad(data);
			},
			error: function(data){
				if(data && data.responseText)
				{
					if(data.responseText.indexOf("[Error]")!=-1 || data.responseText.indexOf("[ErrorException]")!=-1 || data.responseText.indexOf("Query Error")!=-1)
					{
						$('#block_error').show();
						$('#res_error').append('<div>'+data.responseText+'</div>');
					}
				}
				obj.errorStatus = true;
				$('#block_error_import').show();
			},
			timeout: 180000
		});
	},
	
	OnLoad: function(data)
	{
		data = $.trim(data);
		var returnLabel = '<!--module_return_data-->';
		if(data.indexOf(returnLabel)!=-1)
		{
			data = $.trim(data.substr(data.indexOf(returnLabel) + returnLabel.length));
			var returnLabel2 = returnLabel.replace('<!--', '<!--/');
			if(data.indexOf(returnLabel2)!=-1)
			{
				data = $.trim(data.substr(0, data.indexOf(returnLabel2)));
			}
		}
		if(data.indexOf('{')!=0)
		{
			this.SendData();
			return true;
		}
		try {
			eval('var result = '+data+';');
		} catch (err) {
			var result = false;
		}
		if(typeof result == 'object')
		{
			if(result.sessid)
			{
				$('#sessid').val(result.sessid);
			}
			
			if(typeof result.errors == 'object' && result.errors.length > 0)
			{
				$('#block_error').show();
				for(var i=0; i<result.errors.length; i++)
				{
					$('#res_error').append('<div>'+result.errors[i]+'</div>');
				}
			}
			
			if(result.action=='continue')
			{
				this.UpdateStatus(result.params);
				this.params = result.params;
				this.SendData();
				return true;
			}
		}
		else
		{
			this.SendData();
			return true;
		}

		this.UpdateStatus(result.params, true);
		BX.closeWait(null, this.wait);
		/*$('#res_continue').hide();
		$('#res_finish').show();*/
		
		if(typeof result == 'object' && result.params.redirect_url && result.params.redirect_url.length > 0)
		{
			$('#redirect_message').html($('#redirect_message').html() + result.params.redirect_url);
			$('#redirect_message').show();
			setTimeout(function(){window.location.href = result.params.redirect_url}, 3000);
		}
		return false;
	}
}

var ESettings = {
	AddValue: function(link)
	{
		var input = $(link).prev('div').find('input[type=text]');
		var name = (input.length > 0 ? input[0].name : '');
		$(link).before('<div><input type="text" name="'+name+'" value=""></div>');
	},
	
	AddMargin: function(link)
	{
		var div = $(link).closest('td').find('.kda-ie-settings-margin:eq(0)');
		if(!div.is(':visible'))
		{
			div.show();
		}
		else
		{
			var div2 = div.clone(true);
			$('input', div2).val('');
			$('select', div2).prop('selectedIndex', 0); 
			$(link).before(div2);
		}
	},
	
	RemoveMargin: function(link)
	{
		var divs = $(link).closest('td').find('.kda-ie-settings-margin');
		if(divs.length > 1)
		{
			$(link).closest('.kda-ie-settings-margin').remove();
		}
		else
		{
			$('input', divs).val('');
			$('select', divs).prop('selectedIndex', 0); 
			divs.hide();
		}
	},
	
	ShowMarginTemplateBlock: function(link)
	{
		var div = $('#margin_templates');
		div.toggle();
	},
	
	ShowMarginTemplateBlockLoad: function(link, action)
	{
		var div = $('#margin_templates_load');
		if(action == 'hide') div.hide();
		else div.toggle();
	},
	
	SaveMarginTemplate: function(input, message)
	{
		var div = $(input).closest('div');
		var tid = $('select[name=MARGIN_TEMPLATE_ID]', div).val();
		var tname = $('input[name=MARGIN_TEMPLATE_NAME]', div).val();
		if(tid.length==0 && tname.length==0) return false;
		
		var wm = BX.WindowManager.Get();
		var url = wm.PARAMS.content_url;
		var params = wm.GetParameters().replace(/(^|&)action=[^&]*($|&)/, '&').replace(/^&+/, '').replace(/&+$/, '')
		params += '&action=save_margin_template&template_id='+tid+'&template_name='+tname;
		$.post(url, params, function(data){
			var jData = $(data);
			$('#margin_templates').replaceWith(jData.find('#margin_templates'));
			$('#margin_templates_load').replaceWith(jData.find('#margin_templates_load'));
			alert(message);
		});
		
		return false;
	},
	
	LoadMarginTemplate: function(input)
	{
		var div = $(input).closest('div');
		var tid = $('select[name=MARGIN_TEMPLATE_ID]', div).val();
		if(tid.length==0) return false;
		
		var wm = BX.WindowManager.Get();
		var url = wm.PARAMS.content_url;
		var params = wm.GetParameters().replace(/(^|&)action=[^&]*($|&)/, '&').replace(/^&+/, '').replace(/&+$/, '')
		params += '&action=load_margin_template&template_id='+tid;
		var obj = this;
		$.post(url, params, function(data){
			var jData = $(data);
			$('#settings_margins').replaceWith(jData.find('#settings_margins'));
			obj.ShowMarginTemplateBlockLoad('hide');
		});
		
		return false;
	},
	
	RemoveMarginTemplate: function(input, message)
	{
		var div = $(input).closest('div');
		var tid = $('select[name=MARGIN_TEMPLATE_ID]', div).val();
		if(tid.length==0) return false;
		
		var wm = BX.WindowManager.Get();
		var url = wm.PARAMS.content_url;
		var params = wm.GetParameters().replace(/(^|&)action=[^&]*($|&)/, '&').replace(/^&+/, '').replace(/&+$/, '')
		params += '&action=delete_margin_template&template_id='+tid;
		$.post(url, params, function(data){
			var jData = $(data);
			$('#margin_templates').replaceWith(jData.find('#margin_templates'));
			$('#margin_templates_load').replaceWith(jData.find('#margin_templates_load'));
			alert(message);
		});
		
		return false;
	},
	
	GetFieldNames: function()
	{
		if(!this.fieldNames)
		{
			this.fieldNames = {};
			if(typeof admKDASettingMessages=='object')
			{
				for(var k in admKDASettingMessages)
				{
					if(typeof admKDASettingMessages[k]=='string')
					{
						this.fieldNames[k] = admKDASettingMessages[k];
					}
					/*
					for(var k2 in admKDASettingMessages[k].FIELDS)
					{
						this.fieldNames[k2] = admKDASettingMessages[k].FIELDS[k2]+' ('+admKDASettingMessages[k].TITLE+')';
					}
					*/
				}
			}
		}
		return this.fieldNames;
	},
	
	BindConversionEvents: function()
	{
		var obj = this;
		$('.kda-ie-settings-conversion:not([data-events-init])').each(function(){
			var parent = this;
			$(this).attr('data-events-init', 1);
			$('span.kda-ie-conv-select-value', parent).bind('click', function(e){
				e.stopPropagation();
				$(this).hide();
				var fieldObj = $(this).closest('.kda-ie-conv-select');
				var convsWrap = fieldObj.closest('.kda-ie-conv-share-wrap');
				var selectName = fieldObj.attr('data-select-name');
				var selectObj = convsWrap.find('select[name="'+selectName+'"]').clone();
				var value = $('input[type="hidden"]', fieldObj).val();
				if(value.length > 0 && value!='0') selectObj.val($('input[type="hidden"]', fieldObj).val());
				else selectObj.prop('selectedIndex', 0);
				fieldObj.find('.kda-ie-conv-select-sel').remove;
				var selectWrap = $('<span class="kda-ie-conv-select-sel"></span>');
				selectWrap.append(selectObj);
				fieldObj.append(selectWrap);
				
				/*$('body').one('click', function(e){
					e.stopPropagation();
					return false;
				});*/
				if(typeof selectObj.chosen == 'function') selectObj.chosen({search_contains: true});
				selectObj.bind('change', function(){
					var selectObj = $(this);
					var fieldObj = selectObj.closest('.kda-ie-conv-select');
					var opt = $('option', selectObj).eq(selectObj.prop('selectedIndex'));
					var opttext = opt.text().replace(/\(.*\)/, '');
					var optval = opt.val();
					$('.kda-ie-conv-select-sel', fieldObj).remove();
					$('.kda-ie-conv-select-value', fieldObj).html(opttext).show();
					$('input[type="hidden"]', fieldObj).val(optval).trigger('change');
				});
				var chosenDiv = selectObj.next('.chosen-container')[0];
				$('a:eq(0)', chosenDiv).trigger('mousedown');
				
				var lastClassName = chosenDiv.className;
				var interval = setInterval( function() {   
					   var className = chosenDiv.className;
						if (className !== lastClassName) {
							selectObj.trigger('change');
							lastClassName = className;
							clearInterval(interval);
						}
					},30);
				
				return false;
			});
			$('select.field_cell, span.field_cell input[type="hidden"], select.field_when, span.field_when input[type="hidden"]', parent).bind('change', function(){
				var wrap = $(this).closest('.kda-ie-settings-conversion');
				var cell = $('.field_cell input[type="hidden"]', wrap).val();
				var when = $('.field_when input[type="hidden"]', wrap).val();
				$('select.field_when, span.field_when', parent).show();
				$('.field_from', parent).show();
				if(cell=='ELSE' || cell=='LOADED' || cell=='DUPLICATE')
				{
					$('select.field_when, span.field_when', parent).hide();
					$('.field_from', parent).hide();
				}
				else if(when=='EMPTY' || when=='NOT_EMPTY' || when=='ANY')
				{
					$('.field_from', parent).hide();
				}
			}).trigger('change');
			$('.field_from input, .field_from textarea, .field_to input, .field_to textarea', parent).bind('change keyup', function(){
				this.rows = (this.value.indexOf("\n")==-1 ? 1 : 2);
				/*
				var arVals = this.value.match(/(#[A-Za-z0-9\_]+#)/g);
				var title = '';
				if(arVals && (typeof arVals=='object') && arVals.length > 0)
				{
					var fieldNames = obj.GetFieldNames();
					var fieldKey;
					for(var i=0; i<arVals.length; i++)
					{
						fieldKey = arVals[i].substring(1, arVals[i].length - 1);
						if(fieldNames[fieldKey])
						{
							title += (title.length > 0 ? "\r\n" : '')+arVals[i]+' - '+fieldNames[fieldKey];
						}
					}
				}
				this.title = title;
				*/
			}).trigger('change');
		});
	},
	
	AddConversion: function(link, event)
	{
		var prevDiv = $(link).prev('.kda-ie-settings-conversion');
		if(!prevDiv.is(':visible'))
		{
			prevDiv.show();
			this.AddDefaultConversionVals(link, prevDiv);
		}
		else
		{
			var div = prevDiv.clone();
			div.removeAttr('data-events-init');
			$('input', div).attr('id', '');
			if(typeof event == 'object' && (event.ctrlKey || event.shiftKey))
			{
				$('select, input', prevDiv).each(function(){
					$(this.tagName.toLowerCase()+'[name="'+this.name+'"]', div).val(this.value);
				});
			}
			else
			{
				$('select, input, textarea', div).not('.choose_val').val('').attr('title', '');
				$('select', div).prop('selectedIndex', 0); 
				$('.kda-ie-conv-select-value[data-default-val]', div).each(function(){
					$(this).html($(this).attr('data-default-val'));
				});
				this.AddDefaultConversionVals(link, div);
			}
			$(link).before(div);
		}
		ESettings.BindConversionEvents();
		return false;
	},
	
	AddDefaultConversionVals: function(link, div)
	{
		$('.kda-ie-conv-select[data-select-name]', div).each(function(){
			var selectName = $(this).attr('data-select-name');
			var wrap = $(link).closest('.kda-ie-conv-share-wrap');
			var val = $('select[name="'+selectName+'"]', wrap).find('option:eq(0)').prop('value');
			$('input[type="hidden"]', this).val(val);
		});
	},
	
	RemoveConversion: function(link)
	{
		var div = $(link).closest('.kda-ie-settings-conversion');
		if($(link).closest('td').find('.kda-ie-settings-conversion').length > 1)
		{
			div.remove();
		}
		else
		{
			$('input, textarea', div).not('.choose_val').val('');
			$('select', div).prop('selectedIndex', 0); 
			div.hide();
		}
	},
	
	ConversionUp: function(link)
	{
		var div = $(link).closest('.kda-ie-settings-conversion');
		var prev = div.prev('.kda-ie-settings-conversion');
		if(prev.length > 0)
		{
			div.insertBefore(prev);
		}
	},
	
	ConversionDown: function(link)
	{
		var div = $(link).closest('.kda-ie-settings-conversion');
		var next = div.next('.kda-ie-settings-conversion');
		if(next.length > 0)
		{
			div.insertAfter(next);
		}
	},
	
	ShowChooseVal: function(btn, cnt)
	{
		if(cnt < 1) return;
		var field = $(btn).prev('input, textarea')[0];
		this.focusField = field;
		var arLines = [];
		for(var i=0; i<cnt; i++)
		{
			arLines.push({'TEXT':admKDASettingMessages.CELL_VALUE+' '+(i+1),'TITLE':'#CELL'+(i+1)+'# - '+admKDASettingMessages.CELL_VALUE+' '+(i+1),'ONCLICK':'ESettings.SetUrlVar(\'#CELL'+(i+1)+'#\')'});
		}
		if(admKDASettingMessages.VALUES && typeof admKDASettingMessages.VALUES=='object')
		{
			var values = admKDASettingMessages.VALUES;
			var menuValsItems = [];
			for(var i=0; i<values.length; i++)
			{
				menuValsItems.push({
					TEXT: values[i],
					TITLE: values[i],
					ONCLICK: 'ESettings.SetUrlVar(this)'
				});
			}
			arLines.push({'TEXT':BX.message("KDA_IE_PROP_VALUES"),MENU: menuValsItems});
		}
		for(var key in admKDASettingMessages)
		{
			if(key.indexOf('RATE_')==0)
			{
				var currency = key.substr(5);
				arLines.push({'TEXT':admKDASettingMessages[key],'TITLE':'#'+currency+'# - '+admKDASettingMessages[key],'ONCLICK':'ESettings.SetUrlVar(\'#'+currency+'#\')'});
			}
		}
		arLines.push({'TEXT':admKDASettingMessages.CELL_LINK,'TITLE':'#CLINK# - '+admKDASettingMessages.CELL_LINK,'ONCLICK':'ESettings.SetUrlVar(\'#CLINK#\')'});
		arLines.push({'TEXT':admKDASettingMessages.IFILENAME,'TITLE':'#FILENAME# - '+admKDASettingMessages.IFILENAME,'ONCLICK':'ESettings.SetUrlVar(\'#FILENAME#\')'});
		arLines.push({'TEXT':admKDASettingMessages.ISHEETNAME,'TITLE':'#SHEETNAME# - '+admKDASettingMessages.ISHEETNAME,'ONCLICK':'ESettings.SetUrlVar(\'#SHEETNAME#\')'});
		BX.adminShowMenu(btn, arLines, '');
	},
	
	AddProfileDescription: function(link)
	{
		var tr = $(link).closest('tr');
		tr.hide();
		tr.next('tr').show();
	},
	
	ShowPHPExpression: function(link)
	{
		var div = $(link).next('.kda-ie-settings-phpexpression');
		if(div.is(':visible')) div.hide();
		else div.show();
	},
	
	SetUrlVar: function(id)
	{
		if(typeof id=='object') id = id.title;
		var obj_ta = this.focusField;
		//IE
		if (document.selection)
		{
			obj_ta.focus();
			var sel = document.selection.createRange();
			sel.text = id;
			//var range = obj_ta.createTextRange();
			//range.move('character', caretPos);
			//range.select();
		}
		//FF
		else if (obj_ta.selectionStart || obj_ta.selectionStart == '0')
		{
			var startPos = obj_ta.selectionStart;
			var endPos = obj_ta.selectionEnd;
			var caretPos = startPos + id.length;
			obj_ta.value = obj_ta.value.substring(0, startPos) + id + obj_ta.value.substring(endPos, obj_ta.value.length);
			obj_ta.setSelectionRange(caretPos, caretPos);
			obj_ta.focus();
		}
		else
		{
			obj_ta.value += id;
			obj_ta.focus();
		}

		BX.fireEvent(obj_ta, 'change');
		obj_ta.focus();
	},
	
	AddDefaultProp: function(select, type, varname)
	{
		if(!select.value) return;
		var parent = $(select).closest('tr');
		if(!varname)
		{
			var inputName = 'ADDITIONAL_SETTINGS['+(type ? type.toUpperCase() : 'ELEMENT')+'_PROPERTIES_DEFAULT]['+select.value+']';
		}
		else
		{
			var inputName = varname+'['+select.value+']';
		}
		if($(parent).closest('table').find('input[name="'+inputName+'"]').length > 0) return;
		var tmpl = parent.prev('tr.kda-ie-list-settings-defaults');
		var tr = tmpl.clone();
		tr.css('display', '');
		$('.adm-detail-content-cell-l', tr).html(select.options[select.selectedIndex].innerHTML+':');
		$('input[type=text]', tr).attr('name', inputName);
		tr.insertBefore(tmpl);
		$(select).val('').trigger('chosen:updated');
	},
	
	RemoveDefaultProp: function(link)
	{
		$(link).closest('tr').remove();
	},
	
	RemoveLoadingRange: function(link)
	{
		$(link).closest('div').remove();
	},
	
	AddNewLoadingRange: function(link)
	{
		var div = $(link).prev('div');
		var newRange = div.clone().insertBefore(div);
		newRange.show();
	},
}

var EHelper = {
	ShowHelp: function(index)
	{
		var dialog = new BX.CAdminDialog({
			'title':BX.message("KDA_IE_POPUP_HELP_TITLE"),
			'content_url':'/bitrix/admin/'+kdaIEModuleFilePrefix+'_popup_help.php?lang='+BX.message('LANGUAGE_ID')+'&suffix=highload',
			'width':'900',
			'height':'450',
			'resizable':true});
			
		BX.addCustomEvent(dialog, 'onWindowRegister', function(){
			$('#kda-ie-help-faq > li > a').bind('click', function(){
				var div = $(this).next('div');
				if(div.is(':visible')) div.stop().slideUp();
				else div.stop().slideDown();
				return false;
			});
			
			if(index > 0)
			{
				$('#kda-ie-help-tabs .kda-ie-tabs-heads a:eq('+parseInt(index)+')').trigger('click');
			}
		});
			
		dialog.Show();
	},
	
	SetTab: function(link)
	{
		var parent = $(link).closest('.kda-ie-tabs');
		var heads = $('.kda-ie-tabs-heads a', parent);
		var bodies = $('.kda-ie-tabs-bodies > div', parent);
		var index = 0;
		for(var i=0; i<heads.length; i++)
		{
			if(heads[i]==link)
			{
				index = i;
				break;
			}
		}
		heads.removeClass('active');
		$(heads[index]).addClass('active');
		
		bodies.removeClass('active');
		$(bodies[index]).addClass('active');
	}
}

function Select2Text(div, select)
{
	this.Init = function(div, select)
	{
		this.div = div;
		this.select = select;
		this.selectParent = $('<div class="kda-ie-select"></div>');
		this.selectParent.appendTo(this.div);
		this.select.appendTo(this.selectParent);
		var obj = this
		if(typeof this.select.chosen == 'function') this.select.chosen({search_contains: true}).bind('change', function(){obj.Change();}).trigger('change');
	};
	
	this.Change = function()
	{
		if(!$(this.selectParent).is(':visible')) return;
		if(this.select.val().length==0) return;
		this.selectParent.hide();
		var actionText = $('option', this.select).eq(this.select.prop('selectedIndex')).text();
		$('.kda-ie-actiontext', this.div).remove();
		this.div.append('<a href="#" class="kda-ie-actiontext">'+actionText+'</a>');
		var obj = this;
		$('.kda-ie-actiontext', this.div).bind('click', function(e){
			e.stopPropagation();
			if($('option', obj.select).length > 1)
			{
				$(this).remove();
				obj.selectParent.show();
				$('body').one('click', function(e){
					e.stopPropagation();
					return false;
				});
				var chosenDiv = obj.select.next('.chosen-container')[0];
				$('a:eq(0)', chosenDiv).trigger('mousedown');
				
				var lastClassName = chosenDiv.className;
				var interval = setInterval( function() {   
					   var className = chosenDiv.className;
						if (className !== lastClassName) {
							obj.select.trigger('change');
							lastClassName = className;
							clearInterval(interval);
						}
					},30);
			}
			return false;
		});
	}
	
	this.Init(div, select);
}

$(document).ready(function(){
	if($('#preview_file').length > 0)
	{
		var post = $('#preview_file').closest('form').serialize() + '&ACTION=SHOW_REVIEW_LIST';
		$.post(window.location.href, post, function(data){
			$('#preview_file').html(data);
			if($('.kda-ie-tbl:not([data-init])').length > 0)
			{
				EList.Init();
				$('.kda-ie-tbl').attr('data-init', 1);
			}
		});
	}

	EProfile.Init();
	
	if($('#kda-ie-updates-message').length > 0)
	{
		$.post('/bitrix/admin/'+kdaIEModuleFilePrefix+'.php?lang='+BX.message('LANGUAGE_ID'), 'MODE=AJAX&ACTION=SHOW_MODULE_MESSAGE', function(data){
			data = $(data);
			var inner = $('#kda-ie-updates-message-inner', data);
			if(inner.length > 0 && inner.html().length > 0)
			{
				$('#kda-ie-updates-message-inner').replaceWith(inner);
				$('#kda-ie-updates-message').show();
			}
		});
	}
});