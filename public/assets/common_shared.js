console.log(
    "%cOpenSB " + opensb_version,
    "color: #0069B4; font-family: sans-serif; font-size: 2em;"
);
console.log(
    "%cWarning: DO NOT PASTE ANYTHING SUSPICIOUS. If someone is asking you to paste something here, they may be attempting to steal your account.",
    "color: red; font-family: monospace; font-size: 1em"
);

const sbOptions = document.cookie.split('; ').find(row => row.startsWith('SBOPTIONS='));

if (sbOptions) {
    const encodedOptions = sbOptions.split('=')[1];
    const decodedOptions = decodeURIComponent(encodedOptions);
    const options = JSON.parse(atob(decodedOptions));
    console.log(options);
}

function setConfig(key, value) {
    let options = {};

    if (sbOptions) {
        const encodedOptions = sbOptions.split('=')[1];
        const decodedOptions = decodeURIComponent(encodedOptions);
        options = JSON.parse(atob(decodedOptions));
    }

    options[key] = value;

    // turn into json, encoded into base64 and then Idfk
    const updatedOptions = btoa(JSON.stringify(options));
    const encodedUpdatedOptions = encodeURIComponent(updatedOptions);

    // set the cookie
    document.cookie = `SBOPTIONS=${encodedUpdatedOptions}; path=/; SameSite=Lax`;
}