// script.js for lembar_penilaian.html
document.addEventListener('DOMContentLoaded', () => {
    const API_BASE_URL = 'API.php'; 

    // --- ELEMEN UI ---
    const projectNameInput = document.getElementById('projectName');
    const addParameterBtn = document.getElementById('addParameterBtn');
    const parametersContainer = document.getElementById('parametersContainer');
    const emptyParametersPlaceholder = document.getElementById('emptyParametersPlaceholder');
    
    const overallTotalMistakesEl = document.getElementById('overallTotalMistakes');
    const overallTotalScoreEl = document.getElementById('overallTotalScore');
    const predicateEl = document.getElementById('predicate');
    const statusSelectEl = document.getElementById('statusSelect'); 
    
    const examinerNotesInput = document.getElementById('examinerNotes');
    const examinerNameInput = document.getElementById('examinerName'); 
    
    const editDataBtn = document.getElementById('editDataBtn');
    const saveDataBtn = document.getElementById('saveDataBtn');
    const backToManagementBtn = document.getElementById('backToManagementBtn');

    const parameterTemplate = document.getElementById('parameterTemplate');
    const subAspectTemplate = document.getElementById('subAspectTemplate');

    const submissionInfoDisplay = document.getElementById('submissionInfoDisplay');
    const submissionTitleDisplay = document.getElementById('submissionTitleDisplay');
    const submissionMahasiswaDisplay = document.getElementById('submissionMahasiswaDisplay'); 
    // const submissionFileLink = document.getElementById('submissionFileLink'); // Dihapus
    const docVersionP = document.getElementById('docVersion');


    let currentProjectId = null; 
    let currentSubmissionId = null; 
    let isFormEditable = true;
    let loggedInUser = null; // Mengganti loggedInAdminUser agar lebih generik

    let assessmentData = {
        id: null, 
        submission_id: null, 
        projectName: '', 
        parameters: [], 
        overallTotalMistakes: 0,
        overallTotalScore: 90, 
        predicate: 'Istimewa', 
        status: 'LANJUT', 
        examinerNotes: '',
        examinerName: '', 
        docVersion: 'default_assessment_v1'
    };

    async function checkUserLoginAndInitialize() { // Nama fungsi diubah
        try {
            const response = await fetch(`${API_BASE_URL}/auth/session`);
            const data = await response.json();
            if (data.loggedIn && (data.user.role === 'admin' || data.user.role === 'mahasiswa')) {
                loggedInUser = data.user; 
                if (data.user.role === 'admin') {
                    examinerNameInput.value = loggedInUser.nama_lengkap; 
                    assessmentData.examinerName = loggedInUser.nama_lengkap;
                } else { 
                    examinerNameInput.readOnly = true; 
                    examinerNameInput.classList.add('bg-gray-100'); // Style untuk readonly
                }
                await initializeApp(); 
            } else {
                alert("Akses ditolak. Silakan login.");
                window.location.href = 'login.html';
            }
        } catch (error) {
            console.error('Error checking session:', error);
            alert("Terjadi kesalahan saat verifikasi sesi. Silakan login kembali.");
            window.location.href = 'login.html';
        }
    }

    function displayMahasiswaInfo(dataMahasiswaParsed) {
        let html = '';
        if (Array.isArray(dataMahasiswaParsed)) {
            if (dataMahasiswaParsed.length === 1 && (dataMahasiswaParsed[0].status === undefined || dataMahasiswaParsed[0].status !== "Ketua")) { 
                html = `Pengerjaan: Individu<br>Mahasiswa: <strong>${dataMahasiswaParsed[0].nama || '-'}</strong> (NRP: ${dataMahasiswaParsed[0].nrp || '-'})`;
            } else { 
                html = 'Pengerjaan: Kelompok<ul class="list-disc list-inside ml-4 mt-1">';
                dataMahasiswaParsed.forEach(mhs => {
                    html += `<li><strong>${mhs.nama || '-'}</strong> (NRP: ${mhs.nrp || '-'}) ${mhs.status ? `<em>(${mhs.status})</em>` : ''}</li>`;
                });
                html += '</ul>';
            }
        } else {
            html = 'Data mahasiswa tidak tersedia.';
        }
        submissionMahasiswaDisplay.innerHTML = html;
    }

    function updateUIFromAssessmentData() {
        projectNameInput.value = assessmentData.projectName || '';
        if (loggedInUser && loggedInUser.role === 'admin') { 
            examinerNameInput.value = assessmentData.examinerName || loggedInUser.nama_lengkap;
        } else if (assessmentData.examinerName) { 
             examinerNameInput.value = assessmentData.examinerName;
        }

        examinerNotesInput.value = assessmentData.examinerNotes || '';
        if (statusSelectEl) statusSelectEl.value = assessmentData.status || 'LANJUT';
        if (docVersionP) docVersionP.textContent = assessmentData.docVersion || 'Versi Dok: -';

        overallTotalMistakesEl.textContent = assessmentData.overallTotalMistakes || 0;
        overallTotalScoreEl.textContent = assessmentData.overallTotalScore || 0;
        predicateEl.textContent = assessmentData.predicate || '-';

        if (statusSelectEl) {
            statusSelectEl.classList.remove('status-lanjut-select', 'status-ulang-select');
            if (assessmentData.status === 'LANJUT') statusSelectEl.classList.add('status-lanjut-select');
            else statusSelectEl.classList.add('status-ulang-select');
        }
        
        renderParameters();
        calculateAndUpdateTotals(); 
    }
    
    function renderParameters() {
        // ... (fungsi ini tetap sama seperti sebelumnya) ...
        parametersContainer.innerHTML = ''; 
        if (!assessmentData.parameters || assessmentData.parameters.length === 0) {
            if(emptyParametersPlaceholder) emptyParametersPlaceholder.style.display = 'flex';
        } else {
            if(emptyParametersPlaceholder) emptyParametersPlaceholder.style.display = 'none';
            assessmentData.parameters.forEach(param => {
                const parameterElement = createParameterElement(param);
                parametersContainer.appendChild(parameterElement);
            });
        }
    }

    function createParameterElement(paramData) {
        // ... (fungsi ini tetap sama seperti sebelumnya) ...
        const templateClone = parameterTemplate.content.cloneNode(true);
        const parameterBlock = templateClone.querySelector('.parameter-block');
        parameterBlock.dataset.parameterId = paramData.id;

        const nameInput = parameterBlock.querySelector('.parameter-name');
        nameInput.value = paramData.name || '';
        nameInput.addEventListener('change', (e) => { 
            paramData.name = e.target.value; 
        });
        nameInput.disabled = !isFormEditable;

        const subAspectsContainer = parameterBlock.querySelector('.sub-aspects-container');
        if (paramData.subAspects && Array.isArray(paramData.subAspects)) {
            paramData.subAspects.forEach(subAspect => {
                const subAspectElement = createSubAspectElement(subAspect, paramData.id);
                subAspectsContainer.appendChild(subAspectElement);
            });
        }
        
        const btnAddSubAspect = parameterBlock.querySelector('.add-sub-aspect-btn');
        btnAddSubAspect.addEventListener('click', () => addNewSubAspect(paramData.id, subAspectsContainer) );
        btnAddSubAspect.disabled = !isFormEditable;
        btnAddSubAspect.style.display = isFormEditable ? 'flex' : 'none';

        const btnRemoveParam = parameterBlock.querySelector('.remove-parameter-btn');
        btnRemoveParam.addEventListener('click', () => {
            removeParameter(paramData.id);
            calculateAndUpdateTotals();
        });
        btnRemoveParam.disabled = !isFormEditable;
        btnRemoveParam.style.display = isFormEditable ? 'inline-flex' : 'none';

        updateParameterTotalMistakesDisplay(parameterBlock, paramData.totalMistakes || 0); 
        return parameterBlock;
    }

    function createSubAspectElement(subAspectData, parameterId) {
        // ... (fungsi ini tetap sama seperti sebelumnya) ...
        const templateClone = subAspectTemplate.content.cloneNode(true);
        const subAspectItem = templateClone.querySelector('.sub-aspect-item');
        subAspectItem.dataset.subAspectId = subAspectData.id;

        const nameInput = subAspectItem.querySelector('.sub-aspect-name');
        nameInput.value = subAspectData.name || '';
        nameInput.addEventListener('change', (e) => {
            subAspectData.name = e.target.value;
        });
        nameInput.disabled = !isFormEditable;

        const mistakesInput = subAspectItem.querySelector('.sub-aspect-mistakes');
        mistakesInput.value = subAspectData.mistakes || 0;
        mistakesInput.addEventListener('input', (e) => { 
            subAspectData.mistakes = parseInt(e.target.value, 10) || 0;
            calculateAndUpdateTotals(); 
        });
        mistakesInput.disabled = !isFormEditable;
        
        const btnRemoveSub = subAspectItem.querySelector('.remove-sub-aspect-btn');
        btnRemoveSub.addEventListener('click', () => {
            removeSubAspect(parameterId, subAspectData.id);
            calculateAndUpdateTotals();
        });
        btnRemoveSub.disabled = !isFormEditable;
        btnRemoveSub.style.display = isFormEditable ? 'inline-flex' : 'none';

        return subAspectItem;
    }
    
    function updateParameterTotalMistakesDisplay(parameterElement, totalMistakes) {
        // ... (fungsi ini tetap sama seperti sebelumnya) ...
        const displayElement = parameterElement.querySelector('.parameter-total-mistakes');
        if (displayElement) displayElement.textContent = totalMistakes;
    }

    function setFormEditableState(editable) {
        // ... (fungsi ini tetap sama, tapi perhatikan role mahasiswa) ...
        isFormEditable = editable; 
        
        if (loggedInUser && loggedInUser.role === 'mahasiswa') {
            isFormEditable = false; // Mahasiswa selalu view-only di halaman ini
        }

        projectNameInput.disabled = !isFormEditable;
        examinerNotesInput.disabled = !isFormEditable;
        statusSelectEl.disabled = !isFormEditable;

        document.querySelectorAll('.parameter-name, .sub-aspect-name, .sub-aspect-mistakes')
            .forEach(input => input.disabled = !isFormEditable);

        document.querySelectorAll('.remove-parameter-btn, .add-sub-aspect-btn, .remove-sub-aspect-btn')
            .forEach(btn => {
                btn.disabled = !isFormEditable;
                if (btn.classList.contains('add-sub-aspect-btn')) {
                    btn.style.display = isFormEditable ? 'flex' : 'none';
                } else {
                    btn.style.display = isFormEditable ? 'inline-flex' : 'none';
                }
            });
        
        addParameterBtn.disabled = !isFormEditable;
        addParameterBtn.style.display = isFormEditable ? 'flex' : 'none';

        backToManagementBtn.style.display = 'inline-flex'; 

        if (loggedInUser && loggedInUser.role === 'admin') {
            if (currentProjectId) { 
                editDataBtn.style.display = isFormEditable ? 'none' : 'inline-flex';
                saveDataBtn.style.display = isFormEditable ? 'inline-flex' : 'none';
            } else if (currentSubmissionId) { 
                editDataBtn.style.display = 'none'; 
                saveDataBtn.style.display = 'inline-flex';
            } else { 
                 editDataBtn.style.display = 'none';
                 saveDataBtn.style.display = 'inline-flex';
            }
        } else { // Mahasiswa
            editDataBtn.style.display = 'none';
            saveDataBtn.style.display = 'none';
        }
    }

    function addNewParameter() {
        // ... (fungsi ini tetap sama seperti sebelumnya) ...
        const newParameterId = `param_${Date.now()}`;
        const newParameter = { id: newParameterId, name: `Parameter Baru ${(assessmentData.parameters || []).length + 1}`, subAspects: [], totalMistakes: 0 };
        if (!assessmentData.parameters) assessmentData.parameters = [];
        assessmentData.parameters.push(newParameter);
        const defaultSubAspectId = `sub_${Date.now()}`;
        newParameter.subAspects.push({ id: defaultSubAspectId, name: 'Sub-Aspek Default', mistakes: 0 });
        
        renderParameters(); 
        setFormEditableState(isFormEditable); 
        calculateAndUpdateTotals();
    }

    function removeParameter(parameterId) {
        // ... (fungsi ini tetap sama seperti sebelumnya) ...
        assessmentData.parameters = (assessmentData.parameters || []).filter(p => p.id !== parameterId);
        renderParameters();
    }

    function addNewSubAspect(parameterId, containerElement) {
        // ... (fungsi ini tetap sama seperti sebelumnya) ...
        const parameter = (assessmentData.parameters || []).find(p => p.id === parameterId);
        if (parameter) {
            const newSubAspectId = `sub_${Date.now()}_${Math.random().toString(36).substr(2, 5)}`;
            const newSubAspect = { id: newSubAspectId, name: '', mistakes: 0 };
            if (!parameter.subAspects) parameter.subAspects = [];
            parameter.subAspects.push(newSubAspect);
            const subAspectElement = createSubAspectElement(newSubAspect, parameterId);
            containerElement.appendChild(subAspectElement); 
            setFormEditableState(isFormEditable); 
            calculateAndUpdateTotals();
        }
    }

    function removeSubAspect(parameterId, subAspectId) {
        // ... (fungsi ini tetap sama seperti sebelumnya) ...
        const parameter = (assessmentData.parameters || []).find(p => p.id === parameterId);
        if (parameter && parameter.subAspects) {
            parameter.subAspects = parameter.subAspects.filter(sa => sa.id !== subAspectId);
            const subAspectElementToRemove = parametersContainer.querySelector(`.sub-aspect-item[data-sub-aspect-id="${subAspectId}"]`);
            if (subAspectElementToRemove) subAspectElementToRemove.remove();
        }
    }
    
    function calculateAndUpdateTotals() {
        // ... (fungsi ini tetap sama seperti sebelumnya) ...
        let overallMistakes = 0;
        (assessmentData.parameters || []).forEach(param => {
            let paramTotalMistakes = 0;
            (param.subAspects || []).forEach(sub => {
                paramTotalMistakes += (parseInt(sub.mistakes, 10) || 0);
            });
            param.totalMistakes = paramTotalMistakes;
            overallMistakes += paramTotalMistakes;

            const paramElement = parametersContainer.querySelector(`.parameter-block[data-parameter-id="${param.id}"]`);
            if (paramElement) {
                updateParameterTotalMistakesDisplay(paramElement, paramTotalMistakes);
            }
        });

        assessmentData.overallTotalMistakes = overallMistakes;
        assessmentData.overallTotalScore = Math.max(0, 90 - overallMistakes); 

        if (assessmentData.overallTotalScore >= 86) assessmentData.predicate = 'Istimewa';
        else if (assessmentData.overallTotalScore >= 78) assessmentData.predicate = 'Sangat Baik';
        else if (assessmentData.overallTotalScore >= 65) assessmentData.predicate = 'Baik';
        else assessmentData.predicate = 'Cukup';
        
        assessmentData.status = assessmentData.overallTotalScore < 64 ? 'ULANG' : 'LANJUT';


        overallTotalMistakesEl.textContent = assessmentData.overallTotalMistakes;
        overallTotalScoreEl.textContent = assessmentData.overallTotalScore;
        predicateEl.textContent = assessmentData.predicate;
        
        if (statusSelectEl) {
            statusSelectEl.value = assessmentData.status;
            statusSelectEl.classList.remove('status-lanjut-select', 'status-ulang-select');
            if (assessmentData.status === 'LANJUT') statusSelectEl.classList.add('status-lanjut-select');
            else statusSelectEl.classList.add('status-ulang-select');
        }
    }

    function gatherDataFromUI() {
        // ... (fungsi ini tetap sama seperti sebelumnya) ...
        assessmentData.projectName = projectNameInput.value || "Tanpa Nama Proyek Penilaian"; 
        assessmentData.examinerNotes = examinerNotesInput.value;
        if (loggedInUser && loggedInUser.role === 'admin') { 
             assessmentData.examinerName = loggedInUser.nama_lengkap;
        }
        if (statusSelectEl) assessmentData.status = statusSelectEl.value; 
        assessmentData.docVersion = docVersionP ? docVersionP.textContent.replace('Versi Dok: ', '') : 'default_v_js';
        
        calculateAndUpdateTotals(); 

        const dataToSend = { ...assessmentData };
        
        if (dataToSend.parameters) {
            dataToSend.parameters = dataToSend.parameters.map(p => ({
                id: p.id, 
                name: p.name,
                subAspects: p.subAspects.map(sa => ({
                    id: sa.id, 
                    name: sa.name,
                    mistakes: sa.mistakes
                }))
            }));
        }
        
        if (!currentProjectId) {
            delete dataToSend.id; 
        } else {
            dataToSend.id = currentProjectId; 
        }
        if (currentSubmissionId) {
            dataToSend.submission_id = currentSubmissionId;
        }

        return JSON.parse(JSON.stringify(dataToSend)); 
    }

    async function handleSaveData() {
        // ... (fungsi ini tetap sama seperti sebelumnya) ...
        const dataToSave = gatherDataFromUI();
        let url = `${API_BASE_URL}/projects`; 
        let method = 'POST';

        if (currentProjectId && dataToSave.id) { 
            url = `${API_BASE_URL}/projects/${currentProjectId}`;
            method = 'PUT';
        } else if (!currentSubmissionId && !currentProjectId) {
            alert("Tidak ada proyek unggahan yang dipilih untuk dinilai.");
            return;
        }
        
        console.log(`Mengirim data ke ${url} dengan metode ${method}:`, dataToSave);
        saveDataBtn.disabled = true; 
        saveDataBtn.innerHTML = `<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Menyimpan...`;

        try {
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(dataToSave),
            });
            const resultText = await response.text(); 
            let result;
            try {
                result = JSON.parse(resultText); 
            } catch (e) {
                console.error("Gagal parse JSON dari server (save):", resultText);
                throw new Error("Respons server tidak valid (save). Isi: " + resultText.substring(0, 200));
            }

            if (!response.ok) {
                throw new Error(result.error || `HTTP error! status: ${response.status}`);
            }
            
            console.log('Respon dari server (save):', result);
            alert(result.message || "Data berhasil diproses.");

            if (result.project && result.project.project_id) { 
                currentProjectId = result.project.project_id.toString(); 
                assessmentData.id = currentProjectId;
                await loadProjectData(currentProjectId, false); 
                setFormEditableState(false); 
            } else {
                 alert("Terjadi kesalahan pada respons server.");
            }
        } catch (error) {
            console.error('Gagal menyimpan data:', error);
            alert(`Gagal menyimpan data: ${error.message}`);
        } finally {
            saveDataBtn.disabled = false; 
            saveDataBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg> Simpan Penilaian`;
        }
    }

    async function loadProjectData(projectId, isViewModeOnly) { 
        if (!projectId) {
            console.warn("loadProjectData dipanggil tanpa projectId.");
            return;
        }
        console.log(`Mencoba memuat data untuk Project ID Penilaian: ${projectId}`);
        try {
            const response = await fetch(`${API_BASE_URL}/projects/${projectId}`);
            const resultText = await response.text();
            let loadedData;
            try {
                loadedData = JSON.parse(resultText);
            } catch (e) {
                 console.error("Gagal parse JSON saat memuat:", resultText);
                throw new Error("Respons server tidak valid saat memuat. Isi: " + resultText.substring(0, 200));
            }

            if (!response.ok) {
                 throw new Error(loadedData.error || `HTTP error! status: ${response.status}`);
            }
            
            assessmentData = {
                id: loadedData.project_id ? loadedData.project_id.toString() : null,
                submission_id: loadedData.submission_id ? loadedData.submission_id.toString() : null,
                projectName: loadedData.project_name || '',
                parameters: (loadedData.parameters || []).map(p => ({ 
                    id: p.id || p.parameter_client_id, 
                    name: p.name || p.parameter_name || '',
                    totalMistakes: parseInt(p.totalMistakes || p.total_mistakes_parameter, 10) || 0,
                    subAspects: (p.subAspects || []).map(sa => ({ 
                        id: sa.id || sa.sub_aspect_client_id, 
                        name: sa.name || sa.sub_aspect_name || '',
                        mistakes: parseInt(sa.mistakes, 10) || 0
                    }))
                })), 
                overallTotalMistakes: parseInt(loadedData.overall_total_mistakes, 10) || 0,
                overallTotalScore: parseInt(loadedData.overall_total_score, 10) || 0,
                predicate: loadedData.predicate_text || 'Cukup',
                status: loadedData.status || 'LANJUT',
                examinerNotes: loadedData.examiner_notes || '',
                examinerName: loadedData.examiner_name || (loggedInUser?.role === 'admin' ? loggedInUser.nama_lengkap : ''),
                docVersion: loadedData.doc_version || 'default_assessment_v_loaded'
            };
            
            if (loadedData.submission_id) {
                submissionTitleDisplay.textContent = loadedData.submission_project_title || '-';
                if (loadedData.submission_data_mahasiswa_parsed) {
                    displayMahasiswaInfo(loadedData.submission_data_mahasiswa_parsed);
                } else { // Fallback jika parsing gagal atau data tidak ada
                     submissionMahasiswaDisplay.innerHTML = `Mahasiswa: <strong>${loadedData.nama_mahasiswa_submission || '-'}</strong> (NRP: ${loadedData.nrp_mahasiswa_submission || '-'})`;
                }
                
                // File tidak lagi di-handle, jadi info file dihapus dari sini
                // if(loadedData.submission_file_path && loadedData.submission_file_path !== '#') { ... }
                submissionInfoDisplay.style.display = 'block';
            } else {
                submissionInfoDisplay.style.display = 'none';
            }

            updateUIFromAssessmentData(); 
            setFormEditableState(loggedInUser?.role === 'admin' ? !isViewModeOnly : false); 
            console.log('Data penilaian berhasil dimuat dari API:', assessmentData);
        } catch (error) {
            console.error('Gagal memuat data project penilaian:', error);
            alert(`Gagal memuat data project penilaian: ${error.message}.`);
        }
    }
    
    async function loadSubmissionDetailsForNewAssessment(submissionId) {
        console.log(`Mencoba memuat detail submission untuk penilaian baru: Submission ID ${submissionId}`);
        try {
            const response = await fetch(`${API_BASE_URL}/submissions/${submissionId}`); 
            const subDetails = await response.json();
            if (!response.ok) throw new Error(subDetails.error || "Gagal ambil detail submission");
            
            assessmentData.projectName = subDetails.project_title || `Proyek dari Unggahan #${submissionId}`;
            assessmentData.submission_id = submissionId; 
            currentSubmissionId = submissionId; 

            submissionTitleDisplay.textContent = subDetails.project_title || '-';
            if (subDetails.data_mahasiswa_parsed) {
                displayMahasiswaInfo(subDetails.data_mahasiswa_parsed);
            } else {
                submissionMahasiswaDisplay.innerHTML = `Mahasiswa: <strong>${subDetails.nama_pengaju || '-'}</strong> (NRP: ${subDetails.nrp_pengaju || '-'})`;
            }
            
            // File tidak lagi dihandle
            submissionInfoDisplay.style.display = 'block';
            
            updateUIFromAssessmentData();
            setFormEditableState(true); 
            if (assessmentData.parameters.length === 0) { 
                addNewParameter();
            }

        } catch (err) {
            console.error("Gagal memuat detail submission untuk penilaian:", err);
            alert("Gagal memuat detail proyek unggahan. Silakan coba lagi. Error: " + err.message);
            projectNameInput.value = `Penilaian untuk Unggahan ID ${submissionId}`; 
            updateUIFromAssessmentData();
            setFormEditableState(true);
             if (assessmentData.parameters.length === 0) {
                addNewParameter();
            }
        }
    }

    addParameterBtn.addEventListener('click', addNewParameter);
    saveDataBtn.addEventListener('click', handleSaveData);
    
    editDataBtn.addEventListener('click', () => {
        if (loggedInUser && loggedInUser.role === 'admin') {
            setFormEditableState(true);
        }
    });
    backToManagementBtn.addEventListener('click', () => {
        if (loggedInUser && loggedInUser.role === 'admin') {
            window.location.href = 'index.html'; 
        } else {
            window.location.href = 'riwayat_penilaian.html'; 
        }
    });

    projectNameInput.addEventListener('change', (e) => assessmentData.projectName = e.target.value);
    examinerNotesInput.addEventListener('change', (e) => assessmentData.examinerNotes = e.target.value);
    if (statusSelectEl) {
        statusSelectEl.addEventListener('change', (e) => {
            assessmentData.status = e.target.value;
            calculateAndUpdateTotals(); 
        });
    }

    async function initializeApp() {
        const urlParams = new URLSearchParams(window.location.search);
        const projectIdFromUrl = urlParams.get('projectId');
        const submissionIdFromUrl = urlParams.get('submission_id');
        const viewModeFromUrl = urlParams.get('view') === 'true'; 

        const effectiveViewMode = (loggedInUser && loggedInUser.role === 'mahasiswa') ? true : viewModeFromUrl;

        if (projectIdFromUrl) { 
            currentProjectId = projectIdFromUrl;
            document.title = `Penilaian Proyek - ID ${currentProjectId}`; 
            await loadProjectData(projectIdFromUrl, effectiveViewMode);
        } else if (submissionIdFromUrl && loggedInUser && loggedInUser.role === 'admin') { 
            currentSubmissionId = submissionIdFromUrl;
            document.title = `Nilai Proyek dari Unggahan ID ${currentSubmissionId}`;
            await loadSubmissionDetailsForNewAssessment(submissionIdFromUrl);
        } else {
            if (loggedInUser && loggedInUser.role === 'mahasiswa') {
                 alert("Mode tidak valid. Kembali ke riwayat.");
                 window.location.href = 'riwayat_penilaian.html';
                 return;
            }
            alert("Mode tidak valid atau parameter tidak lengkap. Kembali ke manajemen.");
            window.location.href = 'index.html';
        }
    }

    checkUserLoginAndInitialize(); 
});
