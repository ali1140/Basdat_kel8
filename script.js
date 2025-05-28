// script.js
document.addEventListener('DOMContentLoaded', () => {
    const API_BASE_URL = 'http://localhost/Basdat_project_kel8/api.php/projects'; 

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
    const loadDataBtn = document.getElementById('loadDataBtn'); 
    const backToManagementBtn = document.getElementById('backToManagementBtn');

    const parameterTemplate = document.getElementById('parameterTemplate');
    const subAspectTemplate = document.getElementById('subAspectTemplate');

    let currentProjectId = null; 
    let isFormEditable = true; // Defaultnya form bisa diedit untuk project baru

    let assessmentData = {
        id: null, 
        projectName: '',
        parameters: [], 
        overallTotalMistakes: 0,
        overallTotalScore: 90, // Nilai awal default
        predicate: 'Istimewa', // Nilai awal default
        status: 'LANJUT', 
        examinerNotes: '',
        examinerName: '',
        docVersion: document.getElementById('docVersion') ? document.getElementById('docVersion').textContent : 'default'
    };

    function updateUIFromAssessmentData() {
        projectNameInput.value = assessmentData.projectName || '';
        examinerNameInput.value = assessmentData.examinerName || '';
        examinerNotesInput.value = assessmentData.examinerNotes || '';
        if (statusSelectEl) statusSelectEl.value = assessmentData.status || 'LANJUT';

        // Update tampilan total berdasarkan data dari server (atau state saat ini)
        overallTotalMistakesEl.textContent = assessmentData.overallTotalMistakes || 0;
        overallTotalScoreEl.textContent = assessmentData.overallTotalScore || 0;
        predicateEl.textContent = assessmentData.predicate || '-';

        // Perbarui warna status select
        if (statusSelectEl) {
            statusSelectEl.classList.remove('status-lanjut-select', 'status-ulang-select');
            if (assessmentData.status === 'LANJUT') statusSelectEl.classList.add('status-lanjut-select');
            else statusSelectEl.classList.add('status-ulang-select');
        }
        
        renderParameters(); // Render ulang parameter dan sub-aspeknya
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
        // Tidak ada lagi updateCalculationsAndTotals() di sini, karena data total diambil dari server
    }

    function createParameterElement(paramData) {
        const templateClone = parameterTemplate.content.cloneNode(true);
        const parameterBlock = templateClone.querySelector('.parameter-block');
        parameterBlock.dataset.parameterId = paramData.id;

        const nameInput = parameterBlock.querySelector('.parameter-name');
        nameInput.value = paramData.name || '';
        nameInput.addEventListener('change', (e) => paramData.name = e.target.value );
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
        btnRemoveParam.addEventListener('click', () => removeParameter(paramData.id) );
        btnRemoveParam.disabled = !isFormEditable;
        btnRemoveParam.style.display = isFormEditable ? 'inline-flex' : 'none';

        // Tampilkan totalMistakes per parameter dari data (dihitung DB)
        updateParameterTotalMistakesDisplay(parameterBlock, paramData.totalMistakes || 0); 
        return parameterBlock;
    }

    function createSubAspectElement(subAspectData, parameterId) {
        const templateClone = subAspectTemplate.content.cloneNode(true);
        const subAspectItem = templateClone.querySelector('.sub-aspect-item');
        subAspectItem.dataset.subAspectId = subAspectData.id;

        const nameInput = subAspectItem.querySelector('.sub-aspect-name');
        nameInput.value = subAspectData.name || '';
        nameInput.addEventListener('change', (e) => subAspectData.name = e.target.value );
        nameInput.disabled = !isFormEditable;

        const mistakesInput = subAspectItem.querySelector('.sub-aspect-mistakes');
        mistakesInput.value = subAspectData.mistakes || 0;
        mistakesInput.addEventListener('input', (e) => {
            subAspectData.mistakes = parseInt(e.target.value, 10) || 0;
            // Tidak ada kalkulasi ulang di frontend. Perubahan akan dikirim ke server saat save.
        });
        mistakesInput.disabled = !isFormEditable;
        
        const btnRemoveSub = subAspectItem.querySelector('.remove-sub-aspect-btn');
        btnRemoveSub.addEventListener('click', () => removeSubAspect(parameterId, subAspectData.id) );
        btnRemoveSub.disabled = !isFormEditable;
        btnRemoveSub.style.display = isFormEditable ? 'inline-flex' : 'none';

        return subAspectItem;
    }
    
    function updateParameterTotalMistakesDisplay(parameterElement, totalMistakes) {
        const displayElement = parameterElement.querySelector('.parameter-total-mistakes');
        if (displayElement) displayElement.textContent = totalMistakes;
    }

    function setFormEditableState(editable) {
        isFormEditable = editable; // Set state global
        projectNameInput.disabled = !editable;
        examinerNameInput.disabled = !editable;
        examinerNotesInput.disabled = !editable;
        statusSelectEl.disabled = !editable;

        // Update semua input dan tombol yang sudah ada di DOM
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
        addParameterBtn.style.display = editable ? 'inline-flex' : 'none';

        backToManagementBtn.style.display = 'inline-flex'; 

        if (currentProjectId) { 
            loadDataBtn.style.display = 'none'; 
            editDataBtn.style.display = editable ? 'none' : 'inline-flex';
            saveDataBtn.style.display = editable ? 'inline-flex' : 'none';
        } else { 
            editDataBtn.style.display = 'none';
            saveDataBtn.style.display = 'inline-flex';
            loadDataBtn.style.display = 'inline-flex'; 
        }
    }

    function addNewParameter() {
        const newParameterId = `param_${Date.now()}`;
        const newParameter = { id: newParameterId, name: `Parameter Baru ${(assessmentData.parameters || []).length + 1}`, subAspects: [], totalMistakes: 0 };
        if (!assessmentData.parameters) assessmentData.parameters = [];
        assessmentData.parameters.push(newParameter);
        const defaultSubAspectId = `sub_${Date.now()}`;
        newParameter.subAspects.push({ id: defaultSubAspectId, name: 'Sub-Aspek Default', mistakes: 0 });
        renderParameters(); 
        setFormEditableState(isFormEditable); // Pastikan elemen baru mengikuti state editable
    }

    function removeParameter(parameterId) {
        assessmentData.parameters = (assessmentData.parameters || []).filter(p => p.id !== parameterId);
        renderParameters();
        // Tidak ada kalkulasi ulang di frontend
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
            setFormEditableState(isFormEditable); // Pastikan elemen baru mengikuti state editable
            // Tidak ada kalkulasi ulang di frontend
        }
    }

    function removeSubAspect(parameterId, subAspectId) {
        const parameter = (assessmentData.parameters || []).find(p => p.id === parameterId);
        if (parameter && parameter.subAspects) {
            parameter.subAspects = parameter.subAspects.filter(sa => sa.id !== subAspectId);
            const subAspectElementToRemove = parametersContainer.querySelector(`.sub-aspect-item[data-sub-aspect-id="${subAspectId}"]`);
            if (subAspectElementToRemove) subAspectElementToRemove.remove();
            // Tidak ada kalkulasi ulang di frontend
        }
    }
    
    function gatherDataFromUI() {
        const currentDocVersionEl = document.getElementById('docVersion');
        // Kumpulkan data mentah dari UI ke dalam objek assessmentData
        assessmentData.projectName = projectNameInput.value || "Tanpa Nama Proyek"; 
        assessmentData.examinerNotes = examinerNotesInput.value;
        assessmentData.examinerName = examinerNameInput.value;
        if (statusSelectEl) assessmentData.status = statusSelectEl.value; 
        assessmentData.docVersion = currentDocVersionEl ? currentDocVersionEl.textContent : 'default_v_js';
        // Data parameter dan sub-aspek sudah terupdate di assessmentData melalui event listeners pada input mereka.
        // Tidak ada kalkulasi total di sini.
        
        const dataToSend = { ...assessmentData };
        if (!currentProjectId) {
            delete dataToSend.id; 
        } else {
            dataToSend.id = currentProjectId; 
        }
        // Hapus nilai agregat yang akan dihitung DB dari payload kiriman
        delete dataToSend.overallTotalMistakes;
        delete dataToSend.overallTotalScore;
        delete dataToSend.predicate;
        if (dataToSend.parameters) {
            dataToSend.parameters.forEach(param => {
                delete param.totalMistakes; // Ini hanya untuk UI, DB akan hitung
            });
        }
        return JSON.parse(JSON.stringify(dataToSend)); 
    }

    async function handleSaveData() {
        const dataToSave = gatherDataFromUI();
        let url = API_BASE_URL;
        let method = 'POST';

        if (currentProjectId && dataToSave.id) { 
            url = `${API_BASE_URL}/${currentProjectId}`;
            method = 'PUT';
        }
        
        console.log(`Mengirim data ke ${url} dengan metode ${method}:`, dataToSave);
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

            // Update assessmentData dengan data lengkap dari server, termasuk nilai yang dihitung DB
            if (result.project) {
                assessmentData = { ...assessmentData, ...result.project }; // Timpa dengan data dari server
                // Jika API tidak mengembalikan parameters & subAspects, kita perlu fetch ulang
                // Untuk sekarang, asumsikan respons 'project' sudah lengkap atau kita fetch ulang
                await loadProjectData(result.project.project_id || currentProjectId); // Muat ulang untuk data lengkap
            } else if (method === 'POST' && result.project_id) {
                 currentProjectId = result.project_id.toString(); 
                 assessmentData.id = currentProjectId;
                 await loadProjectData(currentProjectId); // Muat ulang data lengkap
            } else {
                 await loadProjectData(currentProjectId); // Muat ulang data untuk update
            }
            
            window.history.replaceState({}, '', `index.html?projectId=${currentProjectId}&view=true`);
            setFormEditableState(false); 
            updateUIFromAssessmentData(); // Perbarui UI dengan data baru dari server

        } catch (error) {
            console.error('Gagal menyimpan data:', error);
            alert(`Gagal menyimpan data: ${error.message}`);
        }
    }

    async function loadProjectData(projectId) { 
        console.log(`Mencoba memuat data untuk Project ID: ${projectId} dari ${API_BASE_URL}/${projectId}`);
        try {
            const response = await fetch(`${API_BASE_URL}/${projectId}`);
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
                examinerName: loadedData.examiner_name || '',
                docVersion: loadedData.doc_version || (document.getElementById('docVersion') ? document.getElementById('docVersion').textContent : 'default')
            };
            
            updateUIFromAssessmentData(); // Perbarui seluruh UI dengan data yang dimuat
            console.log('Data berhasil dimuat dari API:', assessmentData);
        } catch (error) {
            console.error('Gagal memuat data project:', error);
            alert(`Gagal memuat data project: ${error.message}.`);
        }
    }
    
    async function handleGeneralLoadData() {
        const projectIdToLoad = prompt("Masukkan ID Project yang ingin dimuat (dari database):");
        if (!projectIdToLoad || projectIdToLoad.trim() === "") {
            alert("ID Project tidak valid atau tidak dimasukkan.");
            return;
        }
        currentProjectId = projectIdToLoad.trim(); 
        document.title = `Penilaian Proyek - ${currentProjectId}`;
        window.history.pushState({}, '', `index.html?projectId=${currentProjectId}&view=true`); 
        await loadProjectData(currentProjectId);
        setFormEditableState(false); 
    }

    addParameterBtn.addEventListener('click', addNewParameter);
    saveDataBtn.addEventListener('click', handleSaveData);
    loadDataBtn.addEventListener('click', handleGeneralLoadData); 
    editDataBtn.addEventListener('click', () => {
        setFormEditableState(true);
    });
    backToManagementBtn.addEventListener('click', () => {
        window.location.href = 'manajemen_project.html'; 
    });

    projectNameInput.addEventListener('change', (e) => assessmentData.projectName = e.target.value);
    examinerNotesInput.addEventListener('change', (e) => assessmentData.examinerNotes = e.target.value);
    examinerNameInput.addEventListener('change', (e) => assessmentData.examinerName = e.target.value);
    if (statusSelectEl) {
        statusSelectEl.addEventListener('change', (e) => {
            assessmentData.status = e.target.value;
            // Tidak ada updateTotalsDisplay() di sini karena status tidak mempengaruhi kalkulasi
        });
    }

    async function initializeApp() {
        const urlParams = new URLSearchParams(window.location.search);
        const projectIdFromUrl = urlParams.get('projectId');
        const viewModeFromUrl = urlParams.get('view') === 'true'; 

        if (projectIdFromUrl) { 
            currentProjectId = projectIdFromUrl;
            document.title = `Penilaian Proyek - ${currentProjectId}`; 
            await loadProjectData(projectIdFromUrl);
            setFormEditableState(!viewModeFromUrl); 
        } else {
            currentProjectId = null; 
            document.title = "Tambah Penilaian Proyek Baru";
            assessmentData = { 
                id: null, projectName: '', parameters: [], overallTotalMistakes: 0,
                overallTotalScore: 90, predicate: 'Istimewa', status: 'LANJUT', 
                examinerNotes: '', examinerName: '',
                docVersion: document.getElementById('docVersion') ? document.getElementById('docVersion').textContent : 'default'
            };
            updateUIFromAssessmentData(); // Tampilkan nilai default
            if (assessmentData.parameters.length === 0) {
                addNewParameter(); 
            }
            setFormEditableState(true); 
        }
    }

    initializeApp();
});
