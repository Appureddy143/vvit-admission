document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('page1Form')) {
        setupPage1();
    } else if (document.getElementById('page2Form')) {
        setupPage2();
    }
});

// ------------------ PAGE 1 ------------------
function setupPage1() {
    const form = document.getElementById('page1Form');
    const keaRadio = document.getElementById('kea');
    const managementRadio = document.getElementById('management');
    const keaFields = document.getElementById('keaFields');
    const managementFields = document.getElementById('managementFields');

    function setRequired(section, isRequired) {
        const inputs = section.querySelectorAll('input, select');
        inputs.forEach(input => (input.required = isRequired));
    }

    function toggleFields() {
        if (keaRadio.checked) {
            keaFields.classList.remove('hidden');
            managementFields.classList.add('hidden');
            setRequired(keaFields, true);
            setRequired(managementFields, false);
        } else if (managementRadio.checked) {
            managementFields.classList.remove('hidden');
            keaFields.classList.add('hidden');
            setRequired(managementFields, true);
            setRequired(keaFields, false);
        } else {
            keaFields.classList.add('hidden');
            managementFields.classList.add('hidden');
            setRequired(keaFields, false);
            setRequired(managementFields, false);
        }
    }

    // Trigger toggle on page load
    toggleFields();

    keaRadio.addEventListener('change', toggleFields);
    managementRadio.addEventListener('change', toggleFields);

    // Form submission
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        localStorage.setItem('registrationDataP1', JSON.stringify(data));
        window.location.href = 'upload.html';
    });
}

// ------------------ PAGE 2 ------------------
function setupPage2() {
    const form = document.getElementById('page2Form');
    const casteIncomeSection = document.getElementById('casteIncomeSection');
    const casteIncomeInput = document.getElementById('caste_income');
    const submitButton = form.querySelector('button[type="submit"]');

    // Modal setup
    const modal = document.createElement('div');
    modal.id = 'modalPopup';
    modal.style = `
        position: fixed; top:0; left:0; width:100%; height:100%; 
        background: rgba(0,0,0,0.5); display:flex; justify-content:center; 
        align-items:center; z-index:1000;`;
    modal.innerHTML = `
        <div style="background:#fff; padding:30px; border-radius:10px; text-align:center; width:90%; max-width:400px;">
            <p id="modalMessage">Please wait, uploading documents. This may take several minutes...</p>
            <a id="downloadPDF" href="#" download class="hidden" style="display:block; margin:15px 0; background:#d90429; color:#fff; padding:10px; border-radius:5px; text-decoration:none;">Download PDF</a>
            <button id="closeModal" class="hidden" style="background:#2b2d42; color:#fff; padding:10px 15px; border:none; border-radius:5px; cursor:pointer;">Close</button>
        </div>
    `;
    document.body.appendChild(modal);
    const modalMessage = modal.querySelector('#modalMessage');
    const downloadPDF = modal.querySelector('#downloadPDF');
    const closeModal = modal.querySelector('#closeModal');

    // Custom file input file name display
    form.querySelectorAll('input[type="file"]').forEach(input => {
        const fileNameSpan = input.parentElement.querySelector('.file-name');
        input.addEventListener('change', () => {
            fileNameSpan.textContent = input.files[0] ? input.files[0].name : 'No file chosen';
        });
    });

    // Load Page1 data
    const page1Data = JSON.parse(localStorage.getItem('registrationDataP1'));
    if (!page1Data) {
        window.location.href = 'index.html';
        return;
    }

    if (page1Data.category && page1Data.category !== 'NOT APPLICABLE') {
        casteIncomeSection.classList.remove('hidden');
        casteIncomeInput.required = true;
    }

    // Submit form
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        submitButton.disabled = true;
        modal.style.display = 'flex';
        modalMessage.textContent = 'Please wait, uploading documents. This may take several minutes...';

        const finalFormData = new FormData();
        Object.keys(page1Data).forEach(key => finalFormData.append(key, page1Data[key]));
        form.querySelectorAll('input[type="file"]').forEach(input => {
            if (input.files[0]) finalFormData.append(input.name, input.files[0]);
        });

        try {
            const response = await fetch('/api/submit', { method: 'POST', body: finalFormData });
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || 'Submission failed.');
            localStorage.removeItem('registrationDataP1');

            modalMessage.textContent = `Submission successful! Your Admission ID is: ${result.studentId}`;

            downloadPDF.href = result.pdfUrl;
            downloadPDF.classList.remove('hidden');

            downloadPDF.addEventListener('click', () => {
                const whatsappMessage = `Thank you for registering. Your Admission ID is ${result.studentId}.`;
                const studentPhone = page1Data.mobile_number.replace(/\D/g, '');
                window.open(`https://wa.me/91${studentPhone}?text=${encodeURIComponent(whatsappMessage)}`, '_blank');
            });

            closeModal.classList.remove('hidden');
            closeModal.addEventListener('click', () => modal.style.display = 'none');

        } catch (error) {
            modalMessage.textContent = `Error: ${error.message}`;
            closeModal.classList.remove('hidden');
            closeModal.addEventListener('click', () => modal.style.display = 'none');
        } finally {
            submitButton.disabled = false;
        }
    });
}
