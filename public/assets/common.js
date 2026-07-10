console.log(
    "%cOpenSB " + opensb_version,
    "color: #0069B4; font-family: sans-serif; font-size: 2em;"
);
console.log(
    "%cWarning: DO NOT PASTE ANYTHING HERE. If someone is asking you to paste something here, they could be trying to steal your account.",
    "color: red; font-family: monospace; font-size: 1em;"
);

let options = {};

const row = document.cookie
    .split('; ')
    .find(r => r.startsWith('SBOPTIONS='));

if (row) {
    try {
        options = JSON.parse(
            atob(decodeURIComponent(row.slice('SBOPTIONS='.length)))
        );
        console.table(options);
    } catch (e) {
        console.warn('Invalid SBOPTIONS', e);
    }
}

function setOptions(patch) {
    Object.assign(options, patch);

    document.cookie =
        'SBOPTIONS=' +
        encodeURIComponent(btoa(JSON.stringify(options))) +
        '; path=/; SameSite=Lax';
}

if (!options.timezone) {
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    setOptions({
        timezone: timezone
    });
}

function toggleElementDisplay(element, display = "block") {
    if (element.style.display === display) {
        element.style.display = "none";
    } else {
        element.style.display = display;
    }
}

function setUpModal(trigger_button, modal, close_button) {
    if (trigger_button) {
        trigger_button.addEventListener("click", () => {
            modal.showModal();
        });

        close_button.addEventListener("click", () => {
            event.preventDefault();
            modal.close();
        });
        console.debug("Modal set up for", modal.id);
    }
}

async function fetchWithRetry(url, options = {}, maxRetries = 3, baseDelayMs = 500) {
    console.debug(`Fetching ${url}`);
    for (let attempt = 0; attempt <= maxRetries; attempt++) {
        let response;
        try {
            response = await fetch(url, options);
        } catch (err) {
            if (attempt === maxRetries) {
                throw err;
            }
            const delayMs = baseDelayMs * 2 ** attempt;
            console.log(`Network error fetching ${url} (${err.message}). Retrying in ${delayMs}ms (attempt ${attempt + 1}/${maxRetries})`);
            await new Promise(resolve => setTimeout(resolve, delayMs));
            continue;
        }

        if (response.status !== 429 || attempt === maxRetries) {
            return response;
        }

        const delayMs = baseDelayMs * 2 ** attempt;
        console.log(`Rate limited while trying to fetch ${url}. Retrying in ${delayMs}ms (attempt ${attempt + 1}/${maxRetries})`);
        await new Promise(resolve => setTimeout(resolve, delayMs));
    }
}