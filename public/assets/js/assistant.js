// AI Customer Assistant - client side
// Semua teks ditampilkan dengan textContent (bukan innerHTML) agar aman dari XSS.
(function () {
    'use strict';

    function initAssistant() {
        var root = document.getElementById('aiChat');
        if (!root || root.dataset.inited) return;
        root.dataset.inited = '1';

        var endpoint = root.dataset.endpoint;
        var resetEndpoint = root.dataset.resetEndpoint;
        var csrf = root.dataset.csrf;
        var orderId = root.dataset.orderId ? parseInt(root.dataset.orderId, 10) : null;
        var maxLength = parseInt(root.dataset.maxLength, 10) || 500;

        var list = document.getElementById('aiMessages');
        var form = document.getElementById('aiForm');
        var input = document.getElementById('aiInput');
        var sendBtn = document.getElementById('aiSend');
        var counter = document.getElementById('aiCounter');
        var resetBtn = document.getElementById('aiReset');
        var suggestions = document.getElementById('aiSuggestions');

        // Isolasi Penyimpanan Client-Side per User ID (Cegah kebocoran chat antar akun)
        var currentUserId = parseInt(root.getAttribute('data-user-id'), 10) || 0;
        var LOCAL_STORE_KEY = currentUserId > 0 ? ('laundry_ai_chat_uid_' + currentUserId) : 'laundry_ai_chat_guest';
        var LOCAL_TTL_MS = 24 * 60 * 60 * 1000; // 24 Jam

        // Deteksi pergantian akun login di browser yang sama
        var LAST_ACTIVE_UID_KEY = 'laundry_ai_last_active_uid';
        try {
            var lastUid = localStorage.getItem(LAST_ACTIVE_UID_KEY);
            if (lastUid !== null && lastUid !== String(currentUserId)) {
                // User berganti (misal Santos logout lalu Admin Wildan login)
                if (lastUid === '0') {
                    localStorage.removeItem('laundry_ai_chat_guest');
                } else {
                    localStorage.removeItem('laundry_ai_chat_uid_' + lastUid);
                }
                localStorage.removeItem('laundry_ai_chat_local_v1'); // Bersihkan legacy key global
            }
            localStorage.setItem(LAST_ACTIVE_UID_KEY, String(currentUserId));
        } catch (e) { /* storage disabled */ }

        function saveLocalHistory(role, text) {
            try {
                var raw = localStorage.getItem(LOCAL_STORE_KEY);
                var obj = null;
                try { obj = raw ? JSON.parse(raw) : null; } catch (e) { obj = null; }
                if (!obj || typeof obj !== 'object' || !Array.isArray(obj.data)) {
                    obj = { timestamp: Date.now(), data: [] };
                }
                obj.timestamp = Date.now();
                obj.data.push({ role: role, content: text });
                if (obj.data.length > 20) obj.data = obj.data.slice(-20);
                localStorage.setItem(LOCAL_STORE_KEY, JSON.stringify(obj));
            } catch (e) { /* storage disabled or quota */ }
        }

        function clearLocalHistory() {
            try {
                localStorage.removeItem(LOCAL_STORE_KEY);
                localStorage.removeItem('laundry_ai_chat_local_v1');
            } catch (e) { /* abaikan */ }
        }

        function restoreLocalHistoryIfEmpty() {
            // Jika server sudah me-render chat (misal dari PHP session), kita tidak perlu timpa
            var nonWelcome = list.querySelectorAll('.ai-msg:not([data-welcome="1"])');
            if (nonWelcome.length > 0) return;

            try {
                // Bersihkan legacy key jika ada agar tidak bocor ke user baru
                localStorage.removeItem('laundry_ai_chat_local_v1');

                var raw = localStorage.getItem(LOCAL_STORE_KEY);
                if (!raw) return;
                var obj = JSON.parse(raw);
                if (obj && obj.timestamp && Array.isArray(obj.data)) {
                    if (Date.now() - obj.timestamp < LOCAL_TTL_MS) {
                        obj.data.forEach(function (m) {
                            if (m && m.role && m.content) {
                                addMessage(m.role, m.content, false, true);
                            }
                        });
                    } else {
                        // Sudah lewat dari 24 jam, bersihkan otomatis
                        localStorage.removeItem(LOCAL_STORE_KEY);
                    }
                }
            } catch (e) { /* abaikan */ }
        }

        var busy = false;
        var cooldownTimer = null;
        var cooldownActive = false;
        var disabledByServer = input.disabled; // AI belum dikonfigurasi

        function scrollToBottom() {
            list.scrollTop = list.scrollHeight;
        }

        function formatMarkdown(raw) {
            if (!raw) return '';
            var div = document.createElement('div');
            div.textContent = raw;
            var escaped = div.innerHTML;
            var html = escaped.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
            html = html.replace(/\n/g, '<br>');
            return html;
        }

        function renderAttachmentCard(attachment) {
            if (!attachment || typeof attachment !== 'object') return null;
            var card = document.createElement('div');
            card.className = 'ai-attachment-card p-3 my-2 rounded-3 border bg-white text-dark shadow-sm';
            card.style.maxWidth = '320px';

            if (attachment.type === 'qris') {
                var title = document.createElement('div');
                title.className = 'fw-bold mb-1 d-flex align-items-center justify-content-between';
                title.innerHTML = '<span><i class="bi bi-qr-code text-primary me-1"></i> QRIS Pembayaran</span><span class="badge bg-light text-dark border">' + (attachment.order_number || '') + '</span>';
                card.appendChild(title);

                var amount = document.createElement('div');
                amount.className = 'fs-5 fw-bold text-success mb-2';
                amount.textContent = attachment.amount || '';
                card.appendChild(amount);

                if (attachment.qris_url) {
                    var imgWrap = document.createElement('div');
                    imgWrap.className = 'text-center my-2 p-2 bg-light rounded border';
                    var img = document.createElement('img');
                    img.src = attachment.qris_url;
                    img.alt = 'QRIS Code ' + (attachment.order_number || '');
                    img.style.maxWidth = '180px';
                    img.style.height = 'auto';
                    imgWrap.appendChild(img);
                    card.appendChild(imgWrap);
                }

                if (attachment.expired_at) {
                    var countdown = document.createElement('div');
                    countdown.className = 'small text-muted mb-2 text-center';
                    countdown.innerHTML = '<i class="bi bi-clock me-1"></i> Batas bayar: <strong>' + attachment.expired_at + '</strong>';
                    card.appendChild(countdown);
                }

                var payLink = document.createElement('a');
                payLink.href = attachment.pay_page || '#';
                payLink.className = 'btn btn-sm btn-primary w-100 rounded-pill fw-medium mt-1';
                payLink.innerHTML = '<i class="bi bi-box-arrow-up-right me-1"></i> Buka Halaman Pembayaran';
                card.appendChild(payLink);
            } else if (attachment.type === 'maps') {
                var mTitle = document.createElement('div');
                mTitle.className = 'fw-bold mb-1 d-flex align-items-center justify-content-between';
                mTitle.innerHTML = '<span><i class="bi bi-geo-alt-fill text-danger me-1"></i> Lokasi Pelanggan</span><span class="badge bg-light text-dark border">' + (attachment.order_number || '') + '</span>';
                card.appendChild(mTitle);

                var addr = document.createElement('div');
                addr.className = 'small text-dark mb-2 p-2 bg-light rounded border';
                addr.textContent = attachment.address || '-';
                card.appendChild(addr);

                var btns = document.createElement('div');
                btns.className = 'd-flex flex-column gap-1 mt-2';

                if (attachment.navigate_url) {
                    var navBtn = document.createElement('a');
                    navBtn.href = attachment.navigate_url;
                    navBtn.target = '_blank';
                    navBtn.rel = 'noopener noreferrer';
                    navBtn.className = 'btn btn-sm btn-success rounded-pill fw-medium text-center';
                    navBtn.innerHTML = '<i class="bi bi-compass-fill me-1"></i> Mulai Navigasi';
                    btns.appendChild(navBtn);
                }

                if (attachment.view_url) {
                    var mapBtn = document.createElement('a');
                    mapBtn.href = attachment.view_url;
                    mapBtn.target = '_blank';
                    mapBtn.rel = 'noopener noreferrer';
                    mapBtn.className = 'btn btn-sm btn-outline-primary rounded-pill fw-medium text-center';
                    mapBtn.innerHTML = '<i class="bi bi-map me-1"></i> Buka Google Maps';
                    btns.appendChild(mapBtn);
                }

                if (attachment.customer_phone) {
                    var phoneBtn = document.createElement('a');
                    phoneBtn.href = 'tel:' + attachment.customer_phone;
                    phoneBtn.className = 'btn btn-sm btn-outline-secondary rounded-pill fw-medium text-center';
                    phoneBtn.innerHTML = '<i class="bi bi-telephone-fill me-1"></i> Hubungi (' + attachment.customer_phone + ')';
                    btns.appendChild(phoneBtn);
                }

                card.appendChild(btns);
            }

            return card;
        }

        function addMessage(role, text, isError, skipSave, attachment) {
            var wrap = document.createElement('div');
            wrap.className = 'ai-msg ai-msg--' + role + (isError ? ' ai-msg--error' : '');
            var bubble = document.createElement('div');
            bubble.className = 'ai-msg__bubble';
            if (role === 'assistant') {
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
            scrollToBottom();
            if (!isError && !skipSave) {
                saveLocalHistory(role, text);
            }
            return wrap;
        }

        function addTyping() {
            var wrap = document.createElement('div');
            wrap.className = 'ai-msg ai-msg--assistant ai-typing';
            var bubble = document.createElement('div');
            bubble.className = 'ai-msg__bubble';
            for (var i = 0; i < 3; i++) {
                var dot = document.createElement('span');
                dot.className = 'dot';
                bubble.appendChild(dot);
            }
            wrap.appendChild(bubble);
            list.appendChild(wrap);
            scrollToBottom();
            return wrap;
        }

        function refreshControls() {
            var locked = disabledByServer || busy || cooldownActive;
            sendBtn.disabled = locked;
            input.disabled = disabledByServer || busy;
        }

        function updateCounter() {
            counter.textContent = input.value.length + '/' + maxLength;
        }

        function autoGrow() {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 140) + 'px';
        }

        function startCooldown(seconds) {
            cooldownActive = true;
            refreshControls();
            var remaining = Math.min(parseInt(seconds, 10) || 10, 300);
            var icon = sendBtn.innerHTML;
            clearInterval(cooldownTimer);
            sendBtn.textContent = remaining + 's';
            cooldownTimer = setInterval(function () {
                remaining -= 1;
                if (remaining <= 0) {
                    clearInterval(cooldownTimer);
                    cooldownActive = false;
                    sendBtn.innerHTML = icon;
                    refreshControls();
                } else {
                    sendBtn.textContent = remaining + 's';
                }
            }, 1000);
        }

        function updateSuggestions(q) {
            if (!suggestions) return;
            var text = (q || '').toLowerCase();
            var list = ['Berapa harga cuci kiloan?', 'Bagaimana cara pesan?', 'Berapa tarif ongkirnya?', 'Metode pembayaran apa saja?'];
            if (/kiloan|harga|tarif|biaya/.test(text)) {
                list = ['Bagaimana alur pemesanan?', 'Berapa ongkirnya?', 'Cuci Bedcover & Jas', 'Estimasi selesai berapa jam?'];
            } else if (/cara pesan|alur|langkah|order|jemput/.test(text)) {
                list = ['Berapa ongkos kirimnya?', 'Kapan saya harus bayar?', 'Berapa lama selesai?', 'Garansi cuci ulang'];
            } else if (/status|pesanan|lacak/.test(text)) {
                list = ['Kapan saya harus bayar?', 'Berapa total ongkirnya?', 'Bagaimana jika ada noda?'];
            } else if (/ongkir|jarak/.test(text)) {
                list = ['Bagaimana cara pesan?', 'Metode pembayaran apa saja?', 'Lihat harga cuci kiloan'];
            } else if (/bayar|qris|tunai/.test(text)) {
                list = ['Berapa lama laundry selesai?', 'Garansi cuci ulang 100%', 'Lihat tarif satuan'];
            }
            suggestions.innerHTML = '';
            list.forEach(function (label) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ai-chip btn btn-sm btn-outline-secondary rounded-pill me-1 mb-1';
                btn.textContent = label;
                btn.addEventListener('click', function () {
                    send(label);
                });
                suggestions.appendChild(btn);
            });
            suggestions.style.display = 'flex';
            suggestions.style.flexWrap = 'wrap';
            suggestions.style.gap = '6px';
        }

        async function send(text) {
            text = (text || '').trim();
            if (!text || busy || cooldownActive || disabledByServer) return;
            if (text.length > maxLength) {
                addMessage('assistant', 'Pertanyaan terlalu panjang. Maksimal ' + maxLength + ' karakter.', true);
                return;
            }

            busy = true;
            refreshControls();
            addMessage('user', text);
            input.value = '';
            updateCounter();
            autoGrow();
            var typing = addTyping();

            try {
                var res = await fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrf,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ message: text, order_id: orderId })
                });

                var type = res.headers.get('content-type') || '';
                if (type.indexOf('application/json') === -1) {
                    // Biasanya redirect ke halaman login / lengkapi profil
                    throw new Error('session');
                }

                var data = await res.json();
                typing.remove();

                if (res.ok && data.success) {
                    addMessage('assistant', data.reply, false, false, data.attachment);
                    updateSuggestions(text);
                } else {
                    addMessage('assistant', data.message || 'Terjadi kesalahan. Silakan coba lagi.', true);
                    if (data.retry_after) startCooldown(data.retry_after);
                }
            } catch (err) {
                typing.remove();
                if (err && err.message === 'session') {
                    addMessage('assistant', 'Sesi Anda berakhir atau profil belum lengkap. Muat ulang halaman, lalu login kembali.', true);
                } else {
                    addMessage('assistant', 'Tidak dapat terhubung ke server. Periksa koneksi internet Anda lalu coba lagi.', true);
                }
            } finally {
                busy = false;
                refreshControls();
                if (!disabledByServer) input.focus();
            }
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            send(input.value);
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
                e.preventDefault();
                send(input.value);
            }
        });

        input.addEventListener('input', function () {
            updateCounter();
            autoGrow();
        });

        document.querySelectorAll('.ai-chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                send(chip.textContent);
            });
        });

        resetBtn.addEventListener('click', async function () {
            if (busy) return;
            clearLocalHistory();
            try {
                var res = await fetch(resetEndpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrf,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (!res.ok) throw new Error('reset');
            } catch (err) {
                addMessage('assistant', 'Gagal memulai percakapan baru. Silakan muat ulang halaman.', true);
                return;
            }
            Array.prototype.slice.call(list.children).forEach(function (el) {
                if (!el.hasAttribute('data-welcome')) el.remove();
            });
            if (suggestions) suggestions.style.display = '';
        });

        // Tampilkan chip saran agar pengguna dapat terus berinteraksi dengan mudah
        if (suggestions) {
            suggestions.style.display = 'flex';
            suggestions.style.flexWrap = 'wrap';
            suggestions.style.gap = '6px';
        }

        // Widget melayang: buka/tutup panel
        var launcher = document.getElementById('aiLauncher');
        var panel = document.getElementById('aiPanel');
        var closeBtn = document.getElementById('aiClose');
        if (launcher && panel) {
            var setOpen = function (open) {
                panel.classList.toggle('ai-widget__panel--open', open);
                launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
                try { sessionStorage.setItem('aiWidgetOpen', open ? '1' : '0'); } catch (e) { /* abaikan */ }
                if (open) {
                    scrollToBottom();
                    if (!disabledByServer) input.focus();
                }
            };
            launcher.addEventListener('click', function () {
                setOpen(!panel.classList.contains('ai-widget__panel--open'));
            });
            if (closeBtn) closeBtn.addEventListener('click', function () { setOpen(false); });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && panel.classList.contains('ai-widget__panel--open')) setOpen(false);
            });
            try {
                if (sessionStorage.getItem('aiWidgetOpen') === '1') setOpen(true);
            } catch (e) { /* abaikan */ }
        }

        // Format markdown pada bubble pesan asisten yang sudah dirender oleh PHP di awal load
        list.querySelectorAll('.ai-msg--assistant .ai-msg__bubble').forEach(function (bubble) {
            if (!bubble.querySelector('.ai-attachment-card')) {
                bubble.innerHTML = formatMarkdown(bubble.textContent);
            }
        });

        restoreLocalHistoryIfEmpty();
        updateCounter();
        scrollToBottom();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAssistant);
    } else {
        initAssistant();
    }
})();
