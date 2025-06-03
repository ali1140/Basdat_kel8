// script.js for lembar_penilaian.html
document.addEventListener('DOMContentLoaded', () => {
    const API_BASE_URL = 'API.php'; // Sesuaikan jika path API.php berbeda

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
    const examinerNameInput = document.getElementById('examinerName'); // Akan diisi dari sesi admin
    
    const editDataBtn = document.getElementById('editDataBtn');
    const saveDataBtn = document.getElementById('saveDataBtn');
    const backToManagementBtn = document.getElementById('backToManagementBtn');

    const parameterTemplate = document.getElementById('parameterTemplate');
    const subAspectTemplate = document.getElementById('subAspectTemplate');

    // Submission Info Display
    const submissionInfoDisplay = document.getElementById('submissionInfoDisplay');
    const submissionTitleDisplay = document.getElementById('submissionTitleDisplay');
    const submissionMahasiswaDisplay = document.getElementById('submissionMahasiswaDisplay');
    const submissionNrpDisplay = document.getElementById('submissionNrpDisplay');
    const submissionFileLink = document.getElementById('submissionFileLink');
    const docVersionP = document.getElementById('docVersion');


    let currentProjectId = null; // Untuk project penilaian yang sudah ada
    let currentSubmissionId = null; // Untuk project unggahan yang baru akan dinilai
    let isFormEditable = true;
    let loggedInAdminUser = null;

    let assessmentData = {
        id: null, 
        submission_id: null, // Penting untuk link ke submission
        projectName: '', // Nama project penilaian, bisa beda dari judul submission
        parameters: [], 
        overallTotalMistakes: 0,
        overallTotalScore: 90, 
        predicate: 'Istimewa', 
        status: 'LANJUT', 
        examinerNotes: '',
        examinerName: '', // Akan diisi dari sesi admin
        docVersion: 'default_assessment_v1'
    };

    async function checkAdminLoginAndInitialize() {
        try {
            const response = await fetch(`${API_BASE_URL}/auth/session`);
            const data = await response.json();
            if (data.loggedIn && data.user.role === 'admin') {
                loggedInAdminUser = data.user;
                examinerNameInput.value = loggedInAdminUser.nama_lengkap; // Set nama penguji
                assessmentData.examinerName = loggedInAdminUser.nama_lengkap;
                await initializeApp(); // Lanjutkan inisialisasi setelah admin terkonfirmasi
            } else {
                alert("Akses ditolak. Silakan login sebagai admin.");
                window.location.href = 'login.html';
            }
        } catch (error) {
            console.error('Error checking admin session:', error);
            alert("Terjadi kesalahan saat verifikasi sesi. Silakan login kembali.");
            window.location.href = 'login.html';
        }
    }


    function updateUIFromAssessmentData() {
        projectNameInput.value = assessmentData.projectName || '';
        // examinerNameInput.value sudah di-set dari sesi admin
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
        calculateAndUpdateTotals(); // Pastikan total dihitung setelah render
    }

    function renderParameters() {
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
        const templateClone = parameterTemplate.content.cloneNode(true);
        const parameterBlock = templateClone.querySelector('.parameter-block');
        parameterBlock.dataset.parameterId = paramData.id;

        const nameInput = parameterBlock.querySelector('.parameter-name');
        nameInput.value = paramData.name || '';
        nameInput.addEventListener('change', (e) => { 
            paramData.name = e.target.value; 
            // calculateAndUpdateTotals(); // Tidak perlu trigger dari sini, akan dihandle oleh event input mistakes
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
        const templateClone = subAspectTemplate.content.cloneNode(true);
        const subAspectItem = templateClone.querySelector('.sub-aspect-item');
        subAspectItem.dataset.subAspectId = subAspectData.id;

        const nameInput = subAspectItem.querySelector('.sub-aspect-name');
        nameInput.value = subAspectData.name || '';
        nameInput.addEventListener('change', (e) => {
            subAspectData.name = e.target.value;
            // calculateAndUpdateTotals(); // Tidak perlu trigger dari sini
        });
        nameInput.disabled = !isFormEditable;

        const mistakesInput = subAspectItem.querySelector('.sub-aspect-mistakes');
        mistakesInput.value = subAspectData.mistakes || 0;
        mistakesInput.addEventListener('input', (e) => { // 'input' untuk update real-time
            subAspectData.mistakes = parseInt(e.target.value, 10) || 0;
            calculateAndUpdateTotals(); // Panggil kalkulasi saat ada perubahan mistakes
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
        const displayElement = parameterElement.querySelector('.parameter-total-mistakes');
        if (displayElement) displayElement.textContent = totalMistakes;
    }

    function setFormEditableState(editable) {
        isFormEditable = editable; 
        projectNameInput.disabled = !editable;
        // examinerNameInput selalu readonly karena dari sesi
        examinerNotesInput.disabled = !editable;
        statusSelectEl.disabled = !editable;

        document.querySelectorAll('.parameter-name, .sub-aspect-name, .sub-aspect-mistakes')
            .forEach(input => input.disabled = !editable);

        document.querySelectorAll('.remove-parameter-btn, .add-sub-aspect-btn, .remove-sub-aspect-btn')
            .forEach(btn => {
                btn.disabled = !editable;
                if (btn.classList.contains('add-sub-aspect-btn')) {
                    btn.style.display = editable ? 'flex' : 'none';
                } else {
                    btn.style.display = editable ? 'inline-flex' : 'none';
                }
            });
        
        addParameterBtn.disabled = !editable;
        addParameterBtn.style.display = editable ? 'flex' : 'none';

        backToManagementBtn.style.display = 'inline-flex'; 

        if (currentProjectId) { // Jika sedang view/edit project penilaian yang sudah ada
            editDataBtn.style.display = editable ? 'none' : 'inline-flex';
            saveDataBtn.style.display = editable ? 'inline-flex' : 'none';
        } else if (currentSubmissionId) { // Jika menilai submission baru
            editDataBtn.style.display = 'none'; // Tidak ada mode edit sebelum simpan pertama
            saveDataBtn.style.display = 'inline-flex';
        } else { // Default (seharusnya tidak terjadi jika alur benar)
             editDataBtn.style.display = 'none';
             saveDataBtn.style.display = 'inline-flex';
        }
    }

    function addNewParameter() {
        const newParameterId = `param_${Date.now()}`;
        const newParameter = { id: newParameterId, name: `Parameter Baru ${(assessmentData.parameters || []).length + 1}`, subAspects: [], totalMistakes: 0 };
        if (!assessmentData.parameters) assessmentData.parameters = [];
        assessmentData.parameters.push(newParameter);
        // Tambah satu sub-aspek default
        const defaultSubAspectId = `sub_${Date.now()}`;
        newParameter.subAspects.push({ id: defaultSubAspectId, name: 'Sub-Aspek Default', mistakes: 0 });
        
        renderParameters(); 
        setFormEditableState(isFormEditable); // Re-apply edit state to new elements
        calculateAndUpdateTotals();
    }

    function removeParameter(parameterId) {
        assessmentData.parameters = (assessmentData.parameters || []).filter(p => p.id !== parameterId);
        renderParameters();
        // calculateAndUpdateTotals sudah dipanggil dari event listener tombol hapus
    }

    function addNewSubAspect(parameterId, containerElement) {
        const parameter = (assessmentData.parameters || []).find(p => p.id === parameterId);
        if (parameter) {
            const newSubAspectId = `sub_${Date.now()}_${Math.random().toString(36).substr(2, 5)}`;
            const newSubAspect = { id: newSubAspectId, name: '', mistakes: 0 };
            if (!parameter.subAspects) parameter.subAspects = [];
            parameter.subAspects.push(newSubAspect);
            const subAspectElement = createSubAspectElement(newSubAspect, parameterId);
            containerElement.appendChild(subAspectElement); 
            setFormEditableState(isFormEditable); // Re-apply edit state
            calculateAndUpdateTotals();
        }
    }

    function removeSubAspect(parameterId, subAspectId) {
        const parameter = (assessmentData.parameters || []).find(p => p.id === parameterId);
        if (parameter && parameter.subAspects) {
            parameter.subAspects = parameter.subAspects.filter(sa => sa.id !== subAspectId);
            const subAspectElementToRemove = parametersContainer.querySelector(`.sub-aspect-item[data-sub-aspect-id="${subAspectId}"]`);
            if (subAspectElementToRemove) subAspectElementToRemove.remove();
            // calculateAndUpdateTotals sudah dipanggil dari event listener tombol hapus
        }
    }
    
    function calculateAndUpdateTotals() {
        let overallMistakes = 0;
        (assessmentData.parameters || []).forEach(param => {
            let paramTotalMistakes = 0;
            (param.subAspects || []).forEach(sub => {
                paramTotalMistakes += (parseInt(sub.mistakes, 10) || 0);
            });
            param.totalMistakes = paramTotalMistakes;
            overallMistakes += paramTotalMistakes;

            // Update tampilan total per parameter
            const paramElement = parametersContainer.querySelector(`.parameter-block[data-parameter-id="${param.id}"]`);
            if (paramElement) {
                updateParameterTotalMistakesDisplay(paramElement, paramTotalMistakes);
            }
        });

        assessmentData.overallTotalMistakes = overallMistakes;
        assessmentData.overallTotalScore = Math.max(0, 90 - overallMistakes); // Nilai tidak boleh negatif

        if (assessmentData.overallTotalScore >= 86) assessmentData.predicate = 'Istimewa';
        else if (assessmentData.overallTotalScore >= 78) assessmentData.predicate = 'Sangat Baik';
        else if (assessmentData.overallTotalScore >= 65) assessmentData.predicate = 'Baik';
        else assessmentData.predicate = 'Cukup';
        
        // Status LANJUT/ULANG sudah diatur di backend oleh Stored Procedure
        // Tapi kita bisa set di frontend juga untuk tampilan sebelum simpan
        assessmentData.status = assessmentData.overallTotalScore < 64 ? 'ULANG' : 'LANJUT';


        overallTotalMistakesEl.textContent = assessmentData.overallTotalMistakes;
        overallTotalScoreEl.textContent = assessmentData.overallTotalScore;
        predicateEl.textContent = assessmentData.predicate;
        
        // Update status select element
        if (statusSelectEl) {
            statusSelectEl.value = assessmentData.status;
            statusSelectEl.classList.remove('status-lanjut-select', 'status-ulang-select');
            if (assessmentData.status === 'LANJUT') statusSelectEl.classList.add('status-lanjut-select');
            else statusSelectEl.classList.add('status-ulang-select');
        }
    }

    function gatherDataFromUI() {
        assessmentData.projectName = projectNameInput.value || "Tanpa Nama Proyek Penilaian"; 
        assessmentData.examinerNotes = examinerNotesInput.value;
        // assessmentData.examinerName sudah diisi dari sesi
        if (statusSelectEl) assessmentData.status = statusSelectEl.value; 
        assessmentData.docVersion = docVersionP ? docVersionP.textContent.replace('Versi Dok: ', '') : 'default_v_js';
        
        // Pastikan semua total sudah terupdate
        calculateAndUpdateTotals(); 

        const dataToSend = { ...assessmentData };
        // Hapus field yang dihitung server atau tidak perlu dikirim saat create/update penilaian
        // overallTotalMistakes, overallTotalScore, predicate akan dihitung/diset oleh SP di backend.
        // Kita hanya mengirim 'status' yang dipilih admin.
        
        // Untuk 'parameters', pastikan formatnya sesuai dengan yang diharapkan backend
        // Backend mengharapkan 'id' (client_id), 'name', dan 'subAspects' (dengan 'id', 'name', 'mistakes')
        // totalMistakes per parameter tidak perlu dikirim karena akan dihitung SP
        if (dataToSend.parameters) {
            dataToSend.parameters = dataToSend.parameters.map(p => ({
                id: p.id, // Ini adalah parameter_client_id
                name: p.name,
                subAspects: p.subAspects.map(sa => ({
                    id: sa.id, // Ini adalah sub_aspect_client_id
                    name: sa.name,
                    mistakes: sa.mistakes
                }))
            }));
        }
        
        // Hapus id project jika ini adalah penilaian baru (currentProjectId null)
        if (!currentProjectId) {
            delete dataToSend.id; 
        } else {
            dataToSend.id = currentProjectId; // Kirim project_id jika update
        }
        // submission_id harus selalu ada jika ini adalah penilaian dari submission
        if (currentSubmissionId) {
            dataToSend.submission_id = currentSubmissionId;
        }


        return JSON.parse(JSON.stringify(dataToSend)); 
    }

    async function handleSaveData() {
        const dataToSave = gatherDataFromUI();
        let url = `${API_BASE_URL}/projects`; // Default untuk POST (create)
        let method = 'POST';

        if (currentProjectId && dataToSave.id) { // Jika ada currentProjectId, berarti ini UPDATE
            url = `${API_BASE_URL}/projects/${currentProjectId}`;
            method = 'PUT';
        } else if (!currentSubmissionId && !currentProjectId) {
            // Seharusnya tidak terjadi jika alur benar, admin harus memilih submission
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
                currentProjectId = result.project.project_id.toString(); // Update currentProjectId jika baru dibuat
                assessmentData.id = currentProjectId;
                // Muat ulang data untuk menampilkan nilai terbaru dari SP dan DB
                await loadProjectData(currentProjectId, false); // false untuk mode edit setelah simpan
                setFormEditableState(false); // Kembali ke mode view setelah simpan
            } else {
                 // Fallback jika struktur respons tidak seperti yang diharapkan
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
                examinerName: loadedData.examiner_name || loggedInAdminUser?.nama_lengkap || '', // Prioritaskan dari DB, fallback ke sesi
                docVersion: loadedData.doc_version || 'default_assessment_v_loaded'
            };
            
            // Tampilkan info submission jika ada
            if (loadedData.submission_id) {
                submissionTitleDisplay.textContent = loadedData.submission_project_title || '-';
                submissionMahasiswaDisplay.textContent = loadedData.nama_mahasiswa_submission || '-';
                submissionNrpDisplay.textContent = loadedData.nrp_mahasiswa_submission || '-';
                if(loadedData.submission_file_path) {
                    submissionFileLink.href = loadedData.submission_file_path; // Pastikan path ini bisa diakses web
                    submissionFileLink.textContent = loadedData.submission_original_file_name || 'Download File';
                } else {
                    submissionFileLink.href = '#';
                    submissionFileLink.textContent = 'File tidak tersedia';
                }
                submissionInfoDisplay.style.display = 'block';
            } else {
                submissionInfoDisplay.style.display = 'none';
            }

            updateUIFromAssessmentData(); 
            setFormEditableState(!isViewModeOnly);
            console.log('Data penilaian berhasil dimuat dari API:', assessmentData);
        } catch (error) {
            console.error('Gagal memuat data project penilaian:', error);
            alert(`Gagal memuat data project penilaian: ${error.message}.`);
            // Mungkin redirect atau tampilkan pesan error di halaman
        }
    }
    
    async function loadSubmissionDetailsForNewAssessment(submissionId) {
        console.log(`Mencoba memuat detail submission untuk penilaian baru: Submission ID ${submissionId}`);
        // Untuk penilaian baru, kita tidak punya project_id, jadi kita fetch detail submission
        // Endpoint GET /submissions/{id} mungkin perlu dibuat jika belum ada
        // Untuk sementara, kita asumsikan data submission dasar (judul) sudah cukup dari URL param atau state sebelumnya.
        // Atau, kita bisa buat endpoint khusus /submissions/{id}/details_for_assessment
        // Untuk contoh ini, kita akan set judul project dari prompt atau data yang mungkin sudah ada.
        // Idealnya, fetch detail submission dari API.
        // Misal, GET API.php/submissions/{submissionId} (perlu endpoint ini di API.php)
        // Untuk sekarang, kita set default saja dan admin bisa edit.
        
        // Simulasi fetch submission detail (HARUSNYA DARI API)
        // Ini hanya contoh, idealnya ada fetch ke API untuk mendapatkan detail submission
        try {
            // Anggap kita punya endpoint untuk get detail submission (belum ada di API.php saat ini)
            // const response = await fetch(`${API_BASE_URL}/submissions/${submissionId}/details`);
            // const subDetails = await response.json();
            // if (!response.ok) throw new Error(subDetails.error || "Gagal ambil detail submission");
            
            // Placeholder:
            const subDetails = { 
                project_title: `Proyek dari Unggahan #${submissionId}`, 
                nama_mahasiswa: 'Nama Mahasiswa (dari unggahan)', 
                nrp_mahasiswa: 'NRP (dari unggahan)',
                file_path: '#', // Path file unggahan
                original_file_name: 'Nama File Asli (dari unggahan)'
            }; // Ganti dengan fetch API yang sebenarnya

            assessmentData.projectName = subDetails.project_title;
            assessmentData.submission_id = submissionId; // Simpan submission_id
            currentSubmissionId = submissionId; // Set untuk proses save

            submissionTitleDisplay.textContent = subDetails.project_title;
            submissionMahasiswaDisplay.textContent = subDetails.nama_mahasiswa;
            submissionNrpDisplay.textContent = subDetails.nrp_mahasiswa;
            if(subDetails.file_path && subDetails.file_path !== '#') {
                 submissionFileLink.href = subDetails.file_path;
                 submissionFileLink.textContent = subDetails.original_file_name || 'Download File';
            } else {
                 submissionFileLink.href = '#';
                 submissionFileLink.textContent = 'File tidak tersedia';
            }
            submissionInfoDisplay.style.display = 'block';
            
            updateUIFromAssessmentData();
            setFormEditableState(true); // Form bisa diedit untuk penilaian baru
            if (assessmentData.parameters.length === 0) { // Tambah parameter default jika baru
                addNewParameter();
            }

        } catch (err) {
            console.error("Gagal memuat detail submission untuk penilaian:", err);
            alert("Gagal memuat detail proyek unggahan. Silakan coba lagi.");
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
        setFormEditableState(true);
    });
    backToManagementBtn.addEventListener('click', () => {
        window.location.href = 'index.html'; 
    });

    // Event listener untuk input agar langsung update data model (opsional, tergantung preferensi)
    projectNameInput.addEventListener('change', (e) => assessmentData.projectName = e.target.value);
    examinerNotesInput.addEventListener('change', (e) => assessmentData.examinerNotes = e.target.value);
    if (statusSelectEl) {
        statusSelectEl.addEventListener('change', (e) => {
            assessmentData.status = e.target.value;
            calculateAndUpdateTotals(); // Recalculate if status change affects predicate or score logic locally
        });
    }

    async function initializeApp() {
        const urlParams = new URLSearchParams(window.location.search);
        const projectIdFromUrl = urlParams.get('projectId');
        const submissionIdFromUrl = urlParams.get('submission_id');
        const viewModeFromUrl = urlParams.get('view') === 'true'; 

        if (projectIdFromUrl) { 
            currentProjectId = projectIdFromUrl;
            document.title = `Penilaian Proyek - ID ${currentProjectId}`; 
            await loadProjectData(projectIdFromUrl, viewModeFromUrl);
        } else if (submissionIdFromUrl) { // Menilai submission baru
            currentSubmissionId = submissionIdFromUrl;
            document.title = `Nilai Proyek dari Unggahan ID ${currentSubmissionId}`;
            // Load detail submission (judul, mahasiswa, dll) dan set form
            await loadSubmissionDetailsForNewAssessment(submissionIdFromUrl);
            setFormEditableState(true); // Selalu editable untuk penilaian baru
        } else {
            // Seharusnya tidak sampai sini jika alur dari index.html benar (selalu ada submission_id)
            alert("Mode tidak valid. Kembali ke manajemen.");
            window.location.href = 'index.html';
            // Default untuk form kosong jika tidak ada ID (misal, direct access tanpa param)
            // currentProjectId = null; 
            // currentSubmissionId = null;
            // document.title = "Tambah Penilaian Proyek Baru (Manual)";
            // assessmentData = { 
            //     id: null, submission_id: null, projectName: '', parameters: [], overallTotalMistakes: 0,
            //     overallTotalScore: 90, predicate: 'Istimewa', status: 'LANJUT', 
            //     examinerNotes: '', examinerName: loggedInAdminUser?.nama_lengkap || '',
            //     docVersion: 'default_manual_v1'
            // };
            // updateUIFromAssessmentData(); 
            // if (assessmentData.parameters.length === 0) {
            //     addNewParameter(); 
            // }
            // setFormEditableState(true); 
        }
    }

    checkAdminLoginAndInitialize(); // Panggil fungsi pengecekan sesi utama
});
