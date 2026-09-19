{{--
    Live password policy feedback.

    Include it directly under a password input. The script watches the input and
    ticks each rule off as it is satisfied, so the user never has to guess what
    "strong password" means before submitting.

    Usage:
        @include('auth.partials.password-rules', ['pwField' => 'password'])
--}}
@php $pwField = $pwField ?? 'password'; @endphp

<div class="pw-rules" data-pw-for="{{ $pwField }}">
    <div class="pw-meter" aria-hidden="true"><span></span></div>
    <ul class="pw-checklist">
        <li data-rule="length">At least 8 characters</li>
        <li data-rule="lower">A lowercase letter (a&ndash;z)</li>
        <li data-rule="upper">An uppercase letter (A&ndash;Z)</li>
        <li data-rule="number">A number (0&ndash;9)</li>
        <li data-rule="symbol">A symbol such as ! @ # $ %</li>
    </ul>
</div>

<style>
    .pw-rules { margin-top: .6rem; }
    .pw-meter { height: 5px; border-radius: 999px; background: #E7DFC7; overflow: hidden; }
    .pw-meter > span { display: block; height: 100%; width: 0; border-radius: 999px; background: #A23B32; transition: width .2s ease, background .2s ease; }
    .pw-checklist { list-style: none; margin: .6rem 0 0; padding: 0; display: grid; gap: .32rem; }
    .pw-checklist li { display: flex; align-items: center; gap: .5rem; font-size: .78rem; line-height: 1.35; color: var(--ink-600, #5B6678); transition: color .15s ease; }
    .pw-checklist li::before {
        content: ""; flex: 0 0 auto; width: 14px; height: 14px; border-radius: 50%;
        border: 1.5px solid currentColor; opacity: .5; transition: color .15s ease, background .15s ease, opacity .15s ease;
    }
    .pw-checklist li.ok { color: #2F7A4D; }
    .pw-checklist li.ok::before {
        content: "\2713"; opacity: 1; border-color: #2F7A4D; background: rgba(47, 122, 77, .12);
        font-size: 9px; line-height: 11px; text-align: center; font-weight: 700;
    }
</style>

<script>
(function () {
    function initPasswordRules() {
        document.querySelectorAll('.pw-rules[data-pw-for]').forEach(function (box) {
            var input = document.getElementById(box.getAttribute('data-pw-for'));
            if (!input) return;

            var items = box.querySelectorAll('.pw-checklist li[data-rule]');
            var bar = box.querySelector('.pw-meter > span');

            var tests = {
                length: function (v) { return v.length >= 8; },
                lower:  function (v) { return /[a-z]/.test(v); },
                upper:  function (v) { return /[A-Z]/.test(v); },
                number: function (v) { return /[0-9]/.test(v); },
                symbol: function (v) { return /[^A-Za-z0-9]/.test(v); }
            };

            function refresh() {
                var value = input.value || '';
                var passed = 0;

                items.forEach(function (item) {
                    var test = tests[item.getAttribute('data-rule')];
                    var ok = test ? test(value) : false;
                    item.classList.toggle('ok', ok);
                    if (ok) passed++;
                });

                if (!bar) return;

                bar.style.width = Math.round((passed / items.length) * 100) + '%';
                bar.style.background = passed <= 2 ? '#A23B32' : (passed <= 4 ? '#C9A227' : '#2F7A4D');
            }

            input.addEventListener('input', refresh);
            refresh();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPasswordRules);
    } else {
        initPasswordRules();
    }
})();
</script>
