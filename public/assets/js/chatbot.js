// Chatbot Beranda -> Node.js server (chatbot-server) -> Nous Research API + Smart Engine.
(function () {
    'use strict';

    function init() {
        var host = document.getElementById('nousChatbot');
        if (!host || host.dataset.inited) return;
        host.dataset.inited = '1';

        var API = host.dataset.api || 'http://localhost:3001/api/chat';
        var MAX = 500;
        var STORE = 'nbHistory';
        var TTL_MS = 24 * 60 * 60 * 1000; // 24 Jam (1 Hari)
        var DEFAULT_SUGGESTIONS = [
            'Berapa harga laundry kiloan? 🧺',
            'Bagaimana cara pesan antar-jemput? 🛵',
            'Berapa ongkos kirimnya? 📍',
            'Metode pembayaran apa saja? 💳',
            'Cuci Bedcover & Jas Satuan 🛏️',
            'Berapa lama laundry selesai? ⏱️'
        ];

        var history = [];
        try {
            var rawStore = localStorage.getItem(STORE);
            if (rawStore) {
                var parsed = JSON.parse(rawStore);
                if (parsed && parsed.timestamp && Array.isArray(parsed.data)) {
                    if (Date.now() - parsed.timestamp < TTL_MS) {
                        history = parsed.data;
                    } else {
                        localStorage.removeItem(STORE);
                        history = [];
                    }
                } else if (Array.isArray(parsed)) {
                    history = parsed;
                }
            }
        } catch (e) { history = []; }
        if (!Array.isArray(history)) history = [];

        var busy = false, cooling = false, timer = null;

        function el(tag, cls, text) {
            var n = document.createElement(tag);
            if (cls) n.className = cls;
            if (text !== undefined) n.textContent = text;
            return n;
        }

        // Safe Markdown Parser (XSS Protected)
        function formatMarkdown(raw) {
            if (!raw) return '';
            // 1. Escape HTML
            var div = document.createElement('div');
            div.textContent = raw;
            var escaped = div.innerHTML;

            // 2. Bold: **text**
            var html = escaped.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            // 3. Italic: *text*
            html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
            // 4. Line breaks
            html = html.replace(/\n/g, '<br>');
            return html;
        }

        // ----- DOM Elements -----
        var root = el('div', 'nb-root');

        var panel = el('div', 'nb-panel');
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', 'Chatbot Asisten Laundry');

        var header = el('div', 'nb-header');
        var titleWrap = el('div');
        var headerTitle = el('div', 'nb-header__title');
        headerTitle.innerHTML = '<span>🧺</span> Asisten Laundry';
        titleWrap.appendChild(headerTitle);
        titleWrap.appendChild(el('div', 'nb-header__sub', 'Tanya layanan, tarif kiloan & antar-jemput'));
        var headerBtns = el('div');
        var resetBtn = el('button', '', '\u21BA');
        resetBtn.type = 'button'; resetBtn.title = 'Mulai Percakapan Baru'; resetBtn.setAttribute('aria-label', 'Percakapan baru');
        var closeBtn = el('button', '', '\u2715');
        closeBtn.type = 'button'; closeBtn.title = 'Tutup'; closeBtn.setAttribute('aria-label', 'Tutup');
        headerBtns.appendChild(resetBtn); headerBtns.appendChild(closeBtn);
        header.appendChild(titleWrap); header.appendChild(headerBtns);

        var list = el('div', 'nb-messages');
        list.setAttribute('aria-live', 'polite');

        // Chips Section (Persistent & Scrollable)
        var chipsWrap = el('div', 'nb-chips-wrapper');
        var chipsLabel = el('div', 'nb-chips-label', '💡 Pertanyaan Populer:');
        chipsWrap.appendChild(chipsLabel);
        var chips = el('div', 'nb-chips');
        chipsWrap.appendChild(chips);

        function renderChips(items) {
            chips.innerHTML = '';
            var listItems = (items && items.length) ? items : DEFAULT_SUGGESTIONS;
            listItems.forEach(function (s) {
                var c = el('button', 'nb-chip', s);
                c.type = 'button';
                c.addEventListener('click', function () {
                    send(s.replace(/[\u{1F300}-\u{1F6FF}]/gu, '').trim());
                });
                chips.appendChild(c);
            });
        }
        renderChips(DEFAULT_SUGGESTIONS);

        var form = el('form', 'nb-form');
        form.setAttribute('autocomplete', 'off');
        var input = el('textarea', 'nb-input');
        input.rows = 1; input.maxLength = MAX; input.placeholder = 'Tanya harga, cara pesan, estimasi...';
        input.setAttribute('aria-label', 'Pertanyaan');
        var sendBtn = el('button', 'nb-send');
        sendBtn.innerHTML = '➤';
        sendBtn.type = 'submit'; sendBtn.setAttribute('aria-label', 'Kirim');
        form.appendChild(input); form.appendChild(sendBtn);

        var foot = el('div', 'nb-foot');
        foot.appendChild(el('span', '', 'Layanan Resmi Marketplace Laundry'));
        var counter = el('span', '', '0/' + MAX);
        foot.appendChild(counter);

        panel.appendChild(header);
        panel.appendChild(list);
        panel.appendChild(chipsWrap);
        panel.appendChild(form);
        panel.appendChild(foot);

        var launcher = el('button', 'nb-launcher');
        launcher.type = 'button';
        launcher.setAttribute('aria-label', 'Buka chatbot');
        launcher.setAttribute('aria-expanded', 'false');
        var icon = el('i', 'bi bi-chat-dots-fill');
        launcher.appendChild(icon);

        root.appendChild(panel); root.appendChild(launcher);
        host.appendChild(root);

        // Fallback icon jika Bootstrap Icons belum termuat
        setTimeout(function () {
            var content = window.getComputedStyle(icon, '::before').getPropertyValue('content');
            if (!content || content === 'none' || content === 'normal' || content === '""') icon.textContent = '💬';
        }, 800);

        // ----- Helpers -----
        function scrollDown() {
            setTimeout(function () {
                list.scrollTop = list.scrollHeight;
            }, 50);
        }

        function renderAttachmentCard(attachment) {
            if (!attachment || typeof attachment !== 'object') return null;
            var card = el('div', 'nb-card-attachment border rounded p-2 my-2 bg-light text-dark');
            card.style.maxWidth = '300px';

            if (attachment.type === 'qris') {
                var title = el('div', 'fw-bold mb-1', '🏷️ QRIS Pembayaran #' + (attachment.order_number || ''));
                card.appendChild(title);
                var amt = el('div', 'fs-6 fw-bold text-success mb-2', attachment.amount || '');
                card.appendChild(amt);
                if (attachment.qris_url) {
                    var img = el('img', 'img-fluid rounded border mb-2 d-block mx-auto');
                    img.src = attachment.qris_url;
                    img.alt = 'QRIS Code';
                    img.style.maxWidth = '180px';
                    card.appendChild(img);
                }
                var link = el('a', 'btn btn-sm btn-primary w-100 rounded-pill mt-1', 'Buka Halaman Pembayaran');
                link.href = attachment.pay_page || '#';
                card.appendChild(link);
            } else if (attachment.type === 'maps') {
                var mTitle = el('div', 'fw-bold mb-1', '📍 Lokasi Pelanggan #' + (attachment.order_number || ''));
                card.appendChild(mTitle);
                var addr = el('div', 'small mb-2', attachment.address || '-');
                card.appendChild(addr);
                if (attachment.navigate_url) {
                    var nav = el('a', 'btn btn-sm btn-success w-100 rounded-pill mb-1 text-center', 'Mulai Navigasi');
                    nav.href = attachment.navigate_url; nav.target = '_blank'; nav.rel = 'noopener noreferrer';
                    card.appendChild(nav);
                }
                if (attachment.view_url) {
                    var map = el('a', 'btn btn-sm btn-outline-primary w-100 rounded-pill mb-1 text-center', 'Buka Google Maps');
                    map.href = attachment.view_url; map.target = '_blank'; map.rel = 'noopener noreferrer';
                    card.appendChild(map);
                }
            }

            return card;
        }

        function addMsg(role, text, isError, attachment) {
            var wrap = el('div', 'nb-msg nb-msg--' + role + (isError ? ' nb-msg--error' : ''));
            var bubble = el('div', 'nb-bubble');
            if (role === 'bot') {
                bubble.innerHTML = formatMarkdown(text);
                if (attachment) {
                    var card = renderAttachmentCard(attachment);
                    if (card) bubble.appendChild(card);
                }
            } else {
                bubble.textContent = text;
            }
            wrap.appendChild(bubble);
            list.appendChild(wrap);
            scrollDown();
            return wrap;
        }

        function addTyping() {
            var wrap = el('div', 'nb-msg nb-msg--bot nb-typing');
            var b = el('div', 'nb-bubble');
            for (var i = 0; i < 3; i++) b.appendChild(el('span', 'nb-dot'));
            wrap.appendChild(b);
            list.appendChild(wrap);
            scrollDown();
            return wrap;
        }

        function persist() {
            history = history.slice(-12);
            try {
                if (history.length === 0) {
                    localStorage.removeItem(STORE);
                } else {
                    localStorage.setItem(STORE, JSON.stringify({
                        timestamp: Date.now(),
                        data: history
                    }));
                }
            } catch (e) { /* abaikan */ }
        }

        function refresh() {
            sendBtn.disabled = busy || cooling;
            input.disabled = busy;
        }

        function cooldown(sec) {
            cooling = true; refresh();
            var left = Math.min(parseInt(sec, 10) || 10, 300);
            sendBtn.textContent = left + 's';
            clearInterval(timer);
            timer = setInterval(function () {
                left -= 1;
                if (left <= 0) {
                    clearInterval(timer); cooling = false; sendBtn.innerHTML = '➤'; refresh();
                } else {
                    sendBtn.textContent = left + 's';
                }
            }, 1000);
        }

        function welcome() {
            var welcomeText = "Halo! Selamat datang di **Marketplace Laundry**! 😊🧺✨\n\n"
                + "Saya adalah Asisten Virtual yang siap membantu Anda menjelaskan segala hal seputar layanan kami secara detail:\n\n"
                + "• 🧺 **Tarif Cuci Kiloan**: Reguler Rp 7.000/kg & Express 6 Jam Rp 12.000/kg\n"
                + "• 🛏️ **Cuci Satuan & Dry Clean**: Bedcover, Jas, Sepatu, Helm, Boneka\n"
                + "• 🛵 **Alur Antar-Jemput**: Kurir menjemput cucian langsung ke rumah Anda\n"
                + "• 💳 **Metode Pembayaran**: QRIS instan & Tunai (COD)\n"
                + "• 🛡️ **Garansi 100%**: 1 Mesin 1 Pelanggan & Garansi Cuci Ulang Gratis\n\n"
                + "Silakan ketik pertanyaan Anda atau pilih tombol bantuan di bawah ini!";
            addMsg('bot', welcomeText);
        }

        async function send(text) {
            text = (text || '').trim();
            if (!text || busy || cooling) return;
            if (text.length > MAX) {
                addMsg('bot', 'Pertanyaan terlalu panjang. Maksimal ' + MAX + ' karakter.', true);
                return;
            }

            busy = true; refresh();
            addMsg('user', text);
            input.value = ''; counter.textContent = '0/' + MAX; input.style.height = 'auto';
            var typing = addTyping();

            try {
                var res = await fetch(API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: text, history: history.slice(-6) })
                });
                var data = await res.json().catch(function () { return null; });
                typing.remove();

                if (res.ok && data && data.success) {
                    addMsg('bot', data.reply, false, data.attachment);
                    history.push({ role: 'user', content: text });
                    history.push({ role: 'assistant', content: data.reply });
                    persist();
                    if (data.suggestions && data.suggestions.length) {
                        renderChips(data.suggestions);
                    }
                } else {
                    var msg = (data && data.message) || 'Terjadi kesalahan pada chatbot. Silakan coba lagi.';
                    if (data && data.detail) msg += '\n\n[Detail teknis]\n' + data.detail;
                    addMsg('bot', msg, true);
                    if (data && data.retry_after) cooldown(data.retry_after);
                }
            } catch (err) {
                typing.remove();
                console.warn('[chatbot] Gagal menghubungi endpoint ' + API + '.', err);
                addMsg('bot', 'Maaf, chatbot sedang tidak dapat terhubung. Silakan coba sesaat lagi.', true);
            } finally {
                busy = false; refresh();
                input.focus();
            }
        }

        function setOpen(open) {
            root.classList.toggle('nb-open', open);
            launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
            icon.className = open ? 'bi bi-x-lg' : 'bi bi-chat-dots-fill';
            if (open) { scrollDown(); input.focus(); }
        }

        // ----- Events -----
        launcher.addEventListener('click', function () { setOpen(!root.classList.contains('nb-open')); });
        closeBtn.addEventListener('click', function () { setOpen(false); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });

        form.addEventListener('submit', function (e) { e.preventDefault(); send(input.value); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
                e.preventDefault(); send(input.value);
            }
        });
        input.addEventListener('input', function () {
            counter.textContent = input.value.length + '/' + MAX;
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 120) + 'px';
        });

        resetBtn.addEventListener('click', function () {
            if (busy) return;
            history = []; persist();
            list.innerHTML = '';
            welcome();
            renderChips(DEFAULT_SUGGESTIONS);
        });

        // ----- Initialization -----
        if (history.length) {
            history.forEach(function (m) {
                if (m && typeof m.content === 'string') addMsg(m.role === 'user' ? 'user' : 'bot', m.content);
            });
        } else {
            welcome();
        }
        refresh();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
