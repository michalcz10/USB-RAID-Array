function handleDragOver(event) {
    event.preventDefault();
    event.currentTarget.classList.add('dragover');
}

function handleDragLeave(event) {
    event.currentTarget.classList.remove('dragover');
}

function handleDrop(event) {
    event.preventDefault();
    event.currentTarget.classList.remove('dragover');

    if (event.dataTransfer.items) {
        const items = event.dataTransfer.items;
        processDroppedItems(items);
    } else {
        const files = event.dataTransfer.files;
        uploadFiles(files);
    }
}

function processDroppedItems(items) {
    const allFiles = [];
    let pendingItems = 0;
    
    function handleEntry(entry, path = '') {
        if (entry.isFile) {
            pendingItems++;
            entry.file(file => {
                Object.defineProperty(file, 'webkitRelativePath', {
                    value: path + file.name
                });
                
                allFiles.push(file);
                pendingItems--;

                if (pendingItems === 0) {
                    uploadFiles(allFiles);
                }
            });
        } else if (entry.isDirectory) {
            const reader = entry.createReader();
            readEntries(reader, path + entry.name + '/');
        }
    }
    
    function readEntries(reader, path) {
        pendingItems++;
        reader.readEntries(entries => {
            if (entries.length > 0) {
                for (const entry of entries) {
                    handleEntry(entry, path);
                }
                readEntries(reader, path);
            }
            pendingItems--;
            
            if (pendingItems === 0 && allFiles.length > 0) {
                uploadFiles(allFiles);
            }
        });
    }

    for (let i = 0; i < items.length; i++) {
        const item = items[i];
        if (item.kind !== 'file') continue;
        
        const entry = item.webkitGetAsEntry ? item.webkitGetAsEntry() : item.getAsEntry();
        if (entry) {
            handleEntry(entry);
        }
    }

    if (pendingItems === 0 && allFiles.length === 0) {
        alert('No valid files or directories found.');
    }
}

function handleFileSelect(event) {
    const files = event.target.files;
    uploadFiles(files);
}

function uploadFiles(files) {
    if (!files || files.length === 0) {
        alert('No files selected for upload.');
        return;
    }

    const formData = new FormData();
    formData.append('currentPath', currentPath);

    let filesAdded = 0;
    let directories = new Set();

    for (const file of files) {
        const relativePath = file.webkitRelativePath || '';

        if (relativePath) {
            const parts = relativePath.split('/');
            let currentPath = '';
            for (let i = 0; i < parts.length - 1; i++) {
                currentPath += (i > 0 ? '/' : '') + parts[i];
                if (currentPath) {
                    directories.add(currentPath);
                }
            }
        }
        
        formData.append('files[]', file);
        formData.append('paths[]', relativePath);
        filesAdded++;
    }

    if (directories.size > 0) {
        formData.append('create_dirs', JSON.stringify(Array.from(directories)));
    }

    if (filesAdded === 0) {
        alert('No valid files selected for upload.');
        return;
    }

    const uploadStatus = document.createElement('div');
    uploadStatus.className = 'alert alert-info';
    uploadStatus.textContent = 'Uploading files, please wait...';
    document.querySelector('.action-buttons').after(uploadStatus);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'upload.php', true);

    xhr.onerror = () => {
        uploadStatus.className = 'alert alert-danger';
        uploadStatus.textContent = 'Network error occurred during upload.';
        setTimeout(() => uploadStatus.remove(), 5000);
    };
    
    xhr.timeout = 300000; // 5 minutes
    xhr.ontimeout = () => {
        uploadStatus.className = 'alert alert-danger';
        uploadStatus.textContent = 'Upload timed out. Try with smaller files or fewer files.';
        setTimeout(() => uploadStatus.remove(), 5000);
    };

    xhr.onload = () => {
        if (xhr.status === 200) {
            uploadStatus.className = 'alert alert-success';
            uploadStatus.textContent = 'Upload successful!';
            setTimeout(() => {
                uploadStatus.remove();
                window.location.reload();
            }, 1500);
        } else {
            uploadStatus.className = 'alert alert-danger';
            uploadStatus.textContent = 'Upload failed: ' + (xhr.responseText || xhr.statusText);
            setTimeout(() => uploadStatus.remove(), 5000);
        }
    };

    uploadStatus.setAttribute('role', 'status');
    xhr.upload.addEventListener('progress', event => {
        if (event.lengthComputable) uploadStatus.textContent = 'Uploading ' + filesAdded + ' file(s) · ' + Math.round(event.loaded / event.total * 100) + '%';
    });
    xhr.send(formData);
}

function createDirectory() {
    const dirName = document.getElementById('dirName').value.trim();
    if (!dirName) {
        alert('Please enter a directory name.');
        return;
    }

    const formData = new FormData();
    formData.append('currentPath', currentPath);
    formData.append('dirName', dirName);
    formData.append('action', 'createDir');

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'createdir.php', true);
    xhr.onload = () => {
        if (xhr.status === 200) {
            alert('Directory created successfully!');
            document.getElementById('createDirModal').close();
            window.location.reload();
        } else {
            alert('Failed to create directory: ' + xhr.responseText || xhr.statusText);
        }
    };
    xhr.send(formData);
}

function confirmDelete(event) {
    event.preventDefault();
    
    if (confirm("Do you really want to delete this file?")) {
        event.target.form.submit();
    }
}

function createFile() {
    const fileName = document.getElementById('fileName').value.trim();
    if (!fileName) {
        alert('Please enter a file name.');
        return;
    }

    if (fileName.indexOf('.') === -1) {
        alert('Please include a file extension (e.g., .txt, .html, .php)');
        return;
    }

    const formData = new FormData();
    formData.append('currentPath', currentPath);
    formData.append('fileName', fileName);
    formData.append('action', 'createFile');

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'createfile.php', true);
    xhr.onload = () => {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    alert('File created successfully!');
                    document.getElementById('createFileModal').close();
                    window.location.reload();
                } else {
                    alert('Failed to create file: ' + (response.message || 'Unknown error'));
                }
            } catch (e) {
                alert('Error processing response: ' + xhr.responseText);
            }
        } else {
            alert('Failed to create file: ' + xhr.responseText || xhr.statusText);
        }
    };
    xhr.send(formData);
}
function openRenameModal(name, path, isDirectory = false) {
    document.getElementById('originalName').value = name;
    document.getElementById('fullPath').value = path;
    document.getElementById('isDirectory').value = isDirectory ? '1' : '0';
    document.getElementById('newName').value = name.endsWith('/') ? name.slice(0, -1) : name;

    document.getElementById('renameModal').showModal();
}


// Filtering is local to the current directory; no remote request is needed.
const search = document.getElementById('fileSearch');
const typeFilter = document.getElementById('typeFilter');
function filterFiles() {
    const query = search.value.trim().toLocaleLowerCase();
    const rows = [...document.querySelectorAll('[data-file-name]')];
    let visible = 0;
    rows.forEach(row => {
        row.hidden = !row.dataset.fileName.toLocaleLowerCase().includes(query) || (typeFilter.value !== 'all' && row.dataset.kind !== typeFilter.value);
        if (!row.hidden) visible++;
    });
    document.getElementById('fileCount').textContent = visible + ' of ' + rows.length + ' items';
    document.getElementById('emptySearch').hidden = visible !== 0;
}
search?.addEventListener('input', filterFiles);
typeFilter?.addEventListener('change', filterFiles);
if (search) filterFiles();
document.addEventListener('keydown', event => {
    if (event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey && !event.target.closest('input, textarea, select, [contenteditable], dialog')) {
        event.preventDefault();
        search?.focus();
    }
});
