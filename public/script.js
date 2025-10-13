document.addEventListener('DOMContentLoaded', () => {
    // Check which page's form we are on
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

    // Helper function to set the 'required' attribute on inputs
    function setRequired(section, isRequired) {
        const inputs = section.querySelectorAll('input, select');
        inputs.forEach(input => {
            input.required = isRequired;
        });
    }

    // It listens for a change on the radio buttons.
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

    // This part handles the form submission
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
    const casteIncomeSection = document.getElementById('casteIncomeSection');
    const casteIncomeInput = document.getElementById('caste_income');
    const submitStatus = document.getElementById('submit-status');

    // This makes the custom file inputs show the selected file name.
    const allFileInputs = form.querySelectorAll('input[type="file"]');
    allFileInputs.forEach(input => {
        input.addEventListener('change', function() {
            // Find the span that shows the file name
            const fileNameSpan = this.parentElement.querySelector('.file-name');
            if (this.files.length > 0) {
                fileNameSpan.textContent = this.files[0].name;
            } else {
                fileNameSpan.textContent = 'No file chosen';
            }
        });
    });

    // Get data from the first page
    const page1Data = JSON.parse(localStorage.getItem('registrationDataP1'));
    if (!page1Data) {
        window.location.href = 'index.html'; // Go back if no data
        return;
    }

    // Conditionally show the caste certificate upload field
    if (page1Data.category && page1Data.category !== 'NOT APPLICABLE') {
        casteIncomeSection.classList.remove('hidden');
        casteIncomeInput.required = true;
    }
    
    // Handle the final form submission
   form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const submitButton = form.querySelector('button[type="submit"]');
    submitButton.disabled = true;

    const modal = document.getElementById('submissionModal');
    const modalMessage = document.getElementById('modalMessage');
    const modalLoading = document.getElementById('modalLoading');
    const downloadPDF = document.getElementById('downloadPDF');
    const closeModal = document.getElementById('closeModal');

    modal.classList.remove('hidden');
    modalMessage.textContent = 'Uploading documents... This may take several minutes.';
    modalLoading.classList.remove('hidden');
    downloadPDF.classList.add('hidden');
    closeModal.classList.add('hidden');

    const finalFormData = new FormData();
    const page1Data = JSON.parse(localStorage.getItem('registrationDataP1'));
    for (const key in page1Data) finalFormData.append(key, page1Data[key]);

    form.querySelectorAll('input[type="file"]').forEach(input => {
        if (input.files[0]) finalFormData.append(input.name, input.files[0]);
    });

    try {
        const response = await fetch('/api/submit', { method: 'POST', body: finalFormData });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || 'Submission failed.');
        localStorage.removeItem('registrationDataP1');

        modalLoading.classList.add('hidden');
        modalMessage.textContent = `Submission successful! Your Admission ID is: ${result.studentId}`;

        downloadPDF.href = result.pdfUrl;
        downloadPDF.classList.remove('hidden');

        downloadPDF.addEventListener('click', () => {
            const whatsappMessage = `Thank you for registering. Your Admission ID is ${result.studentId}.`;
            const studentPhone = page1Data.mobile_number.replace(/\D/g, '');
            window.open(`https://wa.me/91${studentPhone}?text=${encodeURIComponent(whatsappMessage)}`, '_blank');
        });

        closeModal.classList.remove('hidden');
        closeModal.addEventListener('click', () => modal.classList.add('hidden'));

    } catch (error) {
        modalLoading.classList.add('hidden');
        modalMessage.textContent = `Error: ${error.message}`;
        closeModal.classList.remove('hidden');
        closeModal.addEventListener('click', () => modal.classList.add('hidden'));
    } finally {
        submitButton.disabled = false;
    }
});
