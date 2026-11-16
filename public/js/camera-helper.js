/**
 * Smart Attendance School - Camera Engine & Hardware Manager
 * Provides unified, resilient camera access with:
 * - HTML5 MediaDevices API normalization & legacy polyfill
 * - Secure Context diagnostics for local development (Chrome flags & HTTPS guidance)
 * - Multi-device camera enumeration & switching with persistence
 * - Constraint fallback (HD -> standard -> minimal)
 * - Detailed error handling & diagnostics
 * - Stream lifecycle management (clean track stopping to prevent device locks)
 */

(function (window) {
    'use strict';

    class SchoolCameraHelper {
        constructor() {
            this.activeStream = null;
            this.activeVideoElement = null;
            this.activeDeviceId = localStorage.getItem('preferred_school_camera_id') || null;
            this.isInitializing = false;
        }

        /**
         * Check whether the current browser environment is a Secure Context.
         * Google Chrome and other modern browsers require a Secure Context for MediaDevices.
         */
        isSecureContext() {
            if (typeof window.isSecureContext === 'boolean') {
                return window.isSecureContext;
            }
            // Fallback check for protocol and hostname
            const proto = window.location.protocol;
            const host = window.location.hostname;
            return proto === 'https:' || host === 'localhost' || host === '127.0.0.1' || host === '[::1]';
        }

        /**
         * Check if Camera API is supported in the current environment.
         */
        hasCameraApiSupport() {
            if (navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function') {
                return true;
            }
            // Check legacy polyfills
            const legacy = navigator.getUserMedia ||
                navigator.webkitGetUserMedia ||
                navigator.mozGetUserMedia ||
                navigator.msGetUserMedia;
            return typeof legacy === 'function';
        }

        /**
         * Stop all active camera tracks to release hardware locks on Windows.
         */
        stopActiveStream() {
            if (this.activeStream) {
                try {
                    this.activeStream.getTracks().forEach(track => {
                        try {
                            track.stop();
                        } catch (e) {
                            console.warn('Error stopping track:', e);
                        }
                    });
                } catch (e) {
                    console.warn('Error iterating stream tracks:', e);
                }
                this.activeStream = null;
            }

            if (this.activeVideoElement) {
                try {
                    this.activeVideoElement.srcObject = null;
                } catch (e) {
                    // Ignore
                }
            }
        }

        /**
         * Request a camera stream with graceful constraint fallback.
         * Tries: 1. Desired HD + facingMode / deviceId
         *        2. Relaxed deviceId without resolution constraints
         *        3. Basic { video: true }
         */
        async requestMediaStream(targetDeviceId = null) {
            this.stopActiveStream();

            const deviceId = targetDeviceId || this.activeDeviceId;

            // Strategy 1: Ideal HD stream with preferred device or facingMode
            const primaryConstraints = {
                audio: false,
                video: deviceId
                    ? { deviceId: { exact: deviceId }, width: { ideal: 1280 }, height: { ideal: 720 } }
                    : { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } }
            };

            try {
                return await this._getUserMedia(primaryConstraints);
            } catch (err1) {
                console.warn('Primary camera constraints failed, attempting fallback...', err1);

                // If OverconstrainedError or NotReadableError with exact device, try ideal deviceId
                if (deviceId) {
                    try {
                        const fallbackDeviceConstraints = {
                            audio: false,
                            video: { deviceId: { ideal: deviceId } }
                        };
                        return await this._getUserMedia(fallbackDeviceConstraints);
                    } catch (err2) {
                        console.warn('Fallback device constraint failed, trying generic video...', err2);
                    }
                }

                // Strategy 2: Relaxed user-facing video
                try {
                    const fallbackFacingConstraints = {
                        audio: false,
                        video: { facingMode: 'user' }
                    };
                    return await this._getUserMedia(fallbackFacingConstraints);
                } catch (err3) {
                    console.warn('Fallback facingMode constraint failed, trying minimal { video: true }...', err3);
                }

                // Strategy 3: Minimal generic constraint
                return await this._getUserMedia({ audio: false, video: true });
            }
        }

        /**
         * Low-level getUserMedia call with legacy fallback wrapper.
         */
        async _getUserMedia(constraints) {
            if (navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function') {
                return await navigator.mediaDevices.getUserMedia(constraints);
            }

            // Legacy fallback for older browser interfaces
            const legacyGetUserMedia = navigator.getUserMedia ||
                navigator.webkitGetUserMedia ||
                navigator.mozGetUserMedia ||
                navigator.msGetUserMedia;

            if (legacyGetUserMedia) {
                return new Promise((resolve, reject) => {
                    legacyGetUserMedia.call(navigator, constraints, resolve, reject);
                });
            }

            // Check if insecure context is the reason
            if (!this.isSecureContext()) {
                const err = new Error('INSECURE_CONTEXT');
                err.name = 'InsecureContextError';
                throw err;
            }

            const unsuppErr = new Error('BROWSER_UNSUPPORTED');
            unsuppErr.name = 'BrowserNotSupportedError';
            throw unsuppErr;
        }

        /**
         * Initialize camera on a video element.
         * @param {HTMLVideoElement} videoElement
         * @param {string|null} preferredDeviceId
         * @returns {Promise<{ stream: MediaStream, deviceId: string }>}
         */
        async initializeCamera(videoElement, preferredDeviceId = null) {
            if (this.isInitializing) {
                return null;
            }
            this.isInitializing = true;
            this.activeVideoElement = videoElement;

            try {
                // Check Secure Context first before attempting hardware access
                if (!this.hasCameraApiSupport() && !this.isSecureContext()) {
                    const err = new Error('INSECURE_CONTEXT');
                    err.name = 'InsecureContextError';
                    throw err;
                }

                const stream = await this.requestMediaStream(preferredDeviceId);
                this.activeStream = stream;

                // Determine active deviceId from stream tracks
                const videoTrack = stream.getVideoTracks()[0];
                if (videoTrack) {
                    const settings = videoTrack.getSettings ? videoTrack.getSettings() : {};
                    if (settings.deviceId) {
                        this.activeDeviceId = settings.deviceId;
                        localStorage.setItem('preferred_school_camera_id', settings.deviceId);
                    }
                }

                videoElement.srcObject = stream;

                // Ensure attributes for reliable autoplay across browsers
                videoElement.setAttribute('autoplay', '');
                videoElement.setAttribute('playsinline', '');
                videoElement.muted = true;

                await new Promise((resolve, reject) => {
                    let resolved = false;

                    const onReady = async () => {
                        if (resolved) return;
                        resolved = true;
                        videoElement.removeEventListener('loadedmetadata', onReady);
                        videoElement.removeEventListener('canplay', onReady);

                        try {
                            const playPromise = videoElement.play();
                            if (playPromise !== undefined) {
                                await playPromise;
                            }
                            resolve({ stream, deviceId: this.activeDeviceId });
                        } catch (playErr) {
                            console.warn('Video play interrupted or rejected:', playErr);
                            // On some browsers, play may reject if not user-interacted, but stream is ready
                            resolve({ stream, deviceId: this.activeDeviceId });
                        }
                    };

                    videoElement.addEventListener('loadedmetadata', onReady, { once: true });
                    videoElement.addEventListener('canplay', onReady, { once: true });

                    // Timeout fallback in case loadedmetadata does not fire promptly
                    setTimeout(() => {
                        if (!resolved) {
                            resolved = true;
                            videoElement.play().catch(() => {});
                            resolve({ stream, deviceId: this.activeDeviceId });
                        }
                    }, 2500);
                });

                return { stream, deviceId: this.activeDeviceId };
            } finally {
                this.isInitializing = false;
            }
        }

        /**
         * Enumerate all video input devices (webcams).
         * Will ask for permission if not yet granted to get proper labels.
         */
        async getAvailableCameras() {
            if (!navigator.mediaDevices || typeof navigator.mediaDevices.enumerateDevices !== 'function') {
                return [];
            }

            try {
                const devices = await navigator.mediaDevices.enumerateDevices();
                const videoDevices = devices.filter(d => d.kind === 'videoinput');

                return videoDevices.map((device, index) => ({
                    deviceId: device.deviceId,
                    label: device.label || `Kamera ${index + 1} (${device.deviceId.substring(0, 8)}...)`,
                    groupId: device.groupId
                }));
            } catch (err) {
                console.warn('Could not enumerate media devices:', err);
                return [];
            }
        }

        /**
         * Setup camera select dropdown element.
         * @param {HTMLSelectElement} selectElement
         * @param {Function} onCameraChangedCallback
         */
        async setupCameraSelect(selectElement, onCameraChangedCallback) {
            if (!selectElement) return;

            try {
                const cameras = await this.getAvailableCameras();

                // If no cameras or only default without labels, keep current
                if (cameras.length === 0) {
                    selectElement.innerHTML = '<option value="">Default Kamera</option>';
                    return;
                }

                selectElement.innerHTML = '';
                cameras.forEach(cam => {
                    const option = document.createElement('option');
                    option.value = cam.deviceId;
                    option.textContent = cam.label;
                    if (this.activeDeviceId && cam.deviceId === this.activeDeviceId) {
                        option.selected = true;
                    }
                    selectElement.appendChild(option);
                });

                // Attach change listener
                selectElement.onchange = async () => {
                    const selectedId = selectElement.value;
                    if (selectedId) {
                        this.activeDeviceId = selectedId;
                        localStorage.setItem('preferred_school_camera_id', selectedId);
                    }
                    if (typeof onCameraChangedCallback === 'function') {
                        onCameraChangedCallback(selectedId);
                    }
                };
            } catch (err) {
                console.warn('Failed to populate camera select:', err);
            }
        }

        /**
         * Parse any camera error into user-friendly diagnostic information.
         */
        parseError(err) {
            const errorName = err ? (err.name || err.message || '') : '';
            const isSecure = this.isSecureContext();
            const currentOrigin = window.location.origin;

            // Insecure Context detection (Google Chrome on http://absensi-sekolah.test)
            if (errorName === 'InsecureContextError' ||
                err.message === 'INSECURE_CONTEXT' ||
                (!isSecure && (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia))) {
                return {
                    code: 'INSECURE_CONTEXT',
                    title: 'Peramban Membutuhkan Akses Aman (Secure Context)',
                    message: `Google Chrome membatasi akses webcam pada domain HTTP (${currentOrigin}).`,
                    details: 'Kamera hanya diizinkan pada HTTPS, localhost, atau domain yang didaftarkan di Chrome Flags.',
                    actionType: 'INSECURE_CONTEXT',
                    origin: currentOrigin,
                    httpsUrl: 'https://' + window.location.host + window.location.pathname + window.location.search
                };
            }

            // Permission denied by user
            if (errorName === 'NotAllowedError' || errorName === 'PermissionDeniedError') {
                return {
                    code: 'PERMISSION_DENIED',
                    title: 'Izin Kamera Ditolak',
                    message: 'Akses ke kamera tidak diizinkan oleh peramban.',
                    details: 'Harap klik ikon gembok/kamera di sebelah kiri bilah URL peramban, lalu ubah status izin kamera menjadi "Izinkan" (Allow) dan muat ulang halaman.',
                    actionType: 'RETRY'
                };
            }

            // Device in use by another app (Zoom, Teams, OBS, Skype, another browser tab)
            if (errorName === 'NotReadableError' || errorName === 'TrackStartError') {
                return {
                    code: 'DEVICE_IN_USE',
                    title: 'Kamera Sedang Digunakan Aplikasi Lain',
                    message: 'Perangkat kamera tidak dapat diakses karena sedang digunakan oleh aplikasi lain atau terkunci oleh sistem operasi.',
                    details: 'Pastikan aplikasi seperti Zoom, Microsoft Teams, Google Meet, OBS Studio, WhatsApp Desktop, atau aplikasi kamera bawaan Windows telah ditutup.',
                    actionType: 'RETRY'
                };
            }

            // Camera device not found
            if (errorName === 'NotFoundError' || errorName === 'DevicesNotFoundError') {
                return {
                    code: 'DEVICE_NOT_FOUND',
                    title: 'Perangkat Kamera Tidak Ditemukan',
                    message: 'Tidak ada perangkat webcam yang terdeteksi pada laptop/komputer ini.',
                    details: 'Periksa apakah kamera laptop memiliki saklar fisik privasi (privacy shutter) atau tombol fungsi Fn untuk mengaktifkan webcam, atau periksa koneksi webcam USB Anda.',
                    actionType: 'RETRY'
                };
            }

            // Overconstrained
            if (errorName === 'OverconstrainedError') {
                return {
                    code: 'OVERCONSTRAINED',
                    title: 'Resolusi Kamera Tidak Didukung',
                    message: 'Kamera Anda tidak mendukung spesifikasi resolusi yang diminta.',
                    details: 'Sistem telah mencoba menurunkan resolusi otomatis. Silakan coba hubungkan ulang.',
                    actionType: 'RETRY'
                };
            }

            // Browser unsupported
            if (errorName === 'BROWSER_UNSUPPORTED' || errorName === 'BrowserNotSupportedError') {
                return {
                    code: 'BROWSER_UNSUPPORTED',
                    title: 'Peramban Tidak Mendukung Camera API',
                    message: 'Peramban yang Anda gunakan tidak mendukung HTML5 Camera API.',
                    details: 'Gunakan Google Chrome, Microsoft Edge, atau Mozilla Firefox versi terbaru.',
                    actionType: 'RELOAD'
                };
            }

            // Generic / Fallback
            return {
                code: 'CAMERA_INIT_FAILED',
                title: 'Kamera Gagal Diinisialisasi',
                message: err.message || 'Terjadi kesalahan teknis saat mengakses perangkat kamera.',
                details: 'Pastikan izin webcam aktif pada Windows Settings (Settings > Privacy & Security > Camera).',
                actionType: 'RETRY'
            };
        }
    }

    // Attach singleton to window
    window.SchoolCameraHelper = SchoolCameraHelper;
    window.schoolCamera = new SchoolCameraHelper();

})(window);
