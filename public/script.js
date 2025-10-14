document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('page1Form')) {
        setupPage1();
    } else if (document.getElementById('page2Form')) {
        setupPage2();
    }
});

function setupPage1() {
    const form = document.getElementById('page1Form');
    const keaRadio = document.getElementById('kea');
    const managementRadio = document.getElementById('management');
    const keaFields = document.getElementById('keaFields');
    const managementFields = document.getElementById('managementFields');

    function setRequired(section, isRequired) {
        const inputs = section.querySelectorAll('input, select');
        inputs.forEach(input => {
            input.required = isRequired;
        });
    }

    keaRadio.addEventListener('change', () => {
        if (keaRadio.checked) {
            keaFields.classList.remove('hidden');
            managementFields.classList.add('hidden');
            setRequired(keaFields, true);
            setRequired(managementFields, false);
        }
    });

    managementRadio.addEventListener('change', () => {
        if (managementRadio.checked) {
            managementFields.classList.remove('hidden');
            keaFields.classList.add('hidden');
            setRequired(managementFields, true);
            setRequired(keaFields, false);
        }
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        localStorage.setItem('registrationDataP1', JSON.stringify(data));
        window.location.href = 'upload.html';
    });
}

function setupPage2() {
    const form = document.getElementById('page2Form');
    // Modal elements
    const modal = document.getElementById('loadingModal');
    const modalText = document.getElementById('modal-text');
    const loader = document.getElementById('loader');
    const successContent = document.getElementById('success-content');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const pdfDownloadLink = document.getElementById('pdfDownloadLink');
    const whatsappLink = document.getElementById('whatsappLink');

    const casteIncomeSection = document.getElementById('casteIncomeSection');
    const casteIncomeInput = document.getElementById('caste_income');

    const allFileInputs = form.querySelectorAll('input[type="file"]');
    allFileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const fileNameSpan = this.parentElement.querySelector('.file-name');
            if (this.files.length > 0) {
                fileNameSpan.textContent = this.files[0].name;
            } else {
                fileNameSpan.textContent = 'No file chosen';
            }
        });
    });

    const page1Data = JSON.parse(localStorage.getItem('registrationDataP1'));
    if (!page1Data) {
        window.location.href = 'index.html';
        return;
    }

    if (page1Data.category && page1Data.category !== 'NOT APPLICABLE') {
        casteIncomeSection.classList.remove('hidden');
        casteIncomeInput.required = true;
    }

    closeModalBtn.addEventListener('click', () => modal.classList.add('hidden'));
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // --- Reset and show modal for new submission ---
        modal.classList.remove('hidden');
        loader.classList.remove('hidden');
        successContent.classList.add('hidden');
        closeModalBtn.classList.add('hidden');
        modalText.innerHTML = "Uploading documents...<br>This may take a few moments.";


        const finalFormData = new FormData();
        for (const key in page1Data) {
            finalFormData.append(key, page1Data[key]);
        }
        
        form.querySelectorAll('input[type="file"]').forEach(input => {
            if (input.files[0]) {
                finalFormData.append(input.name, input.files[0]);
            }
        });

        try {
            const response = await fetch('/api/submit', {
                method: 'POST',
                body: finalFormData
            });
            const result = await response.json();

            if (!response.ok) throw new Error(result.error || 'Submission failed.');
            
            // --- THIS IS THE SUCCESS STATE ---
            loader.classList.add('hidden');
            modalText.innerHTML = `Submission successful!<br>Your Admission ID is: <strong>${result.studentId}</strong>`;
            
            const pdfBlob = new Blob([Uint8Array.from(atob(result.pdfData), c => c.charCodeAt(0))], { type: 'application/pdf' });
            pdfDownloadLink.href = URL.createObjectURL(pdfBlob);

            const whatsappMessage = `Thank you for registering at Vijay Vittal Institute of Technology. Your Admission ID is ${result.studentId}.`;
            const studentPhone = page1Data.mobile_number.replace(/\D/g, '');
            whatsappLink.href = `https://wa.me/91${studentPhone}?text=${encodeURIComponent(whatsappMessage)}`;

            successContent.classList.remove('hidden');
            localStorage.removeItem('registrationDataP1');

        } catch (error) {
            // --- THIS IS THE CORRECTED ERROR STATE ---
            loader.classList.add('hidden');
            modalText.textContent = `Error: Internal Server Error. Please check the logs.`;
            successContent.classList.add('hidden'); // Hide download/share buttons
            closeModalBtn.classList.remove('hidden'); // Show the close button
        }
    });
}

