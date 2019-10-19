//////////////////////////////////////////////////////////////////////////
//	Ajax plugin for f8
//////////////////////////////////////////////////////////////////////////
//
// 	data-provider="Speed.HTTP.GetIpAddress"
// 	data-endpoint="{href}.post.json"
// 	data-receiver="#content-{id}"
// 	data-event="click"
//
// 	provider => required
// 	endpoint 	=>	optional [path.html -> path.post.json]
// 	receiver 	=>	optional [self]
// 	event 		=>	optional [click]
//
//////////////////////////////////////////////////////////////////////////

;(function ($) {

	'use strict';

	var immediates = {
		collection: [],
		loaded: []
	};

	var datasets = {};
	var receivers = {};

	// =======================================================================================================
	var is_input = function is_input (el) {
		var inputs = 'input select textarea button[type!=button]'.split(' ');
		//return inputs.filter((i) => console.log(i, el.prop('tagName').toLowerCase(), el.is(i)));
		return 0 < inputs.filter((i) => el.is(i)).length;
	};

	// =======================================================================================================
	var get_data_key = function get_data_key (data) {
		if (undefined == data.endpoint || undefined == data.provider) return null;
		return data.endpoint+'/'+data.provider;
	};

	// =======================================================================================================
	var get_dataset = function get_dataset (key) {
		return datasets[key] || null;
	};

	// =======================================================================================================
	var put_dataset = function put_dataset (data, bundle) {
		var data_key = get_data_key(data);

		if (data_key) {
			datasets[get_data_key(data)] = bundle;
		}
	};

	var set_receiver_props = function set_receiver_props (el, data_key) {
		if (undefined == el.data('source')) el.attr('data-source', data_key);
		if (undefined == el.data('node')) el.attr('data-node', '');

		return el;
	};

	/*var hide_attributes_data = function hide_attributes_data () {
		$('[data-provider], [data-node]').each(function () {			
			var data = $(this).data();
			$(this).removeAttr('data-node data-source data-endpoint data-receiver data-provider data-event').data(data);
			
			if (undefined != data.provider) $(this).addClass('data-provider');
			if (undefined != data.node) $(this).addClass('data-receiver');
		});
	};*/

	// =======================================================================================================
	/*var parse_string_tokens = function parse_string_tokens (str, vars) {
		return str.parse(/@(\w+)/gim, vars);
	};*/

	// =======================================================================================================
	var find_receivers_by_association = function find_receivers_by_association (el, data_key) {
		var receivers = [];

		$('[data-source]').each(function () {
			var $this = set_receiver_props($(this), data_key);
			var d = $(this).data();

			// Check if the data-source attribute is actually a CSS selector
			// that points to the element sending the request.
			// We need a try -> catch wrap to suppress any errors arising 
			// from an invalid CSS selector, which is inevitable.
			try {
				if ($(d.source).is($(el))) {
					$this.attr('data-source', data_key);
					receivers.push($this);
				}
			}

			// We have a match if the data-source attribute is the same as the
			// given data_key. This only happens when the selector search fails.
			catch (e) {
				if (d.source == data_key) {
					receivers.push($this);
				}
			}
		});

		return receivers;
	};

	// =======================================================================================================
	var init_receivers = function init_receivers (el, data) {
		var rcvs = [], rcv_selector = (undefined != el && $(el).data('receiver'));
		//var rcvs = ((rcv && $(rcv).length) ? $(rcv) : $(el)).toArray();

		var data_key = get_data_key($(el).data());

		if (data_key) {

			if (rcv_selector) {
				$(rcv_selector).each((i, e) => rcvs.push(set_receiver_props($(e), data_key)));
				/*$(rcv_selector).each( function () {
					if (undefined == $(this).data('source')) $(this).attr('data-source', data_key);
					if (undefined == $(this).data('node')) $(this).attr('data-node', '');
					rcvs.push($(this));
				});*/
			}
			else rcvs.push(set_receiver_props($(el), data_key));

			console.log(data_key, rcvs);

			receivers[data_key] = rcvs.concat(find_receivers_by_association(el, data_key));
		}

		//console.log(receivers);
	};	

	// =======================================================================================================
	var get_receivers = function get_receivers (el) {
		var data = el.data(), rcvs = receivers[get_data_key($(el).data())];
		
		//console.log(rcvs);
		//return rcvs.filter((r) => console.log(el.data('event'), r.data('event')));
		//return rcvs.filter((r) => el.data('event') == r.data('event'));
		//return rcvs.filter((r) => (undefined == r.data('event') || 'immediate' == r.data('event') ));
		//return rcvs.filter((r) => (undefined == r.data('event')));
		return rcvs;
	};	

	// =======================================================================================================
	var slice_data_rows = function slice_data_rows (rows, data) {
		var len = (undefined != rows.length && rows.length) || 0, start = 0;

		if (undefined != data.rows && data.rows) {
			var r = data.rows.toString().split(',', 2);
			if (2 == r.length) {
				len = Number(r[1]);
				start = Number(r[0]);
			}
			else if (1 == r.length) len = Number(r[0]);
		}

		return rows.slice(start, len + start);
	};

	// =======================================================================================================
	var notify_receivers = function notify_receivers (receivers, response, change, request) {
		$(receivers).each(function () {
			if ('immediate' == request.event) immediates.loaded.push($(this));

			$(this).trigger('received.active_data', [response, change, request]);
		});
	};

	// =======================================================================================================
	var on_receive = function on_receive (el, response, request) {
		var change = {
			value: (undefined != el.data('type') && el.val().trim())
		};

		notify_receivers(get_receivers(el), response, change, request);

		if ('immediate' == request.event) {
			if (immediates.collection.length == immediates.loaded.length) {
				$(document).trigger('immediates_loaded.active_data', [immediates]);
			}
		}
	};

	// =======================================================================================================
	var parse_data_row = function parse_data_row (row, el) {
		var snippet = el.clone(true).insertAfter(el).trigger('mounted.active_data', row);
		snippet[0].outerHTML = el[0].outerHTML.parse(/@(\w+)/gim, row);
	};

	// =======================================================================================================
	var parse_receiver = function parse_receiver (el, rows, change, request) {

		var data = el.data();
		//el.removeAttr('data-node data-rows data-endpoint data-receiver data-provider data-event').data(data);

		rows = slice_data_rows(rows.reverse(), data);

		for (var i in rows) {
			parse_data_row(rows[i], el);
		}

		el.remove();
	};

	// =======================================================================================================
	var send_request = function send_request (e, data) {

		var 
			$this = $(this),
			ep = data.endpoint.split(/\./g).reverse(),
			format = ep[0], method = ep[1];

		// Prevent clickthrough if required data properties
		// for AJAX are present (indication of intent)
		if (format && method && 'undefined' !== typeof data.provider) {
			if (e) e.preventDefault();
		}
		else return;

		// In case there is a cache for this request, process from cache rather
		// than making another request. You know there is a cache when you have
		// the same data_key for both requests.
		// Format of the data_key is such that requests made to same endpoing
		// for same data provider would always be the same. 
		var cache = get_dataset(get_data_key(data));

		if (cache) {
			on_receive($this, cache, data, null);	
			return;		
		}

		return undefined != $[method] && $[method].call($this, data.endpoint, Object.sort(data), function (response, request, xhr) {
			put_dataset(data, response);

			on_receive($this, response, data, xhr);
		}, format || 'json');
	};

	// =======================================================================================================
	var ajax_object = function ajax_object (el, provider, opts) {
		opts = ('object' == typeof opts && opts) || {};

		var endpoint = (undefined != typeof opts.endpoint && opts.endpoint)
			|| window.location.pathname.toString().replace(/\.\w+$/, '.post.json');

		var event = (undefined != opts.event && opts.event) || 'immediate';

		//$(el).addClass('event-'+event);

		var data = $.extend(opts, {
			event: event,
			endpoint: endpoint,
			provider: provider
		});

		var type = el.prop('type');

		if (type && 'input select textarea'.split(' ').indexOf(type)) {
			data.value = el.val().trim();
			el.data('type', type);
		}

		init_receivers(el, data);
		//hide_attributes_data();

		console.log(el.data());
		//console.log(el);

		/*$(el).each(function () {
			var dat = $(this).data();
			$(this).removeAttr('data-endpoint data-receiver data-provider data-event').data(dat);
		})*/
		//el.removeAttr('data-endpoint data-receiver data-provider data-event').data(dat);
		el.on(event, data, function (e) {
			send_request.call(this, e, data);
		});

		if ('immediate' === event) {	
			//console.log(data);

			el.each(function () {
				//$(this).removeAttr('data-endpoint data-receiver data-provider data-event').data(data);
				immediates.collection.push($(this));
				//send_request.call(this, null, data);
			})
			.trigger('immediate', data);
		}

		return el;
	};

	// =======================================================================================================
	// Shadow all AJAX properties (set in DOM and hide from view)
	/*$('[data-provider]').each(function () {
		return new ajax_object($(this), $(this).data('provider'), $(this).data());
	});

	// Make all elements capable of making AJAX requests
	$.extend($.fn, {
		active_data: function (provider, opts) {
			opts.provider = provider;
			$.each(opts, (k, v) => $(this).attr('data-'+k, v));

			return new ajax_object($(this), provider, opts);
		}
	});*/

	// Prevent envent propagation
	$('[data-node]').on('received.active_data', function (e, response, change, request) {
		e.stopPropagation();

		var el = $(this), data = el.data();
		var result = Object.value_of(response, data.node);

		if ('array' !== $.type(result)) result = [result];
		
		// If no event is defined for this receiver, process it immediately 
		// data is received from its provider.
		if (undefined == data.event) {
			parse_receiver(el, result, change, request);
		}

		// Otherwise wait until the event defined for the reciver to be triggered
		// before processing it.
		else {
			el.on(data.event, () => parse_receiver(el, result, change, request));
		}
	});






	// 1. 
	// Find all data providers and initialize them.
	// Thus; retrieve all required data attributes and store them on the elements 
	// in a data object. In cases where initialixation is done using Javascript,
	// set data object on the elements from the opts argument passed.
	// 
	// After that, add the data-provider class and removed all custom data 
	// attributes to cleanup the element.
	// 
	$.extend($.fn, {
		active_data: function (provider, opts) {
			opts.provider = provider;
			$.each(opts, (k, v) => $(this).attr('data-'+k, v));

			return new ajax_object($(this), provider, opts);
		}
	});
	$('[data-provider]').each(function () {
		return new ajax_object($(this), $(this).data('provider'), $(this).data());
	});

})(jQuery);



// testing

// $('*').on('received.active_data', function (e, data) {
/*$('.auto_ajax_receiver')
.on('received.active_data', function (e, response, change, request) {
	//$(this).find('a[href]').text(response.status.message +'.. '+ response.data);
	//console.log(this, e, response);
	//console.log(response, change);
	//console.log('HTML version called');
});

$('.live_input')
.on('received.active_data', function (e, response, change, request) {
	//$(this).find('a[href]').text(response.status.message +'.. '+ response.data);
	//console.log(this, e, response, request, change);
	//console.log(response, change);
	//console.log('INPUT version called');
});*/

$('.manual_ajax').active_data('Speed.HTTP.GetIpAddress', {
	endpoint: 'test.post.json',
	event: 'mouseleave'//,
	//source: 'test.post.json/Speed.HTTP.GetIpAddress',
	//node: 'data'
});
/*.on('received.active_data', function (e, response, change, request) {
	//$(this).text(response.status.message);
	//console.log(this, e, response);
	console.log(response, change);
	//console.log('JS version called');
});*/

// $('*').on('received.active_data', function (e, response) {
// 	$(this).text(response.status.message +'.. '+ response.data);
// });

/*$('.event-immediate').on('loaded.active_data', function (e, response) {
	//this.fade();
	//console.log(this);
});*/

$(document).on('immediates_loaded.active_data', function (e, els) {
	//console.log(els);
	//console.log('all immediates loaded');
	//$('body')[0].fade();
});