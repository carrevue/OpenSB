// debug only code
function generateUUID() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
        const r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
}

const dropZone = document.getElementById('dropZone');

dropZone.addEventListener('dragover', e => { e.preventDefault(); });
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    if (e.dataTransfer?.files?.[0]) applyFile(e.dataTransfer.files[0]);
});

function applyFile(file) {
    const dt = new DataTransfer();
    dt.items.add(file);
    document.getElementById('fileInput').files = dt.files;

    document.getElementById('dropZone').style.display = 'none';
    document.getElementById('uploadingView').style.display = 'block';
    document.getElementById('title').value = file.name;
}

async function startUpload() {
    const file = document.getElementById('fileInput').files[0];
    if (!file) return;

    document.getElementById('uploadBtn').disabled = true;

    const chunkSize = 5 * 1024 * 1024; // 5 mb, finetune this later?
    const totalChunks = Math.ceil(file.size / chunkSize);

    // check if this is an unfinished upload
    let uploadId = localStorage.getItem('upload_' + file.name);
    if (!uploadId) {
        uploadId = generateUUID();
        localStorage.setItem('upload_' + file.name, uploadId);
    }

    const statusRes = await fetch('status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ upload_id: uploadId })
    });

    const status = await statusRes.json();
    const uploadedChunks = status.uploaded ?? [];

    for (let i = 0; i < totalChunks; i++) {
        if (uploadedChunks.includes(i)) continue;

        const start = i * chunkSize;
        const end = Math.min(file.size, start + chunkSize);
        const chunk = file.slice(start, end);

        const form = new FormData();
        form.append('chunk', chunk);
        form.append('upload_id', uploadId);
        form.append('index', i);
        form.append('total', totalChunks);
        form.append('filename', file.name);
        form.append('filesize', file.size);

        document.getElementById('statusMsg').textContent = `Uploading chunk ${i + 1}/${totalChunks}...`;

        const res = await fetch('upload', { method: 'POST', body: form });

        if (!res.ok) {
            document.getElementById('statusMsg').textContent = `Chunk ${i + 1} failed (HTTP ${res.status}).`;
            document.getElementById('uploadBtn').disabled = false;
            return;
        }

        document.getElementById('progressBar').value = Math.round(((i + 1) / totalChunks) * 100);
    }

    document.getElementById('statusMsg').textContent = 'Upload complete!';
    document.getElementById('progressBar').value = 100;
    localStorage.removeItem('upload_' + file.name);
}