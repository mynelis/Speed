var CMS =
{
	site: "",

	ajaxfile: "cms",

	contentxml: null,

	binding: null,

	entries: {},

	//defaultaccess: {},

	useraccess: {},

	refmodal: null,

	prevmodal: null,

	Initialize: function (utk)
	{
		var obj = {
			'type': 'json',
			'call': 'GetAccess',
			'utk': utk
		};

		$.modal.loaderimage = CMS.site+"system/f7/img/ajax-loader.gif";

		CMS.PostJSON(obj, null, function (data) {
			if (data) {
				CMS.site = data.baseurl;
				//CMS.defaultaccess = JSON.parse(data.defaultaccess);
				// console.log(data);
				CMS.useraccess = data.useraccess;// JSON.parse(data.useraccess);

				// console.log(CMS.site, CMS.useraccess);

				// parse_binding_file(CMS.site+"app/f7.xml", function (xml) {
				parse_binding_file(CMS.site+'f7/binding.xml', function (xml) {
					CMS.contentxml = xml;
					// CMS.InitializeBinding();
					initialize_binding();
					initialize_markers();
					setTimeout(function () {
						CMS.LoadDashboard();
					}, 2000);
				});
			}
		});
	}
};

var animateScrollTop = function animateScrollTop () {
	var body = $('html, body');
	body.stop().animate({
			scrollTop: 0
		}, 500, 'swing'
	);
};

var initialize_markers = function initialize_markers () {
	var markers = $('[data-bind]');
	if (0 < markers.length) {
		markers.each(function (i, el) {
			attach_tools($(el));
		});
		/*$('#f7cms form input').each(function (i, el) {
			$(el).defaultvalue();
			//$(el).topassword();
		});*/
	}
};

CMS.GetJSON = function (obj)
{
	obj.type = "json";
	var cb_okay = arguments[1] || false, cb_fail = arguments[2] || false;
	$.getJSON(CMS.ajaxfile, obj, function (data) {
		if (true === data) {
			if (cb_okay) cb_okay(data);
		}
		else {
			if (cb_fail) cb_fail(data);
		}
	});
};

CMS.PostJSON = function (obj)
{
	obj.type = "json";
	var cb_okay = arguments[1] || false, cb_fail = arguments[2] || false;
	$.post(CMS.ajaxfile, obj, function (data) {
		if (true === data) {
			if (cb_okay) cb_okay(data);
		}
		else {
			if (cb_fail) cb_fail(data);
		}
	}, "json");
};

CMS.GetHTML = function (obj)
{
	obj.type = "html";
	var cb_okay = arguments[1] || false, cb_fail = arguments[2] || false;
	$.get(CMS.ajaxfile, obj, function (data) {
		if (data) {
			if (cb_okay) cb_okay(data);
		}
		else {
			if (cb_fail) cb_fail(data);
		}
	});
};

CMS.GetXML = function (obj)
{
	obj.type = "xml";
	var cb_okay = arguments[1] || false, cb_fail = arguments[2] || false;
	$.get(CMS.ajaxfile, obj, function (data) {
		if (data) {
			if (cb_okay) cb_okay(data);
		}
		else {
			if (cb_fail) cb_fail(data);
		}
	}, "xml");
};

var parse_binding_file = function parse_binding_file (file, handler) {
	$.get(file, function (response, status, xhr) {
		if (response) {
			handler.call(xhr, response);
		}
	}, 'xml');
};

/*CMS.ParseXMLFile = function (file, handler)
{
	if (window.XMLHttpRequest) {
		xhttp = new XMLHttpRequest();
	}
	else {// IE 5/6
		xhttp = new ActiveXObject("Microsoft.XMLHTTP");
	}
	xhttp.open("GET", file);
	//xhttp.setRequestHeader('Cache-Control', 'max-age=0, must-revalidate');
	xhttp.send();
	xhttp.onreadystatechange = function () {
		if (4 === this.readyState && 'OK' === this.statusText) {
			handler.call(this, this.responseXML);
		}
	};
};*/

CMS.GetForm = function (f)
{
	//console.log(f);

	$.modal.open();
	var obj = {"call":"GetForm", "form":f};

	CMS.GetHTML(obj, function (data) {
		$.modal.data = data;
		$.modal.fill();

		$('#modal #_content_').addClass(f);
		/*$("#modal form input").each(function (i, el) {
			$(el).defaultvalue();
			$(el).topassword();
		});*/
	});
};

CMS.ChangePassword = function (fm, btn)
{
	var obj = $(fm).noempty("input").object;
	if (!obj) {
		return false;
	}

	obj.call = "ChangePassword";
	$(btn).attr("disabled","true");

	CMS.PostJSON(obj, function (data) {
		CMS.Logout();
	}, function (data) {
		$(btn).removeAttr("disabled");
		return $.modal.notify(data, true);
	});
};


CMS.Login = function (fm, btn)
{
	var obj = $(fm).noempty("input").object;
	if (!obj) {
		return false;
	}

	var login_form = $('#modal #_content_.login'), attempts = parseInt(login_form.data('attempts')) || 1;

	obj.call = "Login";
	// $(btn).attr("disabled","true");

	CMS.PostJSON(obj, function (data) {
		window.location = CMS.site;
	}, function (data) {
		/*console.log(data);
		return false;*/

		$(btn).removeAttr("disabled");	
		login_form.addClass('failed animated shake');

		setTimeout(function () {
			if (3 == attempts) {
				$.modal.close();
				window.location = CMS.site;
			}
			$('#modal #_content_.login').data('attempts', attempts + 1).removeClass('failed animated shake');
		}, 1000);
		//return $.modal.notify(data, true);
	});
};

CMS.Logout = function ()
{
	$.modal.confirm2('Are you sure you want to logout?', 'Yes', 'No', function () {
		$.modal.fill('Logging out...');
		CMS.GetJSON({"call":"Logout"}, function (data) {
			$.modal.close();
			window.location = CMS.site;
		});
	}, function () {
		$.modal.close();
	}, 'red', 'green');
	toggleDashboard(false);
};

CMS.GetPasswordForm = function ()
{
	CMS.GetForm('password');
	toggleDashboard(false);
	return false;
};

/*var _initBinding = function ()
{
	if (CMS.binding) return CMS.binding;

	var groups = {}, nodes = CMS.contentxml.documentElement.getElementsByTagName("group");
	for (var i=0; i<nodes.length; i++) {
		var id = nodes[i].getAttribute('id');
		groups[id] = {
			id: id,
			label: nodes[i].getAttribute('label'),
			entries: _getBindingEntries(id, nodes[i]),
			_group: nodes[i]
		};
	}

	CMS.binding = groups;
	return groups;
};*/

var binding_entries = function binding_entries (groupid, group) {
	var ent = {}, nodes = group.getElementsByTagName('entry');
	for (var i = 0; i < nodes.length; i++) {
		var id = nodes[i].getAttribute('id');
		var scope = nodes[i].getAttribute('scope');

		CMS.entries[id] = nodes[i];

		if (undefined === CMS.useraccess[id] && 'core' !== scope) continue;

		ent[id] = nodes[i];
	}

	return ent;
};

/*var _getBindingEntries = function (groupid, group)
{
	var ent = {}, nodes = group.getElementsByTagName("entry");
	for (var i=0; i<nodes.length; i++) {
		var id = nodes[i].getAttribute('id');
		var scope = nodes[i].getAttribute('scope');

		CMS.entries[id] = nodes[i];

		if (undefined === CMS.useraccess[id] && 'core' !== scope) continue;

		ent[id] = nodes[i];
	}

	return ent;
};
*/
var initialize_binding = function initialize_binding () {
	if (!CMS.contentxml) return false;

	// _initBinding();
	if (CMS.binding) return CMS.binding;

	var groups = {}, nodes = CMS.contentxml.documentElement.getElementsByTagName('group');
	for (var i = 0; i < nodes.length; i++) {
		var id = nodes[i].getAttribute('id');

		groups[id] = {
			id: id,
			label: nodes[i].getAttribute('label'),
			entries: binding_entries(id, nodes[i]),
			_group: nodes[i]
		};
	}

	CMS.binding = groups;
	return groups;
};

CMS.FindEntries = function (groupid)
{
	if (!CMS.binding) {
		return false;
	}
	for (var i in CMS.binding) {
		if (groupid == i) return CMS.binding[i];
	}
	return null;
};

var attach_tools = function attach_tools (el)
{
	var data = el.data(), bind = data.bind, id = data.id || null;

	/*if (undefined === CMS.useraccess[bind] || undefined === CMS.entries[bind]) {
		return false;
	}*/

	var entry = CMS.entries[bind];
	var access = CMS.useraccess[bind];

	if (undefined == entry || undefined == access) return false;
	if (0 === access.c && 0 === access.r && 0 === access.u && 0 === access.d) return false;

	// console.log(bind, access);

	group = CMS.binding[entry.parentNode.getAttribute('id')];

	var custom_icon = entry.getAttribute('icon') || 'toolbox';

	var tool = $('<div/>').addClass('cms_tool');
	var txt = group.label+' > '+entry.getAttribute('label');
	var fa = $('<i/>').addClass('fas fa-'+custom_icon+' toolbox_icon');

	var icon_list = $('<i/>').addClass('fas fa-list-ul');
	var icon_edit = $('<i/>').addClass('fas fa-edit');
	var icon_add = $('<i/>').addClass('fas fa-plus');

	var list_tool = $('<a/>').attr({href: '', title: txt+' > List'}).addClass('list_tool').append(icon_list).data('bind', bind);
	var edit_tool = $('<a/>').attr({href: '', title: txt+' > Edit'}).addClass('edit_tool').append(icon_edit).data({bind: bind, id: id});
	var add_tool = $('<a/>').attr({href: '', title: txt+' > Add'}).addClass('add_tool').append(icon_add).data({bind: bind, id: 0});

	// if (1 === access.c || 1 === access.r || 1 === access.u || 1 === access.d) tool.append(fa);
	tool.append(fa);
	if (1 === access.r) tool.append(list_tool);
	if (1 === access.c) tool.append(add_tool);
	if (1 === access.u && id) tool.append(edit_tool);

	// tool.append(fa, list_tool, add_tool);

	/*if (id) {
		tool.append(edit_tool);
	}*/
	/*else {
		tool.append(list_tool);
	}*/

	if (0 === el.find('.cms_tool').length) {
		el.css('position', 'relative').prepend(tool);
	}

	tool.find('a[href]').on('click', function (e) {
		e.preventDefault();
		var _bind = $(this).data('bind'), _id = $(this).data('id');

		if (_id > 0) {
			CMS.GetContent(_bind, _id);
		}
		else if (_id === 0) {
			CMS.GetContent(_bind, 0);
		}
		else {
			CMS.GetBox(_bind);
		}
	});
};

CMS.InitializeMenu = function ()
{
	for (var i in CMS.binding) {
		if (CMS.binding.hasOwnProperty(i)) {
			add_menu_group(CMS.binding[i]);
		}
	}

	$('#f7cms #dashboard li a').on('click', function (e) {
		var data = $(this).data();
		if (data && data.id) {
			e.preventDefault();
			if ('core' == data.scope) {
				if (data.action && CMS[data.action]) {
					CMS[data.action].call(data);
				}
			}
			else {
				CMS.GetBox(data.id);
			}
			return false;
		}
	});
};

var add_menu_group = function add_menu_group (group)
{
	if (!Object.keys(group.entries).length) return;
	var entries = {};

	// console.log(CMS.useraccess);

	$.each(group.entries, function (key, entry) {
		// var access = {c: 1, r: 1, u: 1, d: 1};
		var access = CMS.useraccess[key];
		var has_access = false;

		if ('core' === entry.getAttribute('scope')) {
			has_access = true;
		}
		else if (1 === access.c || 1 === access.r || 1 === access.u || 1 === access.d) {
			has_access = true;
		}

		if (has_access) entries[key] = entry;

		// console.log(key, access, has_access);
	});
	// console.log(CMS.useraccess, group.entries);

	var grp = $('<ul/>').addClass('menugroup').attr('id', 'menugroup_'+group.id);
	var title = $('<h3/>').text(group.label);

	// console.log(entries);

	grp.append(title);
	addMenuEntries(grp, entries);

	$('#f7cms #dashboard').append(grp);
};

var addMenuEntries = function (grp, entries)
{
	for (var i in entries) {
		if (entries.hasOwnProperty(i)) {
			var label = entries[i].getAttribute('label');
			var scope = entries[i].getAttribute('scope');
			var action = entries[i].getAttribute('action');

			if (!label) continue;

			var link = $('<a/>').attr('href', '').text(label).data({
				id: i,
				scope: scope,
				action: action
			});

			var menu = $('<li/>').append(link);
			if (scope) menu.addClass('scope_'+scope);
			grp.append(menu);
		}
	}
};

CMS.LoadDashboard = function ()
{
	var main = $('<div/>').attr('id', 'f7cms').text('Loading dashboard...').prependTo($('body'));

	var img = $('<img/>').attr({src: CMS.site+'system/f7/img/f7-speed.png'});

	var icon = $('<a/>', {id: 'cmsicon', href: CMS.site+'cms/dashboard'}).on("click", function () {
		toggleDashboard(true);
		return false;
	}).addClass('animated jello').append(img);

	var dash = $('<div/>', {id: 'dashboard'}).on("mouseleave", function () {
		toggleDashboard(false);
	});
	var intro = $('<div/>', {id: 'cms-intro'});

	var obj = {
		'call': 'GetCMSUser',
		'type': 'json'
	};

	$.getJSON(CMS.ajaxfile, obj, function (data) {
		if (data) {
			var firstname = data.fullname.split(/\s/)[0];
			//intro.text('Welcome to f7 CMS, '+firstname+'!').prependTo(dash);
			intro.text(firstname).prependTo(dash);
			main.empty().append(icon, dash);
			CMS.InitializeMenu();
		}
	});
};

var toggleDashboard = function (flag)
{
	if (flag) {
		$('#dashboard').addClass('shown');
	}
	else {
		$('#dashboard').removeClass('shown');
	}
};

var setRefmodalBackButton = function ()
{
	var head = $('#modal #_content_ #list-form-label');
	var link = $('<a/>').html('&laquo; Back').attr({
		'href': '',
		'id': 'refmodal-back'
	});

	//head.prepend(link);
};

CMS.GetBox = function (bind, page, opts)
{
	$.modal.open();
	var obj = {
		"call": "GetBox",
		"bind": bind,
		"page": page || 0
	};

	if (opts) {
		for (var i in opts) {
			if (opts.hasOwnProperty(i)) obj[i] = opts[i];
		}
	}

	animateScrollTop();

	CMS.GetHTML(obj, function (data) {

		$.modal.scroll = true;
		$.modal.data = data;
		$.modal.fill();

		CMS.refmodal = false;
		if (!opts) {
			CMS.refmodal = true;
		}

		if (CMS.prevmodal) {
			setRefmodalBackButton();
		}

		$('#refmodal-back').on('click', function (e) {
			e.preventDefault();
			$.modal.data = CMS.prevmodal;
			$.modal.fill();
		});

		if ('undefined' !== typeof $.tablesorter) {
			$("#cms_box").tablesorter();
		}
	});

	toggleDashboard(false);
};

CMS.GetContent = function (bind, content_id, opts)
{
	$.modal.open();
	var obj = {"call":"GetContent", "bind":bind	, "id":content_id};

	animateScrollTop();

	if (opts) {
		for (var i in opts) {
			if (opts.hasOwnProperty(i)) obj[i] = opts[i];
		}
	}

	CMS.GetHTML(obj, function (data) {
		$.modal.scroll = true;
		$.modal.data = data;
		$.modal.fill();

		init_cms_access_table();

		if (CMS.refmodal) {
			CMS.prevmodal = $('#modal #_content_').html();
		}
	});
};

CMS.SaveContent = function (fm, btn, bind, content_id, opts)
{
	$(".editor").each(function(){
		$("#html_"+$(this).attr("id")).val($(this).html());
    });

	if (!$(fm).checkformat("input, select, textarea")) {
		return $.modal.notify("Some required fields are not valid", true);
	}

	var obj = $(fm).objectmap();
	obj.bind = bind;
	obj.id = content_id;

	obj.call = "SaveContent";
	$(btn).attr("disabled","true");

	CMS.PostJSON(obj, function (data) {
		CMS.GetBox(bind, null, opts);
	}, function (data) {

		if ('string' != typeof data) {
			data = Object.values(data)[0];
		}
		
		$(btn).removeAttr('disabled');
		return $.modal.notify(data, true, 2000);
	});
};

CMS.Delete = function (bind, content_id, opts)
{
	var obj = {"call":"Delete", "bind":bind, "id":content_id};
	CMS.GetJSON(obj, function (data) {
		CMS.GetBox(bind, null, opts);
	});
};

CMS.Uploaded = function (el, file, type)
{
	if (el && file && 'undefined' === typeof type) {
		$.modal.notify(file, true, 3000, null, '#upload_progress_'+el);
		return;
	}

	$.modal.locked = false;
	if ("image" == type) {
		var rnd = new Date().getTime();
		$("#img_"+el).attr("src", "assets/images/"+file+'?'+rnd).css("display","block");
	}
	$.modal.notify("Upload complete", false, 2000, function () {
		$("#upload_progress_"+el).text(file);
	}, '#upload_progress_'+el);
	$("#el_"+el).val(file);
};

CMS.PreviewContent = function (txt, eid, instance)
{
	$("#modal #_content_").prepend($('<div id="preview_content" />'));
	$("#modal #_content_ #preview_content").prepend($('<button type="button" id="cms_closepreview" onclick="CMS.ClosePreview();return false;">Close</button>'));
	$("#modal #_content_ #preview_content").append(txt);
	$("#cms_form").css("display","none");
};

CMS.ClosePreview = function ()
{
	$("#modal #_content_ #preview_content").remove();
	$("#cms_form").css("display","block");
};

CMS.MapAccessKey = function (key)
{
	var baseurl = document.querySelector('head base[href]').getAttribute('href');

	$(document).on('keyup', function (e)
	{
		if (baseurl && e.keyCode === key) {
			if ($.cookie.get('utk')) {
				CMS.Logout();
			}
			else {
				window.location = baseurl+'cms';
			}
		}
	});
};

var init_cms_access_table = function init_cms_access_table () {

	$('.access_table[data-binding]').each(function () {

		var table = $(this);
		if (!table.length) return false;

		var write_value = function write_value (values) {
			table.find('textarea[name="'+table.data('name')+'"]').val(JSON.stringify(values));
		};

		var set_default_values = function set_default_values () {
			var values = table.find('textarea[name="'+table.data('name')+'"]').val();
			if (!values) return;

			values = JSON.parse(values);

			var access = {
				'1111': 'cr',
				'0110': 'ed',
				'0100': 're'
			};

			$.each(values, function (entry, crud) {
				var _crud = Object.values(crud).join('');
				var _class = access[_crud] || 'none';
				var altered = access[_crud] ? 'clean' : 'dirty';

				//console.log(entry, _crud, _class, altered, crud);

				table.find('.preset[data-entry="'+entry+'"].'+_class).attr('checked', true).prop('checked', true)
				//input.parent().addClass(altered);

				$.each(crud, function (m, v) {
					var el = table.find('input.custom[data-entry="'+entry+'"].'+m).attr('checked', 1 == v).prop('checked', 1 == v);
					var parent = el.parentsUntil('tr').parent();
					parent.addClass(altered);
				});
			});
		};

		table.find('tbody tr td input.preset').on('change', function () {
			var 
				el = $(this),
				d = el.data(),
				roles = d.modes.split(''),
				parent = el.parentsUntil('tr').parent()
				;

			parent.find('input.custom[data-entry="'+d.entry+'"]').attr('checked', false).prop('checked', false).trigger('change');
			$.each(roles, function (i, role) {
				parent.find('input.custom[data-entry="'+d.entry+'"][data-mode="'+role+'"]').attr('checked', true).prop('checked', true).trigger('change');
			});
		});

		set_default_values();
		//var vars = {};
		var vars = table.find('textarea[name="'+table.data('name')+'"]').val();
		var vars = vars ? JSON.parse(vars) : {};

		table.find('tbody tr td input.custom').on('change', function () {
			var $this = $(this), d = $this.data(), checked = $this.is(':checked') ? 1 : 0, vals = [];
			vals[d.mode] = checked;

			if (undefined == vars[d.entry]) vars[d.entry] = {};
			vars[d.entry][d.mode] = checked;

			write_value(vars);
		});

		table.find('.group th a').on('click', function (e) {
			e.preventDefault();
			var group = $(this).data('id');

			table.find('tbody tr[data-group]').hide();
			table.find('tbody tr[data-group="'+group+'"]').show();
		});

		table.find('.group:first-child th a').trigger('click');
	});
};