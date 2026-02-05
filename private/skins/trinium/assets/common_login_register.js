function error(error) {
    console.error("OpenSB Trinium Skin Error: " + error);
}

let typingTimeout;
const TYPING_DELAY = 300;

document.addEventListener("DOMContentLoaded", () => {
    if (!document.documentElement.className) {
        return;
    }

    let page = document.documentElement.className;

    if (page == "register") {
        const usernameInput = document.querySelector('input[name="username"]');

        usernameInput.addEventListener('input', () => {
            clearTimeout(typingTimeout);

            typingTimeout = setTimeout(() => {
                console.debug(usernameInput.value);
            }, TYPING_DELAY);
        });
    }
});