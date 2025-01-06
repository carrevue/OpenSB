console.log(
    "%cOpenSB " + opensb_version,
    "color: blue; font-family: sans-serif; font-size: 4em;"
);
console.log(
    "%cWarning: If someone instructs you to copy and paste content here, they may be attempting to access your account information.",
    "color: red; font-family: monospace; font-size: 2em"
);

const sbOptions = document.cookie.split('; ').find(row => row.startsWith('SBOPTIONS='));

if (sbOptions) {
    const encodedOptions = sbOptions.split('=')[1];
    const decodedOptions = decodeURIComponent(encodedOptions);
    const options = JSON.parse(atob(decodedOptions));
    console.log(options);
}

function updateConfig(key, value) {
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