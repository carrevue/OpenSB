console.log(
    "%cOpenSB " + opensb_version,
    "color: #0069B4; font-family: sans-serif; font-size: 2em;"
);
console.log(
    "%cWarning: DO NOT PASTE ANYTHING HERE. If someone is asking you to paste something here, they could be trying to steal your account.",
    "color: red; font-family: monospace; font-size: 1em;"
);

const sbOptions = document.cookie.split('; ').find(row => row.startsWith('SBOPTIONS='));

if (sbOptions) {
    try {
        const value = sbOptions.startsWith('SBOPTIONS=')
            ? sbOptions.slice('SBOPTIONS='.length)
            : sbOptions;

        const decodedOptions = decodeURIComponent(value);
        const options = JSON.parse(atob(decodedOptions));

        console.table(options);
    } catch (e) {
        console.warn('Invalid SBOPTIONS', e);
    }
}

function setOptions(patch) {
    let options = {};

    if (typeof sbOptions === 'string') {
        try {
            options = JSON.parse(
                atob(decodeURIComponent(sbOptions.replace(/^SBOPTIONS=/, '')))
            );
        } catch {}
    }

    Object.assign(options, patch);

    document.cookie =
        'SBOPTIONS=' +
        encodeURIComponent(btoa(JSON.stringify(options))) +
        '; path=/; SameSite=Lax';
}

function toggleElementDisplay(element) {
    if (element.style.display === "block") {
        element.style.display = "none";
    } else {
        element.style.display = "block";
    }
}

function setUpModal(trigger_button, modal, close_button) {
    if (trigger_button) {
        trigger_button.addEventListener("click", () => {
            modal.showModal();
        });

        close_button.addEventListener("click", () => {
            modal.close();
        });
        console.debug("Modal set up for", modal.id);
    }
}