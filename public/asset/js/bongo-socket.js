/*
 * Bongo live updates: connects to `php bongo websocket:serve`.
 *
 *   <script src="{{ asset('asset/js/bongo-socket.js') }}"></script>
 *   <script>
 *     const socket = BongoSocket.connect('{{ websocket_url() }}');
 *     socket.on('orders', 'order.created', data => console.log('New order', data.id));
 *     socket.subscribe('private-user.5', '{{ channel_token("private-user.5") }}');
 *   </script>
 *
 * Reconnects by itself (and subscribes again) when the server restarts.
 */
(function (global) {
    'use strict';

    function connect(url) {
        var socket = null;
        var channels = {};      // channel -> token ('' for public channels)
        var handlers = [];      // {channel, event, callback}
        var delay = 1000;
        var closed = false;

        function send(message) {
            if (socket && socket.readyState === 1) socket.send(JSON.stringify(message));
        }

        function open() {
            socket = new WebSocket(url);
            socket.onopen = function () {
                delay = 1000;
                Object.keys(channels).forEach(function (channel) {
                    send({ subscribe: channel, token: channels[channel] });
                });
            };
            socket.onmessage = function (message) {
                var data;
                try { data = JSON.parse(message.data); } catch (e) { return; }
                if (data.error) { console.warn('[BongoSocket]', data.error); return; }
                handlers.forEach(function (h) {
                    if (h.channel === data.channel && (h.event === '*' || h.event === data.event)) {
                        h.callback(data.data, data.event);
                    }
                });
            };
            socket.onclose = function () {
                if (closed) return;
                setTimeout(open, delay);
                delay = Math.min(delay * 2, 30000);
            };
        }

        var api = {
            /** Listen to a channel (token only for private- channels) */
            subscribe: function (channel, token) {
                channels[channel] = token || '';
                send({ subscribe: channel, token: token || '' });
                return api;
            },
            unsubscribe: function (channel) {
                delete channels[channel];
                handlers = handlers.filter(function (h) { return h.channel !== channel; });
                send({ unsubscribe: channel });
                return api;
            },
            /** on(channel, event, callback): event '*' gets every event of the channel */
            on: function (channel, event, callback) {
                if (!(channel in channels)) api.subscribe(channel);
                handlers.push({ channel: channel, event: event, callback: callback });
                return api;
            },
            close: function () {
                closed = true;
                if (socket) socket.close();
            }
        };

        open();
        return api;
    }

    global.BongoSocket = { connect: connect };
})(window);
