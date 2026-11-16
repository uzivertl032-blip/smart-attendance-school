{{-- Cinematic one-per-session opening reveal --}}
<div class="sa-intro" id="saIntro" aria-label="Animasi pembuka SmartAttendance School Edition">
    <div class="sa-intro__grid" aria-hidden="true"></div>
    <div class="sa-intro__glow" aria-hidden="true"></div>

    <div class="sa-intro__particles" aria-hidden="true">
        @for ($particle = 0; $particle < 18; $particle++)
            <span style="--sa-x: {{ ($particle * 37 + 11) % 100 }}%; --sa-y: {{ ($particle * 53 + 7) % 100 }}%; --sa-delay: {{ number_format(($particle % 9) * 0.32, 2) }}s; --sa-size: {{ 2 + ($particle % 4) }}px; --sa-duration: {{ 5 + ($particle % 5) }}s;"></span>
        @endfor
    </div>

    <div class="sa-intro__stage">
        <div class="sa-intro__logo-wrap">
            <span class="sa-intro__ring sa-intro__ring--1" aria-hidden="true"></span>
            <span class="sa-intro__ring sa-intro__ring--2" aria-hidden="true"></span>
            <span class="sa-intro__ring sa-intro__ring--3" aria-hidden="true"></span>
            <span class="sa-intro__spark sa-intro__spark--1" aria-hidden="true"></span>
            <span class="sa-intro__spark sa-intro__spark--2" aria-hidden="true"></span>
            <span class="sa-intro__spark sa-intro__spark--3" aria-hidden="true"></span>
            <span class="sa-intro__spark sa-intro__spark--4" aria-hidden="true"></span>

            <div class="sa-intro__logo">
                <i class="bi bi-person-bounding-box" aria-hidden="true"></i>
            </div>
        </div>

        <div class="sa-intro__wordmark">Smart<span>Attendance</span></div>
        <div class="sa-intro__edition">School Edition</div>

        <div class="sa-intro__progress">
            <div class="sa-intro__bar" role="progressbar" aria-label="Progres pembuka" aria-valuemin="0" aria-valuemax="100">
                <span id="saIntroBar"></span>
            </div>
            <div class="sa-intro__meta">
                <span id="saIntroLabel">Menyiapkan pengalaman premium...</span>
                <span class="sa-intro__percent" id="saIntroPercent" aria-live="polite">0%</span>
            </div>
        </div>
    </div>

    <button type="button" class="sa-intro__skip" id="saIntroSkip">
        Lewati <i class="bi bi-arrow-right" aria-hidden="true"></i>
    </button>
</div>
