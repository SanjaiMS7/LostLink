/* LostLink - client-side behaviour.
   Client checks only improve usability; every check is repeated in PHP. */
document.addEventListener('DOMContentLoaded', function () {

    /* ---- Responsive navigation ---- */
    var toggle = document.getElementById('navToggle');
    var links = document.getElementById('navLinks');
    if (toggle && links) {
        toggle.addEventListener('click', function () {
            var open = links.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    /* ---- Alerts: close button and auto-hide of success messages ---- */
    document.querySelectorAll('.alert-close').forEach(function (btn) {
        btn.addEventListener('click', function () { btn.closest('.alert').remove(); });
    });
    document.querySelectorAll('.alert-success').forEach(function (a) {
        setTimeout(function () { if (a.parentNode) { a.remove(); } }, 6000);
    });

    /* ---- Confirmation dialogs for destructive actions ---- */
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.getAttribute('data-confirm'))) { e.preventDefault(); }
        });
    });

    /* ---- Form validation ---- */
    function clearError(field) {
        field.classList.remove('invalid');
        var next = field.nextElementSibling;
        if (next && next.classList.contains('field-error')) { next.remove(); }
    }
    function showError(field, msg) {
        clearError(field);
        field.classList.add('invalid');
        var span = document.createElement('span');
        span.className = 'field-error';
        span.textContent = msg;
        field.insertAdjacentElement('afterend', span);
    }

    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var firstBad = null;
            var pw = form.querySelector('#password');
            var cf = form.querySelector('#confirm_password');

            form.querySelectorAll('input, select, textarea').forEach(function (f) {
                if (f.type === 'hidden' || f.type === 'radio' || f.type === 'file' || f.disabled) { return; }
                clearError(f);
                var v = f.value.trim();
                var msg = '';
                if (f.required && v === '') {
                    msg = 'This field is required.';
                } else if (v !== '') {
                    if (f.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) { msg = 'Enter a valid e-mail address.'; }
                    else if (f.minLength > 0 && v.length < f.minLength) { msg = 'Enter at least ' + f.minLength + ' characters.'; }
                    else if (f.pattern && !new RegExp('^(?:' + f.pattern + ')$').test(v)) { msg = 'Please match the requested format.'; }
                    else if (f.name === 'phone' && !/^[0-9+\-\s]{10,15}$/.test(v)) { msg = 'Enter a valid contact number (10 to 15 digits).'; }
                    else if (f.type === 'date' && f.max && v > f.max) { msg = 'The date cannot be in the future.'; }
                }
                if (!msg && f.id === 'password' && f.autocomplete === 'new-password' && !/(?=.*[A-Za-z])(?=.*\d).{8,}/.test(f.value)) {
                    msg = 'Use at least 8 characters with a letter and a digit.';
                }
                if (msg) { showError(f, msg); if (!firstBad) { firstBad = f; } }
            });

            if (pw && cf && cf.value !== '' && pw.value !== cf.value) {
                showError(cf, 'The passwords do not match.');
                if (!firstBad) { firstBad = cf; }
            }
            if (firstBad) { e.preventDefault(); firstBad.focus(); }
        });
        form.addEventListener('input', function (e) {
            if (e.target.classList && e.target.classList.contains('invalid')) { clearError(e.target); }
        });
    });

    /* ---- Report form: lost / found switch ---- */
    var typeRadios = document.querySelectorAll('input[name="type"][type="radio"]');
    var keptGroup = document.getElementById('keptAtGroup');
    var keptInput = document.getElementById('kept_at');
    var locLabel = document.getElementById('locationLabel');
    var dateLabel = document.getElementById('dateLabel');
    function applyType() {
        if (!typeRadios.length) { return; }
        var type = document.querySelector('input[name="type"]:checked').value;
        if (keptGroup) { keptGroup.hidden = (type !== 'found'); }
        if (keptInput) { keptInput.required = (type === 'found'); }
        if (locLabel) { locLabel.textContent = type === 'found' ? 'Place where it was found' : 'Place where it was last seen'; }
        if (dateLabel) { dateLabel.textContent = type === 'found' ? 'Date found' : 'Date lost'; }
    }
    typeRadios.forEach(function (r) { r.addEventListener('change', applyType); });
    applyType();

    /* ---- Description character counter ---- */
    var desc = document.getElementById('description');
    var counter = document.getElementById('descCount');
    if (desc && counter) {
        var upd = function () { counter.textContent = desc.value.length; };
        desc.addEventListener('input', upd);
        upd();
    }

    /* ---- Photograph preview and basic check ---- */
    var imgInput = document.getElementById('image');
    var preview = document.getElementById('imagePreview');
    if (imgInput && preview) {
        imgInput.addEventListener('change', function () {
            clearError(imgInput);
            var file = imgInput.files && imgInput.files[0];
            if (!file) { preview.hidden = true; return; }
            if (!/^image\/(jpeg|png|gif|webp)$/.test(file.type)) {
                showError(imgInput, 'Only JPG, PNG, GIF or WEBP photographs are allowed.');
                imgInput.value = ''; preview.hidden = true; return;
            }
            if (file.size > 2 * 1024 * 1024) {
                showError(imgInput, 'The photograph must be 2 MB or smaller.');
                imgInput.value = ''; preview.hidden = true; return;
            }
            var reader = new FileReader();
            reader.onload = function (ev) { preview.src = ev.target.result; preview.hidden = false; };
            reader.readAsDataURL(file);
        });
    }

    /* ---- Item details: show the claim form ---- */
    var claimToggle = document.getElementById('claimToggle');
    var claimForm = document.getElementById('claimForm');
    if (claimToggle && claimForm) {
        claimToggle.addEventListener('click', function () {
            claimForm.hidden = !claimForm.hidden;
            if (!claimForm.hidden) { claimForm.querySelector('textarea').focus(); }
        });
    }

    /* ---- Search interaction: auto-apply filters ---- */
    var filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.querySelectorAll('select').forEach(function (s) {
            s.addEventListener('change', function () { filterForm.submit(); });
        });
        var search = filterForm.querySelector('input[type="search"]');
        if (search) {
            var timer;
            search.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () { filterForm.submit(); }, 700);
            });
            // keep the cursor at the end after reload
            if (search.value && document.activeElement === document.body && location.search.indexOf('q=') !== -1) {
                search.focus();
                search.setSelectionRange(search.value.length, search.value.length);
            }
        }
    }
});
