// global objects
// window.alert = function (s) {
//     console.log(s);
// }

/*$(window).load(function () {
    $.mouse.init();
});*/

String.prototype.toCammelCase = function toCammelCase () {
    return this.substring(0, 1)
        .toLowerCase() 
        + (this.split(/-|_|\s/)
            .map(
                (s) => s.substring(0, 1)
                .toUpperCase() 
                + s.substring(1)
            )
            .join('')
            .substring(1)
        );
};

$.extend($, {
    dataFormats:
    {
        keyword: {
            rule: /\w+/,
            hint: 'One or more words separated by spaces'
        },
        number: {
            rule: /^\d+$/,
            hint: 'Numeric digits (0-9)'
        },
        email: {
            rule: /^(.+?)\@(.+?)\.(.+?)$/,
            hint: 'Email address containing username, domain and TLD (.com, .net, .org, etc)'
        },
        phone: {
            rule: /^\d{10,20}$/,
            hint: 'A valid phone number must be at least 10 digits and at most 20, strictly numeric digits only'
        },
        word: {
            rule: /^\w+$/,
            hint: 'Only one word is required, with no special characters'
        },
        text: {
            rule: /.+/,
            hint: 'This input does not accept empty values'
        },
        url: {
            rule: /http[s]:\/\/.+\..+$/,
            hint: 'Website or webpage address. It must begin with http:// or https:// and end with a TLD (.com, .net, .org, etc)'
        },
        file: {
            rule: /.+\..+$/,
            hint: 'File name must end with .[ext], where "ext" is the extention of the file'
        },
        custom: {
            hint: 'Value must match the specified data format above'
        }
    },

    // mouse position tracer
    // eg: $.mouse.x
    mouse: {
        init: function () {
            $(document).mousemove(function (e) {
                $.mouse = {
                    x: e.pageX,
                    y: e.clientY
                };
            });
            this.initialized = true;
        }
    },

    // Cookie methods
    // todo: set, unset
    cookie: {
        // example: alert($.cookie.get("name"));
        get: function (k) {
            if (document.cookie) {
                var i, c, cs = document.cookie.split(/;[\s]/g);
                for (i=0; i<cs.length; i+=1) {
                    c = cs[i].split(/=/g);
                    if (c[0] == k && c[1]) {
                        return c[1];
                    }
                }
            }
            return null;
        },
        // example: $.cookie.set({name:"nelis"},{expire:3});
        set: function (obj) {
            for (var i in obj) {
                if (obj.hasOwnProperty(i)) {
                    var props = arguments[1] || {}, e="", p, d, s, dt = new Date();
                    p = props.path ? "; path="+props.path : "; path=/";
                    d = props.domain ? "; domain="+props.domain : "";
                    s = (props.secure && true === props.secure) ? "; secure" : "";

                    if (props.expire) {
                        dt.setTime(dt.getTime()+(props.expire*60*1000));
                        e = "; expires="+dt.toGMTString();
                    }
                    document.cookie = i+"="+obj[i]+e+p+d+s;
                }
            }
        },
        // example: $.cookie.unset("name");
        unset: function (k) {
            if ($.cookie.get(k)) {
                document.cookie = k+"=;expires=Thu, 01-Jan-70 00:00:01 GMT";
            }
        }
    },

    // my custom overlay [modal]
    // eg:
    //  $.modal.open('login',{name:'nelis',age:10},function () {
    //      alert('the modal is now open');
    //  })
    modal: {
        renew: function () {
            $('#modal').remove();
            //$('<div id="modal" />').insertBefore($('body')).children()[0];
            $('body').prepend($('<div id="modal" />'));
            $('#modal').append($('<div id="_overlay_" />'));
            $('#modal').append($('<div id="_content_" class="shadow rounded" />'));
            $('#modal #_content_').append($('<div id="modal_notice" />'));
            //$('<div id="modal_notice"></div>').insertBefore($('#modal #_content_').children()[0]);
        },
        open: function () {
            var box = arguments[0]||false,
                query = arguments[1]||{},
                cb = arguments[2]||false;

            $.modal.scroll = false;
            $.modal.locked = false;
            $.modal.cb = cb;
            $.modal.renew();
            $('#modal #_overlay_').css('display','block');

            if (!$.modal.loaderimage) {
            	$.modal.loaderimage = "cms/img/ajax-loader.gif";
            }
            $.modal.loading();
            $('#modal #_overlay_').click(function () {
                $.modal.close();
            });
            $.modal._monitor_escape();

			if (box) {
				query.modal = box;
				// added the box query string for each modal to be
				// cached separately if caching is enabled
				$.post('modal/?nocache&box='+box,query, function (data) {
					$.modal.data = data;
					$.modal.fill();
					$.modal._show();
				});
			}
        },
        close: function (cb, flag) {
        	//var cb = arguments[0]|| false;
            if (flag) $.modal.locked = false;

            if (!$.modal.locked) {
				$('#modal #_content_').slideUp('fast', function () {
					$('#modal').remove();
					if (cb) {
						cb();
					}
					else if ($.modal.cb) {
						$.modal.cb();
					}
				});
            }
        },
        _show: function () {
        	$('#modal #_content_').slideDown('slow',function () {
				$.modal._callback();
			});
        },
        _callback: function () {
        	var cb = $.modal.cb;
        	if (cb) {
                if ('string'==typeof(cb)) {
                	eval(cb+'()');
                }
				else {
					cb();
				}
            }
        },
        _monitor_escape: function () {
            $(document).keydown(function(e) {
                if (27 == e.keyCode) {
                    $.modal.close();
                }
            });
        },
        loading: function () {
        	var img = $('<img id="_loading_" src="'+$.modal.loaderimage+'" border="0" />');
        	if (0 < $('#modal #_content_').children().length) {
        		img.insertBefore($('#modal #_content_').children()[0]);
        		$('#modal #_content_').children().not('#_loading_').hide();
        	}
        	else {
        		$('#modal #_content_').append(img);
        	}
        },
        fill: function () {
        	if (arguments[0]) {
        		$.modal.data = arguments[0];
        	}
        	if ($.modal.scroll) {
        		$('#modal').css('position','absolute');
        	}
        	$('#modal #_content_ #_loading_').remove();

        	//$($("#modal #_content_").children().slice(1)).remove();
        	//$('#modal #_content_').append($.modal.data);
        	$("#modal #_content_").empty().append($('<div id="modal_notice" />')).append($.modal.data);
            $.modal.data = "";
        },
        error: function (err) {
        	$('#modal #_error_').remove();
        	$('<div class="_error_">'+err+'</div>')
        		.insertBefore($('#modal #_content_').children()[0]);
        	$.modal._callback();
        },
        notify: function (notice) {
        	//$("#modal #modal_notice").remove();
        	//$('<div id="modal_notice"></div>').insertBefore($('#modal #_content_').children()[0]);

        	var type = (true==arguments[1]) ? "error" : "notice",
				timeout = arguments[2] ? arguments[2] : 1000;
				cb = arguments[3] ? arguments[3] : false,
				container = arguments[4] ? arguments[4] : "#modal_notice";

			$(container).attr({"class":"modal_"+type}).html(notice).fadeIn("slow",
				function () {
					setTimeout(function () {
						$(container).fadeOut("slow", function () {
							if (cb) {
								cb();
							}
						});
					}, timeout);
				}
			);
			if ("error" == type) {
				return false;
			}
        },
        confirm: function (notice, cb, yes, no) {
        	$.modal.open();
        	var cb2 = arguments[4] ? arguments[4] : false;

        	var okay = '<button type="button" onclick="'+cb+';return false;">'+yes+'</button>',
        		cancel = '<button type="button" onclick="$.modal.close();return false;">'+no+'</button>',
        		buttons = '<div id="modal_buttons" />';

        	if (cb2) {
        		cancel = '<button type="button" onclick="'+cb2+';return false;">'+no+'</button>';
        	}

        	$.modal.fill('<div id="modal_title">'+notice+'</div>'+okay+' '+cancel);
        },
        confirm2: function (notice, yes, no, cb1, cb2, css_yes, css_no) {
            $.modal.open();

            var okay = $('<button/>').attr({
                'type': 'button'
            })
            .text(yes)
            .click(function () {
                if (cb1) cb1.call();
                return false;
            });
            if (css_yes) okay.addClass(css_yes);

            var cancel = $('<button/>').attr({
                'type': 'button'
            })
            .text(no)
            .click(function () {
                if (cb2) cb2.call();
                return false;
            });
            if (css_no) cancel.addClass(css_no);

            var dv = $('<div/>'), tdv = $('<div/>').attr('id', 'modal_title').text(notice);
            dv.append(tdv, okay, cancel);
            $.modal.fill(dv);
        }
    }
});

$.extend($.fn, {

	// autogrow textarea
	autogrow: function () {
		return $(this).each(function () {

			if (!this.cols) this.cols = 30;
			if (!this.rows) this.rows = 1;

			var h,
				rows = this.rows,
				cols = this.cols,
				elem = this;

			$(elem).keyup(function (e) {

				var linescount = 0,
					lines = $(elem).val().split("\n");

				for (var i=lines.length-1; i>=0; --i) {
					linescount += Math.floor((lines[i].length / cols) + 1);
				}

				h = linescount > rows ? linescount+1 : rows;
				$(elem).attr("rows",h);

			}).bind("focus", function(){
				$(elem).keyup();
			});

		});
	},

	// autogrow textarea
	/*expandable: function () {
		var i = 1;
		return $(this).each(function () {
			var el = $(this),

				id = el[0].id ? el[0].id+"_"+i : "expandable_"+i,

				he = $("<input />").attr({"type":"hidden", "identity":id, "class":"_expandable", "name":el[0].name})
					.val(el.val())
					.insertAfter(el),

				dv = $("<textarea />").attr({"class":el[0].className||"expandable", "id":el[0].id||""})
					.css({"width":el.width(), "min-height":el.height(), "cursor":"text"})
					.attr({"contenteditable":true})
					.html(el.val())
					.bind("keyup", function () {
						var v = dv.html();
						he.val(dv.html());
					})
					.insertAfter(el);

			++i;
			el.remove();
		});
	},*/

	// expandable text input improved
	expandablei: function () {
		var i = 1;
		return $(this).each(function () {
			var el = $(this),
				id = el.attr("id") ? el.attr("id")+"_"+i : "expandable_"+i,

				he = $("<input />").attr({"type":"hidden", "identity":id, "class":"_expandable", "name":el.attr("name")})
					.val(el.val())
					.insertAfter(el),

				dv = el//.attr({"class":el[0].className||"expandable", "id":el[0].id||""})
					//.css({"width":el.width(), "min-height":el.height(), /*"float":"left",*/"cursor":"text"})
					.attr({"contenteditable":true})
					//.html(el.val())
					.bind("keyup", function () {
						var v = dv.html();
						he.val(dv.html());
					})
					;//.insertAfter(el);

			++i;
			//el.remove();
		});
	},

    expandable: function () {
        var i = 1;
        return $(this).each(function () {
            var el = $(this), name = el.attr('name'), format = el.attr('format') || false, id = el.attr('id') || false;

            el.attr({
                'contenteditable': true
            });

            var he = $('<input />').attr({
                'type': 'hidden',
                'name': name
            });
            if (format) he.attr('format', format);
            if (id) he.attr('id', id);

            he.addClass('_expandable').val(el.html()).insertAfter(el);

            el.removeAttr('name').bind('keyup', function () {
                he.val(el.html());
            });
            ++i;
        });
    },

    expand: function () {
        return $(this).each(function (i, o) {
            var el = $(o).attr('type', 'hidden');

            var grow = el.data('orientation') || 'vertical';

            var div = $('<div />')
            .attr('contenteditable', true)
            .addClass('element-expand')
            .html(el.val())
            .insertAfter(el)
            .on('keyup', function (e) {
                el.val(div.html());
            });

            return el;
        });
    },

	// shift+enter for new line, enter for submit
	enter: function (cb) {
		return $(this).each(function (){

			$(this).keyup(function(e){
				if (13 == e.keyCode) {
					if (!e.shiftKey && cb) {
						cb(this);
					}
				}
			});
		});
	},

	fancy: function () {
		return $(this).each(function () {
			var but = $(this);

				but.css({
					"padding": "2px 4px 2px 4px",
					"margin-top": "3px",
					"border": "1px outset #360",
					"background": "#390",
					"color": "#fff",
					"border-radius": "3px",
    				"-moz-border-radius": "3px",
    				"-webkit-border-radius": "3px",
    				"text-shadow": "1px 1px 1px #666"
				});

			but.mouseover(function () {
				but.css({
					"background": "#380"
				});
			}).mouseout(function () {
				but.css({
					"background": "#390"
				});
			}).focus(function () {
				but.mouseover();
				if (but.onfocus) {
					but.onfocus()
				};
			}).blur(function () {
				but.mouseout();
				if (but.onblur) {
					but.onblur();
				}
			});

		});
	},

	light: function () {
		return $(this).each(function () {
			var obj = $(this);

				obj.css({
					"padding": "2px 4px 2px 4px",
					"border": "1px solid #ccc",
					"background": "#fff",
					"border-radius": "3px",
    				"-moz-border-radius": "3px",
    				"-webkit-border-radius": "3px"
				});

			obj.mouseover(function () {
				obj.css({
					"border-color": "#999"
				});
			}).mouseout(function () {
				obj.css({
					"border-color": "#ccc"
				});
			}).focus(function () {
				obj.mouseover();
				if (obj.onfocus) {
					obj.onfocus()
				};
			}).blur(function () {
				obj.mouseout();
				if (obj.onblur) {
					obj.onblur();
				}
			});

		});
	},

	blind: function (clr) {
		return $(this).each(function () {
			$(this).unblind();

			var cover = $('<div class="_blind_cover_" />').css({
				"background": clr || "#fff",
				"opacity": "0.8",
				"filter": "alpha(opacity=80)",
				"position": "absolute",
				"width": $(this).width(),
				"height": $(this).height()
			});
			$(this).css("position","relative");
			cover.insertBefore($(this).children()[0]);
		});
	},

	unblind: function () {
		return $(this).each(function () {
			$(this).find("._blind_cover_").remove();
		});
	},

    // custom onblur event handler for browsers that do not
    // support onblur, especially for anchor tags
    // example: $('.menu-trigger').blurhandler();
    blurhandler: function () {
        var callback_delay = arguments[0] || 200;
        $(this).each(function () {
            var e = $(this),
                clk = e.attr('onclick'),
                blr = e.attr('onblur'),
                but = $('<button type="button"></button>').css(
                    {
                    'position':'absolute',
                    'left':'-99999px'
                    }).insertAfter($(this));

            e.click(function () {
                but.focus();
                if (clk) {
                    clk();
                }
            });

//             but.focus(function () {
//                 if (clk) {
//                     clk();
//                 }
//             })
            but.blur(function () {
                if (blr) {
                    setTimeout(function () {
                        blr()
                    }, callback_delay);
                }
            });

            return e;
        });
    },

    // default value tweak
    // example: $('input[type=text]').defaultvalue();
    defaultvalue: function () {
        return $(this).each(function () {
            if ('true'==$(this).attr('keepdefault')) {
                return $(this);
            }

            var el = $(this)[0],
                e = $(el);
            e.focus(function () {
                if (e.val()==el.defaultValue) {
                    e.val('');
                }
            });
            e.blur(function () {
                if (''==e.val()) {
                    e.val(el.defaultValue);
                }
            });

            //return e;
        });
    },

    // form check
    // example: onsubmit="return $(this).check('border:1px solid #f00',function(e){e.hint();});"
    check: function () {
        var style = arguments[0] || '',
            cb = arguments[1] || false,
            elems = $(this)[0].elements,
            valid = true;

        $(elems).each(function (k, e) {
			e = elems[k];
			$(e).validate(style, function () {
				cb($(e));
			});
			$(e).focus().blur();
			if ('true' != $(e).attr('valid')) {
				$(e).blur();
				valid = false;
			}
			//console.log($(e).attr('valid'));
        });

        if (!valid) return false;
        return $(this).serializeArray();
    },

    // fill form with data
    fill: function (data) {
    	if (!$(this).is('form')) return;

		for (var i=0; i<data.length; ++i)	{
			$(this).find('[name='+data[i].name+']').val(data[i].value);
		}
		return $(this);
    },

    // convert a form to an object map of key => value pairs
    // this method provides only a linear map and cannot include
    // multi-value inputs
    objectmap: function () {
    	if (!$(this).is('form')) return;

    	var i,
    		el,
    		obj = {},
    		elems = $(this)[0].elements;

    	for (i=0; i<elems.length; ++i) {
    		el = $(elems[i]);
    		if ("radio" == el.attr("type") && !el.is(":checked")) continue;
    		if (el.attr('name')) {
    			obj[el.attr('name')] = el.val();
    		}
    	}
    	return obj;
    },

    // individual input validation with the role attribute
    // example: $('input, select, textarea').validate('border:1px solid #f00', function (e) {e.hint();});
    validate: function () {
    	// input is valid if a format or a role is not specified
        //if (!$(this).attr('format') && !$(this).attr('role')) return true;

        // data types regex map for validation
        $.dataFormats = $.dataFormats || {
            keyword: {rule:/\w+/,hint:'One or more words separated by spaces'},
            number: {rule:/^\d+$/,hint:'Numeric digits (0-9)'},
            email: {rule:/^(.+?)\@(.+?)\.(.+?)$/,hint:'Email address containing username, domain and TLD (.com, .net, .org, etc)'},
            phone: {rule:/^\d{10,20}$/,hint:'A valid phone number must be at least 10 digits and at most 20, strictly numeric digits only'},
            word: {rule:/^\w+$/,hint:'Only one word is required, with no special characters'},
            password: {rule:/.+/,hint:'As long as the field is not empty, it is a password'},
            url: {rule:/http[s]:\/\/.+\..+$/,hint:'Website or webpage address. It must begin with http:// or https:// and end with a TLD (.com, .net, .org, etc)'},
            file: {rule:/.+\..+$/,hint:'File name must end with .[ext], where "ext" is the extention of the file'},
            custom: {hint:'Value must match the specified data format above'}
        };

        var style = arguments[0] || '',
            cb = arguments[1] || null,
            hc = '<div style="border-bottom:1px dotted #ccc;margin-bottom:3px"><b>{role}</b> "{rule}"</div>{hint}';
        //# end of global input vars

        $(this).each(function () {
            var e = $(this), st = e.attr('style')||'', role = e.attr('role'),
                fmt = e.attr('format') ? new RegExp(e.attr('format')) : null;

        	// validation is enabled only when the format and role attributes are present
            // input is valid if a format or a role is not specified
        	if (!fmt && !role) {
        		e.attr('valid','true');
        		return true;
        	}

            // even if validation is enabled, it can be initialized only once
            if ($(this).attr('valid')) return;
            // let all elements be initially invalid
            e.attr('valid','false');

            e.blur(function () {
                var v = $(this).val(),
                    valid =
                        fmt ? fmt.test(v) :
                        $.dataFormats[role] ? $.dataFormats[role].rule.test(v) :
                        false,
                    s = $.dataFormats[role] ? $.dataFormats[role].hint : '',
                    h = fmt ?
                        hc.replace('{role}','custom')
                        .replace('{rule}',fmt)
                        .replace('{hint}',$.dataFormats.custom.hint) :
                        hc.replace('{role}',role)
                        .replace('{rule}',$.dataFormats[role].rule)
                        .replace('{hint}',s);

                $(this).attr({'valid':'true','style':st,'hint':''});

                if (!valid || (!$(this).attr('keepdefault') && $(this).val()==$(this)[0].defaultValue)) {
                    $(this).attr({'valid':'false','style':(st ? st + ';'+style : style),'hint':h});
                    if (cb) cb($(this));
                }

            });
        });
    },

    // hint/tooltip/helper
    // example:
    //-  $('a').each(function () {
    //-      $(this).mouseover(function () {
    //-          $(this).hint();
    //-      });
    //- });
    hint: function () {
    	var opt = arguments[0]||{};
        if (!$.hinter) {
            $.hinter =
                $('<div></div>')
                .css({
                    'background': opt.background||'#fff',
                    'position': 'absolute',
                    'top': $.mouse.y,
                    'left': $.mouse.x,
                    'padding': '4px',
                    'width': opt.width||'200px',
                    'text-align': 'left',
                    'line-height': '14px',
                    'font-size': opt.fontSize||'7.5pt',
                    'display': 'none',
                    'z-index': '99999999',
                    '-moz-border-radius': '5px',
                    '-webkit-border-radius': '5px',
                    'border': '4px solid rgba(0,0,0,0.4)',
                    'background-clip': 'padding-box',
                    '-moz-background-clip': 'padding',
                    '-webkit-background-clip': 'padding'
                }).appendTo($('body'));
        }

        $(this).mousemove(function () {
            if (!$(this).attr('hint')) return $(this);
            $.hinter.html($(this).attr('hint'));
            if ('none'==$.hinter.css('display')) {
                $.hinter.css('display','block');
            }
            $.hinter.css({
                'top': ($.mouse.y+$.hinter.attr('clientHeight')-20),
                'left': $.mouse.x+10
            });
        }).mouseout(function () {
            $.hinter.css('display','none');
        }).blur(function () {
            $(this).mouseout();
        });
        var el = $(this);
        // press escape to close hint
        $(document).keydown(function (e) {
            if (e.keyCode == 27) {
                el.mouseout();
            }
        });
    },

    // convert text inputs to password type
    // example: $('input[type=password]').topassword();
    topassword: function () {
        return $(this).each(function (i, e) {
            if ('password'!=$(e).attr('type')) return false;
            e.type = 'text';
            $(e).keyup(function () {
                e.type = e.value?'password':'text';
            })/*
            .keydown(function () {
                $(e).keyup();
            })*/;
        });
    },

    // custom file chooser
    // example: $('#file_1').fileinput()
    fileinput: function () {
        $(this).css({
        	'position':'absolute',
        	'right':'0px',
        	'opacity':'0',
        	'filter':'alpha(opacity=0)',
        	'top':'0px'
        	}
        );

        var label = $(this).attr('label') || 'Choose File',
            klass = $(this).attr('class') || 'submit',
            div = $('<div style="position:relative"></div>'),
            but = $('<button type="button" class="'+klass+'">'+label+'</button>'),
            inp = $('<input type="text" class="txt" readonly="readonly" value="" />');

        div.css({'width':'210px'});

        if ($(this).attr('role')) {
        	inp.attr('role',$(this).attr('role'));
        	$(this).blur(function(){inp.blur});
        }
        if ($(this).attr('format')) {
        	inp.attr('format',$(this).attr('format'));
        	$(this).blur(function(){inp.blur});
        }

        $(this).wrap(div);
        inp.insertBefore($(this));
        but.insertAfter(inp);

        but.css({'position':'absolute','right':'-1px'});
        inp.css({'border':'1px inset #ccc'});

        $(this).change(function () {
            //inp.val($(this).val());
            var v = $(this).val();
            v = 13 >= v.length ? v : '...'+v.substring(v.length-13);
            inp.val(v);
        });
    },

    filebrowser: function ()
    {
        $(this).each(function () {
            var el = $(this), data = el.data(), label = data.label || 'Browse file...';
            el.hide();

            var helper = $('<a/>').text(label).attr('href', '').on('click', function (e) {
                e.preventDefault();
                el.trigger('click');
            });

            el.on('change', function () {
                var v = $(this).val();
                var chunks = v.split(/\\|\//);
                if (chunks.length) v = chunks[chunks.length-1];
                helper.text(v || label);
            });

            helper.insertAfter(el);
        });
    },

    /*fileupload: function () {
    	var e = $(this),
    		dv = $("<span />").insertBefore(e).css({
    			"overflow": "hidden",
    			"position": "relative"
    		}),
    		btn = $("<button />").attr({
    			"type": "button"
    		}).text("Choose File...").appendTo(dv),
	    	tx = $("<span />").insertAfter(btn);

    	e.attr({
    		"size": 1
    	}).css({
    		"position": "absolute",
    		"left": 0,
    		"top": 0,
    		"opacity": 0,
    		"cursor":" pointer"
    	}).bind("change", function () {
    		var v = e.val().split(/\\/);
    		v = v.length ? v[v.length-1] : v;
    		tx.text(v);
    		//e.attr({"size":(btn.width()/6)});
    	}).insertBefore(btn);
    },*/

    noempty: function (exp) {
    	var e, o, obj={}, fm=this, valid=true;

    	if (!$(this).is("form")) {
    		alert("This method applies to forms only");
    		return false;
    	}

    	$(fm).find(exp).each(function(){
			o = $(this), e = o[0];
			if ("true"!==o.attr("ignore")) {
				if (!o.val() || (e.defaultValue == o.val())) {
					o.removeClass('valid').addClass("invalid has-error");
					valid = false;
				}
				else {
                    o.removeClass("invalid has-error").addClass('valid');
				}
			}
			obj[e.name] = o.val();
		});

		if (!valid) {
            $(fm).removeClass('valid').addClass('invalid');
			return false;
		}
		else {
            $(fm).removeClass('invalid').addClass('valid');
			fm.object = obj;
			return fm;
		}
    },

    checkformat: function (exp) {
    	var valid = true;

    	$(this).find(exp).each(function(i, el){
    		if ($(el).attr("format")) {
	    		var v=$(el).val(), fmt=new RegExp($(el).attr('format'));

				if (!fmt.test(v)) {
					//$(el).attr("class","invalid");
					$(el).addClass("invalid");
					valid = false;
				}
				else {
					//$(el).removeAttr("class");
					$(el).removeClass("invalid");
					//valid = true;
				}
			}
		});
		if (!valid) {
			return false;
		}
		return true;
    },

    requireFormValidation: function ()
    {
        var testDataFormat = function (value, format)
        {
            var rule = $.dataFormats[format] && $.dataFormats[format].rule;
            return (rule || (new RegExp(format)) || /\.+/).test(value);
        };

        /*var getInputHint = function (rule)
        {
            if ($.dataFormats[rule]) {
                return $.dataFormats[rule].hint;
            }
            return false;
        };*/

        var validateInput = function (el, valid)
        {
            if ($(el).data('format')) {
                var value = $(el).val(), format = $(el).data('format');//, hint = getInputHint(format);

                if (!testDataFormat(value, format)) {
                    $(el).removeClass('valid').addClass('invalid');
                    /*if (hint) {
                        $(el).tooltip(hint);
                    }*/
                    valid = false;
                }
                else {
                    $(el).removeClass('invalid').addClass('valid');
                }
            }

            return valid;
        };

        var validateForm = function (fm)
        {
            var valid = true;
            var els = $(fm).find('[data-format]');

            if (els.length) {
                els.each(function(i, el) {
                    valid = validateInput(el, valid);
                });

                els.on('change', function () {
                    valid = validateInput(this);
                });
            }

            console.log(valid);

            return valid;
        };

        $(this).find('form').each(function (i, fm) {
            $(fm).on('submit', function (e) {
                return validateForm($(fm));
            });
        });
    },

    tooltip: function (str) {
        $.hinter = $.hinter || $('<div></div>').css({
            top: $.mouse.y,
            left: $.mouse.x
        }).appendTo($('body'));

        if (!$.mouse.initialized) $.mouse.init();

        $(this).mousemove(function () {
            var hint = str || $(this).data('hint') || null;
            if (!hint) return;

            $.hinter.html(hint);
            console.log($.hinter);

            if (!$.hinter.hasClass('shown')) {
                $.hinter.addClass('shown');
            }

            $.hinter.css({
                top: ($.mouse.y + $.hinter.attr('clientHeight') - 20),
                left: $.mouse.x + 10
            });
        }).mouseleave(function () {
            $.hinter.removeClass('shown');
        });
        /*.blur(function () {
            $(this).mouseout();
        });*/

        // press escape to close hint
        var el = $(this);
        $(document).keydown(function (e) {
            if (e.keyCode == 27) {
                el.mouseout();
            }
        });
    }

});