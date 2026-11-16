{{-- ===========================================================================
     SmartAttendance School Edition - Global 3D Glassmorphism Popup System
     Combines: Glassmorphism + Modern Minimalist + Dark Premium + 3D Animated
     Used globally across Admin, Teacher, Student, Home, and Auth pages.
     =========================================================================== --}}

{{-- 3D Animated Vector Icon Templates (Zero External Assets) --}}
<div id="sa3DIconTemplates" class="d-none" aria-hidden="true">
    {{-- 1. SUCCESS (Emerald Sphere + 3D Checkmark + Orbital Halo) --}}
    <div data-icon-tpl="success">
        <div class="sa-3d-icon sa-3d-icon--success">
            <svg class="sa-3d-svg" viewBox="0 0 96 96" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <radialGradient id="saGlowSuccess" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="#10B981" stop-opacity="0.55"/>
                        <stop offset="100%" stop-color="#10B981" stop-opacity="0"/>
                    </radialGradient>
                    <linearGradient id="saSphereSuccess" x1="24" y1="18" x2="72" y2="78" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#34D399"/>
                        <stop offset="45%" stop-color="#10B981"/>
                        <stop offset="100%" stop-color="#047857"/>
                    </linearGradient>
                    <linearGradient id="saRingSuccess" x1="12" y1="48" x2="84" y2="48" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#6EE7B7" stop-opacity="0.95"/>
                        <stop offset="50%" stop-color="#10B981" stop-opacity="0.15"/>
                        <stop offset="100%" stop-color="#059669" stop-opacity="0.85"/>
                    </linearGradient>
                    <filter id="saDropSuccess" x="0" y="0" width="96" height="96" filterUnits="userSpaceOnUse">
                        <feDropShadow dx="0" dy="6" stdDeviation="6" flood-color="#064E3B" flood-opacity="0.6"/>
                    </filter>
                </defs>
                <circle cx="48" cy="48" r="44" fill="url(#saGlowSuccess)"/>
                <ellipse cx="48" cy="48" rx="38" ry="14" transform="rotate(-24 48 48)" stroke="url(#saRingSuccess)" stroke-width="3" stroke-dasharray="7 3.5"/>
                <circle cx="48" cy="48" r="28" fill="url(#saSphereSuccess)" filter="url(#saDropSuccess)"/>
                <ellipse cx="38" cy="34" rx="12" ry="7" transform="rotate(-30 38 34)" fill="white" fill-opacity="0.45"/>
                <path d="M37 49L44 56L60 39" stroke="white" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="28" cy="26" r="2.5" fill="#6EE7B7" fill-opacity="0.8"/>
                <circle cx="68" cy="68" r="2" fill="#34D399" fill-opacity="0.7"/>
            </svg>
        </div>
    </div>

    {{-- 2. ERROR / DANGER (Crimson Hexagon + 3D Cross + Alert Waves) --}}
    <div data-icon-tpl="error">
        <div class="sa-3d-icon sa-3d-icon--error">
            <svg class="sa-3d-svg" viewBox="0 0 96 96" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <radialGradient id="saGlowError" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="#EF4444" stop-opacity="0.55"/>
                        <stop offset="100%" stop-color="#EF4444" stop-opacity="0"/>
                    </radialGradient>
                    <linearGradient id="saSphereError" x1="24" y1="18" x2="72" y2="78" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#F87171"/>
                        <stop offset="50%" stop-color="#EF4444"/>
                        <stop offset="100%" stop-color="#991B1B"/>
                    </linearGradient>
                    <linearGradient id="saRingError" x1="14" y1="48" x2="82" y2="48" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#FCA5A5" stop-opacity="0.9"/>
                        <stop offset="100%" stop-color="#DC2626" stop-opacity="0.2"/>
                    </linearGradient>
                    <filter id="saDropError" x="0" y="0" width="96" height="96" filterUnits="userSpaceOnUse">
                        <feDropShadow dx="0" dy="6" stdDeviation="6" flood-color="#7F1D1D" flood-opacity="0.6"/>
                    </filter>
                </defs>
                <circle cx="48" cy="48" r="44" fill="url(#saGlowError)"/>
                <ellipse cx="48" cy="48" rx="38" ry="14" transform="rotate(24 48 48)" stroke="url(#saRingError)" stroke-width="3" stroke-dasharray="7 3.5"/>
                <circle cx="48" cy="48" r="28" fill="url(#saSphereError)" filter="url(#saDropError)"/>
                <ellipse cx="38" cy="34" rx="12" ry="7" transform="rotate(-30 38 34)" fill="white" fill-opacity="0.45"/>
                <path d="M38 38L58 58M58 38L38 58" stroke="white" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="26" cy="64" r="2" fill="#FCA5A5" fill-opacity="0.75"/>
            </svg>
        </div>
    </div>

    {{-- 3. WARNING (Amber Rounded Prism + Glowing Exclamation) --}}
    <div data-icon-tpl="warning">
        <div class="sa-3d-icon sa-3d-icon--warning">
            <svg class="sa-3d-svg" viewBox="0 0 96 96" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <radialGradient id="saGlowWarn" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="#F59E0B" stop-opacity="0.55"/>
                        <stop offset="100%" stop-color="#F59E0B" stop-opacity="0"/>
                    </radialGradient>
                    <linearGradient id="saTriWarn" x1="20" y1="16" x2="76" y2="76" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#FDE047"/>
                        <stop offset="45%" stop-color="#F59E0B"/>
                        <stop offset="100%" stop-color="#B45309"/>
                    </linearGradient>
                    <filter id="saDropWarn" x="0" y="0" width="96" height="96" filterUnits="userSpaceOnUse">
                        <feDropShadow dx="0" dy="6" stdDeviation="6" flood-color="#78350F" flood-opacity="0.6"/>
                    </filter>
                </defs>
                <circle cx="48" cy="48" r="44" fill="url(#saGlowWarn)"/>
                <path d="M48 18L76 68C77.5 70.8 75.5 74 72.3 74H23.7C20.5 74 18.5 70.8 20 68L48 18Z" fill="url(#saTriWarn)" filter="url(#saDropWarn)"/>
                <path d="M48 24L68 62H28L48 24Z" fill="white" fill-opacity="0.22"/>
                <path d="M48 35V52M48 60V62" stroke="#1E1B4B" stroke-width="4.5" stroke-linecap="round"/>
                <circle cx="70" cy="30" r="2" fill="#FDE047" fill-opacity="0.8"/>
            </svg>
        </div>
    </div>

    {{-- 4. INFORMATION (Cobalt/Electric Blue Orb + Dimensional Info) --}}
    <div data-icon-tpl="info">
        <div class="sa-3d-icon sa-3d-icon--info">
            <svg class="sa-3d-svg" viewBox="0 0 96 96" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <radialGradient id="saGlowInfo" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="#3B82F6" stop-opacity="0.55"/>
                        <stop offset="100%" stop-color="#3B82F6" stop-opacity="0"/>
                    </radialGradient>
                    <linearGradient id="saSphereInfo" x1="24" y1="18" x2="72" y2="78" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#60A5FA"/>
                        <stop offset="50%" stop-color="#2563EB"/>
                        <stop offset="100%" stop-color="#1E3A8A"/>
                    </linearGradient>
                    <linearGradient id="saRingInfo" x1="14" y1="48" x2="82" y2="48" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#93C5FD" stop-opacity="0.9"/>
                        <stop offset="100%" stop-color="#1D4ED8" stop-opacity="0.2"/>
                    </linearGradient>
                    <filter id="saDropInfo" x="0" y="0" width="96" height="96" filterUnits="userSpaceOnUse">
                        <feDropShadow dx="0" dy="6" stdDeviation="6" flood-color="#172554" flood-opacity="0.6"/>
                    </filter>
                </defs>
                <circle cx="48" cy="48" r="44" fill="url(#saGlowInfo)"/>
                <ellipse cx="48" cy="48" rx="38" ry="14" transform="rotate(-26 48 48)" stroke="url(#saRingInfo)" stroke-width="3" stroke-dasharray="7 3.5"/>
                <circle cx="48" cy="48" r="28" fill="url(#saSphereInfo)" filter="url(#saDropInfo)"/>
                <ellipse cx="38" cy="34" rx="12" ry="7" transform="rotate(-30 38 34)" fill="white" fill-opacity="0.45"/>
                <path d="M48 37V39M48 46V59" stroke="white" stroke-width="5" stroke-linecap="round"/>
                <circle cx="72" cy="34" r="2" fill="#93C5FD" fill-opacity="0.8"/>
            </svg>
        </div>
    </div>

    {{-- 5. CONFIRMATION (Indigo/Violet Dual-Ring Orb + Question Mark) --}}
    <div data-icon-tpl="confirm">
        <div class="sa-3d-icon sa-3d-icon--confirm">
            <svg class="sa-3d-svg" viewBox="0 0 96 96" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <radialGradient id="saGlowConfirm" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="#6366F1" stop-opacity="0.55"/>
                        <stop offset="100%" stop-color="#6366F1" stop-opacity="0"/>
                    </radialGradient>
                    <linearGradient id="saSphereConfirm" x1="24" y1="18" x2="72" y2="78" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#818CF8"/>
                        <stop offset="50%" stop-color="#4F46E5"/>
                        <stop offset="100%" stop-color="#312E81"/>
                    </linearGradient>
                    <linearGradient id="saRingConfirm" x1="14" y1="48" x2="82" y2="48" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#C7D2FE" stop-opacity="0.9"/>
                        <stop offset="100%" stop-color="#4338CA" stop-opacity="0.2"/>
                    </linearGradient>
                    <filter id="saDropConfirm" x="0" y="0" width="96" height="96" filterUnits="userSpaceOnUse">
                        <feDropShadow dx="0" dy="6" stdDeviation="6" flood-color="#1E1B4B" flood-opacity="0.6"/>
                    </filter>
                </defs>
                <circle cx="48" cy="48" r="44" fill="url(#saGlowConfirm)"/>
                <ellipse cx="48" cy="48" rx="38" ry="14" transform="rotate(30 48 48)" stroke="url(#saRingConfirm)" stroke-width="3" stroke-dasharray="7 3.5"/>
                <circle cx="48" cy="48" r="28" fill="url(#saSphereConfirm)" filter="url(#saDropConfirm)"/>
                <ellipse cx="38" cy="34" rx="12" ry="7" transform="rotate(-30 38 34)" fill="white" fill-opacity="0.45"/>
                <path d="M42 39C42 35.6 44.7 34 48 34C51.3 34 54 36 54 39C54 42.5 50 43.5 48 47V50M48 57V59" stroke="white" stroke-width="4.5" stroke-linecap="round"/>
            </svg>
        </div>
    </div>

    {{-- 6. DELETE CONFIRMATION (Fire Crimson Hazard Shield + 3D Trash) --}}
    <div data-icon-tpl="delete">
        <div class="sa-3d-icon sa-3d-icon--delete">
            <svg class="sa-3d-svg" viewBox="0 0 96 96" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <radialGradient id="saGlowDel" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="#DC2626" stop-opacity="0.6"/>
                        <stop offset="100%" stop-color="#DC2626" stop-opacity="0"/>
                    </radialGradient>
                    <linearGradient id="saShieldDel" x1="22" y1="16" x2="74" y2="78" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#EF4444"/>
                        <stop offset="45%" stop-color="#DC2626"/>
                        <stop offset="100%" stop-color="#7F1D1D"/>
                    </linearGradient>
                    <filter id="saDropDel" x="0" y="0" width="96" height="96" filterUnits="userSpaceOnUse">
                        <feDropShadow dx="0" dy="6" stdDeviation="6" flood-color="#450A0A" flood-opacity="0.7"/>
                    </filter>
                </defs>
                <circle cx="48" cy="48" r="44" fill="url(#saGlowDel)"/>
                <path d="M48 18L74 32V60L48 76L22 60V32L48 18Z" fill="url(#saShieldDel)" filter="url(#saDropDel)"/>
                <path d="M48 22L68 34V56L48 48L28 56V34L48 22Z" fill="white" fill-opacity="0.2"/>
                <path d="M37 41H59M43 41V35H53V41M40 45L42 63H54L56 45M45 48V59M51 48V59" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="28" cy="30" r="2" fill="#FCA5A5" fill-opacity="0.8"/>
                <circle cx="68" cy="66" r="2" fill="#EF4444" fill-opacity="0.8"/>
            </svg>
        </div>
    </div>

    {{-- 7. LOGOUT CONFIRMATION (Electric Blue / Dark Navy Portal) --}}
    <div data-icon-tpl="logout">
        <div class="sa-3d-icon sa-3d-icon--logout">
            <svg class="sa-3d-svg" viewBox="0 0 96 96" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <radialGradient id="saGlowLogout" cx="50%" cy="50%" r="50%">
                        <stop offset="0%" stop-color="#3B82F6" stop-opacity="0.55"/>
                        <stop offset="100%" stop-color="#3B82F6" stop-opacity="0"/>
                    </radialGradient>
                    <linearGradient id="saSphereLogout" x1="24" y1="18" x2="72" y2="78" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#60A5FA"/>
                        <stop offset="45%" stop-color="#2563EB"/>
                        <stop offset="100%" stop-color="#0F172A"/>
                    </linearGradient>
                    <filter id="saDropLogout" x="0" y="0" width="96" height="96" filterUnits="userSpaceOnUse">
                        <feDropShadow dx="0" dy="6" stdDeviation="6" flood-color="#020617" flood-opacity="0.7"/>
                    </filter>
                </defs>
                <circle cx="48" cy="48" r="44" fill="url(#saGlowLogout)"/>
                <circle cx="48" cy="48" r="28" fill="url(#saSphereLogout)" filter="url(#saDropLogout)"/>
                <ellipse cx="38" cy="34" rx="12" ry="7" transform="rotate(-30 38 34)" fill="white" fill-opacity="0.4"/>
                <path d="M45 36H36V60H45M54 44L60 48L54 52M60 48H44" stroke="white" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
    </div>
</div>

{{-- ===========================================================================
     Interactive Confirmation Dialog (Glassmorphism + Dark Premium)
     Triggered by data-confirm forms, delete actions, logout, or SmartPopup.confirm()
     =========================================================================== --}}
<div class="modal fade sa-popup-modal sa-confirm-modal" id="saConfirmModal" tabindex="-1" aria-labelledby="saConfirmTitle" aria-describedby="saConfirmMessage" aria-hidden="true" data-variant="danger">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content sa-popup-card">
            {{-- Close Button --}}
            <button type="button" class="btn-close sa-popup-close" data-bs-dismiss="modal" aria-label="Tutup"></button>

            {{-- 3D Animated Icon Stage --}}
            <div class="sa-popup-icon-stage" id="saConfirmIconStage" aria-hidden="true">
                {{-- Dynamically injected 3D icon based on variant --}}
            </div>

            {{-- Badge --}}
            <div class="text-center mb-2">
                <span class="sa-popup-badge" id="saConfirmBadge">Konfirmasi Tindakan</span>
            </div>

            {{-- Title & Message --}}
            <h4 class="sa-popup-title text-center" id="saConfirmTitle">Konfirmasi Tindakan</h4>
            <p class="sa-popup-message text-center" id="saConfirmMessage">Apakah Anda yakin ingin melanjutkan?</p>

            {{-- Actions --}}
            <div class="sa-popup-actions" id="saConfirmActions">
                <button type="button" class="btn sa-popup-btn sa-popup-btn-ghost" data-bs-dismiss="modal" id="saConfirmCancel">
                    Batal
                </button>
                <button type="button" class="btn sa-popup-btn sa-popup-btn-danger" id="saConfirmOk">
                    Ya, Lanjutkan
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ===========================================================================
     Notification Flash Popup (Glassmorphism + Dark Premium)
     Auto-triggered on page load for success, error, warning, info, and validations
     =========================================================================== --}}
<div class="modal fade sa-popup-modal" id="saFlashModal" tabindex="-1" aria-labelledby="saFlashTitle" aria-describedby="saFlashMessage" aria-hidden="true" data-variant="info">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content sa-popup-card">
            {{-- Close Button --}}
            <button type="button" class="btn-close sa-popup-close" data-bs-dismiss="modal" aria-label="Tutup"></button>

            {{-- 3D Animated Icon Stage --}}
            <div class="sa-popup-icon-stage" id="saFlashIconStage" aria-hidden="true">
                {{-- Injected 3D Icon --}}
            </div>

            {{-- Badge --}}
            <div class="text-center mb-2">
                <span class="sa-popup-badge" id="saFlashBadge">Pemberitahuan Sistem</span>
            </div>

            {{-- Title & Message --}}
            <h4 class="sa-popup-title text-center" id="saFlashTitle">Informasi</h4>
            <p class="sa-popup-message text-center" id="saFlashMessage">—</p>

            {{-- Actions --}}
            <div class="sa-popup-actions text-center justify-content-center">
                <button type="button" class="btn sa-popup-btn sa-popup-btn-primary px-4" data-bs-dismiss="modal" id="saFlashOk">
                    Mengerti
                </button>
            </div>
        </div>
    </div>
</div>
