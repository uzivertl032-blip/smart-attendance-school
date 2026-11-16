/* =========================================================
   SmartAttendance School Edition - Shared UI Controller
   Opening animation, page entrance trigger, and SaaS Premium
   Glassmorphism + Dark Premium 3D Animated Popup System.
   Vanilla JS, zero extra dependencies, native Bootstrap 5.3.
   ========================================================= */

(function () {
    'use strict';

    var root = document.documentElement;
    var INTRO_KEY = 'sa_intro_seen';
    var introElement = document.getElementById('saIntro');

    function markPageReady() {
        root.classList.add('sa-page-ready');
    }

    function removeIntro(delay) {
        if (!introElement) {
            return;
        }

        window.setTimeout(function () {
            introElement.classList.add('sa-intro-out');

            window.setTimeout(function () {
                if (introElement && introElement.parentNode) {
                    introElement.parentNode.removeChild(introElement);
                }
            }, 700);
        }, delay || 0);
    }

    function finishIntro() {
        if (!introElement || introElement.dataset.finished === '1') {
            markPageReady();
            return;
        }

        introElement.dataset.finished = '1';

        var bar = document.getElementById('saIntroBar');
        var percent = document.getElementById('saIntroPercent');
        var label = document.getElementById('saIntroLabel');

        if (bar) {
            bar.style.width = '100%';
        }

        if (percent) {
            percent.textContent = '100%';
        }

        if (label) {
            label.textContent = 'Siap';
        }

        markPageReady();
        removeIntro(140);
    }

    function startIntro() {
        if (!introElement || root.classList.contains('sa-intro-skip')) {
            markPageReady();
            removeIntro(0);
            return;
        }

        try {
            window.sessionStorage.setItem(INTRO_KEY, '1');
        } catch (error) {
            // Private browsing can reject storage access. The intro still runs once.
        }

        var bar = document.getElementById('saIntroBar');
        var percent = document.getElementById('saIntroPercent');
        var label = document.getElementById('saIntroLabel');
        var skipButton = document.getElementById('saIntroSkip');
        var start = null;
        var duration = 1250;

        if (skipButton) {
            skipButton.addEventListener('click', finishIntro);
        }

        function tick(now) {
            if (introElement.dataset.finished === '1') {
                return;
            }

            if (start === null) {
                start = now;
            }

            var progress = Math.min(1, (now - start) / duration);
            var eased = 1 - Math.pow(1 - progress, 3);
            var value = Math.round(eased * 100);

            if (bar) {
                bar.style.width = value + '%';
            }

            if (percent) {
                percent.textContent = value + '%';
            }

            if (label) {
                label.textContent = progress < 0.58
                    ? 'Menyiapkan pengalaman premium...'
                    : 'Menyusun antarmuka cerdas...';
            }

            if (progress < 1) {
                window.requestAnimationFrame(tick);
            } else {
                finishIntro();
            }
        }

        window.requestAnimationFrame(tick);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startIntro, { once: true });
    } else {
        startIntro();
    }

    window.addEventListener('pageshow', function (event) {
        if (!event.persisted || !introElement) {
            return;
        }

        markPageReady();
        introElement.classList.add('sa-intro-out');
    });

    /* =========================================================
       SaaS Premium 3D Animated Glassmorphism Popup Controller
       Supports: Success, Error, Warning, Info, Confirm, Delete, Logout
       ========================================================= */

    var confirmElement = document.getElementById('saConfirmModal');
    var flashElement = document.getElementById('saFlashModal');
    var confirmInstance = null;
    var flashInstance = null;
    var pendingAction = null;

    /**
     * Helper to clone 3D vector SVG icon into target container
     */
    function render3DIcon(container, type) {
        if (!container) return;
        container.innerHTML = '';

        var templateContainer = document.getElementById('sa3DIconTemplates');
        if (!templateContainer) {
            return;
        }

        var tpl = templateContainer.querySelector('[data-icon-tpl="' + type + '"]');
        if (!tpl) {
            tpl = templateContainer.querySelector('[data-icon-tpl="info"]');
        }

        if (tpl) {
            var clone = tpl.firstElementChild.cloneNode(true);
            container.appendChild(clone);
        }
    }

    function getBootstrapModal(elem) {
        if (!elem || typeof window.bootstrap === 'undefined' || !window.bootstrap.Modal) {
            return null;
        }
        return window.bootstrap.Modal.getOrCreateInstance(elem, {
            backdrop: true,
            keyboard: true,
            focus: true
        });
    }

    function submitConfirmedForm(form) {
        form.dataset.saConfirmed = '1';

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }

        form.submit();
    }

    /**
     * Global SmartPopup API
     */
    window.SmartPopup = {
        /**
         * Show an interactive confirmation modal
         */
        confirm: function (options) {
            options = options || {};
            var title = options.title || 'Konfirmasi Tindakan';
            var message = options.message || 'Apakah Anda yakin ingin melanjutkan tindakan ini?';
            var okText = options.okText || 'Ya, Lanjutkan';
            var cancelText = options.cancelText || 'Batal';
            var variant = options.variant || 'confirm';
            var badge = options.badge || (variant === 'delete' ? 'Tindakan Permanen' : (variant === 'logout' ? 'Konfirmasi Sesi' : 'Konfirmasi Tindakan'));
            var onConfirm = options.onConfirm || null;
            var onCancel = options.onCancel || null;

            if (!confirmElement) {
                if (window.confirm(message)) {
                    if (typeof onConfirm === 'function') onConfirm();
                } else {
                    if (typeof onCancel === 'function') onCancel();
                }
                return;
            }

            var titleEl = document.getElementById('saConfirmTitle');
            var msgEl = document.getElementById('saConfirmMessage');
            var badgeEl = document.getElementById('saConfirmBadge');
            var iconStage = document.getElementById('saConfirmIconStage');
            var okBtn = document.getElementById('saConfirmOk');
            var cancelBtn = document.getElementById('saConfirmCancel');

            if (titleEl) titleEl.textContent = title;
            if (msgEl) msgEl.textContent = message;
            if (badgeEl) badgeEl.textContent = badge;
            if (iconStage) render3DIcon(iconStage, variant);

            if (cancelBtn) {
                cancelBtn.textContent = cancelText;
            }

            if (okBtn) {
                okBtn.textContent = okText;
                okBtn.className = 'btn sa-popup-btn ' + (
                    variant === 'delete' || variant === 'danger'
                        ? 'sa-popup-btn-danger'
                        : (variant === 'success'
                            ? 'sa-popup-btn-success'
                            : (variant === 'warning'
                                ? 'sa-popup-btn-warning'
                                : 'sa-popup-btn-primary'))
                );
            }

            confirmElement.setAttribute('data-variant', variant);

            pendingAction = {
                onConfirm: onConfirm,
                onCancel: onCancel,
                form: options.form || null
            };

            var modal = getBootstrapModal(confirmElement);
            if (modal) {
                modal.show();
            }
        },

        /**
         * Shortcut for Delete Confirmation
         */
        delete: function (options) {
            options = options || {};
            options.variant = 'delete';
            options.title = options.title || 'Konfirmasi Hapus Data';
            options.message = options.message || 'Tindakan ini tidak dapat dibatalkan. Seluruh data terkait akan dihapus secara permanen dari sistem.';
            options.okText = options.okText || 'Ya, Hapus Data';
            options.badge = options.badge || 'PENGHAPUSAN PERMANEN';
            this.confirm(options);
        },

        /**
         * Show a notification / flash message popup
         */
        show: function (options) {
            options = options || {};
            var type = options.type || 'info';
            var title = options.title || (
                type === 'success' ? 'Berhasil!' :
                type === 'error' ? 'Terjadi Kesalahan' :
                type === 'warning' ? 'Perhatian' : 'Informasi'
            );
            var message = options.message || '';
            var badge = options.badge || (
                type === 'success' ? 'Operasi Sukses' :
                type === 'error' ? 'Pemberitahuan Kendala' :
                type === 'warning' ? 'Peringatan Sistem' : 'Informasi Penting'
            );
            var okText = options.okText || (type === 'error' ? 'Tutup & Periksa' : 'Mengerti');

            if (!flashElement) {
                window.alert(title + '\n\n' + message);
                return;
            }

            var titleEl = document.getElementById('saFlashTitle');
            var msgEl = document.getElementById('saFlashMessage');
            var badgeEl = document.getElementById('saFlashBadge');
            var iconStage = document.getElementById('saFlashIconStage');
            var okBtn = document.getElementById('saFlashOk');

            if (titleEl) titleEl.textContent = title;
            if (msgEl) msgEl.textContent = message;
            if (badgeEl) badgeEl.textContent = badge;
            if (iconStage) render3DIcon(iconStage, type);

            if (okBtn) {
                okBtn.textContent = okText;
                okBtn.className = 'btn sa-popup-btn ' + (
                    type === 'success' ? 'sa-popup-btn-success' :
                    type === 'error' ? 'sa-popup-btn-danger' :
                    type === 'warning' ? 'sa-popup-btn-warning' : 'sa-popup-btn-primary'
                ) + ' px-4';
            }

            flashElement.setAttribute('data-variant', type);

            var modal = getBootstrapModal(flashElement);
            if (modal) {
                modal.show();
            }
        },

        success: function (message, title, options) {
            options = options || {};
            options.type = 'success';
            options.message = message;
            if (title) options.title = title;
            this.show(options);
        },

        error: function (message, title, options) {
            options = options || {};
            options.type = 'error';
            options.message = message;
            if (title) options.title = title;
            this.show(options);
        },

        warning: function (message, title, options) {
            options = options || {};
            options.type = 'warning';
            options.message = message;
            if (title) options.title = title;
            this.show(options);
        },

        info: function (message, title, options) {
            options = options || {};
            options.type = 'info';
            options.message = message;
            if (title) options.title = title;
            this.show(options);
        },

        close: function () {
            if (confirmElement) {
                var c = getBootstrapModal(confirmElement);
                if (c) c.hide();
            }
            if (flashElement) {
                var f = getBootstrapModal(flashElement);
                if (f) f.hide();
            }
        }
    };

    /* ---------------------------------------------------------
       Wire Interactive Confirmation Button Handlers
       --------------------------------------------------------- */
    if (confirmElement) {
        confirmElement.addEventListener('hidden.bs.modal', function () {
            if (pendingAction && typeof pendingAction.onCancel === 'function') {
                pendingAction.onCancel();
            }
            pendingAction = null;
        });

        var confirmOkBtn = document.getElementById('saConfirmOk');
        if (confirmOkBtn) {
            confirmOkBtn.addEventListener('click', function () {
                var action = pendingAction;
                pendingAction = null;

                var modal = getBootstrapModal(confirmElement);
                if (modal) {
                    modal.hide();
                }

                if (action) {
                    if (typeof action.onConfirm === 'function') {
                        action.onConfirm();
                    } else if (action.form) {
                        submitConfirmedForm(action.form);
                    }
                }
            });
        }
    }

    /**
     * Intercept form submits with data-confirm
     */
    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) {
            return;
        }

        if (form.dataset.saConfirmed === '1') {
            delete form.dataset.saConfirmed;
            return;
        }

        if (event.defaultPrevented) {
            return;
        }

        event.preventDefault();

        var message = form.getAttribute('data-confirm') || 'Apakah Anda yakin ingin melanjutkan?';
        var title = form.getAttribute('data-confirm-title') || 'Konfirmasi Tindakan';
        var okText = form.getAttribute('data-confirm-ok') || 'Ya, Lanjutkan';
        var cancelText = form.getAttribute('data-confirm-cancel') || 'Batal';
        var variant = form.getAttribute('data-confirm-variant') || 'confirm';
        var badge = form.getAttribute('data-confirm-badge') || null;

        // Smart variant inference
        var formAction = (form.getAttribute('action') || '').toLowerCase();
        var isDeleteAction = formAction.includes('destroy') || formAction.includes('delete') || form.querySelector('input[name="_method"][value="DELETE"]');

        if (variant === 'danger' || variant === 'delete' || isDeleteAction || message.toLowerCase().includes('hapus')) {
            variant = 'delete';
            if (!badge) badge = 'PENGHAPUSAN PERMANEN';
            if (okText === 'Ya, Lanjutkan') okText = 'Ya, Hapus Data';
        } else if (variant === 'logout' || formAction.includes('logout') || message.toLowerCase().includes('logout') || message.toLowerCase().includes('keluar')) {
            variant = 'logout';
            if (!badge) badge = 'KONFIRMASI SESI';
            if (okText === 'Ya, Lanjutkan') okText = 'Ya, Keluar';
        }

        window.SmartPopup.confirm({
            title: title,
            message: message,
            okText: okText,
            cancelText: cancelText,
            variant: variant,
            badge: badge,
            form: form
        });
    });

    /**
     * Intercept buttons with data-delete-url (e.g. student & teacher table rows)
     * Seamlessly upgrades them to use the 3D animated delete popup
     */
    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-delete-url]');
        if (!btn) return;

        var deleteUrl = btn.getAttribute('data-delete-url');
        if (!deleteUrl) return;

        event.preventDefault();
        event.stopPropagation();

        var studentName = btn.getAttribute('data-student-name');
        var teacherName = btn.getAttribute('data-teacher-name');
        var genericName = btn.getAttribute('data-name');
        var targetName = studentName || teacherName || genericName || 'data ini';

        var title = studentName ? 'Konfirmasi Hapus Siswa' : (teacherName ? 'Konfirmasi Hapus Guru' : 'Konfirmasi Hapus Data');
        var message = 'Anda akan menghapus akun "' + targetName + '". Seluruh data terkait (profil, riwayat absensi) akan ikut terhapus secara permanen dari sistem.';

        var form = document.getElementById('deleteForm');
        if (!form) {
            form = document.createElement('form');
            form.id = 'deleteForm';
            form.method = 'POST';
            form.style.display = 'none';

            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            var csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfMeta ? csrfMeta.getAttribute('content') : '';

            var methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = 'DELETE';

            form.appendChild(csrfInput);
            form.appendChild(methodInput);
            document.body.appendChild(form);
        }

        form.action = deleteUrl;

        window.SmartPopup.delete({
            title: title,
            message: message,
            okText: 'Ya, Hapus',
            form: form
        });
    }, true);

    /**
     * Automatic Flash Notice Handler
     * Triggers on page load if a flash notice element is embedded by Blade
     */
    function checkFlashNotice() {
        var noticeEl = document.querySelector('.sa-flash-notice, #saFlashNotice');
        if (!noticeEl) return;

        var type = noticeEl.getAttribute('data-flash-type') || 'info';
        var title = noticeEl.getAttribute('data-flash-title') || 'Pemberitahuan';
        var message = noticeEl.getAttribute('data-flash-message') || '';

        if (message && message.trim().length > 0) {
            setTimeout(function () {
                window.SmartPopup.show({
                    type: type,
                    title: title,
                    message: message
                });
            }, 300);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            checkFlashNotice();
            initScrollRevealAndCounters();
        }, { once: true });
    } else {
        checkFlashNotice();
        initScrollRevealAndCounters();
    }

    /**
     * Scroll Reveal & Animated Counter Engine 2026
     */
    function initScrollRevealAndCounters() {
        if ('IntersectionObserver' in window) {
            var revealObserver = new IntersectionObserver(function (entries, observer) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1, rootMargin: '0px 0px -30px 0px' });

            document.querySelectorAll('.sa-reveal:not(.is-visible)').forEach(function (el) {
                revealObserver.observe(el);
            });

            var counterObserver = new IntersectionObserver(function (entries, observer) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        var el = entry.target;
                        var targetVal = parseInt(el.getAttribute('data-counter'), 10);
                        if (!isNaN(targetVal) && !el.dataset.counted) {
                            el.dataset.counted = '1';
                            var startTime = null;
                            var duration = 1200;

                            function step(now) {
                                if (!startTime) startTime = now;
                                var progress = Math.min((now - startTime) / duration, 1);
                                var eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
                                var current = Math.floor(targetVal * eased);
                                el.textContent = current.toLocaleString('id-ID');
                                if (progress < 1) {
                                    requestAnimationFrame(step);
                                } else {
                                    el.textContent = targetVal.toLocaleString('id-ID');
                                }
                            }
                            requestAnimationFrame(step);
                        }
                        observer.unobserve(el);
                    }
                });
            }, { threshold: 0.15 });

            document.querySelectorAll('[data-counter]').forEach(function (el) {
                counterObserver.observe(el);
            });
        } else {
            document.querySelectorAll('.sa-reveal').forEach(function (el) {
                el.classList.add('is-visible');
            });
        }
    }

})();
