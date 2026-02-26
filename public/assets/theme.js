document.addEventListener('DOMContentLoaded', function () {
    // init this shit
    updateSkinThemes();
    updatePreview();
});


function updateSkinThemes() {
    const skinSelect = document.getElementById('skin');
    const themeSelect = document.getElementById('theme');
    const selectedSkin = skinSelect.options[skinSelect.selectedIndex];
    const themes = JSON.parse(selectedSkin.getAttribute('data-themes'));

    themeSelect.innerHTML = '';

    // get all themes within skin
    themes.forEach(theme => {
        const option = document.createElement('option');
        const skinThemeValue = `${selectedSkin.value},${theme.id}`;

        option.value = skinThemeValue;
        option.textContent = theme.name;
        option.setAttribute('data-preview-url', `/assets/previews/${selectedSkin.value}_${theme.id}.png`);
        option.setAttribute('data-name', theme.name);
        option.setAttribute('data-description', theme.description);
        option.setAttribute('data-author', theme.author);

        if (skinThemeValue === currentSkinAndTheme) {
            option.selected = true;
        }

        themeSelect.appendChild(option);
    });

    if (typeof weOnTrinium !== "undefined" && weOnTrinium) {
        const theWarning = document.getElementById('notFullySupported');

        if (selectedSkin.value == "bootstrap") {
            theWarning.style.display = "inline";
        } else {
            theWarning.style.display = "none";
        }
    }

    // update skin/theme info
    document.getElementById('skinName').textContent = selectedSkin.textContent;
    document.getElementById('skinDescription').textContent = selectedSkin.getAttribute('data-description') || 'No description available.';
    document.getElementById('skinAuthor').textContent = selectedSkin.getAttribute('data-author') ? `By ${selectedSkin.getAttribute('data-author')}` : '';
    updatePreview();
}

// update theme preview and info
function updatePreview() {
    const themeSelect = document.getElementById('theme');
    const selectedOption = themeSelect.options[themeSelect.selectedIndex];
    const previewUrl = selectedOption.getAttribute('data-preview-url');
    const themePreview = document.getElementById('themePreview');

    document.getElementById('themeName').textContent = selectedOption.getAttribute('data-name') || 'No theme name specified';
    document.getElementById('themeDescription').textContent = selectedOption.getAttribute('data-description') || 'No description available.';
    document.getElementById('themeAuthor').textContent = selectedOption.getAttribute('data-author') ? `By ${selectedOption.getAttribute('data-author')}` : '';

    themePreview.src = previewUrl;
}