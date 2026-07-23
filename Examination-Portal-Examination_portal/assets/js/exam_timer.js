// exam_timer.js — Countdown timer with auto-submit
(function () {
    'use strict';

    // ── State ────────────────────────────────────────────────────────────────
    let totalSeconds    = 0;
    let remainingSeconds = 0;
    let timerInterval   = null;
    let answered        = {};      // { questionId: optionValue }
    let currentQuestion = 1;
    let totalQuestions  = 0;
    let examId          = 0;
    let autoSubmitForm  = null;

    // ── Init ─────────────────────────────────────────────────────────────────
    function init() {
        const timerEl = document.getElementById('timer-display');
        if (!timerEl) return;

        totalSeconds     = parseInt(timerEl.dataset.seconds, 10);
        remainingSeconds = totalSeconds;
        examId           = parseInt(timerEl.dataset.examId, 10);
        totalQuestions   = parseInt(document.getElementById('total-questions').value, 10);
        autoSubmitForm   = document.getElementById('exam-form');

        startTimer();
        setupOptionListeners();
        setupNavigation();
        updatePalette();
        showQuestion(1);

        // Warn before leaving
        window.addEventListener('beforeunload', function (e) {
            e.preventDefault();
            e.returnValue = '';
        });
    }

    // ── Timer ────────────────────────────────────────────────────────────────
    function startTimer() {
        updateTimerDisplay();
        timerInterval = setInterval(function () {
            remainingSeconds--;
            updateTimerDisplay();

            if (remainingSeconds <= 0) {
                clearInterval(timerInterval);
                autoSubmit();
            }
        }, 1000);
    }

    function updateTimerDisplay() {
        const display = document.getElementById('timer-display');
        if (!display) return;

        const h = Math.floor(remainingSeconds / 3600);
        const m = Math.floor((remainingSeconds % 3600) / 60);
        const s = remainingSeconds % 60;

        const hStr = h > 0 ? pad(h) + ':' : '';
        display.textContent = hStr + pad(m) + ':' + pad(s);

        // Color coding
        const pct = remainingSeconds / totalSeconds;
        display.className = 'timer-display';
        if (pct <= 0.1)       display.classList.add('danger');
        else if (pct <= 0.25) display.classList.add('warning');
    }

    function pad(n) { return String(n).padStart(2, '0'); }

    // ── Auto-submit ───────────────────────────────────────────────────────────
    function autoSubmit() {
        const autoInput = document.createElement('input');
        autoInput.type  = 'hidden';
        autoInput.name  = 'auto_submit';
        autoInput.value = '1';
        if (autoSubmitForm) {
            autoSubmitForm.appendChild(autoInput);
            showCountdownOverlay();
        }
    }

    function showCountdownOverlay() {
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed; inset: 0; background: rgba(10,10,26,0.97);
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; z-index: 9999; color: #fff; font-family: Inter, sans-serif;
        `;
        overlay.innerHTML = `
            <div style="font-size:4rem; margin-bottom:16px;">⏰</div>
            <h2 style="font-size:1.8rem; font-weight:700; margin-bottom:8px;">Time's Up!</h2>
            <p style="color:#94a3b8; margin-bottom:24px;">Your exam is being submitted automatically…</p>
            <div id="sub-countdown" style="font-size:3rem; font-weight:800; color:#6366f1;">3</div>
        `;
        document.body.appendChild(overlay);

        let count = 3;
        const cd = setInterval(function () {
            count--;
            const el = document.getElementById('sub-countdown');
            if (el) el.textContent = count;
            if (count <= 0) {
                clearInterval(cd);
                window.removeEventListener('beforeunload', function () {});
                autoSubmitForm.submit();
            }
        }, 1000);
    }

    // ── Option Selection ──────────────────────────────────────────────────────
    function setupOptionListeners() {
        document.querySelectorAll('.option-item').forEach(function (item) {
            item.addEventListener('click', function () {
                const radio    = item.querySelector('input[type=radio]');
                const qId      = radio.dataset.qid;
                const value    = radio.value;

                // Deselect others in same question
                const siblings = document.querySelectorAll('[data-qid="' + qId + '"]');
                siblings.forEach(function (r) {
                    r.closest('.option-item').classList.remove('selected');
                });

                radio.checked = true;
                item.classList.add('selected');
                answered[qId] = value;

                // AJAX save answer
                saveAnswer(qId, value);
                updatePalette();
            });
        });
    }

    function saveAnswer(questionId, answer) {
        const fd = new FormData();
        fd.append('question_id', questionId);
        fd.append('answer', answer);
        fd.append('exam_id', examId);
        fd.append('action', 'save_answer');

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (!d.ok) console.warn('Save answer failed'); })
            .catch(function () {});
    }

    // ── Navigation ────────────────────────────────────────────────────────────
    function setupNavigation() {
        const prevBtn = document.getElementById('prev-btn');
        const nextBtn = document.getElementById('next-btn');
        const submitBtn = document.getElementById('submit-btn');

        if (prevBtn) prevBtn.addEventListener('click', function () { showQuestion(currentQuestion - 1); });
        if (nextBtn) nextBtn.addEventListener('click', function () { showQuestion(currentQuestion + 1); });

        // Submit confirm
        if (autoSubmitForm) {
            autoSubmitForm.addEventListener('submit', function (e) {
                if (!e.submitter || e.submitter.id !== 'submit-btn') return;
                e.preventDefault();
                const answered_count = Object.keys(answered).length;
                const unanswered = totalQuestions - answered_count;
                if (unanswered > 0) {
                    if (!confirm('You have ' + unanswered + ' unanswered question(s). Submit anyway?')) return;
                }
                clearInterval(timerInterval);
                window.removeEventListener('beforeunload', function () {});
                autoSubmitForm.submit();
            });
        }
    }

    function showQuestion(n) {
        if (n < 1 || n > totalQuestions) return;
        currentQuestion = n;

        document.querySelectorAll('.question-slide').forEach(function (slide) {
            slide.style.display = 'none';
        });

        const current = document.getElementById('question-' + n);
        if (current) current.style.display = 'block';

        // Update navigation buttons
        const prevBtn = document.getElementById('prev-btn');
        const nextBtn = document.getElementById('next-btn');
        const submitBtn = document.getElementById('submit-btn');

        if (prevBtn) prevBtn.disabled = (n === 1);
        if (nextBtn) nextBtn.style.display = (n === totalQuestions) ? 'none' : 'inline-flex';
        if (submitBtn) submitBtn.style.display = (n === totalQuestions) ? 'inline-flex' : 'none';

        // Update question counter
        const counter = document.getElementById('question-counter');
        if (counter) counter.textContent = n + ' / ' + totalQuestions;

        updatePalette();
    }

    function updatePalette() {
        document.querySelectorAll('.palette-btn').forEach(function (btn) {
            const qNum = parseInt(btn.dataset.q, 10);
            const qEl  = document.getElementById('question-' + qNum);
            btn.className = 'palette-btn';
            if (qNum === currentQuestion) btn.classList.add('current');

            if (qEl) {
                const radios = qEl.querySelectorAll('input[type=radio]');
                let isAnswered = false;
                radios.forEach(function (r) { if (r.checked) isAnswered = true; });
                if (isAnswered) btn.classList.add('answered');
            }
        });
    }

    // ── Palette Click ─────────────────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('palette-btn')) {
            showQuestion(parseInt(e.target.dataset.q, 10));
        }
    });

    // ── Boot ──────────────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
