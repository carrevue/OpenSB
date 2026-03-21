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