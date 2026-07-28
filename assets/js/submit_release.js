document.addEventListener('DOMContentLoaded', () => {
    // --- Mobile Sidebar & Header Menu Controls (Safe Checks) ---
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const profileTrigger = document.getElementById('profileTrigger');
    const profileDropdown = document.getElementById('profileDropdown');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            sidebar?.classList.add('open');
            sidebarOverlay?.classList.remove('hidden');
        });
    }

    function closeSidebar() {
        sidebar?.classList.remove('open');
        sidebarOverlay?.classList.add('hidden');
    }

    if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

    if (notificationBtn) {
        notificationBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            notificationDropdown?.classList.toggle('hidden');
            profileDropdown?.classList.add('hidden');
        });
    }

    if (profileTrigger) {
        profileTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown?.classList.toggle('hidden');
            notificationDropdown?.classList.add('hidden');
        });
    }

    document.addEventListener('click', () => {
        notificationDropdown?.classList.add('hidden');
        profileDropdown?.classList.add('hidden');
    });

    // --- Multi-Step Wizard Engine (Standard Loop Conversions) ---
    const steps = document.querySelectorAll('.form-step');
    const stepIndicators = document.querySelectorAll('.step');
    const nextButtons = document.querySelectorAll('.next-step-btn');
    const prevButtons = document.querySelectorAll('.prev-step-btn');
    const releaseTypeSelect = document.getElementById('release_type');
    const addTrackBtn = document.getElementById('addTrackBtn');
    const tracksWrapper = document.getElementById('tracks-wrapper');
    const artworkInput = document.getElementById('artwork_input');
    const artworkFileInfo = document.getElementById('artwork_file_info');

    let currentStepIndex = 0;
    
    // Auto-calculates existing track counts on load for corrections
    const existingTrackEntries = document.querySelectorAll('.track-entry');
    let trackCount = existingTrackEntries.length > 0 ? existingTrackEntries.length : 1;

    for (let i = 0; i < nextButtons.length; i++) {
        nextButtons[i].addEventListener('click', () => {
            const currentStepEl = steps[currentStepIndex];
            if (validateStepInputs(currentStepEl)) {
                if (currentStepIndex === 1) {
                    try {
                        generateMetadataPreviews();
                    } catch (err) {
                        console.error("Preview generation bypassed: ", err);
                    }
                }
                currentStepIndex++;
                updateStepDisplay();
            }
        });
    }

    for (let i = 0; i < prevButtons.length; i++) {
        prevButtons[i].addEventListener('click', () => {
            currentStepIndex--;
            updateStepDisplay();
        });
    }

    function updateStepDisplay() {
        for (let i = 0; i < steps.length; i++) {
            steps[i].classList.toggle('active', i === currentStepIndex);
        }
        for (let i = 0; i < stepIndicators.length; i++) {
            stepIndicators[i].classList.toggle('active', i <= currentStepIndex);
        }
    }

    function validateStepInputs(stepContainer) {
        if (!stepContainer) return true;
        const requiredInputs = stepContainer.querySelectorAll('[required]');
        let isValid = true;
        
        for (let i = 0; i < requiredInputs.length; i++) {
            const input = requiredInputs[i];
            
            // Check if the input actually belongs to this specific step
            const parentStep = input.closest('.form-step');
            if (parentStep !== stepContainer) {
                continue; // Skip validating nested inputs belonging to other steps
            }

            if (!input.value.trim()) {
                input.style.borderColor = 'red';
                isValid = false;
            } else {
                input.style.borderColor = '';
            }
        }
        return isValid;
    }

    // Dynamic Tracklist Builder
    if (releaseTypeSelect) {
        releaseTypeSelect.addEventListener('change', (e) => {
            const type = e.target.value;
            if (type === 'single') {
                addTrackBtn?.classList.add('hidden');
                clearTracksTo(1);
            } else {
                addTrackBtn?.classList.remove('hidden');
            }
        });
    }

    if (addTrackBtn) {
        addTrackBtn.addEventListener('click', () => {
            trackCount++;
            const newTrackHtml = `
                <div class="track-entry glass-card" data-track="${trackCount}">
                    <div class="track-entry-header" style="display:flex; justify-content:space-between; align-items:center;">
                        <h4>Track #${trackCount} Details</h4>
                        <button type="button" class="btn-secondary btn-sm" onclick="this.closest('.track-entry').remove()" style="color:#e74c3c;">Remove</button>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Track Title *</label>
                            <input type="text" name="track_title[]" required class="track-title-input" placeholder="Song Title">
                        </div>
                        <div class="form-group">
                            <label>Composer (Full Legal Name, Comma Separated) *</label>
                            <input type="text" name="track_composer[]" required placeholder="Composer Name">
                        </div>
                        <div class="form-group">
                            <label>Lyricist (Full Legal Name, Comma Separated) *</label>
                            <input type="text" name="track_lyricist[]" required placeholder="Lyricist Name">
                        </div>
                        <div class="form-group">
                            <label>Audio File (WAV Only) *</label>
                            <input type="file" name="track_audio[]" accept=".wav,audio/wav,audio/x-wav" required style="width:100%; padding:8px; background:#000; border:1px solid var(--border-color); border-radius:8px;">
                        </div>
                        <div class="form-group">
                            <label>Explicit Content *</label>
                            <select name="track_explicit[]" required>
                                <option value="no">Clean</option>
                                <option value="yes">Explicit</option>
                            </select>
                        </div>
                    </div>
                </div>`;
            tracksWrapper?.insertAdjacentHTML('beforeend', newTrackHtml);
        });
    }

    function clearTracksTo(count) {
        const entries = tracksWrapper?.querySelectorAll('.track-entry');
        if (entries) {
            for (let i = 0; i < entries.length; i++) {
                if (i >= count) entries[i].remove();
            }
        }
        trackCount = count;
    }

    // Artwork Upload Viewer
    if (artworkInput) {
        artworkInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;

            // Strict Pre-flight limit checks: 10MB Cover Art Poster limit
            const MAX_ARTWORK_LIMIT = 10 * 1024 * 1024; // 10MB
            if (file.size > MAX_ARTWORK_LIMIT) {
                alert(`Selected image is ${(file.size/1024/1024).toFixed(2)} MB. Please choose a cover artwork file under 10MB.`);
                artworkInput.value = '';
                return;
            }

            if (artworkFileInfo) {
                artworkFileInfo.textContent = `${file.name} (${(file.size/1024/1024).toFixed(2)} MB)`;
            }

            const reader = new FileReader();
            reader.onload = function(event) {
                const img = new Image();
                img.onload = function() {
                    if (this.width !== 3000 || this.height !== 3000) {
                        alert(`Invalid artwork dimension: ${this.width}x${this.height}px. Jonom Digital requires exactly 3000 x 3000 pixels.`);
                        if (artworkInput) artworkInput.value = '';
                        if (artworkFileInfo) artworkFileInfo.textContent = 'Invalid file rejected.';
                    } else {
                        const posterPreviewWrapper = document.getElementById('posterPreviewWrapper');
                        const posterPreviewImg = document.getElementById('posterPreviewImg');
                        const posterDefaultIcon = document.getElementById('posterDefaultIcon');
                        
                        if (posterPreviewWrapper && posterPreviewImg && posterDefaultIcon) {
                            posterPreviewImg.src = event.target.result;
                            posterPreviewWrapper.style.display = 'block';
                            posterDefaultIcon.style.display = 'none';
                        }

                        const ytArt = document.getElementById('yt_art_img');
                        const ytBackdrop = document.getElementById('yt_art_backdrop');
                        if (ytArt) ytArt.src = event.target.result;
                        if (ytBackdrop) ytBackdrop.style.backgroundImage = `url('${event.target.result}')`;
                    }
                };
                img.src = event.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    function generateMetadataPreviews() {
        const summaryContainer = document.getElementById('summaryContainer');
        const albumTitleVal = document.getElementById('album_title')?.value || 'Untitled';
        
        // Native DOM lookup
        const mainArtistSelects = document.getElementsByName('main_artist[]');
        let mainArtistVals = [];
        for (let i = 0; i < mainArtistSelects.length; i++) {
            const sel = mainArtistSelects[i];
            if (sel.value) mainArtistVals.push(sel.value);
        }
        const mainArtistText = mainArtistVals.length > 0 ? mainArtistVals.join(', ') : 'Unknown';

        const typeVal = releaseTypeSelect?.value.toUpperCase() || 'SINGLE';
        const genreVal = document.getElementById('genre')?.value || 'Pop';
        const liveDateVal = document.getElementById('go_live_date')?.value || 'TBD';

        const firstTrackInput = document.querySelector('.track-title-input');
        const firstTrackTitle = (firstTrackInput && firstTrackInput.value) ? firstTrackInput.value : albumTitleVal;

        const ytTrackTitle = document.getElementById('yt_track_title');
        const ytTrackArtist = document.getElementById('yt_track_artist');
        if (ytTrackTitle) ytTrackTitle.textContent = firstTrackTitle;
        if (ytTrackArtist) ytTrackArtist.textContent = mainArtistText;

        // Unconditional cover artwork preview assignment
        const ytArt = document.getElementById('yt_art_img');
        const ytBackdrop = document.getElementById('yt_art_backdrop');
        const posterPreviewImg = document.getElementById('posterPreviewImg');

        if (ytArt && posterPreviewImg) {
            const activeSrc = posterPreviewImg.getAttribute('src');
            if (activeSrc && activeSrc !== "") {
                ytArt.src = activeSrc;
                if (ytBackdrop) {
                    ytBackdrop.style.backgroundImage = `url('${activeSrc}')`;
                }
            }
        }

        if (summaryContainer) {
            summaryContainer.innerHTML = `
                <div class="summary-item"><span class="label">Product Title:</span><span class="val">${albumTitleVal}</span></div>
                <div class="summary-item"><span class="label">Main Artist(s):</span><span class="val">${mainArtistText}</span></div>
                <div class="summary-item"><span class="label">Format:</span><span class="val">${typeVal}</span></div>
                <div class="summary-item"><span class="label">Genre:</span><span class="val">${genreVal}</span></div>
                <div class="summary-item"><span class="label">Distribution Date:</span><span class="val">${liveDateVal}</span></div>
            `;
        }

        const currentYear = new Date().getFullYear();
        const descriptionTxt = `Provided to YouTube by Jonom Digital

${firstTrackTitle} · ${mainArtistText}

${albumTitleVal}

℗ ${currentYear} Jonom Digital & partners

Released on: ${liveDateVal}

Associated Performer, Vocals: ${mainArtistText}
Auto-generated by YouTube.`;

        const autoDesc = document.getElementById('yt_auto_description');
        if (autoDesc) autoDesc.textContent = descriptionTxt;
    }

    // --- Dynamic XMLHTTPRequest Progress Upload Engine ---
    const releaseForm = document.getElementById('releaseForm');
    if (releaseForm) {
        releaseForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Client-Side WAV validation check
            const audioInputs = document.querySelectorAll('input[name="track_audio[]"]');
            let audioValid = true;
            for (let i = 0; i < audioInputs.length; i++) {
                const input = audioInputs[i];
                const file = input.files[0];
                if (file) {
                    const ext = file.name.split('.').pop().toLowerCase();
                    if (ext !== 'wav') {
                        alert(`Track #${i + 1} validation failed. The selected file format is not a valid WAV file.`);
                        audioValid = false;
                        input.style.borderColor = 'red';
                    } else {
                        input.style.borderColor = '';
                    }
                }
            }

            if (!audioValid) return;

            const submitBtn = document.getElementById('finishSubmissionBtn');
            const progressOverlay = document.getElementById('uploadProgressOverlay');
            const percentageText = document.getElementById('uploadPercentageText');
            const progressBarFill = document.getElementById('uploadProgressBarFill');

            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Submitting... <i class="fa-solid fa-spinner fa-spin"></i>';

            // Gather elements for upload to calculate total bytes
            const artworkFile = artworkInput.files[0];
            const audioFiles = [];
            for (let i = 0; i < audioInputs.length; i++) {
                if (audioInputs[i].files[0]) {
                    audioFiles.push(audioInputs[i].files[0]);
                }
            }

            // Calculate total upload payload size
            let totalBytes = artworkFile ? artworkFile.size : 0;
            for (let i = 0; i < audioFiles.length; i++) {
                totalBytes += audioFiles[i].size;
            }

            // GMAIL/PROFREEHOST COMPATIBILITY CHECK: ENFORCE 100MB HARD UPLOAD LIMIT
            const MAX_UPLOAD_LIMIT = 100 * 1024 * 1024; // 100MB
            if (totalBytes > MAX_UPLOAD_LIMIT) {
                const sizeInMB = (totalBytes / 1024 / 1024).toFixed(2);
                alert(`Upload Blocked.\n\nYour selected files total ${sizeInMB} MB.\n\n[Reason]: The platform has an absolute upload limit of 100MB to maintain network performance.\n\n[Fix]: Please compress your assets or select files under 100MB.`);
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Submit for Moderation <i class="fa-solid fa-paper-plane"></i>';
                return;
            }

            if (progressOverlay) progressOverlay.style.display = 'flex';

            const formData = new FormData(releaseForm);
            
            // Programmatically inject global token
            if (typeof globalCsrfToken !== 'undefined' && globalCsrfToken !== "") {
                formData.set('csrf_token', globalCsrfToken); 
            }
            
            // XMLHTTPRequest targets your same-origin PHP server, enabling secure uploads
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'ajax/release_handler.php', true);

            xhr.upload.addEventListener('progress', (event) => {
                if (event.lengthComputable) {
                    const percentComplete = Math.round((event.loaded / event.total) * 100);
                    if (percentageText) percentageText.textContent = `Uploading Assets: ${percentComplete}%`;
                    if (progressBarFill) progressBarFill.style.width = `${percentComplete}%`;
                }
            });

            xhr.onload = function() {
                if (progressOverlay) progressOverlay.style.display = 'none';

                if (xhr.status === 200) {
                    const rawText = xhr.responseText;
                    try {
                        // =========================================================================
                        // UNBEATABLE REGEX JSON EXTRACTOR GUARD [1]
                        // Slices off any accidental server-side typos (like leading "I" or whitespace)
                        // =========================================================================
                        const jsonMatch = rawText.match(/\{[\s\S]*\}/);
                        if (!jsonMatch) {
                            throw new Error("No valid JSON payload structure found in response.");
                        }
                        const data = JSON.parse(jsonMatch[0]);

                        if (data.success) {
                            alert(data.message);
                            window.location.href = 'releases.php'; // REDIRECTS SUCCESSFULLY! [1]
                        } else {
                            alert(data.message);
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = 'Submit for Moderation <i class="fa-solid fa-paper-plane"></i>';
                        }
                    } catch (parseErr) {
                        console.error("JSON Error. Server Response text:", rawText);
                        alert("Diagnostics - Server returned an unexpected format:\n\n" + rawText.substring(0, 500));
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = 'Submit for Moderation <i class="fa-solid fa-paper-plane"></i>';
                    }
                } else {
                    alert('Assets transmission failed. Server returned status code: ' + xhr.status);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Submit for Moderation <i class="fa-solid fa-paper-plane"></i>';
                }
            };

            xhr.onerror = function() {
                if (progressOverlay) progressOverlay.style.display = 'none';
                alert('Connection failure with release handling systems. Verify if files exceed your server limits.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Submit for Moderation <i class="fa-solid fa-paper-plane"></i>';
            };

            xhr.send(formData);
        });
    }
});

// Helper functions (Standardized loops for safe WebView rendering)
function addArtistField(containerId, fieldName) {
    const container = document.getElementById(containerId);
    if (!container) return;

    let optionsHtml = '<option value="">-- Choose Managed Artist --</option>';
    if (typeof managedArtistsArray !== 'undefined' && Array.isArray(managedArtistsArray)) {
        for (let i = 0; i < managedArtistsArray.length; i++) {
            const art = managedArtistsArray[i];
            optionsHtml += `<option value="${escapeHtml(art.stage_name)}">${escapeHtml(art.stage_name)}</option>`;
        }
    }

    const rowHtml = `
        <div class="dynamic-row" style="margin-top: 5px;">
            <select name="${fieldName}" style="width:100%;">
                ${optionsHtml}
            </select>
            <button type="button" class="remove-btn" onclick="this.closest('.dynamic-row').remove()"><i class="fa-solid fa-xmark"></i></button>
        </div>`;
    container.insertAdjacentHTML('beforeend', rowHtml);
}

function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}