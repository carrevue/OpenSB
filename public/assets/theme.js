// TODO: merge this into common.js -chaziz 05/25/2025

function updatePreview() {
    const themeSelect = document.getElementById('theme');
    const selectedOption = themeSelect.options[themeSelect.selectedIndex];
    const previewUrl = selectedOption.getAttribute('data-preview-url');
    const description = selectedOption.getAttribute('data-description');
    const author = selectedOption.getAttribute('data-author');

    const themePreview = document.getElementById('themePreview');

    const themeDescription = document.getElementById('themeDescription');
    const themeAuthor = document.getElementById('themeAuthor');

    if (previewUrl) {
        themePreview.src = previewUrl;
        themePreview.style.display = 'block';
    } else {
        themePreview.style.display = 'none';
    }

    themeDescription.textContent = description || 'No description available.';
    themeAuthor.textContent = author ? `By ${author}` : '';

    // only on charla frontend (for now)
    if (weOnCharlaFrontendLmao ?? false) {
        const skinName = selectedOption.getAttribute('data-skin-name');
        const skinDescription = selectedOption.getAttribute('data-skin-description');
        const skinAuthor = selectedOption.getAttribute('data-skin-author');

        const skinThemeName = selectedOption.getAttribute('data-name');

        const skinNameElement = document.getElementById('skinName');
        const skinDescriptionElement = document.getElementById('skinDescription');
        const skinAuthorElement = document.getElementById('skinAuthor');
        const skinThemeNameElement = document.getElementById('themeName');

        skinNameElement.textContent = skinName || 'No skin name specified';
        skinDescriptionElement.textContent = skinDescription || 'No description available.';
        skinAuthorElement.textContent = skinAuthor ? `By ${skinAuthor}` : '';
        skinThemeNameElement.textContent = skinThemeName || 'No theme name specified';
    }
}

window.onload = updatePreview;