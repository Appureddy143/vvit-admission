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

    // Helper function to set required attribute on inputs
    function setRequired(section, isRequired) {
        const inputs = section.querySelectorAll('input, select');
        inputs.forEach(input => { input.required = isRequired; });
    }

    // Show/hide conditional fields based on "Admission Through"
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
    const casteIncomeSection = document.getElementById('casteIncomeSection');
    const casteIncomeInput = document.getElementById('caste_income');
    const submitStatus = document.getElementById('submit-status');

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
        submitStatus.textContent = 'Submitting, please wait... This may take a moment.';

        const finalFormData = new FormData();
        for (const key in page1Data) {
            finalFormData.append(key, page1Data[key]);
        }
        
        const page2FileInputs = form.querySelectorAll('input[type="file"]');
        page2FileInputs.forEach(input => {
            if (input.files[0]) {
                if (input.files[0].size > 1 * 1024 * 1024) { // 1MB validation
                    alert(`File ${input.files[0].name} is too large. Maximum size is 1MB.`);
                    submitButton.disabled = false;
                    submitStatus.textContent = '';
                    return;
                }
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
            
            localStorage.removeItem('registrationDataP1');
            alert(`Submission successful! Your Admission ID is: ${result.studentId}`);
            
            const whatsappMessage = `Thank you for registering. Your Admission ID is ${result.studentId}.`;
            const studentPhone = page1Data.mobile_number.replace(/\D/g, '');
            window.location.href = `https://wa.me/91${studentPhone}?text=${encodeURIComponent(whatsappMessage)}`;

        } catch (error) {
            submitStatus.textContent = `Error: ${error.message}`;
            submitButton.disabled = false;
        }
    });
}
