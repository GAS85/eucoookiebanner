{**
 * EU Cookie Banner – front-office overlay template
 * Assigned variables:
 *   {$eucb_text}      – HTML-safe banner text from admin config
 *   {$eucb_leave_url} – URL to redirect to when user "leaves"
 *}

<div id="eucb-overlay" class="eucb-overlay" role="dialog" aria-modal="true"
     aria-label="{l s='Cookie Consent' mod='eucoookiebanner'}"
     data-leave-url="{$eucb_leave_url|escape:'htmlall':'UTF-8'}">

    <div class="eucb-backdrop"></div>

    <div class="eucb-modal">

        <div class="eucb-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none">
                <circle cx="32" cy="32" r="30" stroke="currentColor" stroke-width="3"/>
                <circle cx="22" cy="24" r="4" fill="currentColor"/>
                <circle cx="42" cy="20" r="3" fill="currentColor"/>
                <circle cx="44" cy="38" r="5" fill="currentColor"/>
                <circle cx="26" cy="42" r="3" fill="currentColor"/>
                <circle cx="35" cy="30" r="2" fill="currentColor"/>
                <circle cx="18" cy="38" r="2.5" fill="currentColor"/>
            </svg>
        </div>

        <h2 class="eucb-title">{l s='Cookie Notice' mod='eucoookiebanner'}</h2>

        <div class="eucb-text">{$eucb_text nofilter}</div>

        <div class="eucb-actions">
            <button id="eucb-agree" class="eucb-btn eucb-btn--agree" type="button">
                <span class="eucb-btn-icon" aria-hidden="true">✓</span>
                {l s='I Agree' mod='eucoookiebanner'}
            </button>

            <a id="eucb-leave"
               class="eucb-btn eucb-btn--leave"
               href="{$eucb_leave_url|escape:'htmlall':'UTF-8'}"
               rel="noopener noreferrer">
                {l s='Leave Shop' mod='eucoookiebanner'}
            </a>
        </div>

        <p class="eucb-note">
            {l s='By continuing to use this website you agree to our cookie policy.' mod='eucoookiebanner'}
        </p>

    </div>
</div>
