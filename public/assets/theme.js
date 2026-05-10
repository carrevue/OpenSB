document.addEventListener('DOMContentLoaded', function () {
    // init this shit
    updateSkinThemes();
    updatePreview();
});


function updateSkinThemes() {
    const skinSelect = document.getElementById('skin');
    const themeSelect = document.getElementById('theme');
    const selectedSkin = skinSelect.options[skinSelect.selectedIndex];
    const skinValue = selectedSkin.value;
    const themes = JSON.parse(selectedSkin.getAttribute('data-themes'));

    themeSelect.innerHTML = '';

    // get all themes within skin
    Object.entries(themes).forEach(([id, theme]) => {
        const skinThemeValue = `${skinValue},${id}`;
        const option = new Option(theme.name, skinThemeValue);
        option.selected = skinThemeValue === currentSkinAndTheme;
        option.dataset.previewUrl = `/assets/previews/${skinValue}_${id}.png`;
        option.dataset.name = theme.name;
        option.dataset.description = theme.description;
        option.dataset.author = theme.author;
        themeSelect.appendChild(option);
    });

    if (typeof weOnTrinium !== "undefined" && weOnTrinium) {
        document.getElementById('notFullySupported').style.display =
            skinValue === "bootstrap" ? "inline" : "none";
    }

    document.getElementById('skinName').textContent = selectedSkin.textContent;
    document.getElementById('skinDescription').textContent = selectedSkin.dataset.description || 'No description available.';
    document.getElementById('skinAuthor').textContent = selectedSkin.dataset.author ? `By ${selectedSkin.dataset.author}` : '';
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