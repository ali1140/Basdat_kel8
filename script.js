// script.js
document.addEventListener('DOMContentLoaded', () => {
    // --- KONFIGURASI API ---
    const API_BASE_URL = 'http://localhost/Basdat_project_kel8/api.php/projects'; 

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
    
    const formActionButtonsContainer = document.getElementById('formActionButtons');
    const editDataBtn = document.getElementById('editDataBtn');
    const saveDataBtn = document.getElementById('saveDataBtn');
    const loadDataBtn = document.getElementById('loadDataBtn'); 
    const backToManagementBtn = document.getElementById('backToManagementBtn'); // Tombol Kembali

    const parameterTemplate = document.getElementById('parameterTemplate');
    const subAspectTemplate = document.getElementById('subAspectTemplate');

    let currentProjectId = null; 

    let assessmentData = {
        id: null, 
        projectName: '',
        parameters: [], 
        overallTotalMistakes: 0,
        overallTotalScore: 90,
        predicate: 'Istimewa',
        status: 'LANJUT', 
        examinerNotes: '',
        examinerName: '',
        docVersion: document.getElementById('docVersion') ? document.getElementById('docVersion').textContent : 'default'
    };

    // --- FUNGSI RENDER & UI UPDATE ---
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
        updateCalculationsAndTotals();
    }

    function createParameterElement(paramData) {
        const templateClone = parameterTemplate.content.cloneNode(true);
        const parameterBlock = templateClone.querySelector('.parameter-block');
        parameterBlock.dataset.parameterId = paramData.id;

        const nameInput = parameterBlock.querySelector('.parameter-name');
        nameInput.value = paramData.name || '';
        nameInput.addEventListener('change', (e) => paramData.name = e.target.value );

        const subAspectsContainer = parameterBlock.querySelector('.sub-aspects-container');
        if (paramData.subAspects && Array.isArray(paramData.subAspects)) {
            paramData.subAspects.forEach(subAspect => {
                const subAspectElement = createSubAspectElement(subAspect, paramData.id);
                subAspectsContainer.appendChild(subAspectElement);
            });
        }

        parameterBlock.querySelector('.add-sub-aspect-btn').addEventListener('click', () => addNewSubAspect(paramData.id, subAspectsContainer) );
        parameterBlock.querySelector('.remove-parameter-btn').addEventListener('click', () => removeParameter(paramData.id) );
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

        const mistakesInput = subAspectItem.querySelector('.sub-aspect-mistakes');
        mistakesInput.value = subAspectData.mistakes || 0;
        mistakesInput.addEventListener('input', (e) => {
            subAspectData.mistakes = parseInt(e.target.value, 10) || 0;
            updateCalculationsAndTotals();
        });
        subAspectItem.querySelector('.remove-sub-aspect-btn').addEventListener('click', () => removeSubAspect(parameterId, subAspectData.id) );
        return subAspectItem;
    }
    
    function updateParameterTotalMistakesDisplay(parameterElement, totalMistakes) {
        const displayElement = parameterElement.querySelector('.parameter-total-mistakes');
        if (displayElement) displayElement.textContent = totalMistakes;
    }

    function setFormEditable(isEditable) {
        projectNameInput.disabled = !isEditable;
        examinerNameInput.disabled = !isEditable;
        examinerNotesInput.disabled = !isEditable;
        statusSelectEl.disabled = !isEditable;

        document.querySelectorAll('.parameter-name, .sub-aspect-name, .sub-aspect-mistakes')
            .forEach(input => input.disabled = !isEditable);

        document.querySelectorAll('.remove-parameter-btn, .add-sub-aspect-btn, .remove-sub-aspect-btn')
            .forEach(btn => {
                btn.disabled = !isEditable;
                if (btn.classList.contains('add-sub-aspect-btn')) {
                    btn.style.display = isEditable ? 'flex' : 'none';
                } else {
                    btn.style.display = isEditable ? 'inline-flex' : 'none';
                }
            });
        
        addParameterBtn.disabled = !isEditable;
        addParameterBtn.style.display = isEditable ? 'inline-flex' : 'none';

        // Tombol "Kembali" selalu ditampilkan di halaman ini
        backToManagementBtn.style.display = 'inline-flex'; 

        if (currentProjectId) { // Jika kita sedang melihat/mengedit project yang ada
            loadDataBtn.style.display = 'none'; // Sembunyikan tombol "Muat Data" umum
            editDataBtn.style.display = isEditable ? 'none' : 'inline-flex'; // Tampilkan "Edit" jika !isEditable (mode view)
            saveDataBtn.style.display = isEditable ? 'inline-flex' : 'none'; // Tampilkan "Simpan" jika isEditable
        } else { // Jika ini form untuk project baru
            editDataBtn.style.display = 'none'; // Tidak ada tombol "Edit" untuk project baru
            saveDataBtn.style.display = 'inline-flex'; // Selalu ada tombol "Simpan" untuk project baru
            loadDataBtn.style.display = 'inline-flex'; // Tampilkan tombol "Muat Data" umum
        }
    }

    function addNewParameter() {
        const newParameterId = `param_${Date.now()}`;
        const newParameter = { id: newParameterId, name: `Parameter Baru ${assessmentData.parameters.length + 1}`, subAspects: [], totalMistakes: 0 };
        assessmentData.parameters.push(newParameter);
        const defaultSubAspectId = `sub_${Date.now()}`;
        newParameter.subAspects.push({ id: defaultSubAspectId, name: 'Sub-Aspek Default', mistakes: 0 });
        renderParameters(); 
    }

    function removeParameter(parameterId) {
        assessmentData.parameters = assessmentData.parameters.filter(p => p.id !== parameterId);
        renderParameters();
        updateCalculationsAndTotals();
    }

    function addNewSubAspect(parameterId, containerElement) {
        const parameter = assessmentData.parameters.find(p => p.id === parameterId);
        if (parameter) {
            const newSubAspectId = `sub_${Date.now()}_${Math.random().toString(36).substr(2, 5)}`;
            const newSubAspect = { id: newSubAspectId, name: '', mistakes: 0 };
            if (!parameter.subAspects) parameter.subAspects = [];
            parameter.subAspects.push(newSubAspect);
            const subAspectElement = createSubAspectElement(newSubAspect, parameterId);
            containerElement.appendChild(subAspectElement); 
            updateCalculationsAndTotals();
        }
    }

    function removeSubAspect(parameterId, subAspectId) {
        const parameter = assessmentData.parameters.find(p => p.id === parameterId);
        if (parameter && parameter.subAspects) {
            parameter.subAspects = parameter.subAspects.filter(sa => sa.id !== subAspectId);
            const subAspectElementToRemove = parametersContainer.querySelector(`.sub-aspect-item[data-sub-aspect-id="${subAspectId}"]`);
            if (subAspectElementToRemove) subAspectElementToRemove.remove();
            updateCalculationsAndTotals(); 
        }
    }

    function updateCalculationsAndTotals() {
        let overallMistakes = 0;
        (assessmentData.parameters || []).forEach(param => {
            param.totalMistakes = (param.subAspects || []).reduce((sum, sa) => sum + (parseInt(sa.mistakes, 10) || 0), 0);
            overallMistakes += param.totalMistakes;
            const parameterElement = parametersContainer.querySelector(`.parameter-block[data-parameter-id="${param.id}"]`);
            if (parameterElement) updateParameterTotalMistakesDisplay(parameterElement, param.totalMistakes);
        });

        assessmentData.overallTotalMistakes = overallMistakes;
        assessmentData.overallTotalScore = 90 - overallMistakes;

        if (assessmentData.overallTotalScore >= 86) assessmentData.predicate = 'Istimewa';
        else if (assessmentData.overallTotalScore >= 78) assessmentData.predicate = 'Sangat Baik';
        else if (assessmentData.overallTotalScore >= 65) assessmentData.predicate = 'Baik';
        else assessmentData.predicate = 'Cukup';
        updateTotalsDisplay();
    }

    function updateTotalsDisplay() {
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
        const currentDocVersionEl = document.getElementById('docVersion');
        assessmentData.projectName = projectNameInput.value || "Tanpa Nama Proyek"; 
        assessmentData.examinerNotes = examinerNotesInput.value;
        assessmentData.examinerName = examinerNameInput.value;
        if (statusSelectEl) assessmentData.status = statusSelectEl.value; 
        assessmentData.docVersion = currentDocVersionEl ? currentDocVersionEl.textContent : 'default_v_js';
        updateCalculationsAndTotals(); 
        const dataToSend = { ...assessmentData };
        if (!currentProjectId) {
            delete dataToSend.id; 
        } else {
            dataToSend.id = currentProjectId; 
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
                console.error("Gagal parse JSON dari server:", resultText);
                throw new Error("Respons server tidak valid (bukan JSON). Isi: " + resultText.substring(0, 200));
            }

            if (!response.ok) {
                throw new Error(result.error || `HTTP error! status: ${response.status}`);
            }
            
            console.log('Respon dari server:', result);
            alert(result.message || "Data berhasil diproses.");

            if (method === 'POST' && result.project_id) { 
                currentProjectId = result.project_id.toString(); 
                assessmentData.id = currentProjectId;
                // Ganti URL untuk mencerminkan ID baru dan masuk ke mode view
                window.history.replaceState({}, '', `index.html?projectId=${currentProjectId}&view=true`); 
            }
            setFormEditable(false); // Kembali ke mode view setelah save
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
                throw new Error("Respons server tidak valid saat memuat (bukan JSON). Isi: " + resultText.substring(0, 200));
            }

            if (!response.ok) {
                 throw new Error(loadedData.error || `HTTP error! status: ${response.status}`);
            }
            
            assessmentData = {
                id: loadedData.project_id ? loadedData.project_id.toString() : null,
                projectName: loadedData.project_name || '',
                parameters: [], 
                overallTotalMistakes: parseInt(loadedData.overall_total_mistakes, 10) || 0,
                overallTotalScore: parseInt(loadedData.overall_total_score, 10) || 0,
                predicate: loadedData.predicate_text || 'Cukup',
                status: loadedData.status || 'LANJUT',
                examinerNotes: loadedData.examiner_notes || '',
                examinerName: loadedData.examiner_name || '',
                docVersion: loadedData.doc_version || (document.getElementById('docVersion') ? document.getElementById('docVersion').textContent : 'default')
            };
            
            assessmentData.parameters = (loadedData.parameters || []).map(p => ({ 
                id: p.id || p.parameter_client_id, 
                name: p.name || p.parameter_name || '',
                totalMistakes: parseInt(p.totalMistakes || p.total_mistakes_parameter, 10) || 0,
                subAspects: (p.subAspects || []).map(sa => ({ 
                    id: sa.id || sa.sub_aspect_client_id, 
                    name: sa.name || sa.sub_aspect_name || '',
                    mistakes: parseInt(sa.mistakes, 10) || 0
                }))
            }));

            projectNameInput.value = assessmentData.projectName;
            examinerNotesInput.value = assessmentData.examinerNotes;
            examinerNameInput.value = assessmentData.examinerName;
            if (statusSelectEl) statusSelectEl.value = assessmentData.status;
            const docVersionEl = document.getElementById('docVersion');
            if (docVersionEl && assessmentData.docVersion) {
                docVersionEl.textContent = assessmentData.docVersion;
            }

            renderParameters(); 
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
        setFormEditable(false); 
    }

    addParameterBtn.addEventListener('click', addNewParameter);
    saveDataBtn.addEventListener('click', handleSaveData);
    loadDataBtn.addEventListener('click', handleGeneralLoadData); 
    editDataBtn.addEventListener('click', () => {
        setFormEditable(true);
    });
    // Pastikan event listener untuk tombol kembali mengarah ke halaman manajemen yang benar
    backToManagementBtn.addEventListener('click', () => {
        // Jika Anda mengganti nama manajemen_project.html menjadi index.html dan lembar penilaian menjadi nama lain
        // maka ini harusnya 'index.html'. Jika tidak, 'manajemen_project.html'.
        // Asumsi saat ini: manajemen_project.html adalah nama file halaman manajemen.
        window.location.href = 'index.html'; 
    });

    projectNameInput.addEventListener('change', (e) => assessmentData.projectName = e.target.value);
    examinerNotesInput.addEventListener('change', (e) => assessmentData.examinerNotes = e.target.value);
    examinerNameInput.addEventListener('change', (e) => assessmentData.examinerName = e.target.value);
    if (statusSelectEl) {
        statusSelectEl.addEventListener('change', (e) => {
            assessmentData.status = e.target.value;
            updateTotalsDisplay(); 
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
            setFormEditable(!viewModeFromUrl); // Jika viewMode true, form TIDAK editable.
        } else {
            currentProjectId = null; 
            document.title = "Tambah Penilaian Proyek Baru";
            assessmentData = { 
                id: null, projectName: '', parameters: [], overallTotalMistakes: 0,
                overallTotalScore: 90, predicate: 'Istimewa', status: 'LANJUT', 
                examinerNotes: '', examinerName: '',
                docVersion: document.getElementById('docVersion') ? document.getElementById('docVersion').textContent : 'default'
            };
            projectNameInput.value = '';
            examinerNotesInput.value = '';
            examinerNameInput.value = '';
            if (statusSelectEl) statusSelectEl.value = 'LANJUT';

            if (assessmentData.parameters.length === 0) {
                addNewParameter(); 
            } else {
                renderParameters(); 
            }
            setFormEditable(true); 
        }
        // Panggil setFormEditable di akhir initializeApp juga untuk memastikan tombol kembali
        // diatur dengan benar berdasarkan currentProjectId yang mungkin baru saja di-set.
        // Logika di dalam setFormEditable sudah menangani kasus currentProjectId null atau ada.
        // Tidak perlu memanggilnya lagi jika sudah dipanggil di dalam blok if/else di atas.
        updateTotalsDisplay(); 
    }

    initializeApp();
});
