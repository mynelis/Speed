;(function ($) {

	'use strict';

	/*var doc = {
		id: function get_id (id) {
			return document.getElementById(id) || false;
		}
	};*/

	var
		// Global registry of chat containers
		containers = {}
		;

	/**
	 *	Create a user profile for a new chat participant
	 *
	 *	@param String name
	 * 	@param String status
	 *	@param String image
	 *	@param Date last_seen
	 */
	var create_profile = function create_profile (name, status, image, last_seen) {
		var info = {
			identity: null,
			name: name.split(/\s+/),
			status: (status || 'offline').toLowerCase(),
			image: image || 'default',
			last_seen: last_seen || (new Date()).toGMTString(),
			typing: false
		};

		return {
			info: info,
			dom: $('<div/>').addClass('chat_participant').data(info),
			container: null
		};
	};

	var typing = function typing (profile, state) {
		profile.info.typing = state;
		profile.container.trigger('typing.chat', [profile, state]);
	};

	/**
	 *	Add ser to a chat using previously created profile
	 *
	 *	@param Object container
	 *	@profile Object user profile
	 */
	var add_chat_participant = function add_chat_participant (container, profile) {
		container.data('participants').push(profile);

		profile.info.identity = container.data('participants').length;
		profile.dom.attr('id', 'user_'+profile.info.identity);
		profile.container = container;

		container.append(profile.dom);
		container.trigger('new_participant.chat', [profile.info]);

		return profile;
	};



	var Chat = function Chat (id, opts) {
		var instance = $('#'+id).addClass('chat_container');
		containers[id] = instance;

		var data = $.extend(opts || {}, {
			participants: [],
			addParticipant: function (profile) {
				return add_chat_participant(instance, profile)
			},
			createUserProfile: create_profile
		});

		return instance.data(data);
	};



	var chat = new Chat('live_chat');

	dom.doc.id('live_chat').fade();//'out', 200);
	//chat[0].fadeOut(200);
	//console.log(dom);












	// Chat Participant as user profile
	/**
	 * Chat Participant as user profile.
	 * When a new participant object is created, passing a profile
	 * data will instantiate the participant as an actual user
	 * having all required properties and methods.
	 *
	 * @param JSON profile User profile fetched from database
	 * @return Object A new chat participant
	 */
	/*var ChatParticipant = function ChatParticipant (profile) {
		var instance = this;

		for (var p in profile) {
			instance[p] = profile[p];
		}

		// Set online status
		instance.setOnlineStatus = function setOnlineStatus (status) {
			instance.online = status;
		};

		return instance;
	};*/

	// Live Chat
	/*var LiveChat = function LiveChat (object_id, opts) {
		var
			container_id = object_id.replace('#', ''), // Unique CSS ID of chat container
			header, // header of chat application
			footer, // footer of chat application
			participants = { // all chat participants. both properties
				// will hold a list of users, each represented by a ChatParticipant object
				offline: [], // participants who are currently offline
				online: [] // participants who are online
			},
			host // User hosting the chat, instance of ChatParticipant
			;

		// Poll for chat participants
		var getChatParticipants = function getChatParticipants () {

		};

	};*/

	/*$.extend($.fn, {
		live_chat: function (opts) {
			opts = $.extend((opts || {}), $(this).data());
			opts.private = (undefined == opts.privacy || 'public' != opts.privacy);
			return new LiveChat($(this).attr('id'), opts);
		}
	});*/

})(jQuery);